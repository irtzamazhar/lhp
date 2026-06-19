<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send reminder emails to attendees 3 days and 24 hours before their events';

    public function handle(): int
    {
        $sent = 0;

        $sent += $this->sendRemindersForWindow(
            now()->addDays(3),
            '3 days',
            'reminded_3day_at',
        );

        $sent += $this->sendRemindersForWindow(
            now()->addDay(),
            '24 hours',
            'reminded_24h_at',
        );

        $this->info("Sent {$sent} reminder email(s).");

        return self::SUCCESS;
    }

    private function sendRemindersForWindow(Carbon $target, string $label, string $sentAtColumn): int
    {
        // Match events starting within a 30-minute window around the target time
        $windowStart = $target->copy()->subMinutes(15)->timestamp;
        $windowEnd = $target->copy()->addMinutes(15)->timestamp;

        $sent = 0;

        Event::whereBetween('created_time', [$windowStart, $windowEnd])
            ->where('status', 'published')
            ->with('attendees')
            ->chunkById(100, function ($events) use ($label, $sentAtColumn, &$sent) {
                foreach ($events as $event) {
                    foreach ($event->attendees as $attendee) {
                        if ($attendee->{$sentAtColumn} !== null) {
                            continue;
                        }

                        Mail::to($attendee->email)->send(new EventReminder($attendee, $event, $label));

                        $attendee->update([$sentAtColumn => now()]);
                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
