<?php

namespace Tests\Feature\Info;

use App\Models\InfoDisplay;
use App\Models\InfoIntegration;
use App\Models\InfoItem;
use App\Models\InfoReservation;
use App\Models\User;
use App\Services\Info\DisplayFeed;
use App\Services\Info\ResmioSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfoSystemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_info_admin' => true])->save();

        return $u;
    }

    private function item(array $extra = []): array
    {
        return array_merge(['kind' => 'notice', 'title' => 'Küche', 'active' => true, 'priority' => 0, 'duration' => 20, 'sort_order' => 0], $extra);
    }

    public function test_administration_requires_existing_administrator(): void
    {
        $this->get('/infosystem')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->getJson('/api/infosystem')->assertForbidden();
        $this->actingAs($this->admin())->getJson('/api/infosystem')->assertOk()->assertJsonPath('displays.0.slug', 'kitchen');
    }

    public function test_content_validation_and_task_completion(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/infosystem/items', $this->item(['kind' => 'event']))->assertUnprocessable();
        $this->postJson('/api/infosystem/items', $this->item(['kind' => 'media', 'media_type' => 'image', 'url' => 'http://unsafe.test/x.png']))->assertUnprocessable();
        $id = $this->postJson('/api/infosystem/items', $this->item(['kind' => 'task']))->assertCreated()->json('id');
        $this->patchJson('/api/infosystem/items/'.$id.'/complete', ['completed' => true])->assertOk();
        $this->assertNotNull(InfoItem::find($id)->completed_at);
        $this->patchJson('/api/infosystem/items/'.$id.'/complete', ['completed' => false])->assertOk();
        $this->assertNull(InfoItem::find($id)->completed_at);
        $this->deleteJson('/api/infosystem/items/'.$id)->assertNoContent();
    }

    public function test_display_requires_token_and_rotation_revokes_cookie(): void
    {
        $d = InfoDisplay::first();
        $d->update(['token_hash' => hash('sha256', 'old')]);
        $this->get('/display/kitchen/data')->assertForbidden();
        $this->get('/display/kitchen/connect?token=wrong')->assertForbidden();
        $this->get('/display/kitchen/connect?token=old')->assertRedirect('/display/kitchen')->assertCookie('info_display_'.$d->id);
        $this->withCredentials()->withCookie('info_display_'.$d->id, 'old')->getJson('/display/kitchen/data')->assertOk();
        $this->actingAs($this->admin())->postJson('/api/infosystem/displays/'.$d->id.'/rotate')->assertOk();
        $this->withCredentials()->withCookie('info_display_'.$d->id, 'old')->getJson('/display/kitchen/data')->assertForbidden();
        $this->get('https://stream.hemingway-hilden.de/infosystem')->assertNotFound();
    }

    public function test_feed_filters_schedules_targets_completed_tasks_and_guest_names(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(12, 0));
        $d = InfoDisplay::first();
        $other = InfoDisplay::create(['slug' => 'bar', 'name' => 'Bar']);
        InfoItem::create($this->item(['title' => 'Visible']));
        InfoItem::create($this->item(['title' => 'Future', 'starts_at' => now()->addDay()]));
        InfoItem::create($this->item(['title' => 'Expired', 'ends_at' => now()->subMinute()]));
        InfoItem::create($this->item(['title' => 'Other', 'display_id' => $other->id]));
        InfoItem::create($this->item(['kind' => 'task', 'title' => 'Done', 'completed_at' => now()]));
        InfoItem::create($this->item(['kind' => 'event', 'title' => 'Tomorrow', 'starts_at' => now()->addDay()]));
        InfoReservation::create(['external_id' => '1', 'facility' => 'hemingway', 'starts_at' => now(), 'people' => 3, 'guest_name' => 'Private Name']);
        $feed = app(DisplayFeed::class)->snapshot($d);
        $this->assertSame(['Visible', 'Tomorrow'], $feed['items']->pluck('title')->all());
        $this->assertNull($feed['reservations'][0]['guest_name']);
        $d->update(['show_guest_names' => true]);
        $this->assertSame('Private Name', app(DisplayFeed::class)->snapshot($d)['reservations'][0]['guest_name']);
    }

    public function test_resmio_pagination_idempotence_and_failure_preserve_previous_data(): void
    {
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'test-secret']);
        Http::fakeSequence()->push(['objects' => [['id' => 1, 'date' => '2026-10-21T18:00:00+02:00', 'num' => 4, 'name' => 'Test Guest']], 'meta' => ['next' => '/v1/facility/hemingway/bookings?offset=100']])->push(['objects' => [['id' => 2, 'date' => '2026-10-21T16:00:00Z', 'num' => 2]], 'meta' => ['next' => null]])->push(['objects' => [], 'meta' => ['next' => null]]);
        $this->assertSame(2, app(ResmioSync::class)->run());
        $this->assertDatabaseCount('info_reservations', 2);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/resources*' => Http::response(['objects' => []]), '*' => Http::response(['objects' => [['id' => 1, 'date' => '2026-10-21T18:00:00+02:00', 'num' => 5]], 'meta' => ['next' => null]])]);
        app(ResmioSync::class)->run();
        $this->assertDatabaseCount('info_reservations', 2);
        $this->assertSame(5, InfoReservation::where('external_id', '1')->first()->people);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response('Unauthorized', 401)]);
        try {
            app(ResmioSync::class)->run();
            $this->fail('Must fail');
        } catch (\RuntimeException $e) {
        }
        $this->assertDatabaseCount('info_reservations', 2);
        $this->assertNotNull(InfoIntegration::settings()->last_error);
        $this->assertStringNotContainsString('secret', InfoIntegration::settings()->toJson());
    }

    public function test_pagination_cannot_exfiltrate_credentials(): void
    {
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'raw-secret']);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['objects' => [], 'meta' => ['next' => 'https://evil.example/collect']])]);
        try {
            app(ResmioSync::class)->run();
            $this->fail('Must reject target');
        } catch (\RuntimeException $e) {
        }
        Http::assertSentCount(1);
    }

    public function test_webhooks_authenticate_facility_and_process_cancellation(): void
    {
        Queue::fake();
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'raw-secret', 'webhook_user' => 'hook', 'webhook_password' => 'password']);
        InfoReservation::create(['external_id' => '1', 'facility' => 'hemingway', 'starts_at' => now(), 'people' => 2]);
        $payload = ['id' => 1, 'facility_id' => 'hemingway', 'action' => 'BOOKING_DELETED'];
        $url = '/api/integrations/resmio/webhook';
        $this->postJson($url, $payload)->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('hook:password')])->postJson($url, array_merge($payload, ['facility_id' => 'another']))->assertForbidden();
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('hook:password')])->postJson($url, $payload)->assertAccepted();
        $this->assertSame('deleted', InfoReservation::first()->status);
    }

    public function test_times_are_stored_in_utc_and_private_uploads_are_scoped(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $id = $this->postJson('/api/infosystem/items', $this->item(['kind' => 'event', 'starts_at' => '2026-10-25T02:30:00+02:00', 'ends_at' => '2026-10-25T03:30:00+01:00']))->assertCreated()->json('id');
        $this->assertSame('2026-10-25 00:30:00', InfoItem::find($id)->starts_at->format('Y-m-d H:i:s'));
        $d = InfoDisplay::first();
        $d->update(['token_hash' => hash('sha256', 'media-token')]);
        $other = InfoDisplay::create(['slug' => 'private', 'name' => 'Private']);
        $id = $this->post('/api/infosystem/items', $this->item(['kind' => 'media', 'media_type' => 'image', 'display_id' => $other->id, 'file' => \Illuminate\Http\UploadedFile::fake()->image('test.png')]))->assertCreated()->json('id');
        $path = InfoItem::find($id)->file_path;
        Storage::disk('local')->assertExists($path);
        $this->withCredentials()->withCookie('info_display_'.$d->id, 'media-token')->get('/display/kitchen/media/'.$id)->assertNotFound();
        InfoItem::find($id)->update(['display_id' => null]);
        $this->get('/display/kitchen/media/'.$id)->assertOk();
        $this->deleteJson('/api/infosystem/items/'.$id)->assertNoContent();
        Storage::disk('local')->assertMissing($path);
    }
}
