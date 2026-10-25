<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Jobs\SyncInfoReservations;
use App\Models\InfoIntegration;
use App\Models\InfoReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResmioWebhookController extends Controller
{
    public function __invoke(Request $r)
    {
        $s = InfoIntegration::settings();
        abort_unless($s->enabled && $s->webhook_user && $s->webhook_password
            && hash_equals($s->webhook_user, (string) $r->getUser())
            && hash_equals($s->webhook_password, (string) $r->getPassword()), 401);
        $data = $r->validate(['id' => 'required|integer|min:1', 'facility_id' => 'required|string', 'action' => 'required|string']);
        abort_unless(hash_equals((string) $s->facility, $data['facility_id']), 403);
        $action = strtoupper($data['action']);
        abort_unless(in_array($action, ['BOOKING_CREATED', 'BOOKING_UPDATED', 'BOOKING_DELETED']), 422);
        if ($action === 'BOOKING_DELETED') {
            DB::transaction(function () use ($s, $data) {
                $current = InfoIntegration::whereKey($s->id)->lockForUpdate()->firstOrFail();
                abort_unless($current->enabled && $current->facility === $s->facility, 409);
                app(\App\Services\Reservations\ReservationService::class)->lock();
                app(\App\Services\Reservations\ResmioBookingImport::class)->deleted($s->facility, (string) $data['id']);
                $reservation = InfoReservation::firstOrNew(['external_id' => (string) $data['id']]);
                if (! $reservation->exists) {
                    $reservation->fill(['facility' => $s->facility, 'starts_at' => now(), 'people' => 0]);
                }
                $reservation->status = 'deleted';
                $reservation->facility = $s->facility;
                $reservation->save();
            });
        }
        SyncInfoReservations::dispatch();

        return response()->json(['accepted' => true], 202);
    }
}
