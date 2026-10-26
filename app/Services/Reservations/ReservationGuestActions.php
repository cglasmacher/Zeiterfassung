<?php

namespace App\Services\Reservations;

use App\Models\ReservationBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ReservationGuestActions
{
    public function state(ReservationBooking $booking): string
    {
        return hash_hmac('sha256', implode('|', [$booking->id, mb_strtolower(trim($booking->email ?? '')),
            ReservationService::stamp($booking->starts_at), $booking->party_size]), config('app.key'));
    }

    public function cancellable(ReservationBooking $booking): bool
    {
        return $booking->public_reference && $booking->source !== 'resmio'
            && in_array($booking->status, ['unconfirmed', 'confirmed', 'waiting'], true)
            && $booking->starts_at->isFuture();
    }

    public function url(ReservationBooking $booking): ?string
    {
        if (! $this->cancellable($booking)) {
            return null;
        }
        // Sign a relative URL and prefix only the configured portal origin, never the incoming Host header.
        $path = URL::temporarySignedRoute('reservations.cancel', $booking->starts_at, [
            'booking' => $booking->id, 'state' => $this->state($booking),
        ], absolute: false);

        return rtrim(config('app.url'), '/').$path;
    }

    public function valid(Request $request, ReservationBooking $booking): bool
    {
        return $request->hasValidRelativeSignature() && $booking->public_reference && $booking->source !== 'resmio'
            && hash_equals($this->state($booking), (string) $request->query('state'));
    }
}
