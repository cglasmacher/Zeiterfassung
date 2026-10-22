<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoDisplay;
use App\Models\InfoItem;
use App\Services\Info\DisplayFeed;
use App\Services\Info\YouTubeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DisplayController extends Controller
{
    private function authorizeDisplay(Request $r, string $slug): InfoDisplay
    {
        $display = InfoDisplay::where('slug', $slug)->where('active', true)->firstOrFail();
        $token = $r->cookie('info_display_'.$display->id);
        abort_unless($token && $display->token_hash && hash_equals($display->token_hash, hash('sha256', $token)), 403, 'Display bitte über die Verwaltung verbinden.');

        return $display;
    }

    public function connect(Request $r, string $slug)
    {
        $d = InfoDisplay::where('slug', $slug)->where('active', true)->firstOrFail();
        $token = (string) $r->query('token');
        abort_unless($token && $d->token_hash && hash_equals($d->token_hash, hash('sha256', $token)), 403);

        return redirect('/display/'.$d->slug)->withCookie(cookie('info_display_'.$d->id, $token, 60 * 24 * 365, '/display', null, $r->isSecure(), true, false, 'lax'))
            ->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function show(Request $r, string $slug, DisplayFeed $feed)
    {
        $d = $this->authorizeDisplay($r, $slug);

        return Inertia::render('Info/Display', ['feed' => $feed->snapshot($d)])
            ->toResponse($r)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function data(Request $r, string $slug, DisplayFeed $feed)
    {
        $d = $this->authorizeDisplay($r, $slug);
        $d->update(['last_seen_at' => now()]);

        return response()->json($feed->snapshot($d))->header('Cache-Control', 'no-store');
    }

    public function youtubeSearch(Request $r, string $slug, YouTubeSearch $youtube)
    {
        $this->authorizeDisplay($r, $slug);
        $input = $r->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'page' => ['nullable', 'string', 'max:512', 'regex:/^[a-zA-Z0-9_=-]+$/'],
        ]);

        try {
            return response()->json($youtube->search(trim($input['q']), $input['page'] ?? ''))
                ->header('Cache-Control', 'private, no-store');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503)->header('Cache-Control', 'no-store');
        }
    }

    public function events(Request $r, string $slug, DisplayFeed $feed)
    {
        $d = $this->authorizeDisplay($r, $slug);
        $d->update(['last_seen_at' => now()]);

        // Short SSE windows release PHP-FPM workers and re-authorize every reconnect.
        return response()->stream(function () use ($d, $feed) {
            $last = null;
            for ($i = 0; $i < 10; $i++) {
                $fresh = $d->fresh();
                if (! $fresh || ! $fresh->active || $fresh->token_hash !== $d->token_hash) {
                    echo "event: revoked\ndata: {}\n\n";
                    @ob_flush();
                    flush();
                    break;
                }
                $json = json_encode($feed->snapshot($fresh), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $hash = hash('sha256', $json);
                if ($hash !== $last) {
                    echo "event: update\ndata: ".$json."\n\n";
                    $last = $hash;
                } else {
                    echo ": heartbeat\n\n";
                }
                @ob_flush();
                flush();
                if (connection_aborted()) {
                    break;
                }
                usleep(2000000);
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }

    public function media(Request $r, string $slug, InfoItem $item)
    {
        $d = $this->authorizeDisplay($r, $slug);
        abort_unless($item->kind === 'media' && $item->active && (! $item->display_id || $item->display_id === $d->id)
            && (! $item->starts_at || $item->starts_at->lte(now())) && (! $item->ends_at || $item->ends_at->gte(now())) && $item->file_path, 404);
        abort_unless(Storage::disk('local')->exists($item->file_path), 404);

        return response()->file(Storage::disk('local')->path($item->file_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
