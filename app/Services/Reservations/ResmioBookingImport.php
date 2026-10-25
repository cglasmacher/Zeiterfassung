<?php

namespace App\Services\Reservations;

use App\Models\ReservationBooking;
use App\Models\ReservationTable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResmioBookingImport
{
    public function resources(string $facility, array $catalog): void
    {
        foreach ($catalog as $resource) {
            $existing = DB::table('reservation_resmio_resources')->where('facility', $facility)->where('external_id', $resource['id'])->first();
            $tableId = $existing?->table_id;
            if (($existing?->mapping_mode ?? 'auto') === 'auto' && (! $tableId || ! ReservationTable::find($tableId))) {
                $matches = ReservationTable::all()->filter(fn ($t) => mb_strtolower(trim($t->name)) === mb_strtolower(trim($resource['name'])));
                $tableId = $matches->count() === 1 ? $matches->first()->id : null;
            }
            $name = mb_substr($resource['name'], 0, 255);
            $revision = $existing ? $existing->revision + (($existing->name !== $name || $existing->table_id !== $tableId) ? 1 : 0) : 1;
            DB::table('reservation_resmio_resources')->updateOrInsert(['facility' => $facility, 'external_id' => $resource['id']], ['name' => $name, 'table_id' => $tableId, 'mapping_mode' => $existing?->mapping_mode ?? 'auto', 'revision' => $revision]);
        }
    }

    public function apply(string $facility, array $rows, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row['id'];
            $this->one($facility, $row);
        }
        // Missing from a list is not proof of cancellation. Keep capacity and surface the uncertainty.
        $missing = ReservationBooking::where('resmio_facility', $facility)->where('starts_at', '>=', ReservationService::stamp($from))->where('starts_at', '<', ReservationService::stamp($until))
            ->whereNotIn('resmio_id', $ids)->whereIn('status', ReservationBooking::ACTIVE)->get();
        foreach ($missing as $booking) {
            if (! $booking->resmio_missing) {
                $booking->update(['resmio_missing' => true, 'version' => $booking->version + 1]);
            }
        }
    }

    public function one(string $facility, array $row): void
    {
        if (\App\Models\InfoReservation::where('external_id', $row['id'])->where('facility', $facility)->where('status', 'deleted')->exists()) {
            $this->deleted($facility, $row['id']);

            return;
        }
        $booking = ReservationBooking::where('resmio_facility', $facility)->where('resmio_id', $row['id'])->lockForUpdate()->first();
        if (DB::table('reservation_resmio_deletions')->where('facility', $facility)->where('external_id', $row['id'])->exists() || $booking?->resmio_status === 'deleted') {
            return;
        }
        if ($booking?->resmio_updated_at && ! empty($row['updated']) && CarbonImmutable::parse($row['updated'])->lt($booking->resmio_updated_at)) {
            return;
        }
        $before = $booking?->load('tables')->toArray();
        $snapshot = array_replace($booking?->resmio_data ?? [], $row);
        $rawStatus = strtolower((string) ($snapshot['status'] ?? 'confirmed'));
        $remoteStatus = match ($rawStatus) {
            'unconfirmed', 'confirmed', 'arrived', 'seated', 'finished', 'cancelled', 'waiting' => $rawStatus,
            'noshow', 'no_show' => 'noshow', 'canceled', 'deleted', 'removed' => 'cancelled',
            default => 'unconfirmed',
        };
        $issues = [];
        if (! in_array($rawStatus, ['unconfirmed', 'confirmed', 'arrived', 'seated', 'finished', 'cancelled', 'waiting', 'noshow', 'no_show', 'canceled', 'deleted', 'removed'], true)) {
            $issues[] = 'Unbekannter Resmio-Status: '.$rawStatus;
        }
        if ($snapshot['num'] === 0 && in_array($remoteStatus, ReservationBooking::ACTIVE, true)) {
            throw new \App\Exceptions\ResmioSyncException('Eine aktive resmio-Buchung enthält keine Gästeanzahl.');
        }
        $start = CarbonImmutable::parse($snapshot['date']);
        $duration = $snapshot['booking_timespan'] ?? app(ReservationService::class)->duration(max(1, $snapshot['num']));
        $localStatus = $booking?->resmio_local_status;
        if ($booking && $booking->starts_at->ne($start)) {
            $localStatus = null;
        }
        if (in_array($remoteStatus, ['cancelled', 'noshow', 'finished'], true)) {
            $localStatus = null;
        }
        $status = $localStatus ?? $remoteStatus;
        $localTableIds = $booking?->resmio_local_table_ids;
        if ($booking && ($booking->starts_at->ne($start) || $booking->party_size !== $snapshot['num'])) {
            $localTableIds = null;
        }
        $mapped = [];
        $externalTables = [];
        foreach ($snapshot['facility_resources'] ?? [] as $id) {
            $mapping = DB::table('reservation_resmio_resources')->where('facility', $facility)->where('external_id', $id)->first();
            $table = $mapping?->table_id ? ReservationTable::find($mapping->table_id) : null;
            if ($table) {
                $mapped[] = $table->id;
            } elseif ($localTableIds === null) {
                $issues[] = 'Tisch nicht zugeordnet: '.($mapping?->name ?? '#'.$id);
            }
            $externalTables[] = ['id' => $id, 'name' => $mapping?->name ?? '#'.$id, 'table_id' => $table?->id];
        }
        if ($localTableIds !== null) {
            $mapped = ReservationTable::whereIn('id', $localTableIds)->pluck('id')->all();
        }
        $mapped = array_values(array_unique($mapped));
        $active = in_array($status, ReservationBooking::ACTIVE, true);
        $unassigned = $active && (! $mapped || count($mapped) !== count($localTableIds ?? $externalTables));
        if ($active && ! $externalTables && $localTableIds === null) {
            $issues[] = 'Resmio hat noch keinen Tisch zugeordnet.';
        }
        if ($active) {
            $issues = array_merge($issues, app(ReservationService::class)->allocationIssues($start, $start->addMinutes($duration), $mapped, $snapshot['num'], $booking?->id));
        }
        $booking ??= new ReservationBooking(['id' => (string) Str::uuid()]);
        $booking->fill([
            'kind' => $remoteStatus === 'waiting' ? 'waitlist' : (! empty($snapshot['walk_in']) ? 'walkin' : 'reservation'), 'source' => 'resmio',
            'status' => $status, 'party_size' => $snapshot['num'], 'starts_at' => $start, 'ends_at' => $start->addMinutes($duration),
            'guest_name' => mb_substr($snapshot['name'] ?? '', 0, 120), 'phone' => mb_substr($snapshot['phone'] ?? '', 0, 60), 'email' => mb_substr($snapshot['email'] ?? '', 0, 200),
            'resmio_facility' => $facility, 'resmio_id' => $row['id'], 'resmio_status' => $rawStatus, 'resmio_local_status' => $localStatus,
            'resmio_updated_at' => empty($snapshot['updated']) ? null : CarbonImmutable::parse($snapshot['updated']),
            'resmio_notes' => $snapshot['notes'] ?? null, 'guest_comment' => $snapshot['comment'] ?? null, 'customer_notes' => $snapshot['customer_notes'] ?? null,
            'resmio_tags' => $snapshot['tags'] ?? [], 'customer_tags' => $snapshot['customer_tags'] ?? [], 'resmio_tables' => $externalTables,
            'resmio_local_table_ids' => $localTableIds, 'resmio_data' => $snapshot, 'resmio_issues' => array_values(array_unique($issues)), 'resmio_unassigned' => $unassigned, 'resmio_missing' => false,
        ]);
        $oldIds = $before ? collect($before['tables'])->pluck('id')->sort()->values()->all() : [];
        $newIds = $mapped;
        sort($newIds);
        $changed = ! $before || $booking->isDirty() || $oldIds !== $newIds;
        $booking->resmio_synced_at = CarbonImmutable::now();
        if ($changed) {
            $booking->version = ($booking->version ?? 0) + 1;
        }
        $booking->save();
        $booking->tables()->sync($remoteStatus === 'waiting' ? [] : $mapped);
        if ($changed) {
            DB::table('reservation_events')->insert(['booking_id' => $booking->id, 'user_id' => null, 'action' => 'resmio_sync', 'details' => json_encode(['resmio_id' => $booking->resmio_id, 'issues' => $booking->resmio_issues]), 'created_at' => now()]);
        }
    }

    // Caller holds the integration lock and then the common reservation lock.
    public function deleted(string $facility, string $id): void
    {
        DB::table('reservation_resmio_deletions')->updateOrInsert(['facility' => $facility, 'external_id' => $id], ['deleted_at' => now()]);
        $booking = ReservationBooking::where('resmio_facility', $facility)->where('resmio_id', $id)->first();
        if ($booking && $booking->resmio_status !== 'deleted') {
            $booking->update(['status' => 'cancelled', 'resmio_status' => 'deleted', 'resmio_local_status' => null, 'resmio_unassigned' => false, 'resmio_missing' => false, 'version' => $booking->version + 1]);
            DB::table('reservation_events')->insert(['booking_id' => $booking->id, 'user_id' => null, 'action' => 'resmio_deleted', 'details' => '{}', 'created_at' => now()]);
        }
    }
}
