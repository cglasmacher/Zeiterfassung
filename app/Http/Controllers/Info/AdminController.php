<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InfoDisplay;
use App\Models\InfoIntegration;
use App\Models\InfoItem;
use App\Models\InfoReservation;
use App\Services\Info\ResmioSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index()
    {
        $settings = InfoIntegration::settings();

        return response()->json(['items' => InfoItem::with('employee:id,first_name,last_name')->orderByDesc('id')->get(),
            'displays' => InfoDisplay::orderBy('id')->get(), 'employees' => Employee::where('active', true)->get(['id', 'first_name', 'last_name']),
            'departments' => Department::all(['id', 'name']), 'integration' => $settings,
            'has_authorization' => (bool) $settings->authorization, 'has_webhook_password' => (bool) $settings->webhook_password,
            'reservations' => InfoReservation::where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(200)->get()]);
    }

    public function saveItem(Request $r, ?InfoItem $item = null)
    {
        $item ??= new InfoItem;
        $data = $r->validate([
            'kind' => ['required', Rule::in(['notice', 'task', 'event', 'media'])], 'title' => 'required|string|max:255', 'body' => 'nullable|string|max:10000',
            'display_id' => 'nullable|exists:info_displays,id', 'employee_id' => 'nullable|exists:employees,id',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'due_at' => 'nullable|date',
            'priority' => 'required|integer|between:0,2', 'active' => 'required|boolean',
            'media_type' => ['nullable', Rule::in(['youtube', 'image', 'video'])], 'url' => 'nullable|url:https|max:2048',
            'duration' => 'required|integer|between:5,7200', 'sort_order' => 'required|integer|between:0,10000',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,webm|max:102400',
        ]);
        if (! empty($data['starts_at']) && ! empty($data['ends_at']) && strtotime($data['ends_at']) <= strtotime($data['starts_at'])) {
            return response()->json(['message' => 'Das Ende muss nach dem Beginn liegen.'], 422);
        }
        if ($data['kind'] === 'event' && ! empty($data['starts_at']) && empty($data['ends_at'])) {
            $data['ends_at'] = \Carbon\Carbon::parse($data['starts_at'])->addHours(6)->toIso8601String();
        }
        if ($data['kind'] === 'event' && empty($data['starts_at'])) {
            return response()->json(['message' => 'Events benötigen einen Beginn.'], 422);
        }
        if ($r->hasFile('file') && $data['kind'] !== 'media') {
            return response()->json(['message' => 'Dateien sind nur für Medien erlaubt.'], 422);
        }
        if ($data['kind'] === 'media') {
            if (empty($data['media_type']) || (empty($data['url']) && ! $r->hasFile('file') && ! $item->file_path)) {
                return response()->json(['message' => 'Medientyp und URL oder Datei fehlen.'], 422);
            }
            if ($data['media_type'] === 'youtube' && ! preg_match('~^https://(www\.)?(youtube\.com/(watch\?|embed/|shorts/)|youtu\.be/)~', $data['url'] ?? '')) {
                return response()->json(['message' => 'Bitte einen gültigen YouTube-Link angeben.'], 422);
            }
            if (! $r->hasFile('file') && empty($data['url']) && $item->file_path && $item->media_type !== $data['media_type']) {
                return response()->json(['message' => 'Für den neuen Medientyp bitte eine passende Datei angeben.'], 422);
            }
            if ($data['media_type'] === 'youtube') {
                $url = parse_url($data['url']);
                parse_str($url['query'] ?? '', $query);
                $host = preg_replace('/^www\./', '', $url['host'] ?? '');
                $id = $host === 'youtu.be' ? trim($url['path'] ?? '', '/') : ($query['v'] ?? (explode('/', $url['path'] ?? '')[2] ?? ''));
                if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                    return response()->json(['message' => 'Der YouTube-Link enthält keine gültige Video-ID.'], 422);
                }
            }
            if ($r->hasFile('file')) {
                $mime = $r->file('file')->getMimeType();
                if (! str_starts_with($mime, $data['media_type'].'/')) {
                    return response()->json(['message' => 'Datei und Medientyp passen nicht zusammen.'], 422);
                }
            }
        }
        foreach (['starts_at', 'ends_at', 'due_at'] as $key) {
            if (! empty($data[$key])) {
                $data[$key] = \Carbon\Carbon::parse($data[$key])->utc();
            }
        }
        unset($data['file']);
        $old = $item->file_path;
        if ($r->hasFile('file')) {
            $data['file_path'] = $r->file('file')->store('infosystem', 'local');
            $data['url'] = null;
        } elseif (! empty($data['url'])) {
            $data['file_path'] = null;
        }
        $item->fill($data)->save();
        if ($old && $old !== $item->file_path) {
            Storage::disk('local')->delete($old);
        }

        return response()->json($item, $item->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteItem(InfoItem $item)
    {
        $path = $item->file_path;
        $item->delete();
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return response()->noContent();
    }

    public function complete(Request $r, InfoItem $item)
    {
        abort_unless($item->kind === 'task', 422);
        $r->validate(['completed' => 'required|boolean']);
        $item->update(['completed_at' => $r->boolean('completed') ? now() : null]);

        return response()->json($item);
    }

    public function saveDisplay(Request $r, ?InfoDisplay $display = null)
    {
        $display ??= new InfoDisplay;
        $data = $r->validate(['slug' => ['required', 'alpha_dash', 'max:60', Rule::unique('info_displays')->ignore($display->id)],
            'name' => 'required|string|max:120', 'active' => 'required|boolean', 'show_guest_names' => 'required|boolean', 'department_id' => 'nullable|exists:departments,id']);
        $display->fill($data)->save();

        return response()->json($display);
    }

    public function rotate(InfoDisplay $display)
    {
        $token = Str::random(64);
        $display->update(['token_hash' => hash('sha256', $token)]);

        return response()->json(['connect_url' => 'https://'.config('infosystem.stream_host').'/display/'.$display->slug.'/connect?token='.$token]);
    }

    public function integration(Request $r)
    {
        $data = $r->validate(['enabled' => 'required|boolean', 'facility' => 'nullable|alpha_dash|max:120',
            'authorization' => 'nullable|string|max:2048', 'webhook_user' => 'nullable|string|max:120', 'webhook_password' => 'nullable|string|max:255']);
        $s = InfoIntegration::settings();
        foreach (['authorization', 'webhook_password'] as $secret) {
            if (empty($data[$secret])) {
                unset($data[$secret]);
            }
        }
        if ($data['enabled'] && (empty($data['facility']) || (empty($data['authorization']) && ! $s->authorization))) {
            return response()->json(['message' => 'Facility-ID und API-Authorization fehlen.'], 422);
        }
        // Old-facility reservations must never appear under a new integration.
        if ($s->facility !== ($data['facility'] ?? null)) {
            InfoReservation::query()->delete();
        }
        $s->update($data);

        return response()->json($s);
    }

    public function sync(ResmioSync $sync)
    {
        try {
            return response()->json(['count' => $sync->run()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'resmio-Synchronisation fehlgeschlagen. Zugang und API-Format prüfen.'], 502);
        }
    }
}
