<?php

namespace Tests\Feature\Reservations;

use App\Models\ReservationBooking;
use App\Models\ReservationRoom;
use App\Models\User;
use App\Services\Reservations\PublicReservationService;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\ResmioTestDatabase;
use Tests\TestCase;

class ReservationWidgetTest extends TestCase
{
    use RefreshDatabase, ResmioTestDatabase { ResmioTestDatabase::migrateFreshUsing insteadof RefreshDatabase; }

    private ReservationRoom $room;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Berlin'));
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['access_role' => 'admin']);
        $this->room = $this->makeRoom('Restaurant');
    }

    private function makeRoom(string $name): ReservationRoom
    {
        $room = ReservationRoom::create(['id' => (string) Str::uuid(), 'name' => $name, 'width' => 800, 'height' => 600, 'elements' => []]);
        foreach (['1', '2'] as $i => $number) {
            $room->tables()->create(['id' => (string) Str::uuid(), 'name' => $number, 'capacity' => 2, 'shape' => 'rectangle', 'x' => 100 + $i * 200, 'y' => 100, 'width' => 80, 'height' => 70, 'rotation' => 0]);
        }

        return $room;
    }

    private function enable(array $extra = []): array
    {
        $settings = app(PublicReservationService::class)->settings();
        $settings['configuration'] = array_replace($settings['configuration'], ['enabled' => true, 'privacy_url' => 'https://example.test/privacy'], $extra);
        DB::table('reservation_widget_settings')->where('id', 1)->update(['configuration' => json_encode($settings['configuration'])]);

        return $settings;
    }

    private function requestData(array $extra = []): array
    {
        return array_replace(['date' => '2026-10-26', 'time' => '14:00', 'party_size' => 2, 'room_id' => null, 'waitlist' => false, 'request_key' => (string) Str::uuid(),
            'guest_name' => 'Online Gast', 'phone' => '+49 2103 12345', 'email' => 'GAST@example.test', 'comment' => 'Bitte einen Kinderstuhl.', 'privacy' => true, 'company' => ''], $extra);
    }

    private function slotQuery(array $extra = []): string
    {
        return '/api/public/reservations/slots?'.http_build_query(array_replace(['date' => '2026-10-26', 'party_size' => 2], $extra));
    }

    private function fillTables(string $time = '13:00', int $duration = 60): void
    {
        app(ReservationService::class)->write(['kind' => 'reservation', 'date' => '2026-10-26', 'time' => $time, 'party_size' => 4, 'duration_minutes' => $duration,
            'guest_name' => 'Privater Stammgast', 'phone' => '12345', 'notes' => 'Private Kücheninformation', 'table_ids' => $this->room->tables->pluck('id')->all()], $this->admin->id);
    }

    public function test_widget_is_public_private_book_remains_protected_and_stream_is_isolated(): void
    {
        $response = $this->get('/reservieren')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('reservation-widget');
        $this->assertStringContainsString("frame-ancestors 'self' https://hemingway-hilden.de", $response->headers->get('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-Frame-Options'));
        $this->getJson('/api/reservations/book?date=2026-10-26')->assertUnauthorized();
        $this->getJson($this->slotQuery())->assertForbidden();
        $this->postJson('/api/public/reservations/book', $this->requestData())->assertForbidden();
        $this->assertDatabaseCount('reservation_bookings', 0);
        $this->get('https://'.config('infosystem.stream_host').'/reservieren')->assertNotFound();
    }

    public function test_admin_online_settings_are_separate_revision_checked_and_require_a_privacy_url(): void
    {
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->getJson('/api/reservations/widget/settings')->assertForbidden();
        $this->actingAs($this->admin);
        $data = $this->getJson('/api/reservations/widget/settings')->assertOk()->json();
        unset($data['widget_url']);
        $data['configuration']['enabled'] = true;
        $this->putJson('/api/reservations/widget/settings', $data)->assertUnprocessable();
        $data['configuration']['privacy_url'] = 'https://example.test/privacy';
        $this->putJson('/api/reservations/widget/settings', $data)->assertOk()->assertJsonPath('revision', 2);
        $this->putJson('/api/reservations/widget/settings', $data)->assertConflict();
        $this->assertArrayNotHasKey('enabled', app(ReservationService::class)->settings()['configuration']);
    }

    public function test_slots_use_berlin_time_lead_duration_occupancy_and_do_not_disclose_private_data(): void
    {
        $this->enable();
        $this->fillTables();
        $response = $this->getJson($this->slotQuery())->assertOk()->assertDontSee('Privater Stammgast')->assertDontSee('Private Kücheninformation')->assertDontSee('table_ids');
        $slots = collect($response->json('slots'))->keyBy('time');
        $this->assertArrayNotHasKey('12:00', $slots->all());
        $this->assertSame('waitlist', $slots['12:30']['status']); // 105-minute stay overlaps 13–14.
        $this->assertSame('waitlist', $slots['13:30']['status']);
        $this->assertSame('available', $slots['14:00']['status']);
        $this->assertArrayHasKey('20:00', $slots->all());
        $this->assertArrayNotHasKey('20:30', $slots->all());
        $this->getJson($this->slotQuery(['date' => '2026-10-27']))->assertOk()->assertJsonCount(0, 'slots'); // Tuesday closed.
    }

    public function test_online_booking_ignores_client_status_tables_duration_and_saves_comment_consent_and_source(): void
    {
        $this->enable();
        $data = $this->requestData(['status' => 'seated', 'table_ids' => ['fake'], 'duration_minutes' => 1, 'source' => 'resmio']);
        $receipt = $this->postJson('/api/public/reservations/book', $data)->assertCreated()->assertJsonPath('status', 'confirmed')->json();
        $b = ReservationBooking::with('tables')->firstOrFail();
        $this->assertSame('widget', $b->source);
        $this->assertSame('reservation', $b->kind);
        $this->assertSame('gast@example.test', $b->email);
        $this->assertSame('Bitte einen Kinderstuhl.', $b->guest_comment);
        $this->assertSame(105, (int) $b->starts_at->diffInMinutes($b->ends_at));
        $this->assertCount(1, $b->tables);
        $this->assertSame('https://example.test/privacy', $b->public_meta['privacy_url']);
        $this->assertSame($receipt['reference'], $b->public_reference);
        $this->assertEqualsCanonicalizing(['reference', 'status', 'date', 'time', 'party_size', 'room'], array_keys($receipt));
        $this->assertNull(DB::table('reservation_events')->first()->user_id);
        $this->actingAs($this->admin)->putJson('/api/reservations/bookings/'.$b->id, ['version' => 1, 'source' => 'widget', 'notes' => 'Lokale Ergänzung'])->assertOk();
    }

    public function test_repeated_submission_has_no_duplicate_and_a_changed_payload_cannot_reuse_the_key(): void
    {
        $this->enable();
        $data = $this->requestData();
        $one = $this->postJson('/api/public/reservations/book', $data)->assertCreated()->json();
        $two = $this->postJson('/api/public/reservations/book', $data)->assertCreated()->json();
        $this->assertSame($one, $two);
        $this->assertDatabaseCount('reservation_bookings', 1);
        $this->assertDatabaseCount('reservation_events', 1);
        $this->postJson('/api/public/reservations/book', array_replace($data, ['guest_name' => 'Anderer Gast']))->assertConflict();
        $this->assertDatabaseCount('reservation_bookings', 1);
    }

    public function test_stale_free_slot_cannot_double_book_and_waitlist_holds_no_table(): void
    {
        $this->enable();
        $slots = collect($this->getJson($this->slotQuery())->assertOk()->json('slots'))->keyBy('time');
        $this->assertSame('available', $slots['14:00']['status']);
        $this->fillTables('14:00', 180);
        $this->postJson('/api/public/reservations/book', $this->requestData())->assertConflict();
        $receipt = $this->postJson('/api/public/reservations/book', $this->requestData(['waitlist' => true, 'room_id' => $this->room->id]))->assertCreated()->assertJsonPath('status', 'waiting')->json();
        $b = ReservationBooking::where('public_reference', $receipt['reference'])->firstOrFail();
        $this->assertSame('waitlist', $b->kind);
        $this->assertCount(0, $b->tables);
        $this->assertSame($this->room->id, $b->public_meta['requested_room_id']);
    }

    public function test_waitlist_and_room_selection_policies_cannot_be_bypassed(): void
    {
        $this->enable(['waitlist_enabled' => false, 'room_selection' => false]);
        $this->fillTables('14:00', 180);
        $slots = collect($this->getJson($this->slotQuery())->assertOk()->json('slots'))->keyBy('time');
        $this->assertArrayNotHasKey('14:00', $slots->all());
        $this->postJson('/api/public/reservations/book', $this->requestData(['waitlist' => true]))->assertConflict();
        $this->getJson($this->slotQuery(['room_id' => $this->room->id]))->assertUnprocessable();
    }

    public function test_rooms_combinations_and_online_capacity_remain_authoritative(): void
    {
        $this->enable();
        $other = $this->makeRoom('Terrasse');
        $this->postJson('/api/public/reservations/book', $this->requestData(['party_size' => 7]))->assertConflict(); // Extra chairs are manual only.
        $this->actingAs($this->admin)->postJson('/api/reservations/combinations', ['room_id' => $other->id, 'name' => 'Terrassengruppe', 'capacity' => 5, 'table_ids' => $other->tables->pluck('id')->all()])->assertCreated();
        $this->postJson('/api/public/reservations/book', $this->requestData(['party_size' => 5, 'room_id' => $other->id]))->assertCreated();
        $b = ReservationBooking::where('source', 'widget')->firstOrFail();
        $this->assertCount(2, $b->tables);
        $this->assertTrue($b->tables->every(fn ($t) => $t->room_id === $other->id));
    }

    public function test_manual_confirmation_and_group_threshold_are_applied_on_the_server(): void
    {
        $this->enable(['confirmation_mode' => 'manual']);
        $this->postJson('/api/public/reservations/book', $this->requestData())->assertCreated()->assertJsonPath('status', 'unconfirmed');
        $this->enable(['confirmation_mode' => 'up_to', 'max_auto_party' => 1]);
        $this->postJson('/api/public/reservations/book', $this->requestData(['party_size' => 1, 'time' => '18:00']))->assertCreated()->assertJsonPath('status', 'confirmed');
        $this->postJson('/api/public/reservations/book', $this->requestData(['party_size' => 2, 'time' => '18:00']))->assertCreated()->assertJsonPath('status', 'unconfirmed');
    }

    public function test_horizon_size_time_privacy_and_honeypot_validation_reject_invalid_requests(): void
    {
        $this->enable(['max_days' => 30]);
        $this->postJson('/api/public/reservations/book', $this->requestData(['privacy' => false]))->assertUnprocessable();
        $this->postJson('/api/public/reservations/book', $this->requestData(['company' => 'Spam']))->assertUnprocessable();
        $this->postJson('/api/public/reservations/book', $this->requestData(['phone' => 'x']))->assertUnprocessable();
        $this->postJson('/api/public/reservations/book', $this->requestData(['phone' => '+++++']))->assertUnprocessable();
        $this->postJson('/api/public/reservations/book', $this->requestData(['party_size' => 9]))->assertUnprocessable();
        $this->postJson('/api/public/reservations/book', $this->requestData(['date' => '2027-01-01']))->assertUnprocessable();
        $this->postJson('/api/public/reservations/book', $this->requestData(['time' => '12:00']))->assertConflict();
        $this->postJson('/api/public/reservations/book', $this->requestData(['time' => '14:10']))->assertConflict();
        $this->assertDatabaseCount('reservation_bookings', 0);
    }

    public function test_public_booking_endpoint_throttles_repeated_requests(): void
    {
        $this->enable();
        $this->withMiddleware(ThrottleRequests::class);
        for ($i = 0; $i < 10; $i++) {
            $this->getJson($this->slotQuery())->assertOk();
        }
        $data = $this->requestData();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/public/reservations/book', $data)->assertCreated();
        }
        $this->postJson('/api/public/reservations/book', $data)->assertStatus(429);
        $this->assertDatabaseCount('reservation_bookings', 1);
    }

    public function test_guest_page_does_not_embed_signed_in_staff_or_table_layout_and_waitlist_can_be_promoted(): void
    {
        $this->enable();
        $this->actingAs($this->admin);
        $this->get('/reservieren')->assertOk()->assertDontSee($this->admin->email)->assertDontSee('public_request_key')->assertDontSee('"tables"', false);
        $this->fillTables('14:00', 180);
        $receipt = $this->postJson('/api/public/reservations/book', $this->requestData(['waitlist' => true, 'room_id' => $this->room->id]))->assertCreated()->json();
        $wait = ReservationBooking::where('public_reference', $receipt['reference'])->firstOrFail();
        $this->postJson('/api/reservations/bookings/'.$wait->id.'/promote', ['version' => 1, 'kind' => 'reservation', 'date' => '2026-10-26', 'time' => '18:00', 'source' => 'widget', 'room_id' => $this->room->id])->assertOk()->assertJsonPath('status', 'confirmed')->assertJsonPath('source', 'widget');
    }

    public function test_global_closure_has_no_waitlist_slots_and_unknown_resmio_occupancy_is_not_ignored(): void
    {
        $this->enable();
        $b = ReservationBooking::create(['id' => (string) Str::uuid(), 'kind' => 'reservation', 'source' => 'resmio', 'status' => 'confirmed', 'party_size' => 2, 'starts_at' => CarbonImmutable::parse('2026-10-26 13:00', 'Europe/Berlin'), 'ends_at' => CarbonImmutable::parse('2026-10-26 16:00', 'Europe/Berlin'), 'version' => 1, 'resmio_unassigned' => true]);
        $slots = collect($this->getJson($this->slotQuery())->assertOk()->json('slots'))->keyBy('time');
        $this->assertSame('waitlist', $slots['14:00']['status']);
        $this->postJson('/api/public/reservations/book', $this->requestData())->assertConflict();
        $b->update(['status' => 'cancelled']);
        $this->actingAs($this->admin)->postJson('/api/reservations/blocks', ['reason' => 'Geschlossen', 'start_date' => '2026-10-26', 'start_time' => '13:00', 'end_date' => '2026-10-26', 'end_time' => '16:00'])->assertCreated();
        $slots = collect($this->getJson($this->slotQuery())->assertOk()->json('slots'))->keyBy('time');
        $this->assertArrayNotHasKey('14:00', $slots->all());
    }
}
