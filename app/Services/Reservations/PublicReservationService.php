<?php

namespace App\Services\Reservations;

use App\Models\ReservationBlock;
use App\Models\ReservationBooking;
use App\Models\ReservationCombination;
use App\Models\ReservationRoom;
use App\Models\ReservationTable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicReservationService
{
    public function __construct(private ReservationService $service) {}

    public function settings(): array
    {
        $row = DB::table('reservation_widget_settings')->where('id', 1)->firstOrFail();

        return ['configuration' => json_decode($row->configuration, true), 'revision' => (int) $row->revision];
    }

    public function configuration(): array
    {
        $config = $this->settings()['configuration'];

        return [...$config, 'today' => CarbonImmutable::now('Europe/Berlin')->toDateString(),
            'last_date' => CarbonImmutable::now('Europe/Berlin')->addDays($config['max_days'])->toDateString(),
            'mail_enabled' => app(ReservationMailService::class)->ready(),
            'rooms' => $config['room_selection'] ? ReservationRoom::whereHas('tables')->orderBy('name')->get(['id', 'name'])->toArray() : [],
        ];
    }

    private function window(array $data, array $config): array
    {
        abort_unless($config['enabled'], 403, 'Online-Reservierungen sind derzeit nicht freigeschaltet.');
        $now = CarbonImmutable::now('Europe/Berlin');
        if ($data['date'] < $now->toDateString() || $data['date'] > $now->addDays($config['max_days'])->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Dieses Datum liegt außerhalb des Buchungszeitraums.']);
        }
        $size = (int) $data['party_size'];
        if ($size < $config['min_party'] || $size > $config['max_party']) {
            throw ValidationException::withMessages(['party_size' => 'Diese Gruppengröße kann online nicht gebucht werden.']);
        }
        $room = $data['room_id'] ?? null;
        if ($room && (! $config['room_selection'] || ! ReservationRoom::whereKey($room)->whereHas('tables')->exists())) {
            throw ValidationException::withMessages(['room_id' => 'Dieser Raum ist online nicht verfügbar.']);
        }

        return [$now, $size, $room];
    }

    // One bounded day snapshot keeps slot reads efficient. Submission always rechecks
    // the authoritative service under its common database lock.
    public function slots(array $data): array
    {
        $config = $this->settings()['configuration'];
        [$now,$size,$room] = $this->window($data, $config);
        $rules = $this->service->settings()['configuration'];
        $day = $this->service->localTime($data['date'], '00:00');
        if (! in_array($day->dayOfWeek, $rules['weekdays'], true)) {
            return [];
        }
        $duration = $this->service->duration($size);
        $first = $this->service->localTime($data['date'], $rules['opens_at']);
        $last = $this->service->localTime($data['date'], $rules['closes_at']);
        $tables = ReservationTable::when($room, fn ($q) => $q->where('room_id', $room))->get();
        $combos = ReservationCombination::when($room, fn ($q) => $q->where('room_id', $room))->get();
        $bookings = $this->service->occupied($first, $last->addMinutes($duration))->with('tables')->get();
        $blocks = ReservationBlock::where('starts_at', '<', ReservationService::stamp($last->addMinutes($duration)))->where('ends_at', '>', ReservationService::stamp($first))->get();
        $result = [];
        for ($start = $first; $start <= $last; $start = $start->addMinutes((int) $rules['slot_minutes'])) {
            if ($start < $now->addMinutes($config['lead_minutes'])) {
                continue;
            }
            $end = $start->addMinutes($duration);
            $overlap = $bookings->filter(fn ($b) => $b->starts_at < $end && $b->ends_at > $start);
            $uncertain = $overlap->contains(fn ($b) => $b->source === 'resmio' && $b->resmio_unassigned);
            $used = $overlap->flatMap(fn ($b) => $b->tables->pluck('id'))->all();
            $activeBlocks = $blocks->filter(fn ($b) => CarbonImmutable::parse($b->starts_at) < $end && CarbonImmutable::parse($b->ends_at) > $start);
            // An explicit restaurant/selected-room closure is not an invitation to wait.
            if ($activeBlocks->contains(fn ($b) => ! $b->table_ids && (! $b->room_id || ($room && $b->room_id === $room)))) {
                continue;
            }
            $free = $tables->filter(fn ($t) => ! $uncertain && ! in_array($t->id, $used, true) && ! $activeBlocks->contains(fn ($b) => $b->table_ids ? in_array($t->id, $b->table_ids, true) : (! $b->room_id || $b->room_id === $t->room_id)));
            $ids = $free->pluck('id')->all();
            $available = $free->contains(fn ($t) => $t->capacity >= $size) || $combos->contains(fn ($c) => $c->capacity >= $size && count($c->table_ids) >= 2 && ! array_diff($c->table_ids, $ids));
            if ($available || $config['waitlist_enabled']) {
                $result[] = ['time' => $start->format('H:i'), 'status' => $available ? 'available' : 'waitlist'];
            }
        }

        return $result;
    }

    public function submit(array $data): array
    {
        $values = ['date' => $data['date'], 'time' => $data['time'], 'party_size' => (int) $data['party_size'], 'room_id' => $data['room_id'] ?? null,
            'waitlist' => (bool) $data['waitlist'], 'guest_name' => trim($data['guest_name']), 'phone' => trim($data['phone']), 'email' => mb_strtolower(trim($data['email'])),
            'comment' => trim($data['comment'] ?? ''), 'privacy' => true];
        $hash = hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($data, $values, $hash) {
            $this->service->lock();
            $existing = ReservationBooking::where('public_request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->public_request_hash, $hash), 409, 'Diese Anfrage wurde bereits mit anderen Angaben verarbeitet. Bitte keine zweite Buchung absenden.');

                return $this->receipt($existing);
            }
            $config = $this->settings()['configuration'];
            $this->window($values, $config);
            $slot = collect($this->slots($values))->firstWhere('time', $values['time']);
            $expected = $values['waitlist'] ? 'waitlist' : 'available';
            abort_unless($slot && $slot['status'] === $expected, 409, 'Diese Uhrzeit hat sich inzwischen geändert. Bitte wähle sie erneut aus.');
            $duration = $this->service->duration($values['party_size']);
            $confirmed = $config['confirmation_mode'] === 'automatic' || ($config['confirmation_mode'] === 'up_to' && $values['party_size'] <= $config['max_auto_party']);
            $booking = $this->service->write([
                'kind' => $values['waitlist'] ? 'waitlist' : 'reservation', 'source' => 'widget',
                'status' => $values['waitlist'] ? 'waiting' : ($confirmed ? 'confirmed' : 'unconfirmed'),
                'date' => $values['date'], 'time' => $values['time'], 'party_size' => $values['party_size'],
                'duration_minutes' => $duration, 'guest_name' => $values['guest_name'], 'phone' => $values['phone'], 'email' => $values['email'],
                'room_id' => $values['room_id'], 'table_ids' => [],
            ], null);
            $booking->update(['guest_comment' => $values['comment'] ?: null, 'public_request_key' => $data['request_key'], 'public_request_hash' => $hash,
                'public_reference' => Str::upper(Str::random(10)),
                'public_meta' => ['privacy_accepted_at' => CarbonImmutable::now()->toIso8601String(), 'privacy_url' => $config['privacy_url'],
                    'requested_room_id' => $values['room_id'], 'requested_room_name' => $values['room_id'] ? ReservationRoom::find($values['room_id'])?->name : null],
            ]);

            app(ReservationMailService::class)->record($booking);

            return $this->receipt($booking);
        });
    }

    private function receipt(ReservationBooking $booking): array
    {
        return ['reference' => $booking->public_reference, 'status' => $booking->status, 'date' => $booking->starts_at->toDateString(),
            'time' => $booking->starts_at->format('H:i'), 'party_size' => $booking->party_size, 'room' => $booking->public_meta['requested_room_name'] ?? null];
    }
}
