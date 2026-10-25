<?php

namespace Tests\Feature\Reservations;

use App\Models\InfoIntegration;
use App\Models\InfoReservation;
use App\Models\ReservationBooking;
use App\Models\User;
use App\Services\Info\ResmioSync;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ResmioTestDatabase;
use Tests\TestCase;

class ResmioBookingSyncTest extends TestCase
{
    use RefreshDatabase, ResmioTestDatabase {
        ResmioTestDatabase::migrateFreshUsing insteadof RefreshDatabase;
    }

    private array $rows = [];

    private array $resources = [['id' => 41, 'name' => '1']];

    private bool $tagsFail = false;

    private array $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Berlin'));
        $this->actingAs(User::factory()->create(['access_role' => 'admin']));
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'test-api-key']);
        $this->room = $this->postJson('/api/reservations/floor-plan/rooms', ['name' => 'Restaurant', 'template' => 'restaurant'])->assertCreated()->json();
        $this->rows = [$this->row()];
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $this->assertSame('GET', $request->method());
            $this->assertSame(['test-api-key'], $request->header('Api-Key'));
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/bookings')) {
                $this->assertSame('2027-10-26T22:00:00Z', $request['date__lt']);

                return Http::response(['objects' => $this->rows, 'meta' => ['next' => null]]);
            }
            if (str_ends_with($path, '/resources')) {
                return Http::response(['objects' => $this->resources]);
            }
            if (str_ends_with($path, '/booking_tags')) {
                return $this->tagsFail ? Http::response(['private' => 'test-api-key'], 403) : Http::response(['objects' => [['id' => 1, 'name' => 'Geburtstag']]]);
            }
            if (str_ends_with($path, '/customer_tags')) {
                return Http::response(['objects' => [['id' => 8, 'name' => 'VIP']]]);
            }
            $this->fail('Unexpected endpoint: '.$path);
        });
    }

    private function row(array $extra = []): array
    {
        return array_replace([
            'id' => 100, 'facility' => '/v1/facility/hemingway', 'date' => '2026-10-26T18:00:00Z', 'updated' => '2026-10-26T10:00:00Z',
            'num' => 2, 'booking_timespan' => 105, 'name' => 'Resmio Gast', 'phone' => '0210312345', 'email' => 'gast@example.test',
            'status' => 'confirmed', 'walk_in' => false, 'source' => 'widget', 'ref_num' => 'ABC123',
            'notes' => 'Interne Resmio-Notiz', 'comment' => 'Bitte am Fenster', 'customer_notes' => 'Stammgast',
            'tags' => ['/v1/facility/hemingway/booking_tags/1'], 'customer_tags' => ['/v1/facility/hemingway/customer_tags/8'],
            'facility_resources' => ['/v1/facility/hemingway/resources/41'], 'booking_request_parameters' => ['Kinderstuhl' => '1'],
            'fb_access_token' => 'must-not-be-stored', 'status_code' => 'must-not-be-stored',
        ], $extra);
    }

    private function imported(): ReservationBooking
    {
        return ReservationBooking::where('resmio_id', '100')->firstOrFail()->load('tables');
    }

    public function test_imports_full_notes_tags_contacts_duration_and_table_once_and_never_writes_to_resmio(): void
    {
        $this->assertSame(1, app(ResmioSync::class)->run());
        $booking = $this->imported();
        $this->assertSame('Resmio Gast', $booking->guest_name);
        $this->assertSame('0210312345', $booking->phone);
        $this->assertSame('19:00', $booking->starts_at->format('H:i'));
        $this->assertSame('20:45', $booking->ends_at->format('H:i'));
        $this->assertSame('Interne Resmio-Notiz', $booking->resmio_notes);
        $this->assertSame('Bitte am Fenster', $booking->guest_comment);
        $this->assertSame('Stammgast', $booking->customer_notes);
        $this->assertSame('Geburtstag', $booking->resmio_tags[0]['name']);
        $this->assertSame('VIP', $booking->customer_tags[0]['name']);
        $this->assertSame('1', $booking->tables->first()->name);
        $this->assertFalse($booking->resmio_unassigned);
        $this->assertArrayNotHasKey('fb_access_token', $booking->resmio_data);
        $this->assertArrayNotHasKey('status_code', $booking->resmio_data);
        app(ResmioSync::class)->run();
        $this->assertDatabaseCount('reservation_bookings', 1);
        $this->assertSame(1, $this->imported()->version);
        $this->assertDatabaseCount('reservation_events', 1);
    }

    public function test_local_table_assignment_is_editable_persists_after_sync_and_can_be_reset(): void
    {
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $table = collect($this->room['tables'])->firstWhere('name', '2')['id'];
        $options = $this->getJson('/api/reservations/bookings/'.$b->id.'/table-options')->assertOk()->json('options');
        $this->assertTrue(collect($options)->contains(fn ($o) => $o['table_ids'] === [$table]));
        $edited = $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'table_ids' => [$table], 'notes' => 'Lokal umgesetzt'])->assertOk()->json();
        $this->assertSame([$table], $edited['resmio_local_table_ids']);
        $this->assertSame('2', $edited['tables'][0]['name']);
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->assertSame('2', $b->tables->first()->name);
        $this->assertSame('Lokal umgesetzt', $b->notes);
        $this->assertSame('1', $b->resmio_tables[0]['name']);
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'reset_resmio_tables' => true])->assertOk()->assertJsonPath('tables.0.name', '1')->assertJsonPath('resmio_local_table_ids', null);
    }

    public function test_imported_table_changes_check_actual_times_and_conflicts_disappear_immediately(): void
    {
        $this->rows = [$this->row(['status' => 'seated']), $this->row(['id' => 101, 'date' => '2026-10-26T19:45:00Z'])];
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $book = $this->getJson('/api/reservations/book?date=2026-10-26')->assertOk()->json('bookings');
        $overlap = \App\Services\Reservations\ReservationService::ALLOCATION_ISSUES[0];
        $this->assertNotContains($overlap, collect($book)->firstWhere('resmio_id', '100')['resmio_issues']);
        $this->assertNotContains($overlap, collect($book)->firstWhere('resmio_id', '101')['resmio_issues']);
        $this->rows[] = $this->row(['id' => 102, 'date' => '2026-10-26T19:30:00Z']);
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $book = $this->getJson('/api/reservations/book?date=2026-10-26')->assertOk()->json('bookings');
        $this->assertContains($overlap, collect($book)->firstWhere('resmio_id', '100')['resmio_issues']);
        $table = collect($this->room['tables'])->firstWhere('name', '2')['id'];
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'table_ids' => [$table]])->assertOk();
        // Changing the other booking's table refreshes the remaining card's conflict without a Resmio poll.
        $book = $this->getJson('/api/reservations/book?date=2026-10-26')->assertOk()->json('bookings');
        $this->assertNotContains($overlap, collect($book)->firstWhere('resmio_id', '100')['resmio_issues']);
        $other = ReservationBooking::where('resmio_id', '101')->firstOrFail();
        $this->putJson('/api/reservations/bookings/'.$other->id, ['version' => $other->version, 'table_ids' => [$table]])->assertOk();
        $third = ReservationBooking::where('resmio_id', '102')->firstOrFail();
        $this->putJson('/api/reservations/bookings/'.$third->id, ['version' => $third->version, 'table_ids' => [$table]])->assertUnprocessable();
        $this->getJson('/api/reservations/book?date=2026-10-28')->assertOk()->assertJsonCount(0, 'bookings');
    }

    public function test_local_assignment_resolves_unmapped_source_and_reschedule_resets_override(): void
    {
        $this->resources = [['id' => 41, 'name' => 'Unbekannt']];
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $table = $this->room['tables'][0]['id'];
        $edited = $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'table_ids' => [$table]])->assertOk()->json();
        $this->assertFalse($edited['resmio_unassigned']);
        $this->assertSame([], $edited['resmio_issues']);
        app(ResmioSync::class)->run();
        $this->assertFalse($this->imported()->resmio_unassigned);
        $this->assertSame([$table], $this->imported()->resmio_local_table_ids);
        $this->rows = [$this->row(['date' => '2026-10-26T20:00:00Z', 'updated' => '2026-10-26T11:00:00Z'])];
        app(ResmioSync::class)->run();
        $this->assertNull($this->imported()->resmio_local_table_ids);
        $this->assertTrue($this->imported()->resmio_unassigned);
    }

    public function test_multiple_unmapped_imports_can_be_resolved_without_allowing_duplicate_occupancy(): void
    {
        $this->resources = [['id' => 41, 'name' => 'Unbekannt']];
        $this->rows = [$this->row(), $this->row(['id' => 101])];
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $tables = collect($this->room['tables']);
        $one = $tables->firstWhere('name', '1')['id'];
        $two = $tables->firstWhere('name', '2')['id'];
        $this->assertNotEmpty($this->getJson('/api/reservations/bookings/'.$b->id.'/table-options')->assertOk()->json('options'));
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'table_ids' => [$one]])->assertOk();
        $other = ReservationBooking::where('resmio_id', '101')->firstOrFail();
        $this->putJson('/api/reservations/bookings/'.$other->id, ['version' => $other->version, 'table_ids' => [$one], 'notes' => 'Should rollback'])->assertUnprocessable();
        $this->assertNull($other->fresh()->notes);
        $this->putJson('/api/reservations/bookings/'.$other->id, ['version' => $other->version, 'table_ids' => [$two]])->assertOk();
        $this->putJson('/api/reservations/bookings/'.$other->id, ['version' => $other->version, 'table_ids' => [$one]])->assertConflict();
        app(ResmioSync::class)->run();
        $this->assertSame([$one], $this->imported()->resmio_local_table_ids);
        $this->assertSame([$two], $other->fresh()->resmio_local_table_ids);
    }

    public function test_imported_combination_selection_honours_custom_capacity_and_local_blocks(): void
    {
        app(ResmioSync::class)->run();
        $tables = collect($this->room['tables']);
        $ids = [$tables->firstWhere('name', '2')['id'], $tables->firstWhere('name', '3')['id']];
        $this->postJson('/api/reservations/combinations', ['room_id' => $this->room['id'], 'name' => 'Tisch 2, 3', 'capacity' => 5, 'table_ids' => $ids])->assertCreated();
        $this->rows = [$this->row(['num' => 5])];
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'table_ids' => [$ids[0]]])->assertUnprocessable();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'table_ids' => $ids])->assertOk()->assertJsonCount(2, 'tables');
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->assertEqualsCanonicalizing($ids, $b->tables->pluck('id')->all());
        $blocked = $tables->firstWhere('name', '4')['id'];
        $this->postJson('/api/reservations/blocks', ['reason' => 'Sperre', 'room_id' => $this->room['id'], 'table_ids' => [$blocked], 'start_date' => '2026-10-26', 'start_time' => '19:00', 'end_date' => '2026-10-26', 'end_time' => '20:45'])->assertCreated();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'table_ids' => [$blocked]])->assertUnprocessable();
    }

    public function test_remote_changes_clear_removed_tags_but_keep_local_notes_and_service_status(): void
    {
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => 1, 'notes' => 'Eigene Ergänzung', 'status' => 'finished'])->assertOk();
        $this->rows = [$this->row(['notes' => 'Neue Resmio-Notiz', 'tags' => [], 'updated' => '2026-10-26T11:00:00Z'])];
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->assertSame('Eigene Ergänzung', $b->notes);
        $this->assertSame('Neue Resmio-Notiz', $b->resmio_notes);
        $this->assertSame([], $b->resmio_tags);
        $this->assertSame('finished', $b->status);
        $this->rows = [$this->row(['status' => 'cancelled', 'num' => 0, 'updated' => '2026-10-26T11:05:00Z'])];
        app(ResmioSync::class)->run();
        $this->assertSame('cancelled', $this->imported()->status);
        $this->assertNull($this->imported()->resmio_local_status);
    }

    public function test_missing_optional_fields_do_not_erase_previous_metadata_and_older_payloads_are_ignored(): void
    {
        app(ResmioSync::class)->run();
        $this->rows = [['id' => 100, 'date' => '2026-10-26T18:00:00Z', 'num' => 2]];
        app(ResmioSync::class)->run();
        $this->assertSame('Interne Resmio-Notiz', $this->imported()->resmio_notes);
        $this->assertSame('Geburtstag', $this->imported()->resmio_tags[0]['name']);
        $this->rows = [$this->row(['notes' => 'Veraltet', 'updated' => '2026-10-26T09:00:00Z'])];
        app(ResmioSync::class)->run();
        $this->assertSame('Interne Resmio-Notiz', $this->imported()->resmio_notes);
    }

    public function test_unassigned_source_tables_block_capacity_and_mapping_rebinds_bookings_immediately(): void
    {
        $this->resources = [['id' => 41, 'name' => 'Unbekannter Tisch']];
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->assertTrue($b->resmio_unassigned);
        $local = ['kind' => 'reservation', 'date' => '2026-10-26', 'time' => '19:00', 'party_size' => 2, 'duration_minutes' => 60, 'guest_name' => 'Lokaler Gast', 'phone' => '12345'];
        $this->postJson('/api/reservations/bookings', $local)->assertUnprocessable();
        $resource = $this->getJson('/api/reservations/resmio/resources')->assertOk()->json('0');
        $this->putJson('/api/reservations/resmio/resources/'.$resource['id'], ['revision' => $resource['revision'], 'table_id' => $this->room['tables'][0]['id']])->assertOk();
        $this->assertFalse($this->imported()->resmio_unassigned);
        $this->assertCount(1, $this->imported()->tables);
        $this->putJson('/api/reservations/resmio/resources/'.$resource['id'], ['revision' => $resource['revision'], 'table_id' => null])->assertConflict();
        $this->postJson('/api/reservations/bookings', $local)->assertCreated();
    }

    public function test_tag_fetch_error_preserves_both_old_tv_and_reservation_data_without_secret_leak(): void
    {
        app(ResmioSync::class)->run();
        $this->tagsFail = true;
        $this->rows = [$this->row(['name' => 'Must not commit'])];
        $this->postJson('/api/reservations/resmio/sync')->assertStatus(502)->assertDontSee('test-api-key');
        $this->assertSame('Resmio Gast', $this->imported()->guest_name);
        $this->assertSame('Resmio Gast', InfoReservation::where('external_id', '100')->first()->guest_name);
    }

    public function test_absent_records_are_flagged_not_silently_cancelled(): void
    {
        app(ResmioSync::class)->run();
        $this->rows = [];
        app(ResmioSync::class)->run();
        $this->assertTrue($this->imported()->resmio_missing);
        $this->assertSame('confirmed', $this->imported()->status);
    }

    public function test_authenticated_delete_webhook_wins_over_later_stale_api_pages_even_after_tv_cleanup(): void
    {
        Queue::fake();
        app(ResmioSync::class)->run();
        InfoIntegration::settings()->update(['webhook_user' => 'webhook', 'webhook_password' => 'test-password']);
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('webhook:test-password')])->postJson('/api/integrations/resmio/webhook', ['id' => 100, 'facility_id' => 'hemingway', 'action' => 'BOOKING_DELETED'])->assertStatus(202);
        InfoReservation::where('external_id', '100')->delete();
        app(ResmioSync::class)->run();
        $this->assertSame('cancelled', $this->imported()->status);
        $this->assertSame('deleted', $this->imported()->resmio_status);
        $this->assertDatabaseCount('info_reservations', 0);
    }

    public function test_imported_core_fields_are_read_only_and_local_edit_does_not_require_source_contact_validation(): void
    {
        $this->rows = [$this->row(['phone' => '', 'email' => 'source@example.test'])];
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => 1, 'guest_name' => 'Do not overwrite'])->assertUnprocessable();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => 1, 'notes' => 'Eigene Notiz'])->assertOk();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => 2, 'status' => 'cancelled'])->assertUnprocessable();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => 2, 'status' => 'arrived'])->assertOk();
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->assertSame('arrived', $b->status);
        $this->assertSame('Eigene Notiz', $b->notes);
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => $b->version, 'reset_resmio_status' => true])->assertOk()->assertJsonPath('status', 'confirmed');
    }

    public function test_rescheduled_source_booking_resets_local_finished_override(): void
    {
        app(ResmioSync::class)->run();
        $b = $this->imported();
        $this->putJson('/api/reservations/bookings/'.$b->id, ['version' => 1, 'status' => 'finished'])->assertOk();
        $this->rows = [$this->row(['date' => '2026-10-26T19:00:00Z', 'updated' => '2026-10-26T11:30:00Z'])];
        app(ResmioSync::class)->run();
        $this->assertSame('confirmed', $this->imported()->status);
        $this->assertSame('20:00', $this->imported()->starts_at->format('H:i'));
    }

    public function test_operations_cannot_run_sync_or_change_resource_mappings_but_can_read_imported_details(): void
    {
        app(ResmioSync::class)->run();
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->postJson('/api/reservations/resmio/sync')->assertForbidden();
        $this->getJson('/api/reservations/resmio/resources')->assertForbidden();
        $this->getJson('/api/reservations/book?date=2026-10-26')->assertOk()->assertJsonPath('bookings.0.resmio_tags.0.name', 'Geburtstag')->assertDontSee('test-api-key');
    }
}
