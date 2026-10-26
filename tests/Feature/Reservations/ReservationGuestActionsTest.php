<?php

namespace Tests\Feature\Reservations;

use App\Mail\ReservationGuestMail;
use App\Models\ReservationBooking;
use App\Models\ReservationRoom;
use App\Models\User;
use App\Services\Reservations\PublicReservationService;
use App\Services\Reservations\ReservationGuestActions;
use App\Services\Reservations\ReservationMailService;
use App\Services\Reservations\ReservationReminderService;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\ResmioTestDatabase;
use Tests\TestCase;

class ReservationGuestActionsTest extends TestCase
{
    use RefreshDatabase, ResmioTestDatabase { ResmioTestDatabase::migrateFreshUsing insteadof RefreshDatabase; }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Berlin'));
        config(['app.url' => 'https://portal.example.test', 'reservation_mail.enabled' => true, 'mail.default' => 'smtp', 'mail.from.address' => 'reservierung@hemingway-hilden.de']);
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        Mail::fake();
        $room = ReservationRoom::create(['id' => (string) Str::uuid(), 'name' => 'Restaurant', 'width' => 800, 'height' => 600, 'elements' => []]);
        $room->tables()->create(['id' => (string) Str::uuid(), 'name' => '1', 'capacity' => 8, 'shape' => 'rectangle', 'x' => 100, 'y' => 100, 'width' => 80, 'height' => 70, 'rotation' => 0]);
        $c = app(PublicReservationService::class)->settings()['configuration'];
        DB::table('reservation_widget_settings')->where('id', 1)->update(['configuration' => json_encode([...$c, 'enabled' => true, 'privacy_url' => 'https://example.test/privacy'])]);
    }

    private function book(array $extra = []): ReservationBooking
    {
        $r = app(PublicReservationService::class)->submit(array_replace(['date' => '2026-10-28', 'time' => '18:30', 'party_size' => 2,
            'room_id' => null, 'waitlist' => false, 'request_key' => (string) Str::uuid(), 'guest_name' => 'Privater Name',
            'phone' => '0210312345', 'email' => 'gast@example.test', 'comment' => 'Private Allergie', 'privacy' => true], $extra));

        return ReservationBooking::where('public_reference', $r['reference'])->firstOrFail();
    }

    private function change(ReservationBooking $b, array $data): ReservationBooking
    {
        return app(ReservationService::class)->write([...$data, 'version' => $b->version], 1, $b);
    }

    private function url(ReservationBooking $b): string
    {
        return app(ReservationGuestActions::class)->url($b);
    }

    private function atDue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-27 18:30', 'Europe/Berlin'));
    }

    public function test_get_only_shows_safe_details_and_post_cancels_idempotently_releases_capacity_and_sends_cancel_mail(): void
    {
        $b = $this->book();
        $url = $this->url($b);
        $this->assertStringStartsWith('https://portal.example.test/reservieren/storno/', $url);
        $this->get($url)->assertOk()->assertSee('Reservierung stornieren?')->assertSee('18:30')->assertDontSee('Privater Name')->assertDontSee('gast@example.test')->assertDontSee('Private Allergie')->assertDontSee('0210312345')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame('confirmed', $b->fresh()->status);
        $this->post($url, [])->assertStatus(422);
        $this->assertSame('confirmed', $b->fresh()->status);
        $this->post($url, ['confirm' => '1', 'status' => 'finished'])->assertOk()->assertSee('Reservierung storniert');
        $this->post($url, ['confirm' => '1'])->assertOk();
        $this->get($url)->assertOk()->assertSee('Reservierung storniert');
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame(0, app(ReservationService::class)->occupied($b->starts_at, $b->ends_at)->count());
        $this->assertNotEmpty(app(ReservationService::class)->candidates($b->starts_at, $b->ends_at, 2));
        $this->assertSame(1, DB::table('reservation_events')->where('booking_id', $b->id)->where('action', 'guest_cancelled')->count());
        $this->assertSame(1, DB::table('reservation_mail_outbox')->where('type', 'cancelled')->count());
        app(ReservationMailService::class)->sendDue();
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'cancelled' && $m->cancelUrl === null);
    }

    public function test_forged_expired_and_old_links_cannot_cancel_or_disclose_booking_details(): void
    {
        $b = $this->book();
        $url = $this->url($b);
        foreach ([$url.'&extra=1', str_replace('state=', 'state=x', $url), str_replace($b->id, (string) Str::uuid(), $url)] as $bad) {
            $this->get($bad)->assertStatus(410)->assertDontSee($b->public_reference);
            $this->post($bad, ['confirm' => 1])->assertStatus(410);
        }
        $b = $this->change($b, ['email' => 'neu@example.test']);
        $this->post($url, ['confirm' => 1])->assertStatus(410);
        $new = $this->url($b);
        $b = $this->change($b, ['party_size' => 3]);
        $this->post($new, ['confirm' => 1])->assertStatus(410);
        $new = $this->url($b);
        $b = $this->change($b, ['date' => '2026-10-29', 'time' => '19:00']);
        $this->post($new, ['confirm' => 1])->assertStatus(410);
        $new = $this->url($b);
        $this->travelTo($b->starts_at);
        $this->post($new, ['confirm' => 1])->assertStatus(410);
        $this->assertSame('confirmed', $b->fresh()->status);
    }

    public function test_placed_finished_no_show_and_resmio_cannot_be_cancelled_by_an_old_guest_link(): void
    {
        $b = $this->book();
        $url = $this->url($b);
        foreach (['seated', 'finished', 'noshow'] as $status) {
            $b->update(['status' => $status]);
            $this->post($url, ['confirm' => 1])->assertStatus(410);
            $this->assertSame($status, $b->fresh()->status);
        }
        $b->update(['status' => 'confirmed', 'source' => 'resmio']);
        $this->post($url, ['confirm' => 1])->assertStatus(410);
        $this->assertNull(app(ReservationGuestActions::class)->url($b));
        $this->get('https://'.config('infosystem.stream_host').parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))->assertNotFound();
    }

    public function test_waitlist_and_manual_request_have_safe_cancellation_links_and_correct_cancellation_copy(): void
    {
        $this->book();
        $waiting = $this->book(['waitlist' => true]);
        $this->get($this->url($waiting))->assertOk()->assertSee('Wartelisteneintrag stornieren?');
        $this->post($this->url($waiting), ['confirm' => 1])->assertOk()->assertSee('Wartelisteneintrag storniert');
        $this->assertDatabaseHas('reservation_mail_outbox', ['booking_id' => $waiting->id, 'type' => 'waiting_cancelled']);
        $c = app(PublicReservationService::class)->settings()['configuration'];
        DB::table('reservation_widget_settings')->where('id', 1)->update(['configuration' => json_encode([...$c, 'confirmation_mode' => 'manual'])]);
        $request = $this->book(['time' => '14:00']);
        $this->assertSame('unconfirmed', $request->status);
        $this->post($this->url($request), ['confirm' => 1])->assertOk();
        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_sent_html_and_plaintext_include_personal_link_from_configured_origin_not_incoming_host(): void
    {
        $b = $this->book();
        $this->withServerVariables(['HTTP_HOST' => 'untrusted.example.test']);
        app(ReservationMailService::class)->sendDue();
        Mail::assertSent(ReservationGuestMail::class, function ($m) use ($b) {
            $this->assertStringStartsWith('https://portal.example.test/', $m->cancelUrl);
            $this->assertStringContainsString('Termin stornieren', $m->render());
            $text = view('mail.reservations.guest-text', ['details' => $m->details, 'type' => $m->type, 'heading' => $m->heading($m->type), 'cancel_url' => $m->cancelUrl])->render();
            $this->assertStringContainsString($m->cancelUrl, $text);
            $this->assertStringNotContainsString('&amp;signature', $text);

            return $m->details['reference'] === $b->public_reference;
        });
    }

    public function test_reminder_is_queued_at_configured_time_only_once_even_after_internal_note_edit(): void
    {
        $b = $this->book();
        app(ReservationMailService::class)->sendDue();
        $this->assertSame(0, app(ReservationReminderService::class)->queueDue());
        $b = $this->change($b, ['notes' => 'Nicht an Gast senden']);
        $this->atDue();
        $this->artisan('reservations:send-mails')->assertSuccessful();
        $this->artisan('reservations:send-mails')->assertSuccessful();
        $this->assertSame(1, DB::table('reservation_mail_outbox')->where('type', 'reminder')->count());
        Mail::assertSentCount(2);
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'reminder' && $m->cancelUrl && str_contains($m->render(), 'Erinnerung an deine Reservierung') && ! str_contains($m->render(), 'Nicht an Gast senden'));
    }

    public function test_pending_reminder_rechecks_cancellation_arrival_and_enabled_setting_before_delivery(): void
    {
        $b = $this->book();
        app(ReservationMailService::class)->sendDue();
        $this->atDue();
        $this->assertSame(1, app(ReservationReminderService::class)->queueDue());
        $this->post($this->url($b), ['confirm' => 1])->assertOk();
        app(ReservationMailService::class)->sendDue();
        $this->assertDatabaseHas('reservation_mail_outbox', ['type' => 'reminder', 'status' => 'skipped']);
        Mail::assertNotSent(ReservationGuestMail::class, fn ($m) => $m->type === 'reminder');
    }

    public function test_reminder_uses_latest_party_details_without_duplicate_after_edit(): void
    {
        $b = $this->book();
        app(ReservationMailService::class)->sendDue();
        $this->atDue();
        $this->assertSame(1, app(ReservationReminderService::class)->queueDue());
        $this->change($b, ['party_size' => 3]);
        app(ReservationMailService::class)->sendDue();
        $this->assertSame(0, app(ReservationReminderService::class)->queueDue());
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'reminder' && $m->details['party_size'] === 3);
    }

    public function test_rescheduling_skips_old_pending_reminder_and_allows_one_for_the_new_date(): void
    {
        $b = $this->book();
        app(ReservationMailService::class)->sendDue();
        $this->atDue();
        app(ReservationReminderService::class)->queueDue();
        $b = $this->change($b, ['date' => '2026-10-30', 'time' => '19:00']);
        app(ReservationMailService::class)->sendDue();
        Mail::assertNotSent(ReservationGuestMail::class, fn ($m) => $m->type === 'reminder');
        $this->travelTo(CarbonImmutable::parse('2026-10-29 19:00', 'Europe/Berlin'));
        $this->assertSame(1, app(ReservationReminderService::class)->queueDue());
        app(ReservationMailService::class)->sendDue();
        $this->assertSame(2, DB::table('reservation_mail_outbox')->where('type', 'reminder')->count());
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'reminder' && $m->details['date'] === '30.10.2026');
    }

    public function test_short_notice_and_late_first_confirmation_do_not_get_immediate_reminders(): void
    {
        $this->atDue();
        $this->travel(1)->hours();
        $b = $this->book();
        app(ReservationMailService::class)->sendDue();
        $this->assertSame(0, app(ReservationReminderService::class)->queueDue());
        $this->assertFalse(app(ReservationReminderService::class)->eligible($b));
    }

    public function test_unconfirmed_waitlist_and_service_statuses_never_generate_reminders_and_disabled_is_checked_at_delivery(): void
    {
        $b = $this->book();
        app(ReservationMailService::class)->sendDue();
        $this->atDue();
        foreach (['unconfirmed', 'waiting', 'seated', 'finished', 'cancelled', 'noshow'] as $status) {
            $b->update(['status' => $status]);
            $this->assertSame(0, app(ReservationReminderService::class)->queueDue());
        }
        $b->update(['status' => 'confirmed']);
        $this->assertSame(1, app(ReservationReminderService::class)->queueDue());
        DB::table('reservation_mail_settings')->where('id', 1)->update(['reminders_enabled' => false]);
        app(ReservationMailService::class)->sendDue();
        $this->assertDatabaseHas('reservation_mail_outbox', ['type' => 'reminder', 'status' => 'skipped']);
        Mail::assertNotSent(ReservationGuestMail::class, fn ($m) => $m->type === 'reminder');
    }

    public function test_outage_catchup_is_bounded_and_late_confirmation_does_not_generate_a_second_mail(): void
    {
        $b = $this->book();
        $this->atDue();
        $this->travel(10)->minutes();
        app(ReservationMailService::class)->sendDue();
        $this->assertSame(0, app(ReservationReminderService::class)->queueDue());
        // A confirmation that arrived late must not be followed immediately by a reminder.
        $this->assertDatabaseCount('reservation_mail_outbox', 1);
        $this->travel(51)->minutes();
        $this->assertFalse(app(ReservationReminderService::class)->eligible($b));
    }

    public function test_reminder_tracks_twenty_four_elapsed_hours_across_berlin_summer_time_change(): void
    {
        $b = $this->book(['date' => '2027-03-28', 'time' => '12:00']);
        app(ReservationMailService::class)->sendDue();
        $this->travelTo(CarbonImmutable::parse('2027-03-27 10:59', 'Europe/Berlin'));
        $this->assertSame(0, app(ReservationReminderService::class)->queueDue());
        $this->travelTo(CarbonImmutable::parse('2027-03-27 11:00', 'Europe/Berlin'));
        $this->assertSame(1, app(ReservationReminderService::class)->queueDue());
        app(ReservationMailService::class)->sendDue();
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'reminder' && $m->details['time'] === '12:00');
    }

    public function test_admin_settings_validate_hours_revision_and_permissions(): void
    {
        $settings = ['reminders_enabled' => true, 'reminder_hours' => 12, 'revision' => 1];
        $this->putJson('/api/reservations/mails/settings', $settings)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->putJson('/api/reservations/mails/settings', $settings)->assertForbidden();
        $this->actingAs(User::factory()->create(['access_role' => 'admin']));
        $this->putJson('/api/reservations/mails/settings', [...$settings, 'reminder_hours' => 0])->assertUnprocessable();
        $this->putJson('/api/reservations/mails/settings', [...$settings, 'reminder_hours' => 169])->assertUnprocessable();
        $this->putJson('/api/reservations/mails/settings', $settings)->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('reminder_hours', 12);
        $this->putJson('/api/reservations/mails/settings', $settings)->assertConflict();
        $this->getJson('/api/reservations/mails')->assertOk()->assertJsonPath('settings.reminder_hours', 12);
    }
}
