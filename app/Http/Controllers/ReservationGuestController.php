<?php

namespace App\Http\Controllers;

use App\Models\ReservationBooking;
use App\Services\Reservations\ReservationGuestActions;
use App\Services\Reservations\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationGuestController extends Controller
{
    private function page(string $state, ?ReservationBooking $booking = null, int $status = 200, ?string $action = null)
    {
        return response()->view('reservations.cancel', compact('state', 'booking', 'action'), $status)
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    }

    public function show(Request $request, string $booking, ReservationGuestActions $guest)
    {
        $record = ReservationBooking::find($booking);
        if (! $record || ! $guest->valid($request, $record)) {
            return $this->page('invalid', status: 410);
        }
        if ($record->status === 'cancelled') {
            return $this->page('cancelled', $record);
        }
        if (! $guest->cancellable($record)) {
            return $this->page('unavailable', status: 410);
        }

        return $this->page('confirm', $record, action: $request->getRequestUri());
    }

    public function cancel(Request $request, string $booking, ReservationGuestActions $guest, ReservationService $service)
    {
        // Validate and mutate under the same lock used by every capacity-changing write.
        return DB::transaction(function () use ($request, $booking, $guest, $service) {
            $service->lock();
            $record = ReservationBooking::whereKey($booking)->lockForUpdate()->first();
            if (! $record || ! $guest->valid($request, $record)) {
                return $this->page('invalid', status: 410);
            }
            if ($record->status === 'cancelled') {
                return $this->page('cancelled', $record);
            }
            if (! $guest->cancellable($record)) {
                return $this->page('unavailable', status: 410);
            }
            if (! $request->boolean('confirm')) {
                return $this->page('confirm', $record, 422, $request->getRequestUri());
            }
            $beforeVersion = $record->version;
            $result = $service->write(['status' => 'cancelled', 'version' => $beforeVersion], null, $record);
            DB::table('reservation_events')->insert(['booking_id' => $record->id, 'user_id' => null,
                'action' => 'guest_cancelled', 'details' => json_encode(['previous_version' => $beforeVersion]), 'created_at' => now()]);

            return $this->page('cancelled', $result);
        });
    }
}
