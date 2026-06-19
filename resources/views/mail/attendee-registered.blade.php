<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; margin: 0; padding: 40px 16px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .header { background: linear-gradient(135deg, #7C3AED, #EC4899); padding: 36px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
        .header p { color: rgba(255,255,255,.8); margin: 6px 0 0; font-size: 14px; }
        .body { padding: 32px; }
        .body p { color: #374151; line-height: 1.6; margin: 0 0 16px; }
        .event-box { background: #f3f4f6; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .event-box .label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-bottom: 4px; }
        .event-box .value { font-size: 15px; font-weight: 600; color: #111827; }
        .event-box .sub { font-size: 13px; color: #6b7280; margin-top: 2px; }
        .footer { border-top: 1px solid #e5e7eb; padding: 20px 32px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>You're on the list! 🎉</h1>
            <p>Registration confirmed</p>
        </div>
        <div class="body">
            <p>Hi {{ $attendee->name }},</p>
            <p>Great news — you've been added to the attendee list for the following event.</p>

            <div class="event-box">
                <div class="label">Event</div>
                <div class="value">{{ $attendee->event->payload['name'] ?? 'Event' }}</div>

                @php
                    $startsAt = $attendee->event->payload['schedule']['starts_at'] ?? $attendee->event->created_time;
                    $venue = $attendee->event->payload['venue']['name'] ?? '';
                @endphp

                @if($startsAt)
                    <div class="sub">{{ \Carbon\Carbon::createFromTimestamp($startsAt)->format('l, F j, Y \a\t g:i A T') }}</div>
                @endif
                @if($venue)
                    <div class="sub">{{ $venue }}</div>
                @endif
            </div>

            <p>We'll send you a reminder 3 days before and again 24 hours before the event so you don't miss it.</p>
            <p>See you there!</p>
        </div>
        <div class="footer">
            You're receiving this because you registered interest in an event. If this was a mistake, you can ignore this email.
        </div>
    </div>
</body>
</html>
