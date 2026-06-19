<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    private const COUNT = 1000;

    private const CHUNK = 250;

    private const TYPES = ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'];

    private const ADJECTIVES = ['Annual', 'Global', 'Summer', 'Winter', 'Underground', 'Open', 'International', 'Live', 'Midnight', 'Sunset', 'Urban', 'Indie', 'Grand', 'Pop-up', 'Virtual'];

    private const THEMES = ['Synthwave', 'Founders', 'Jazz', 'Tech', 'Food & Wine', 'Yoga', 'Startup', 'Design', 'Climate', 'Gaming', 'Film', 'Book', 'Marathon', 'Comedy', 'Art'];

    private const FORMATS = ['Festival', 'Meetup', 'Conference', 'Summit', 'Workshop', 'Expo', 'Showcase', 'Gala', 'Jam', 'Retreat', 'Fair', 'Night', 'Tour', 'Symposium', 'Block Party'];

    private const VENUE_PREFIXES = ['The Grand', 'Riverside', 'Downtown', 'Skyline', 'Harbor', 'Old Town', 'Central', 'Sunset'];

    private const VENUE_SUFFIXES = ['Hall', 'Arena', 'Pavilion', 'Gardens', 'Warehouse', 'Theatre', 'Rooftop', 'Stadium'];

    private const ORGANIZERS = ['Eventful Co.', 'Live Nation', 'Spark Events', 'Atlas Collective', 'Urban Stage', 'The Venue Group', 'Peak Productions', 'Mosaic Events'];

    /**
     * City anchors matching LocationResolver::CITIES so every event resolves
     * to a recognisable human-readable location.
     */
    private const CITY_ANCHORS = [
        // United States
        [40.7128, -74.0060], [34.0522, -118.2437], [41.8781, -87.6298], [29.7604, -95.3698],
        [33.4484, -112.0740], [39.9526, -75.1652], [29.4241, -98.4936], [32.7157, -117.1611],
        [32.7767, -96.7970], [37.3382, -121.8863], [30.2672, -97.7431], [37.7749, -122.4194],
        [47.6062, -122.3321], [39.7392, -104.9903], [42.3601, -71.0589], [36.1699, -115.1398],
        [25.7617, -80.1918], [33.7490, -84.3880], [38.9072, -77.0369], [36.1627, -86.7816],
        [45.5152, -122.6784], [29.9511, -90.0715],
        // Canada
        [43.6532, -79.3832], [45.5019, -73.5674], [49.2827, -123.1207], [51.0447, -114.0719],
        // Mexico
        [19.4326, -99.1332], [20.6597, -103.3496], [25.6866, -100.3161], [21.1619, -86.8515],
        // Europe
        [51.5074, -0.1278], [48.8566, 2.3522], [52.5200, 13.4050], [40.4168, -3.7038],
        [41.9028, 12.4964], [52.3676, 4.9041], [41.3851, 2.1734], [48.1351, 11.5820],
        [48.2082, 16.3738], [50.0755, 14.4378], [38.7223, -9.1393], [53.3498, -6.2603],
        [59.3293, 18.0686], [59.9139, 10.7522], [60.1699, 24.9384], [47.3769, 8.5417],
        // Global hubs
        [35.6762, 139.6503], [37.5665, 126.9780], [1.3521, 103.8198], [-33.8688, 151.2093],
        [25.2048, 55.2708], [-23.5505, -46.6333],
    ];

    public function run(): void
    {
        $this->command?->info('Seeding ' . self::COUNT . ' events…');

        $now = now()->toDateTimeString();
        $nowTs = time();
        $year = 365 * 24 * 3600;
        $anchorCount = count(self::CITY_ANCHORS);

        $remaining = self::COUNT;

        while ($remaining > 0) {
            $batchSize = min(self::CHUNK, $remaining);
            $batch = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $type = self::TYPES[array_rand(self::TYPES)];
                $startsAt = mt_rand($nowTs - $year, $nowTs + $year);
                $endsAt = $startsAt + mt_rand(3600, 3 * 24 * 3600);

                $anchor = self::CITY_ANCHORS[mt_rand(0, $anchorCount - 1)];
                $lat = round($anchor[0] + (mt_rand(-500, 500) / 1000), 7);
                $lng = round($anchor[1] + (mt_rand(-500, 500) / 1000), 7);

                $name = self::ADJECTIVES[array_rand(self::ADJECTIVES)]
                    . ' ' . self::THEMES[array_rand(self::THEMES)]
                    . ' ' . self::FORMATS[array_rand(self::FORMATS)];

                $venue = self::VENUE_PREFIXES[array_rand(self::VENUE_PREFIXES)]
                    . ' ' . self::VENUE_SUFFIXES[array_rand(self::VENUE_SUFFIXES)];

                $batch[] = [
                    'id' => (string) Str::uuid(),
                    'type' => $type,
                    'status' => 'published',
                    'created_time' => $startsAt,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'payload' => json_encode([
                        'name' => $name,
                        'category' => $type,
                        'description' => "Join us for {$name} — a {$type} you won't want to miss.",
                        'organizer' => [
                            'name' => self::ORGANIZERS[array_rand(self::ORGANIZERS)],
                            'verified' => (bool) mt_rand(0, 1),
                        ],
                        'venue' => [
                            'name' => $venue,
                            'capacity' => mt_rand(50, 50000),
                        ],
                        'location' => ['lat' => (string) $lat, 'lng' => (string) $lng],
                        'schedule' => ['starts_at' => $startsAt, 'ends_at' => $endsAt],
                        'tags' => [],
                        'notes' => '',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('events')->insert($batch);
            $remaining -= $batchSize;
        }

        $this->command?->info('Done.');
    }
}
