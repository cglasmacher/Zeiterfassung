<?php

namespace Tests\Feature\Reservations;

use App\Mail\ReservationGuestMail;
use App\Models\ReservationBooking;
use App\Models\ReservationRoom;
use App\Models\User;
use App\Services\Reservations\PublicReservationService;
use App\Services\Reservations\ReservationMailService;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\ResmioTestDatabase;
use Tests\TestCase;

class ReservationMailTest extends TestCase
{
    use RefreshDatabase, ResmioTestDatabase { ResmioTestDatabase::migrateFreshUsing insteadof RefreshDatabase; }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Berlin'));
        config(['reservation_mail.enabled' => true, 'mail.default' => 'smtp', 'mail.from.address' => 'reservierung@hemingway-hilden.de', 'reservation_mail.reply_to' => 'kontakt@hemingway-hilden.de']);
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        Mail::fake();
        $room = ReservationRoom::create(['id' => (string) Str::uuid(), 'name' => 'Restaurant', 'width' => 800, 'height' => 600, 'elements' => []]);
        $room->tables()->create(['id' => (string) Str::uuid(), 'name' => '1', 'capacity' => 8, 'shape' => 'rectangle', 'x' => 100, 'y' => 100, 'width' => 80, 'height' => 70, 'rotation' => 0]);
        $this->settings();
    }

    private function settings(array $extra = []): void
    {
        $c = app(PublicReservationService::class)->settings()['configuration'];
        DB::table('reservation_widget_settings')->where('id', 1)->update(['configuration' => json_encode(array_replace($c, ['enabled' => true, 'privacy_url' => 'https://example.test/privacy'], $extra))]);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['date' => '2026-10-26', 'time' => '14:00', 'party_size' => 2, 'room_id' => null, 'waitlist' => false, 'request_key' => (string) Str::uuid(),
            'guest_name' => 'Gast <script>privat</script>', 'phone' => '+49 2103 12345', 'email' => 'gast@example.test', 'comment' => 'Allergie, private Gastnotiz', 'privacy' => true, 'company' => ''], $extra);
    }

    private function book(array $extra = []): ReservationBooking
    {
        $receipt = app(PublicReservationService::class)->submit($this->data($extra));

        return ReservationBooking::where('public_reference', $receipt['reference'])->firstOrFail();
    }

    private function change(ReservationBooking $booking, array $data): ReservationBooking
    {
        return app(ReservationService::class)->write([...$data, 'version' => $booking->version], 1, $booking);
    }

    public function test_initial_confirmation_is_transactional_and_sent_once_outside_the_booking_request(): void
    {
        $data = $this->data();
        $first = app(PublicReservationService::class)->submit($data);
        app(PublicReservationService::class)->submit($data);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('reservation_mail_outbox', 1);
        $this->artisan('reservations:send-mails')->assertSuccessful();
        $this->artisan('reservations:send-mails')->assertSuccessful();
        Mail::assertSent(ReservationGuestMail::class, fn ($mail) => $mail->type === 'confirmed' && $mail->hasTo('gast@example.test') && $mail->details['reference'] === $first['reference'] && str_ends_with($mail->headers()->messageId, '@hemingway-hilden.de'));
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('reservation_mail_outbox', ['status' => 'sent', 'attempts' => 1]);
    }

    public function test_mail_contains_only_public_details_escaped_html_and_plaintext_and_reply_address(): void
    {
        $booking = $this->book();
        $payload = json_decode(DB::table('reservation_mail_outbox')->first()->payload, true);
        $mail = new ReservationGuestMail($payload, 'confirmed');
        $html = $mail->render();
        $this->assertStringContainsString('14:00 Uhr', $html);
        $this->assertStringContainsString('26.10.2026', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('Allergie', $html);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('table_ids', $payload);
        $this->assertArrayNotHasKey('public_request_key', $payload);
        $this->assertStringContainsString($booking->public_reference, view('mail.reservations.guest-text', ['details' => $payload, 'heading' => ReservationGuestMail::heading('confirmed'), 'type' => 'confirmed'])->render());
        $this->assertSame('kontakt@hemingway-hilden.de', $mail->envelope()->replyTo[0]->address);
    }

    public function test_actual_mime_message_has_html_plaintext_and_stable_message_id_without_a_network_transport(): void
    {
        $this->book(['guest_name' => "Lara & Tom O'Brian"]);
        $row = DB::table('reservation_mail_outbox')->first();
        $mail = new ReservationGuestMail(json_decode($row->payload, true), $row->type, $row->id);
        $mailer = (new \Illuminate\Mail\MailManager(app()))->mailer('array');
        $mailer->to('gast@example.test')->send($mail);
        $message = $mailer->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('Lara &amp; Tom O&#039;Brian', $message->getHtmlBody());
        $this->assertStringContainsString("Lara & Tom O'Brian", $message->getTextBody());
        $this->assertSame($row->id.'@hemingway-hilden.de', $message->getHeaders()->get('Message-ID')->getId());
        $this->assertSame('kontakt@hemingway-hilden.de', $message->getReplyTo()[0]->getAddress());
    }

    public function test_manual_request_and_later_confirmation_are_distinct_and_notes_do_not_resend(): void
    {
        $this->settings(['confirmation_mode' => 'manual']);
        $booking = $this->book();
        $this->assertDatabaseHas('reservation_mail_outbox', ['type' => 'requested']);
        app(ReservationMailService::class)->sendDue();
        $booking = $this->change($booking, ['notes' => 'Interne Kücheninformation']);
        $this->assertDatabaseCount('reservation_mail_outbox', 1);
        $booking = $this->change($booking, ['status' => 'confirmed']);
        $this->assertDatabaseHas('reservation_mail_outbox', ['type' => 'confirmed']);
        $booking = $this->change($booking, ['status' => 'confirmed']);
        $this->assertDatabaseCount('reservation_mail_outbox', 2);
        app(ReservationMailService::class)->sendDue();
        Mail::assertSentCount(2);
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'requested' && str_contains($m->render(), 'noch keine bestätigte Reservierung'));
    }

    public function test_waitlist_promotes_to_confirmation_and_waitlist_cancellation_has_own_copy(): void
    {
        $occupied = $this->book();
        $waiting = $this->book(['waitlist' => true]);
        app(ReservationMailService::class)->sendDue();
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'waiting' && str_contains($m->render(), 'noch keine bestätigte Reservierung'));
        $this->change($occupied, ['status' => 'cancelled']);
        $waiting = app(ReservationService::class)->write(['version' => $waiting->version, 'kind' => 'reservation'], 1, $waiting, true);
        app(ReservationMailService::class)->sendDue();
        $this->assertDatabaseHas('reservation_mail_outbox', ['booking_id' => $waiting->id, 'type' => 'confirmed', 'status' => 'sent']);
        $newWaiting = $this->book(['waitlist' => true]);
        $this->change($newWaiting, ['status' => 'cancelled']);
        app(ReservationMailService::class)->sendDue();
        $this->assertDatabaseHas('reservation_mail_outbox', ['booking_id' => $newWaiting->id, 'type' => 'waiting_cancelled', 'status' => 'sent']);
    }

    public function test_superseded_confirmation_is_not_sent_after_cancellation_and_retries_stay_superseded(): void
    {
        $booking = $this->book();
        $this->change($booking, ['status' => 'cancelled']);
        app(ReservationMailService::class)->sendDue();
        $this->assertDatabaseHas('reservation_mail_outbox', ['type' => 'confirmed', 'status' => 'skipped']);
        Mail::assertSentCount(1);
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'cancelled');
    }

    public function test_rapid_status_round_trip_delivers_only_the_newest_confirmation(): void
    {
        $booking = $this->book();
        $booking = $this->change($booking, ['status' => 'unconfirmed']);
        $this->change($booking, ['status' => 'confirmed']);
        $counts = app(ReservationMailService::class)->sendDue();
        $this->assertSame(2, $counts['skipped']);
        $this->assertSame(1, $counts['sent']);
        Mail::assertSentCount(1);
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'confirmed');
    }

    public function test_date_party_and_email_changes_send_current_snapshot_and_do_not_leak_to_old_address(): void
    {
        $booking = $this->book();
        $booking = $this->change($booking, ['date' => '2026-10-28', 'time' => '18:30', 'party_size' => 3, 'email' => 'neu@example.test']);
        app(ReservationMailService::class)->sendDue();
        Mail::assertSentCount(1);
        Mail::assertSent(ReservationGuestMail::class, fn ($m) => $m->type === 'updated' && $m->hasTo('neu@example.test') && $m->details['time'] === '18:30' && $m->details['date'] === '28.10.2026' && $m->details['party_size'] === 3);
        $this->change($booking, ['notes' => 'Weitere interne Notiz']);
        $this->assertDatabaseCount('reservation_mail_outbox', 2);
    }

    public function test_arrival_completion_and_imported_or_manual_reservations_do_not_send_guest_mails(): void
    {
        $booking = $this->book();
        app(ReservationMailService::class)->sendDue();
        $booking = $this->change($booking, ['status' => 'seated']);
        $this->change($booking, ['status' => 'finished']);
        app(ReservationService::class)->write(['kind' => 'reservation', 'date' => '2026-10-28', 'time' => '14:00', 'party_size' => 2, 'guest_name' => 'Telefon', 'phone' => '12345', 'email' => 'telefon@example.test'], 1);
        $booking->source = 'resmio';
        app(ReservationMailService::class)->record($booking);
        $this->assertDatabaseCount('reservation_mail_outbox', 1);
        Mail::assertSentCount(1);
    }

    public function test_mailserver_failure_does_not_lose_booking_and_retries_without_disclosing_transport_secrets(): void
    {
        $booking = $this->book();
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('smtp password SECRET private@example.test'));
        $counts = app(ReservationMailService::class)->sendDue();
        $this->assertSame(1, $counts['retry']);
        $this->assertDatabaseHas('reservation_bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $row = DB::table('reservation_mail_outbox')->first();
        $this->assertSame('pending', $row->status);
        $this->assertStringNotContainsString('SECRET', $row->last_error);
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        Mail::fake();
        $this->assertSame(0, app(ReservationMailService::class)->sendDue()['sent']);
        $this->travel(3)->minutes();
        $this->assertSame(1, app(ReservationMailService::class)->sendDue()['sent']);
        Mail::assertSentCount(1);
    }

    public function test_final_failure_is_visible_to_admin_and_can_be_retried_but_not_by_operations(): void
    {
        $this->book();
        DB::table('reservation_mail_outbox')->update(['attempts' => 7]);
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('private server data'));
        $this->assertSame(1, app(ReservationMailService::class)->sendDue()['failed']);
        $id = DB::table('reservation_mail_outbox')->value('id');
        $this->getJson('/api/reservations/mails')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->getJson('/api/reservations/mails')->assertForbidden();
        $this->postJson("/api/reservations/mails/$id/retry")->assertForbidden();
        $this->actingAs(User::factory()->create(['access_role' => 'admin']));
        $this->getJson('/api/reservations/mails')->assertOk()->assertJsonPath('counts.failed', 1)->assertDontSee('private server data');
        $this->postJson("/api/reservations/mails/$id/retry")->assertOk();
        $this->postJson("/api/reservations/mails/$id/retry")->assertConflict();
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        Mail::fake();
        $this->assertSame(1, app(ReservationMailService::class)->sendDue()['sent']);
    }

    public function test_disabled_and_log_transports_do_not_claim_delivery_and_stale_leases_are_recovered(): void
    {
        config(['reservation_mail.enabled' => false]);
        $booking = $this->book();
        $this->assertDatabaseCount('reservation_mail_outbox', 0);
        $this->assertFalse(app(PublicReservationService::class)->configuration()['mail_enabled']);
        config(['reservation_mail.enabled' => true]);
        app(ReservationMailService::class)->record($booking);
        config(['mail.default' => 'log']);
        $this->assertSame(0, app(ReservationMailService::class)->sendDue()['sent']);
        $this->artisan('reservations:send-mails')->assertFailed();
        $this->assertDatabaseHas('reservation_mail_outbox', ['status' => 'pending', 'attempts' => 0]);
        config(['mail.default' => 'smtp']);
        DB::table('reservation_mail_outbox')->update(['status' => 'processing', 'locked_at' => now()->subMinutes(11), 'lock_token' => (string) Str::uuid()]);
        $this->assertSame(1, app(ReservationMailService::class)->sendDue()['sent']);
        Mail::assertSentCount(1);
    }

    public function test_notes_change_before_delivery_keeps_initial_confirmation_and_unexpired_lease_is_not_taken(): void
    {
        $booking = $this->book();
        $this->change($booking, ['notes' => 'Nur intern']);
        DB::table('reservation_mail_outbox')->update(['status' => 'processing', 'locked_at' => now(), 'lock_token' => (string) Str::uuid()]);
        $this->assertSame(0, app(ReservationMailService::class)->sendDue()['sent']);
        $this->travel(11)->minutes();
        $this->assertSame(1, app(ReservationMailService::class)->sendDue()['sent']);
        Mail::assertSentCount(1);
    }

    public function test_booking_rollback_also_rolls_back_outbox(): void
    {
        try {
            DB::transaction(function () {
                $this->book();
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback', $e->getMessage());
        }
        $this->assertDatabaseCount('reservation_mail_outbox', 0);
        $this->assertDatabaseCount('reservation_bookings', 0);
        Mail::assertNothingSent();
    }
}
