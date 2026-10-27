<?php

namespace App\Services\Reservations;

use App\Models\ReservationBooking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationReminderService
{
    public function settings(): array
    {
        $row = DB::table('reservation_mail_settings')->where('id', 1)->firstOrFail();

        return ['reminders_enabled' => (bool) $row->reminders_enabled, 'reminder_hours' => (int) $row->reminder_hours, 'revision' => (int) $row->revision];
    }

    public function key(ReservationBooking $booking): string
    {
        return hash('sha256', implode('|', [$booking->id, ReservationService::stamp($booking->starts_at), mb_strtolower(trim($booking->email ?? ''))]));
    }

    public function eligible(ReservationBooking $booking, ?array $settings = null): bool
    {
        $settings ??= $this->settings();
        $due = $booking->starts_at->subHours($settings['reminder_hours']);

        return $settings['reminders_enabled'] && $booking->public_reference && $booking->source !== 'resmio'
            && $booking->status === 'confirmed' && $booking->starts_at->isFuture()
            && filter_var($booking->email, FILTER_VALIDATE_EMAIL) && $booking->confirmed_at
            && $booking->confirmed_at->lte($due) && $due->lte(now()) && $due->gte(now()->subHour());
    }

    public function queueDue(int $limit = 100): int
    {
        $settings = $this->settings();
        if (! app(ReservationMailService::class)->ready() || ! $settings['reminders_enabled']) {
            return 0;
        }
        $upper = CarbonImmutable::now()->addHours($settings['reminder_hours']);
        $ids = ReservationBooking::where('status', 'confirmed')->whereNotNull('public_reference')->where('source', '!=', 'resmio')
            ->whereBetween('starts_at', [ReservationService::stamp($upper->subHour()), ReservationService::stamp($upper)])
            ->orderBy('starts_at')->pluck('id');
        $queued = 0;
        foreach ($ids as $id) {
            if ($queued >= $limit) {
                break;
            }
            $queued += DB::transaction(function () use ($id) {
                app(ReservationService::class)->lock();
                $booking = ReservationBooking::whereKey($id)->lockForUpdate()->first();
                $settings = $this->settings();
                if (! $booking || ! $this->eligible($booking, $settings)) {
                    return 0;
                }
                $payload = app(ReservationMailService::class)->snapshot($booking);
                $due = $booking->starts_at->subHours($settings['reminder_hours']);
                // Do not follow a late first confirmation with a second mail a minute later.
                $hasConfirmation = DB::table('reservation_mail_outbox')->where('booking_id', $id)->where('status', 'sent')
                    ->whereIn('type', ['confirmed', 'updated'])->where('sent_at', '<=', ReservationService::stamp($due))->get()
                    ->contains(function ($row) use ($payload) {
                        $sent = json_decode($row->payload, true);

                        return $sent['email'] === $payload['email'] && $sent['date'] === $payload['date'] && $sent['time'] === $payload['time'];
                    });
                if (! $hasConfirmation) {
                    return 0;
                }

                return DB::table('reservation_mail_outbox')->insertOrIgnore([
                    'id' => (string) Str::uuid(), 'booking_id' => $id, 'booking_version' => $booking->version,
                    'type' => 'reminder', 'reminder_key' => $this->key($booking), 'status' => 'pending', 'payload' => json_encode($payload),
                    'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        }

        return $queued;
    }
}
