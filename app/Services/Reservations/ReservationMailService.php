<?php

namespace App\Services\Reservations;

use App\Mail\ReservationGuestMail;
use App\Models\ReservationBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReservationMailService
{
    public function configured(): bool
    {
        $mailer = config('mail.mailers.'.config('mail.default'), []);
        $transport = $mailer['transport'] ?? null;
        $from = config('mail.from.address', '');

        return in_array($transport, ['smtp', 'sendmail', 'ses', 'postmark', 'resend'], true)
            && ($transport !== 'smtp' || ! empty($mailer['host']) || ! empty($mailer['url']))
            && filter_var($from, FILTER_VALIDATE_EMAIL)
            && ! preg_match('/@example\.(com|test|org|net)$/i', $from);
    }

    public function ready(): bool
    {
        return config('reservation_mail.enabled') && $this->configured();
    }

    public function snapshot(ReservationBooking $booking): array
    {
        return [
            'reference' => $booking->public_reference, 'guest_name' => $booking->guest_name,
            'email' => mb_strtolower(trim($booking->email ?? '')), 'status' => $booking->status,
            'date' => $booking->starts_at->format('d.m.Y'), 'time' => $booking->starts_at->format('H:i'),
            'party_size' => $booking->party_size,
        ];
    }

    // Called inside the booking transaction. Never contact SMTP while holding the capacity lock.
    public function record(ReservationBooking $booking, ?array $before = null): void
    {
        if (! config('reservation_mail.enabled') || ! $booking->public_reference || $booking->source === 'resmio'
            || ! filter_var($booking->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $payload = $this->snapshot($booking);
        $previousStatus = $before['status'] ?? null;
        $changed = ! $before || ReservationService::stamp($before['starts_at']) !== ReservationService::stamp($booking->starts_at)
            || collect(['guest_name', 'party_size', 'email'])->contains(fn ($key) => (string) ($before[$key] ?? '') !== (string) $booking->$key);
        $type = match (true) {
            $booking->status === 'cancelled' && ($previousStatus !== 'cancelled' || $changed) => $booking->kind === 'waitlist' ? 'waiting_cancelled' : 'cancelled',
            $booking->status === 'waiting' && (! $before || $previousStatus !== 'waiting') => 'waiting',
            $booking->status === 'waiting' && $changed => 'waiting_updated',
            $booking->status === 'unconfirmed' && (! $before || $previousStatus !== 'unconfirmed' || $changed) => 'requested',
            $booking->status === 'confirmed' && (! $before || $previousStatus !== 'confirmed') => 'confirmed',
            $booking->status === 'confirmed' && $changed => 'updated',
            default => null,
        };
        if (! $type) {
            return;
        }
        DB::table('reservation_mail_outbox')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'booking_id' => $booking->id, 'booking_version' => $booking->version,
            'type' => $type, 'payload' => json_encode($payload), 'status' => 'pending',
            'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function sendDue(int $limit = 25): array
    {
        $counts = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'skipped' => 0];
        if (! $this->ready()) {
            return $counts;
        }
        for ($i = 0; $i < $limit; $i++) {
            $row = DB::transaction(function () {
                $row = DB::table('reservation_mail_outbox')->where(function ($q) {
                    $q->where(fn ($pending) => $pending->where('status', 'pending')->where('available_at', '<=', now()))
                        ->orWhere(fn ($lease) => $lease->where('status', 'processing')->where('locked_at', '<=', now()->subMinutes(10)));
                })->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
                if (! $row) {
                    return null;
                }
                $row->lock_token = (string) Str::uuid();
                $row->attempts++;
                DB::table('reservation_mail_outbox')->where('id', $row->id)->update([
                    'status' => 'processing', 'locked_at' => now(), 'lock_token' => $row->lock_token,
                    'attempts' => $row->attempts, 'updated_at' => now(),
                ]);

                return $row;
            });
            if (! $row) {
                break;
            }
            $query = DB::table('reservation_mail_outbox')->where('id', $row->id)->where('lock_token', $row->lock_token);
            $payload = json_decode($row->payload, true);
            $booking = ReservationBooking::find($row->booking_id);
            // A superseded pending confirmation must not arrive after a cancellation or changed date/address.
            $superseded = DB::table('reservation_mail_outbox')->where('booking_id', $row->booking_id)->where('booking_version', '>', $row->booking_version)->exists();
            if ($superseded || ! $booking || $booking->source === 'resmio' || $this->snapshot($booking) !== $payload) {
                $query->update(['status' => 'skipped', 'locked_at' => null, 'lock_token' => null, 'updated_at' => now()]);
                $counts['skipped']++;

                continue;
            }
            try {
                Mail::to($payload['email'])->send(new ReservationGuestMail($payload, $row->type, $row->id));
                $query->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'locked_at' => null, 'lock_token' => null, 'updated_at' => now()]);
                $counts['sent']++;
            } catch (\Throwable $e) {
                $failed = $row->attempts >= 8;
                $query->update(['status' => $failed ? 'failed' : 'pending',
                    'available_at' => now()->addMinutes(min(360, 2 ** min($row->attempts, 9))),
                    // Transport messages may contain credentials or guest content; never expose them.
                    'last_error' => 'Der Mailserver hat den Versand nicht bestätigt. SMTP-Einstellungen und Serverprotokoll prüfen.',
                    'locked_at' => null, 'lock_token' => null, 'updated_at' => now()]);
                $counts[$failed ? 'failed' : 'retry']++;
            }
        }

        return $counts;
    }
}
