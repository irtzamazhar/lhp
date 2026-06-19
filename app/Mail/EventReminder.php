<?php

namespace App\Mail;

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Attendee $attendee,
        public readonly Event $event,
        public readonly string $timeLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: {$this->event->payload['name']} is in {$this->timeLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.event-reminder',
        );
    }
}
