<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class ReservationGuestMail extends Mailable
{
    public function __construct(public array $details, public string $type, public ?string $deliveryId = null, public ?string $cancelUrl = null) {}

    public static function heading(string $type): string
    {
        return match ($type) {
            'confirmed' => 'Deine Reservierung ist bestätigt',
            'reminder' => 'Erinnerung an deine Reservierung',
            'requested' => 'Deine Reservierungsanfrage ist eingegangen',
            'waiting' => 'Dein Wartelisteneintrag ist eingegangen',
            'waiting_updated' => 'Dein Wartelisteneintrag wurde geändert',
            'cancelled' => 'Deine Reservierung wurde storniert',
            'waiting_cancelled' => 'Dein Wartelisteneintrag wurde storniert',
            default => 'Deine Reservierung wurde geändert',
        };
    }

    public function envelope(): Envelope
    {
        $reply = config('reservation_mail.reply_to');

        return new Envelope(
            replyTo: $reply && filter_var($reply, FILTER_VALIDATE_EMAIL) ? [$reply] : [],
            subject: self::heading($this->type).' · Hemingway · '.$this->details['reference'],
        );
    }

    public function headers(): Headers
    {
        $domain = substr(strrchr(config('mail.from.address'), '@'), 1);

        return new Headers(messageId: $this->deliveryId ? $this->deliveryId.'@'.$domain : null);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reservations.guest', text: 'mail.reservations.guest-text', with: [
            'cancel_url' => $this->cancelUrl, 'heading' => self::heading($this->type), 'details' => $this->details, 'type' => $this->type,
        ]);
    }
}
