# Decisions

## Visual layouts

**Events Visual 1 — Card Grid** (`/events-visual-1`): A dark-themed 3-column responsive grid. Each card has a two-image carousel with dot navigation and arrow buttons (only visible on hover), a type-coloured badge overlaid on the image, venue/location, formatted date/time, and a price. Cards animate in with a staggered fade-up driven by an `IntersectionObserver`.

**Events Visual 2 — Timeline** (`/events-visual-2`): Events are grouped by calendar day along a vertical timeline. A coloured dot (matching the event type) marks each entry; cards slide in from the left on scroll. The two layouts are visually and structurally distinct—one is spatial/browsable, the other is chronological/readable.

## Images

Created 16 locally-served SVG placeholders (`public/images/events/{type}-{1|2}.svg`) — two per event type, each with a different colour gradient and icon so they look distinct. The `event_images` table and `EventImage` model are fully wired up end-to-end. For the 1.25 M-row dataset, populating every row with image records on first run isn't practical; the API falls back to type-based defaults so the UI always renders correctly. A command like `php artisan db:seed --class=EventImageSeeder` can backfill real records for specific events when needed.

## Addresses / location

`LocationResolver` finds the nearest named city to any lat/lng pair using squared Euclidean distance over 68 anchor points — the same anchors the `EventSeeder` uses, so every seeded event resolves to a real city. No external API call is needed. Location filtering passes a ±0.6° bounding box around the chosen city to `WHERE BETWEEN` queries on the indexed `latitude`/`longitude` columns.

## Date & time

Unix timestamps are sent from the backend; the frontend formats them with `Intl.DateTimeFormat` in the viewer's local timezone (e.g. "7:00 PM EDT"). The timezone abbreviation is shown so users know which timezone they're seeing. For a production product you'd also want to show the venue's local time, which would require storing a timezone per event.

## Filtering

Both visual pages support: city (nearest-anchor bounding box), event type, date-from, date-to. The data endpoint (`GET /events/visual-data`) handles all four and only returns `published` events. An index was added on `created_time`, `latitude`, and `longitude` to keep filtered queries fast against the large dataset.

## Attendees & email

Registration is idempotent — a second submission for the same (event, email) pair returns `already_registered: true` without creating a duplicate or re-sending. On first registration a confirmation email is sent immediately (synchronously via the log driver; swap `MAIL_MAILER` to `smtp` etc. for production).

## Reminder emails

`php artisan events:send-reminders` matches events starting within ±15 minutes of the 3-day and 24-hour targets. It records `reminded_3day_at` / `reminded_24h_at` per attendee to prevent duplicate sends. The command is scheduled every 15 minutes in `routes/console.php`. Run `php artisan schedule:run` (or set up a cron for it) to activate.
