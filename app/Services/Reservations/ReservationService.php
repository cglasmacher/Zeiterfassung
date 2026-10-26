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
            ->where('ends_at', '>', self::stamp($start));
    }

    public function candidates(CarbonImmutable $start, CarbonImmutable $end, int $size, ?string $roomId = null, ?string $except = null, bool $resolveImported = false): array
    {
        $inventory = $this->tableInventory($start, $end, $except, $resolveImported);
        $free = collect($inventory)->filter(fn ($t) => $t['available'] && (! $roomId || $t['room_id'] === $roomId))->map(fn ($t) => (object) $t)->keyBy('id');
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

    public function tableInventory(CarbonImmutable $start, CarbonImmutable $end, ?string $except = null, bool $resolveImported = false): array
    {
        $occupied = $this->occupied($start, $end, $except)->with('tables')->get();
        $uncertain = ! $resolveImported && $occupied->contains(fn ($b) => $b->source === 'resmio' && $b->resmio_unassigned);
        $blocks = ReservationBlock::where('starts_at', '<', self::stamp($end))->where('ends_at', '>', self::stamp($start))->get();

        return ReservationTable::orderBy('name')->get()->map(function ($table) use ($occupied, $uncertain, $blocks) {
            $conflicts = $occupied->filter(fn ($b) => $b->tables->contains('id', $table->id));
            $blocked = $blocks->first(fn ($b) => $b->table_ids ? in_array($table->id, $b->table_ids, true) : (! $b->room_id || $b->room_id === $table->room_id));
            $reason = $blocked ? 'Gesperrt: '.$blocked->reason : ($conflicts->isNotEmpty() ? 'Belegt '.$conflicts->map(fn ($b) => $b->starts_at->format('H:i').'–'.$b->ends_at->format('H:i'))->unique()->implode(', ') : ($uncertain ? 'Ungeklärte Resmio-Belegung' : null));

            return ['id' => $table->id, 'room_id' => $table->room_id, 'name' => $table->name, 'capacity' => (int) $table->capacity, 'available' => $reason === null, 'reason' => $reason];
        })->all();
    }

    public function selectionCapacity(array $ids): int
    {
        $capacity = ReservationTable::whereIn('id', $ids)->sum('capacity');
        $combo = ReservationCombination::all()->first(function ($c) use ($ids) {
            $a = $c->table_ids;
            $b = $ids;
            sort($a);
            sort($b);

            return $a === $b;
        });

        return (int) ($combo?->capacity ?? $capacity);
    }

    public function validateTableSelection(array $ids, CarbonImmutable $start, CarbonImmutable $end, int $size, ?string $except = null, bool $resolveImported = false, ?string $roomId = null): array
    {
        $tables = ReservationTable::whereIn('id', $ids)->get();
        $available = collect($this->tableInventory($start, $end, $except, $resolveImported))->where('available', true)->pluck('id')->all();
        if (! $ids || $tables->count() !== count($ids) || count(array_diff($ids, $available)) > 0) {
            throw ValidationException::withMessages(['table_ids' => 'Mindestens ein ausgewählter Tisch ist für diesen Zeitraum belegt, gesperrt oder nicht mehr verfügbar.']);
        }
        if ($tables->pluck('room_id')->unique()->count() !== 1 || ($roomId && $tables->first()->room_id !== $roomId)) {
            throw ValidationException::withMessages(['table_ids' => 'Bitte Tische innerhalb desselben Raumes auswählen.']);
        }
        // Manual selections may use extra chairs; capacity is advisory.
        // Automatic allocation still requires the configured number of seats.

        return $ids;
    }

    public const ALLOCATION_ISSUES = [
        'Tischüberschneidung mit einer anderen Buchung – bitte prüfen.',
        'Resmio-Buchung liegt in einer lokalen Tisch- oder Raumsperre.',
        'Resmio-Gruppengröße überschreitet die lokal hinterlegte Tischkapazität.',
    ];

    public function allocationIssues(CarbonImmutable $start, CarbonImmutable $end, array $ids, int $size, ?string $except = null): array
    {
        if (! $ids) {
            return [];
        }
        $issues = [];
        if ($this->occupied($start, $end, $except)->whereHas('tables', fn ($q) => $q->whereIn('reservation_tables.id', $ids))->exists()) {
            $issues[] = self::ALLOCATION_ISSUES[0];
        }
        $tables = ReservationTable::whereIn('id', $ids)->get();
        $rooms = $tables->pluck('room_id')->all();
        $blocks = ReservationBlock::where('starts_at', '<', self::stamp($end))->where('ends_at', '>', self::stamp($start))->get();
        if ($blocks->contains(fn ($b) => $b->table_ids ? count(array_intersect($ids, $b->table_ids)) > 0 : (! $b->room_id || in_array($b->room_id, $rooms, true)))) {
            $issues[] = self::ALLOCATION_ISSUES[1];
        }
        if ($tables->sum('capacity') < $size) {
            $allowed = ReservationCombination::where('capacity', '>=', $size)->get()->contains(function ($c) use ($ids) {
                $a = $c->table_ids;
                $b = $ids;
                sort($a);
                sort($b);

                return $a === $b;
            });
            if (! $allowed) {
                $issues[] = self::ALLOCATION_ISSUES[2];
            }
        }

        return $issues;
    }

    public function withCurrentIssues(ReservationBooking $booking): ReservationBooking
    {
        if ($booking->source !== 'resmio') {
            return $booking;
        }
        $issues = array_values(array_filter($booking->resmio_issues ?? [], fn ($issue) => ! in_array($issue, self::ALLOCATION_ISSUES, true)
            && ! ($booking->resmio_local_table_ids !== null && (str_starts_with($issue, 'Tisch nicht zugeordnet:') || $issue === 'Resmio hat noch keinen Tisch zugeordnet.'))));
        if (in_array($booking->status, ReservationBooking::ACTIVE, true)) {
            $issues = array_merge($issues, $this->allocationIssues($booking->starts_at, $booking->ends_at, $booking->tables->pluck('id')->all(), $booking->party_size, $booking->id));
        }
        $booking->resmio_issues = array_values(array_unique($issues));

        return $booking;
    }

    public function checkHours(CarbonImmutable $start): void
    {
        $config = $this->settings()['configuration'];
        if (! in_array($start->dayOfWeek, $config['weekdays'], true) || $start->format('H:i') < $config['opens_at'] || $start->format('H:i') > $config['closes_at']) {
            throw ValidationException::withMessages(['time' => 'Der Reservierungsbeginn liegt außerhalb der eingestellten Reservierungszeiten. Walk-ins können vor Ort auch außerhalb dieser Zeiten erfasst werden.']);
        }
    }

    public function write(array $data, ?int $userId, ?ReservationBooking $booking = null, bool $promote = false): ReservationBooking
    {
        return DB::transaction(function () use ($data, $userId, $booking, $promote) {
            $this->lock();
            if ($booking) {
                $booking = ReservationBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                abort_if($booking->version !== (int) ($data['version'] ?? 0), 409, 'Diese Buchung wurde bereits geändert. Bitte die Tagesübersicht neu laden.');
                if ($booking->source === 'resmio') {
                    if ($promote) {
                        throw ValidationException::withMessages(['status' => 'Resmio-Wartelisteneinträge bitte in Resmio übernehmen.']);
                    }

                    return $this->importedLocal($booking->load('tables'), $data, $userId);
                }
            }
            if (($data['status'] ?? null) === 'arrived') {
                $data['status'] = 'seated';
            }
            $before = $booking?->load('tables')->toArray();
            $kind = $booking?->kind ?? $data['kind'];
            $status = $data['status'] ?? $booking?->status ?? ($kind === 'waitlist' ? 'waiting' : ($kind === 'walkin' ? 'seated' : 'confirmed'));
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
                if ($ids) {
                    $ids = $this->validateTableSelection($ids, $start, $end, $size, $booking?->id, false, $data['room_id'] ?? null);
                } else {
                    $selected = $this->candidates($start, $end, $size, $data['room_id'] ?? null, $booking?->id)[0] ?? null;
                    if (! $selected) {
                        throw ValidationException::withMessages(['table_ids' => 'Für die gesamte Aufenthaltsdauer ist kein passender freier Tisch oder keine erlaubte Kombination verfügbar.']);
                    }
                    $ids = $selected['table_ids'];
                }
            }
            $booking ??= new ReservationBooking(['id' => (string) Str::uuid()]);
            $booking->fill($values)->save();
            $booking->tables()->sync($ids);
            $result = $booking->fresh()->load('tables');
            DB::table('reservation_events')->insert(['booking_id' => $result->id, 'user_id' => $userId, 'action' => $promote ? 'promoted' : ($before ? 'updated' : 'created'), 'details' => json_encode(['before' => $before, 'after' => $result->toArray()]), 'created_at' => now()]);

            app(ReservationMailService::class)->record($result, $before);

            return $result;
        });
    }

    private function importedLocal(ReservationBooking $booking, array $data, int $userId): ReservationBooking
    {
        $previousStatus = $booking->status;
        if (($data['status'] ?? null) === 'arrived') {
            $data['status'] = 'seated';
        }
        if (array_diff(array_keys($data), ['version', 'notes', 'status', 'reset_resmio_status', 'table_ids', 'reset_resmio_tables'])) {
            throw ValidationException::withMessages(['source' => 'Buchungsdaten dieser Reservierung werden in Resmio gepflegt. Hier sind eigene Notizen, lokale Tischzuordnungen und Serviceaktionen möglich.']);
        }
        if (array_key_exists('notes', $data)) {
            $booking->notes = $data['notes'];
        }
        if (array_key_exists('table_ids', $data) || ! empty($data['reset_resmio_tables'])) {
            if (! in_array($booking->status, ReservationBooking::ACTIVE, true) || $booking->kind === 'waitlist') {
                throw ValidationException::withMessages(['table_ids' => 'Tische können nur für eine aktive Reservierung oder einen Walk-in geändert werden.']);
            }
            if (! empty($data['reset_resmio_tables']) && array_key_exists('table_ids', $data)) {
                throw ValidationException::withMessages(['table_ids' => 'Bitte entweder einen lokalen Tisch wählen oder die Resmio-Zuordnung übernehmen.']);
            }
            $ids = ! empty($data['reset_resmio_tables'])
                ? array_column($booking->resmio_tables ?? [], 'table_id') : $data['table_ids'];
            $ids = $this->validateTableSelection($ids, $booking->starts_at, $booking->ends_at, $booking->party_size, $booking->id, true);
            $booking->resmio_local_table_ids = ! empty($data['reset_resmio_tables']) ? null : $ids;
            $booking->tables()->sync($ids);
            $booking->load('tables');
            $booking->resmio_unassigned = false;
        }
        if (! empty($data['reset_resmio_status'])) {
            $booking->resmio_local_status = null;
            $booking->status = match ($booking->resmio_status) {
                'waiting','unconfirmed','confirmed','seated','finished' => $booking->resmio_status,
                'arrived' => 'seated',
                'noshow','no_show' => 'noshow', 'cancelled','canceled','deleted','removed' => 'cancelled', default => 'unconfirmed',
            };
        } elseif (isset($data['status'])) {
            if ($booking->resmio_status === 'waiting') {
                throw ValidationException::withMessages(['status' => 'Wartelistenübernahme bitte in Resmio durchführen.']);
            }
            if (! in_array($data['status'], ['unconfirmed', 'confirmed', 'seated', 'finished', 'cancelled', 'noshow'], true)) {
                throw ValidationException::withMessages(['status' => 'Bitte einen gültigen Reservierungsstatus wählen.']);
            }
            if (in_array($booking->resmio_status, ['cancelled', 'canceled', 'deleted', 'removed', 'noshow', 'no_show', 'finished'], true)) {
                throw ValidationException::withMessages(['status' => 'Diese Buchung wurde bereits in Resmio beendet.']);
            }
            if ($data['status'] === 'seated' || (in_array($data['status'], ReservationBooking::ACTIVE, true) && ! in_array($booking->status, ReservationBooking::ACTIVE, true))) {
                if ($booking->resmio_unassigned || $booking->tables->isEmpty()) {
                    throw ValidationException::withMessages(['status' => 'Bitte zuerst alle Resmio-Tische zuordnen und erneut synchronisieren.']);
                }
                $ids = $booking->tables->pluck('id')->all();
                if ($this->occupied($booking->starts_at, $booking->ends_at, $booking->id)->whereHas('tables', fn ($q) => $q->whereIn('reservation_tables.id', $ids))->exists()) {
                    throw ValidationException::withMessages(['status' => 'Ein zugeordneter Tisch überschneidet sich mit einer anderen Buchung.']);
                }
                $rooms = $booking->tables->pluck('room_id')->all();
                $blocks = ReservationBlock::where('starts_at', '<', self::stamp($booking->ends_at))->where('ends_at', '>', self::stamp($booking->starts_at))->get();
                if ($blocks->contains(fn ($b) => $b->table_ids ? count(array_intersect($ids, $b->table_ids)) > 0 : (! $b->room_id || in_array($b->room_id, $rooms, true)))) {
                    throw ValidationException::withMessages(['status' => 'Der zugeordnete Tisch ist lokal gesperrt. Bitte zuerst die Sperre prüfen.']);
                }
            }
            $booking->status = $data['status'];
            $booking->resmio_local_status = $data['status'];
        }
        if (! in_array($previousStatus, ReservationBooking::ACTIVE, true) && in_array($booking->status, ReservationBooking::ACTIVE, true)) {
            $this->validateTableSelection($booking->tables->pluck('id')->all(), $booking->starts_at, $booking->ends_at, $booking->party_size, $booking->id, true);
        }
        $booking->resmio_unassigned = in_array($booking->status, ReservationBooking::ACTIVE, true) && ($booking->tables->isEmpty() || $booking->tables->count() !== count($booking->resmio_local_table_ids ?? $booking->resmio_tables ?? []));
        $this->withCurrentIssues($booking);
        $booking->version++;
        $booking->save();
        DB::table('reservation_events')->insert(['booking_id' => $booking->id, 'user_id' => $userId, 'action' => 'updated', 'details' => json_encode(['local_service_status' => $booking->resmio_local_status, 'local_notes_changed' => array_key_exists('notes', $data), 'local_table_ids' => $booking->resmio_local_table_ids]), 'created_at' => now()]);

        return $booking->fresh()->load('tables');
    }
}
