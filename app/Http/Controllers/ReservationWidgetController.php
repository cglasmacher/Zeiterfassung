<?php

namespace App\Http\Controllers;

use App\Services\Reservations\PublicReservationService;
use App\Services\Reservations\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReservationWidgetController extends Controller
{
    public function __construct(private PublicReservationService $public) {}

    public function show()
    {
        $origins = array_filter(config('reservation_widget.embed_origins'), fn ($url) => preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]+)?$~', $url));
        $response = response()->view('reservation-widget', ['configuration' => $this->public->configuration()]);
        $response->headers->remove('X-Frame-Options');

        return $response->header('Content-Security-Policy', "frame-ancestors 'self' ".implode(' ', $origins))
            ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function query(Request $r): array
    {
        return $r->validate(['date' => ['required', 'date_format:Y-m-d'], 'party_size' => ['required', 'integer', 'between:1,100'], 'room_id' => ['nullable', 'uuid']]);
    }

    public function slots(Request $r)
    {
        return response()->json(['slots' => $this->public->slots($this->query($r))])->header('Cache-Control', 'no-store');
    }

    public function submit(Request $r)
    {
        $data = $r->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'], 'party_size' => ['required', 'integer', 'between:1,100'],
            'room_id' => ['nullable', 'uuid'], 'waitlist' => ['required', 'boolean'], 'request_key' => ['required', 'uuid'],
            'guest_name' => ['required', 'string', 'max:120', 'not_regex:/^\s*$/u'], 'phone' => ['required', 'string', 'max:60', 'regex:/^(?=(?:[^0-9]*[0-9]){5})[+()0-9 .\\/-]{5,60}$/'],
            'email' => ['required', 'email:rfc', 'max:200'], 'comment' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['required', 'accepted'], 'company' => ['nullable', 'string', 'max:0'],
        ]);

        return response()->json($this->public->submit($data), 201)->header('Cache-Control', 'no-store');
    }

    public function settings(Request $r)
    {
        abort_unless($r->user()?->access_role === 'admin', 403);

        return response()->json([...$this->public->settings(), 'widget_url' => rtrim(config('app.url'), '/').'/reservieren']);
    }

    public function updateSettings(Request $r, ReservationService $service)
    {
        abort_unless($r->user()?->access_role === 'admin', 403);
        $data = $r->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'configuration' => ['required', 'array:enabled,waitlist_enabled,room_selection,min_party,max_party,lead_minutes,max_days,confirmation_mode,max_auto_party,privacy_url'],
            'configuration.enabled' => ['required', 'boolean'], 'configuration.waitlist_enabled' => ['required', 'boolean'], 'configuration.room_selection' => ['required', 'boolean'],
            'configuration.min_party' => ['required', 'integer', 'between:1,100'],
            'configuration.max_party' => ['required', 'integer', 'between:1,100', 'gte:configuration.min_party'],
            'configuration.lead_minutes' => ['required', 'integer', 'between:0,10080'], 'configuration.max_days' => ['required', 'integer', 'between:1,730'],
            'configuration.confirmation_mode' => ['required', Rule::in(['automatic', 'manual', 'up_to'])],
            'configuration.max_auto_party' => ['required', 'integer', 'between:1,100'],
            'configuration.privacy_url' => ['nullable', 'url:http,https', 'max:500', 'required_if:configuration.enabled,true'],
        ]);

        return DB::transaction(function () use ($data, $service) {
            $service->lock();
            $current = $this->public->settings();
            abort_if((int) $data['revision'] !== $current['revision'], 409, 'Die Online-Regeln wurden bereits geändert. Bitte neu laden.');
            $config = $data['configuration'];
            foreach (['min_party', 'max_party', 'lead_minutes', 'max_days', 'max_auto_party'] as $key) {
                $config[$key] = (int) $config[$key];
            }
            foreach (['enabled', 'waitlist_enabled', 'room_selection'] as $key) {
                $config[$key] = (bool) $config[$key];
            }
            $config['privacy_url'] = $config['privacy_url'] ?? '';
            DB::table('reservation_widget_settings')->where('id', 1)->update(['configuration' => json_encode($config), 'revision' => $current['revision'] + 1]);

            return response()->json($this->public->settings());
        });
    }
}
