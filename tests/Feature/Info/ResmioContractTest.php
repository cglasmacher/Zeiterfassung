<?php

namespace Tests\Feature\Info;

use App\Models\InfoDisplay;
use App\Models\InfoIntegration;
use App\Models\InfoReservation;
use App\Services\Info\DisplayFeed;
use App\Services\Info\ResmioSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResmioContractTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\ResmioTestDatabase {
        \Tests\Support\ResmioTestDatabase::migrateFreshUsing insteadof RefreshDatabase;
    }

    public function test_documented_headers_endpoints_filters_fields_resources_and_statuses(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway-hilden', 'authorization' => 'Bearer raw-api-key']);
        Http::preventStrayRequests();
        $requests = [];
        Http::fake(function ($r) use (&$requests) {
            $requests[] = $r;
            $this->assertSame(['raw-api-key'], $r->header('Api-Key'));
            $this->assertFalse($r->hasHeader('Authorization'));
            $this->assertSame(['application/json'], $r->header('Content-Type'));
            $path = parse_url($r->url(), PHP_URL_PATH);
            if ($path === '/v1/facility/hemingway-hilden/bookings') {
                $this->assertSame('2026-10-23T22:00:00Z', $r['date__gte']);
                $this->assertSame(now()->timezone('Europe/Berlin')->addDays(366)->startOfDay()->utc()->toIso8601ZuluString(), $r['date__lt']);
                $this->assertArrayNotHasKey('date__lte', $r->data());
                if (($r['offset'] ?? 0) === 0) {
                    return Http::response(['objects' => [
                        ['id' => 10, 'date' => '2026-10-25T16:00:00Z', 'num' => 4, 'name' => 'Test Guest', 'status' => 'confirmed', 'facility' => '/v1/facility/hemingway-hilden', 'facility_resources' => ['/v1/facility/hemingway-hilden/resources/7']],
                        ['id' => 11, 'date' => '2026-10-25T17:00:00Z', 'num' => 2, 'name' => 'Cancelled Guest', 'status' => 'cancelled'],
                    ], 'meta' => ['next' => '?offset=100']]);
                }

                return Http::response(['objects' => [['id' => 12, 'date' => '2026-10-25T18:00:00Z', 'num' => 2, 'name' => 'No Show Guest', 'status' => 'noshow']], 'meta' => ['next' => null]]);
            }
            if ($path === '/v1/facility/hemingway-hilden/resources') {
                if (($r['offset'] ?? 0) === 0) {
                    return Http::response(['objects' => [['id' => 7, 'name' => 'Terrasse 7']], 'meta' => ['next' => '/v1/facility/hemingway-hilden/resources?offset=100']]);
                }

                return Http::response(['objects' => [], 'meta' => ['next' => null]]);
            }
            $this->fail('Unexpected API endpoint');
        });
        $this->assertSame(3, app(ResmioSync::class)->run());
        $this->assertCount(4, $requests);
        $row = InfoReservation::where('external_id', '10')->first();
        $this->assertSame('Test Guest', $row->guest_name);
        $this->assertSame(4, $row->people);
        $this->assertSame('Terrasse 7', $row->tables);
        $feed = app(DisplayFeed::class)->snapshot(InfoDisplay::first());
        $this->assertCount(1, $feed['reservations']);
        $this->assertSame($row->id, $feed['reservations'][0]['id']);
        $this->assertSame('2026-10-25T16:00:00+00:00', $feed['reservations'][0]['starts_at']);
    }

    public function test_pagination_cannot_change_facility_endpoint_or_origin(): void
    {
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'raw-key']);
        foreach (['https://evil.example/v1/facility/hemingway/bookings', '/v1/facility/other/bookings', '/v1/facility/hemingway/bookings-other'] as $next) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::fake(['*' => Http::response(['objects' => [], 'meta' => ['next' => $next]])]);
            try {
                app(ResmioSync::class)->run();
                $this->fail('Must reject pagination');
            } catch (\App\Exceptions\ResmioSyncException $e) {
                $this->assertStringContainsString('Seitenziel', $e->getMessage());
            }
            Http::assertSentCount(1);
        }
    }

    public function test_invalid_resource_response_preserves_existing_bookings(): void
    {
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'raw-key']);
        InfoReservation::create(['external_id' => '1', 'facility' => 'hemingway', 'starts_at' => now(), 'people' => 3]);
        Http::fake(['*/bookings*' => Http::response(['objects' => [['id' => 1, 'date' => now()->toIso8601String(), 'num' => 9, 'name' => 'Updated']]]), '*/resources*' => Http::response(['objects' => [['id' => 1]]])]);
        try {
            app(ResmioSync::class)->run();
            $this->fail('Must reject invalid resources');
        } catch (\App\Exceptions\ResmioSyncException $e) {
        }
        $this->assertSame(3, InfoReservation::first()->people);
        $this->assertNotNull(InfoIntegration::settings()->last_error);
    }
}
