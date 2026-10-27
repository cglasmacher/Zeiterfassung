<?php

namespace Tests\Feature\Reservations;

use App\Models\ReservationBooking;
use App\Models\ReservationTable;
use App\Models\User;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationBookTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000004_create_departments_table.php',
            'database/migrations/2026_10_21_100000_create_infosystem_tables.php',
            'database/migrations/2026_10_21_110000_add_workspace_access.php',
            'database/migrations/2026_10_25_220000_create_reservation_floor_plans.php',
            'database/migrations/2026_10_25_230000_create_reservation_book.php',
            'database/migrations/2026_10_25_235000_add_resmio_reservation_sync.php',
            'database/migrations/2026_10_25_235500_add_local_reservation_table_assignment.php',
            'database/migrations/2026_10_26_140000_create_reservation_widget.php',
            'database/migrations/2026_10_26_150000_create_reservation_mail_outbox.php',
            'database/migrations/2026_10_26_160000_add_reservation_guest_actions.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00', 'Europe/Berlin'));
        $this->actingAs(User::factory()->create(['access_role' => 'admin']));
    }

    private function room(): array
    {
        return $this->postJson('/api/reservations/floor-plan/rooms', ['name' => 'Restaurant', 'template' => 'restaurant'])->assertCreated()->json();
    }

    private function booking(array $extra = []): array
    {
        return array_replace(['kind' => 'reservation', 'date' => '2026-10-26', 'time' => '12:00', 'party_size' => 2, 'duration_minutes' => 60, 'guest_name' => 'Testgast', 'phone' => '0210312345'], $extra);
    }

    private function block(array $extra = []): array
    {
        return array_replace(['reason' => 'Geschlossene Gesellschaft', 'start_date' => '2026-10-26', 'start_time' => '12:00', 'end_date' => '2026-10-26', 'end_time' => '14:00'], $extra);
    }

    public function test_table_inventory_includes_small_free_tables_and_explains_time_specific_occupancy(): void
    {
        $room = $this->room();
        $ids = collect($room['tables'])->keyBy('name');
        $b = $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$ids['1']['id']]]))->assertCreated()->json();
        $this->postJson('/api/reservations/blocks', $this->block(['room_id' => $room['id'], 'table_ids' => [$ids['2']['id']]]))->assertCreated();
        $query = '/api/reservations/availability?'.http_build_query(['kind' => 'reservation', 'date' => '2026-10-26', 'time' => '12:00', 'party_size' => 15, 'duration_minutes' => 60]);
        $tables = collect($this->getJson($query)->assertOk()->json('tables'))->keyBy('id');
        $this->assertFalse($tables[$ids['1']['id']]['available']);
        $this->assertStringContainsString('12:00–13:00', $tables[$ids['1']['id']]['reason']);
        $this->assertFalse($tables[$ids['2']['id']]['available']);
        $this->assertStringContainsString('Gesperrt', $tables[$ids['2']['id']]['reason']);
        $this->assertTrue($tables[$ids['3']['id']]['available']);
        $own = collect($this->getJson('/api/reservations/bookings/'.$b['id'].'/table-options')->assertOk()->json('tables'))->keyBy('id');
        $this->assertTrue($own[$ids['1']['id']]['available']);
        $later = collect($this->getJson(str_replace('time=12%3A00', 'time=14%3A00', $query))->assertOk()->json('tables'))->keyBy('id');
        $this->assertTrue($later[$ids['1']['id']]['available']);
        $this->assertTrue($later[$ids['2']['id']]['available']);
    }

    public function test_manual_multi_table_selection_uses_total_seats_and_rechecks_all_members_on_save(): void
    {
        $room = $this->room();
        $tables = collect($room['tables'])->keyBy('name');
        $ids = collect(['11', '12', '13', '14', '15', '16'])->map(fn ($name) => $tables[$name]['id'])->all();
        $small = $this->postJson('/api/reservations/bookings', $this->booking(['party_size' => 15, 'table_ids' => $ids]))->assertCreated()->assertJsonCount(6, 'tables')->json();
        $this->putJson('/api/reservations/bookings/'.$small['id'], ['version' => 1, 'status' => 'cancelled'])->assertOk();
        $ids[] = $tables['17']['id'];
        $ids[] = $tables['1']['id'];
        $b = $this->postJson('/api/reservations/bookings', $this->booking(['party_size' => 15, 'table_ids' => $ids]))->assertCreated()->assertJsonCount(8, 'tables')->json();
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$tables['16']['id']]]))->assertUnprocessable();
        $this->putJson('/api/reservations/bookings/'.$b['id'], ['version' => 1, 'table_ids' => $ids])->assertOk();
        $this->putJson('/api/reservations/bookings/'.$b['id'], ['version' => 1, 'table_ids' => $ids])->assertConflict();
        $other = $this->room();
        $this->putJson('/api/reservations/bookings/'.$b['id'], ['version' => 2, 'table_ids' => array_merge($ids, [$other['tables'][0]['id']])])->assertUnprocessable();
        $this->assertCount(8, ReservationBooking::find($b['id'])->tables);
    }

    public function test_seven_guests_can_save_three_two_seat_tables_and_automatic_allocation_still_needs_capacity(): void
    {
        $room = $this->room();
        $tables = collect($room['tables'])->keyBy('name');
        $ids = collect(['1', '2', '3'])->map(fn ($name) => $tables[$name]['id'])->all();
        $this->postJson('/api/reservations/bookings', $this->booking(['party_size' => 7]))->assertUnprocessable();
        $b = $this->postJson('/api/reservations/bookings', $this->booking(['party_size' => 7, 'table_ids' => $ids]))->assertCreated()->assertJsonPath('party_size', 7)->assertJsonCount(3, 'tables')->json();
        $this->putJson('/api/reservations/bookings/'.$b['id'], ['version' => 1, 'status' => 'seated'])->assertOk();
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$ids[0]]]))->assertUnprocessable();
        $this->putJson('/api/reservations/bookings/'.$b['id'], ['version' => 2, 'table_ids' => [$ids[0]], 'party_size' => 8])->assertOk()->assertJsonPath('party_size', 8)->assertJsonCount(1, 'tables');
    }

    public function test_legacy_arrived_bookings_are_one_arrival_state_without_losing_occupancy(): void
    {
        $room = $this->room();
        $table = $room['tables'][0]['id'];
        $b = $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$table]]))->assertCreated()->json();
        \Illuminate\Support\Facades\DB::table('reservation_bookings')->where('id', $b['id'])->update(['status' => 'arrived']);
        $this->getJson('/api/reservations/book?date=2026-10-26')->assertOk()->assertJsonPath('bookings.0.status', 'seated');
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$table]]))->assertUnprocessable();
        $this->putJson('/api/reservations/bookings/'.$b['id'], ['version' => 1, 'status' => 'arrived'])->assertOk()->assertJsonPath('status', 'seated');
    }

    public function test_operations_can_run_service_but_cannot_change_settings_or_builder(): void
    {
        $this->get('/reservierungen')->assertOk();
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->get('/reservierungen')->assertOk();
        $this->getJson('/api/reservations/book?date=2026-10-26')->assertOk()->assertJsonPath('can_manage', false);
        $this->get('/reservierungen/verwaltung')->assertForbidden();
        $this->get('/reservierungen/tischplan')->assertForbidden();
        $this->putJson('/api/reservations/settings', [])->assertForbidden();
        $this->postJson('/api/reservations/combinations', [])->assertForbidden();
        $this->postJson('/api/reservations/bookings', $this->booking(['kind' => 'waitlist']))->assertCreated();
        $this->actingAs(User::factory()->create(['access_role' => 'none']));
        $this->get('/reservierungen')->assertForbidden();
        $this->getJson('/api/reservations/book?date=2026-10-26')->assertForbidden();
        $this->postJson('/api/reservations/bookings', $this->booking())->assertForbidden();
    }

    public function test_reservation_persists_local_time_as_utc_and_is_in_the_correct_day(): void
    {
        $this->room();
        $r = $this->postJson('/api/reservations/bookings', $this->booking())->assertCreated()->assertJsonPath('status', 'confirmed')->json();
        $this->assertDatabaseHas('reservation_bookings', ['id' => $r['id'], 'starts_at' => '2026-10-26 11:00:00', 'ends_at' => '2026-10-26 12:00:00']);
        $this->assertSame('12:00', ReservationBooking::find($r['id'])->starts_at->format('H:i'));
        $this->getJson('/api/reservations/book?date=2026-10-26')->assertOk()->assertJsonCount(1, 'bookings');
        $this->getJson('/api/reservations/book?date=2026-10-27')->assertOk()->assertJsonCount(0, 'bookings');
        $this->getJson('/api/reservations/bookings/'.$r['id'].'/events')->assertOk()->assertJsonPath('0.action', 'created');
    }

    public function test_overlap_is_rejected_but_adjacent_bookings_are_allowed(): void
    {
        $room = $this->room();
        $id = $room['tables'][0]['id'];
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$id]]))->assertCreated();
        $this->postJson('/api/reservations/bookings', $this->booking(['time' => '12:30', 'table_ids' => [$id]]))->assertUnprocessable();
        $this->postJson('/api/reservations/bookings', $this->booking(['time' => '13:00', 'table_ids' => [$id]]))->assertCreated();
        $this->assertDatabaseCount('reservation_bookings', 2);
    }

    public function test_real_combinations_use_custom_capacity_and_lock_each_member_table(): void
    {
        $room = $this->room();
        $ids = array_column(array_slice($room['tables'], 0, 2), 'id');
        $combo = $this->postJson('/api/reservations/combinations', ['name' => 'Zusammen', 'room_id' => $room['id'], 'capacity' => 5, 'table_ids' => $ids])->assertCreated()->json();
        $r = $this->postJson('/api/reservations/bookings', $this->booking(['party_size' => 5]))->assertCreated()->json();
        $this->assertCount(2, $r['tables']);
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$ids[0]]]))->assertUnprocessable();
        $this->deleteJson('/api/reservations/combinations/'.$combo['id'])->assertConflict();
        $this->putJson('/api/reservations/bookings/'.$r['id'], ['version' => 1, 'status' => 'cancelled'])->assertOk();
        $this->deleteJson('/api/reservations/combinations/'.$combo['id'])->assertNoContent();
    }

    public function test_hemingway_import_is_repeatable_and_does_not_create_duplicate_combinations(): void
    {
        $room = $this->room();
        $this->postJson('/api/reservations/combinations/import', ['room_id' => $room['id']])->assertOk()->assertJsonPath('created', 11);
        $this->postJson('/api/reservations/combinations/import', ['room_id' => $room['id']])->assertOk()->assertJsonPath('created', 0);
        $this->assertDatabaseCount('reservation_combinations', 11);
    }

    public function test_waitlist_holds_no_capacity_and_failed_promotion_is_atomic(): void
    {
        $room = $this->room();
        $id = $room['tables'][0]['id'];
        $waiting = $this->postJson('/api/reservations/bookings', $this->booking(['kind' => 'waitlist']))->assertCreated()->assertJsonCount(0, 'tables')->json();
        $r = $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$id]]))->assertCreated()->json();
        $this->postJson('/api/reservations/bookings/'.$waiting['id'].'/promote', ['version' => 1, 'kind' => 'reservation', 'table_ids' => [$id]])->assertUnprocessable();
        $this->assertDatabaseHas('reservation_bookings', ['id' => $waiting['id'], 'status' => 'waiting', 'version' => 1]);
        $this->putJson('/api/reservations/bookings/'.$r['id'], ['version' => 1, 'status' => 'cancelled'])->assertOk();
        $this->postJson('/api/reservations/bookings/'.$waiting['id'].'/promote', ['version' => 1, 'kind' => 'reservation', 'table_ids' => [$id]])->assertOk()->assertJsonPath('status', 'confirmed')->assertJsonPath('version', 2);
    }

    public function test_walkins_are_seated_now_without_contact_data_even_outside_booking_hours(): void
    {
        $this->room();
        $this->travelTo(CarbonImmutable::parse('2026-10-27 22:14:20', 'Europe/Berlin'));
        $r = $this->postJson('/api/reservations/bookings', ['kind' => 'walkin', 'party_size' => 2])->assertCreated()->assertJsonPath('status', 'seated')->json();
        $this->assertSame('2026-10-27 22:14', ReservationBooking::find($r['id'])->starts_at->format('Y-m-d H:i'));
        $this->assertSame(105, (int) ReservationBooking::find($r['id'])->starts_at->diffInMinutes(ReservationBooking::find($r['id'])->ends_at));
        $this->postJson('/api/reservations/bookings', $this->booking(['date' => '2026-10-27']))->assertUnprocessable();
    }

    public function test_stale_updates_are_rejected_and_finishing_releases_the_table(): void
    {
        $room = $this->room();
        $id = $room['tables'][0]['id'];
        $r = $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$id]]))->assertCreated()->json();
        $this->putJson('/api/reservations/bookings/'.$r['id'], ['version' => 1, 'status' => 'seated'])->assertOk();
        $this->putJson('/api/reservations/bookings/'.$r['id'], ['version' => 1, 'status' => 'cancelled'])->assertConflict();
        $this->putJson('/api/reservations/bookings/'.$r['id'], ['version' => 2, 'status' => 'finished'])->assertOk();
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$id]]))->assertCreated();
    }

    public function test_seated_bookings_only_block_their_actual_time_interval(): void
    {
        $room = $this->room();
        $id = $room['tables'][0]['id'];
        $r = $this->postJson('/api/reservations/bookings', $this->booking(['time' => '10:00', 'duration_minutes' => 60, 'table_ids' => [$id]]))->assertCreated()->json();
        $this->putJson('/api/reservations/bookings/'.$r['id'], ['version' => 1, 'status' => 'seated'])->assertOk();
        $this->postJson('/api/reservations/bookings', $this->booking(['time' => '10:30', 'table_ids' => [$id]]))->assertUnprocessable();
        $this->postJson('/api/reservations/bookings', $this->booking(['time' => '11:00', 'duration_minutes' => 60, 'table_ids' => [$id]]))->assertCreated();
        $this->postJson('/api/reservations/bookings', $this->booking(['time' => '18:00', 'table_ids' => [$id]]))->assertCreated();
        $this->getJson('/api/reservations/book?date=2026-10-28')->assertOk()->assertJsonCount(0, 'bookings');
    }

    public function test_sperrungen_reject_existing_bookings_and_block_new_allocations(): void
    {
        $room = $this->room();
        $id = $room['tables'][0]['id'];
        $r = $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$id]]))->assertCreated()->json();
        $this->postJson('/api/reservations/blocks', $this->block(['room_id' => $room['id']]))->assertConflict();
        $this->assertDatabaseCount('reservation_blocks', 0);
        $this->putJson('/api/reservations/bookings/'.$r['id'], ['version' => 1, 'status' => 'cancelled'])->assertOk();
        $block = $this->postJson('/api/reservations/blocks', $this->block(['room_id' => $room['id'], 'table_ids' => [$id]]))->assertCreated()->json();
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$id]]))->assertUnprocessable();
        $this->postJson('/api/reservations/bookings', $this->booking(['time' => '14:00', 'table_ids' => [$id]]))->assertCreated();
        $this->deleteJson('/api/reservations/blocks/'.$block['id'])->assertNoContent();
    }

    public function test_global_block_applies_to_all_rooms_and_waitlist_is_still_possible(): void
    {
        $this->room();
        $this->postJson('/api/reservations/blocks', $this->block())->assertCreated();
        $this->postJson('/api/reservations/bookings', $this->booking())->assertUnprocessable();
        $this->postJson('/api/reservations/bookings', $this->booking(['kind' => 'waitlist']))->assertCreated();
    }

    public function test_builder_cannot_remove_or_shrink_a_booked_table(): void
    {
        $room = $this->room();
        $id = $room['tables'][0]['id'];
        $this->postJson('/api/reservations/bookings', $this->booking(['table_ids' => [$id]]))->assertCreated();
        $payload = ['revision' => 1, 'name' => $room['name'], 'width' => $room['width'], 'height' => $room['height'], 'elements' => [], 'tables' => array_map(fn ($t) => array_intersect_key($t, array_flip(['id', 'name', 'capacity', 'shape', 'x', 'y', 'width', 'height', 'rotation'])), $room['tables'])];
        $removed = array_shift($payload['tables']);
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $payload)->assertUnprocessable();
        $removed['capacity'] = 1;
        array_unshift($payload['tables'], $removed);
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $payload)->assertUnprocessable();
        $this->assertEquals(15, ReservationTable::where('room_id', $room['id'])->count());
    }

    public function test_durations_settings_revision_and_nonexistent_dst_time(): void
    {
        $service = app(ReservationService::class);
        $this->assertSame([90, 105, 135, 150], array_map(fn ($n) => $service->duration($n), [1, 2, 4, 6]));
        $settings = $service->settings();
        $settings['configuration']['default_duration'] = 75;
        $this->putJson('/api/reservations/settings', $settings)->assertOk()->assertJsonPath('revision', 2);
        $this->putJson('/api/reservations/settings', $settings)->assertConflict();
        $this->room();
        $this->postJson('/api/reservations/bookings', $this->booking(['date' => '2027-03-28', 'time' => '02:30']))->assertUnprocessable()->assertJsonValidationErrors('time');
    }

    public function test_rejected_writes_leave_no_booking_or_history_and_invalid_cross_room_groups_fail(): void
    {
        $one = $this->room();
        $two = $this->room();
        $this->postJson('/api/reservations/combinations', ['room_id' => $one['id'], 'name' => 'Falsch', 'capacity' => 6, 'table_ids' => [$one['tables'][0]['id'], $two['tables'][0]['id']]])->assertUnprocessable();
        $this->postJson('/api/reservations/bookings', $this->booking(['party_size' => 100]))->assertUnprocessable();
        $this->assertDatabaseCount('reservation_bookings', 0);
        $this->assertDatabaseCount('reservation_events', 0);
        $this->postJson('/api/reservations/bookings', $this->booking(['email' => 'invalid']))->assertUnprocessable();
        $this->postJson('/api/reservations/blocks', $this->block(['end_time' => '11:00']))->assertUnprocessable();
        $this->assertDatabaseCount('reservation_blocks', 0);
    }

    public function test_settings_normalize_numeric_strings_and_status_changes_keep_legacy_bookings_usable(): void
    {
        $this->room();
        $service = app(ReservationService::class);
        $settings = $service->settings();
        $settings['configuration']['require_name'] = false;
        $settings['configuration']['require_phone'] = false;
        $settings['configuration']['weekdays'] = ['0', '1', '3', '4', '5', '6'];
        $this->putJson('/api/reservations/settings', $settings)->assertOk()->assertJsonPath('configuration.weekdays.1', 1);
        $booking = $this->postJson('/api/reservations/bookings', $this->booking(['phone' => null, 'guest_name' => null]))->assertCreated()->json();
        $settings = $service->settings();
        $settings['configuration']['require_name'] = true;
        $settings['configuration']['require_phone'] = true;
        $this->putJson('/api/reservations/settings', $settings)->assertOk();
        $this->putJson('/api/reservations/bookings/'.$booking['id'], ['version' => 1, 'status' => 'arrived'])->assertOk();
    }

    public function test_waitlist_cannot_be_seated_by_status_change_and_duplicate_promotion_is_rejected(): void
    {
        $this->room();
        $booking = $this->postJson('/api/reservations/bookings', $this->booking(['kind' => 'waitlist']))->assertCreated()->json();
        $this->putJson('/api/reservations/bookings/'.$booking['id'], ['version' => 1, 'status' => 'seated'])->assertUnprocessable();
        $this->postJson('/api/reservations/bookings/'.$booking['id'].'/promote', ['version' => 1, 'kind' => 'walkin'])->assertOk()->assertJsonPath('status', 'seated');
        $this->postJson('/api/reservations/bookings/'.$booking['id'].'/promote', ['version' => 2, 'kind' => 'walkin'])->assertUnprocessable();
    }

    public function test_active_bookings_crossing_midnight_remain_visible_on_the_next_day(): void
    {
        $this->room();
        $this->postJson('/api/reservations/bookings', $this->booking(['time' => '20:00', 'duration_minutes' => 720]))->assertCreated();
        $this->getJson('/api/reservations/book?date=2026-10-27')->assertOk()->assertJsonCount(1, 'bookings');
        $this->getJson('/api/reservations/book?date=2026-10-28')->assertOk()->assertJsonCount(0, 'bookings');
    }
}
