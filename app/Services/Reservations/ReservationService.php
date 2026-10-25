<?php

namespace App\Services\Reservations;

use App\Models\ReservationBlock;
use App\Models\ReservationBooking;
use App\Models\ReservationCombination;
use App\Models\ReservationTable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public static function stamp($date): string
    {
        return CarbonImmutable::parse($date)->utc()->format('Y-m-d H:i:s');
    }

    // Every capacity-changing write (including the builder) takes this same lock first.
    public function lock(): object
    {
        return DB::table('reservation_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    public function settings(): array
    {
        $row = DB::table('reservation_settings')->where('id', 1)->firstOrFail();

        return ['configuration' => json_decode($row->configuration, true), 'revision' => (int) $row->revision];
    }

    public function duration(int $size): int
    {
        $config = $this->settings()['configuration'];
        $duration = $config['default_duration'];
        $rules = $config['durations'];
        usort($rules, fn ($a, $b) => $a['from'] <=> $b['from']);
        foreach ($rules as $rule) {
            if ($size >= $rule['from']) {
                $duration = $rule['minutes'];
            }
        }

        return $duration;
    }

    public function localTime(string $date, string $time): CarbonImmutable
    {
        $value = "$date $time";
        $result = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, 'Europe/Berlin');
        if (! $result || $result->format('Y-m-d H:i') !== $value) {
            throw ValidationException::withMessages(['time' => 'Diese Uhrzeit existiert an diesem Datum nicht (Zeitumstellung).']);
        }

        return $result;
    }

    public function occupied(CarbonImmutable $start, CarbonImmutable $end, ?string $except = null)
    {
        return ReservationBooking::whereIn('status', ReservationBooking::ACTIVE)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->where('starts_at', '<', self::stamp($end))
            ->where(function ($q) use ($start) {
                $q->where('ends_at', '>', self::stamp($start))
                    ->orWhere(fn ($q) => $q->where('status', 'seated')->where('ends_at', '<=', self::stamp(now())));
            });
    }

    public function candidates(CarbonImmutable $start, CarbonImmutable $end, int $size, ?string $roomId = null, ?string $except = null): array
    {
        $tables = ReservationTable::query()->when($roomId, fn ($q) => $q->where('room_id', $roomId))->get()->keyBy('id');
        $occupied = $this->occupied($start, $end, $except)->with('tables')->get()->flatMap(fn ($b) => $b->tables->pluck('id'))->all();
        $blocks = ReservationBlock::where('starts_at', '<', self::stamp($end))->where('ends_at', '>', self::stamp($start))->get();
        $free = $tables->filter(function ($table) use ($occupied, $blocks) {
            if (in_array($table->id, $occupied, true)) {
                return false;
            }

            return ! $blocks->contains(fn ($block) => $block->table_ids
                ? in_array($table->id, $block->table_ids, true)
                : (! $block->room_id || $block->room_id === $table->room_id));
        });
        $options = [];
        foreach ($free as $table) {
            if ($table->capacity >= $size) {
                $options[] = ['id' => $table->id, 'room_id' => $table->room_id, 'name' => 'Tisch '.$table->name, 'capacity' => $table->capacity, 'table_ids' => [$table->id]];
            }
        }
        foreach (ReservationCombination::when($roomId, fn ($q) => $q->where('room_id', $roomId))->get() as $combo) {
            if ($combo->capacity >= $size && count($combo->table_ids) >= 2 && count(array_diff($combo->table_ids, $free->keys()->all())) === 0) {
                $options[] = ['id' => $combo->id, 'room_id' => $combo->room_id, 'name' => $combo->name, 'capacity' => $combo->capacity, 'table_ids' => $combo->table_ids];
            }
        }
        usort($options, fn ($a, $b) => [$a['capacity'], count($a['table_ids']), $a['name'], $a['id']] <=> [$b['capacity'], count($b['table_ids']), $b['name'], $b['id']]);

        return $options;
    }

    public function checkHours(CarbonImmutable $start): void
    {
        $config = $this->settings()['configuration'];
        if (! in_array($start->dayOfWeek, $config['weekdays'], true) || $start->format('H:i') < $config['opens_at'] || $start->format('H:i') > $config['closes_at']) {
            throw ValidationException::withMessages(['time' => 'Der Reservierungsbeginn liegt außerhalb der eingestellten Reservierungszeiten. Walk-ins können vor Ort auch außerhalb dieser Zeiten erfasst werden.']);
        }
    }

    public function write(array $data, int $userId, ?ReservationBooking $booking = null, bool $promote = false): ReservationBooking
    {
        return DB::transaction(function () use ($data, $userId, $booking, $promote) {
            $this->lock();
            if ($booking) {
                $booking = ReservationBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                abort_if($booking->version !== (int) ($data['version'] ?? 0), 409, 'Diese Buchung wurde bereits geändert. Bitte die Tagesübersicht neu laden.');
            }
            $before = $booking?->load('tables')->toArray();
            $kind = $booking?->kind ?? $data['kind'];
            $status = $data['status'] ?? ($kind === 'waitlist' ? 'waiting' : ($kind === 'walkin' ? 'seated' : 'confirmed'));
            if ($promote) {
                abort_unless($booking?->status === 'waiting', 422, 'Nur wartende Einträge können übernommen werden.');
                $kind = $data['kind'] ?? 'reservation';
                abort_unless(in_array($kind, ['reservation', 'walkin'], true), 422);
                $status = $kind === 'walkin' ? 'seated' : 'confirmed';
            }
            if (($kind === 'waitlist' && ! in_array($status, ['waiting', 'cancelled'], true)) || ($kind !== 'waitlist' && $status === 'waiting')) {
                throw ValidationException::withMessages(['status' => 'Zum Platzieren bitte die Funktion „Warteliste übernehmen“ verwenden.']);
            }
            $start = isset($data['date'], $data['time']) ? $this->localTime($data['date'], $data['time']) : ($booking?->starts_at ?? CarbonImmutable::now('Europe/Berlin')->startOfMinute());
            if (! $booking && $kind === 'walkin' || $promote && $kind === 'walkin') {
                $start = CarbonImmutable::now('Europe/Berlin')->startOfMinute();
            }
            $size = (int) ($data['party_size'] ?? $booking->party_size);
            $minutes = (int) ($data['duration_minutes'] ?? ($booking ? $booking->starts_at->diffInMinutes($booking->ends_at) : $this->duration($size)));
            $end = $start->addMinutes($minutes);
            $values = [
                'kind' => $kind, 'source' => $data['source'] ?? $booking?->source ?? ($kind === 'walkin' ? 'walkin' : 'phone'),
                'status' => $status, 'party_size' => $size, 'starts_at' => $start, 'ends_at' => $end,
                'version' => ($booking?->version ?? 0) + 1,
            ];
            foreach (['guest_name', 'phone', 'email', 'notes'] as $field) {
                $values[$field] = array_key_exists($field, $data) ? (is_string($data[$field]) ? trim($data[$field]) : null) : $booking?->$field;
            }
            $active = in_array($status, ReservationBooking::ACTIVE, true);
            $config = $this->settings()['configuration'];
            if ($kind === 'reservation' && $active) {
                foreach (['guest_name' => 'require_name', 'phone' => 'require_phone'] as $field => $setting) {
                    $statusOnly = $booking && count(array_diff(array_keys($data), ['status', 'version'])) === 0;
                    if (! $statusOnly && $config[$setting] && ! $values[$field]) {
                        throw ValidationException::withMessages([$field => $field === 'phone' ? 'Bitte eine Telefonnummer eingeben.' : 'Bitte einen Gastnamen eingeben.']);
                    }
                }
                // A status-only update to an existing booking must remain possible after hours change.
                if (! $booking || $promote || $start->ne($booking->starts_at)) {
                    $this->checkHours($start);
                }
            }
            if ($kind === 'waitlist' && ! $values['guest_name']) {
                throw ValidationException::withMessages(['guest_name' => 'Bitte die wartende Gruppe benennen.']);
            }
            $ids = $data['table_ids'] ?? ($booking ? $booking->tables->pluck('id')->all() : []);
            if ($kind === 'waitlist') {
                $ids = [];
            }
            if ($active) {
                $options = $this->candidates($start, $end, $size, $data['room_id'] ?? null, $booking?->id);
                $selected = null;
                if ($ids) {
                    sort($ids);
                    foreach ($options as $option) {
                        $optionIds = $option['table_ids'];
                        sort($optionIds);
                        if ($optionIds === $ids) {
                            $selected = $option;
                            break;
                        }
                    }
                } else {
                    $selected = $options[0] ?? null;
                }
                if (! $selected) {
                    throw ValidationException::withMessages(['table_ids' => 'Für die gesamte Aufenthaltsdauer ist kein passender freier Tisch oder keine erlaubte Kombination verfügbar.']);
                }
                $ids = $selected['table_ids'];
            }
            $booking ??= new ReservationBooking(['id' => (string) Str::uuid()]);
            $booking->fill($values)->save();
            $booking->tables()->sync($ids);
            $result = $booking->fresh()->load('tables');
            DB::table('reservation_events')->insert(['booking_id' => $result->id, 'user_id' => $userId, 'action' => $promote ? 'promoted' : ($before ? 'updated' : 'created'), 'details' => json_encode(['before' => $before, 'after' => $result->toArray()]), 'created_at' => now()]);

            return $result;
        });
    }
}
