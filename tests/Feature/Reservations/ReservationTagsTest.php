<?php

namespace Tests\Feature\Reservations;

use App\Models\ReservationBooking;
use App\Models\ReservationTag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\ResmioTestDatabase;
use Tests\TestCase;

class ReservationTagsTest extends TestCase
{
    use RefreshDatabase, ResmioTestDatabase { ResmioTestDatabase::migrateFreshUsing insteadof RefreshDatabase; }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['access_role' => 'admin']));
    }

    private function booking(string $at = '2026-11-11 12:00', array $extra = []): ReservationBooking
    {
        $start = CarbonImmutable::parse($at, 'Europe/Berlin');

        return ReservationBooking::create([...['id' => (string) Str::uuid(), 'kind' => 'waitlist', 'source' => 'manual', 'status' => 'waiting', 'party_size' => 2, 'guest_name' => 'Testgruppe', 'starts_at' => $start, 'ends_at' => $start->addHour(), 'version' => 1], ...$extra]);
    }

    public function test_catalog_create_normalization_archive_and_permissions(): void
    {
        $this->getJson('/api/reservations/tags')->assertOk()->assertJsonCount(30);
        $tag = $this->postJson('/api/reservations/tags', ['name' => '  Weinprobe  '])->assertCreated()->assertJsonPath('name', 'Weinprobe')->json();
        $this->postJson('/api/reservations/tags', ['name' => 'WEINPROBE'])->assertUnprocessable();
        $booking = $this->booking();
        $booking->tags()->sync([$tag['id']]);
        $this->putJson('/api/reservations/tags/'.$tag['id'], ['name' => 'Verkostung', 'active' => false])->assertOk();
        $this->assertSame('Verkostung', $booking->fresh()->tags->first()->name);
        $this->assertFalse($booking->fresh()->tags->first()->active);
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->postJson('/api/reservations/tags', ['name' => 'Verboten'])->assertForbidden();
        $this->getJson('/api/reservations/tags')->assertOk();
    }

    public function test_local_tags_on_local_and_imported_bookings_preserve_imported_tags_and_version(): void
    {
        $tag = ReservationTag::where('name', 'Geburtstag')->firstOrFail();
        foreach (['manual', 'resmio'] as $source) {
            $booking = $this->booking(extra: ['source' => $source, 'resmio_tags' => [['id' => 'external', 'name' => 'Fensterplatz']], 'resmio_status' => 'waiting']);
            $url = '/api/reservations/bookings/'.$booking->id;
            $this->putJson($url, ['version' => 1, 'tag_ids' => [$tag->id]])->assertOk()->assertJsonPath('tags.0.name', 'Geburtstag')->assertJsonPath('resmio_tags.0.name', 'Fensterplatz');
            $this->putJson($url, ['version' => 1, 'tag_ids' => []])->assertConflict();
            $this->putJson($url, ['version' => 2, 'notes' => 'Nur Notiz'])->assertOk()->assertJsonCount(1, 'tags');
            $this->putJson($url, ['version' => 3, 'tag_ids' => [(string) Str::uuid()]])->assertUnprocessable();
            $this->putJson($url, ['version' => 3, 'tag_ids' => []])->assertOk()->assertJsonCount(0, 'tags');
        }
    }

    public function test_range_is_inclusive_berlin_dates_and_matches_local_and_imported_tags_once(): void
    {
        $tag = ReservationTag::where('name', 'Geburtstag')->firstOrFail();
        $before = $this->booking('2026-11-10 23:59');
        $first = $this->booking('2026-11-11 00:00');
        $last = $this->booking('2026-11-12 23:59', ['resmio_tags' => [['id' => '1', 'name' => 'Geburtstag']]]);
        $guest = $this->booking('2026-11-12 18:00', ['customer_tags' => [['id' => '2', 'name' => 'Geburtstag']], 'status' => 'cancelled']);
        $after = $this->booking('2026-11-13 00:00');
        foreach ([$before, $first, $last, $after] as $b) {
            $b->tags()->sync([$tag->id]);
        }
        $query = '/api/reservations/search?'.http_build_query(['from' => '2026-11-11', 'to' => '2026-11-12', 'tag' => 'Geburtstag']);
        $this->getJson($query)->assertOk()->assertJsonPath('total', 3)->assertJsonCount(3, 'data');
        $this->getJson($query.'&status=waiting')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/reservations/search?from=2026-11-12&to=2026-11-11')->assertUnprocessable();
        $this->actingAs(User::factory()->create(['access_role' => 'staff']));
        $this->getJson($query)->assertForbidden();
    }

    public function test_search_paginates_and_counts_the_whole_range(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $this->booking();
        }
        $url = '/api/reservations/search?from=2026-11-11&to=2026-11-11';
        $this->getJson($url)->assertOk()->assertJsonPath('total', 55)->assertJsonCount(50, 'data');
        $this->getJson($url.'&page=2')->assertOk()->assertJsonPath('total', 55)->assertJsonCount(5, 'data');
    }
}
