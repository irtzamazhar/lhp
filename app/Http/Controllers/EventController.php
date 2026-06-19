<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const TYPES = ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'];

    private const STATUSES = ['draft', 'published', 'cancelled', 'sold_out'];

    public function index(Request $request): Response
    {
        $query = Event::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->search, function ($q, $s) {
                $q->whereRaw("JSON_EXTRACT(payload, '$.name') LIKE ?", ["%{$s}%"]);
            })
            ->orderByDesc('created_time');

        $events = $query->paginate(25)->withQueryString();

        $items = $events->getCollection()->map(fn (Event $e) => $this->formatForManage($e));

        return Inertia::render('Events/Manage', [
            'events' => [
                'data' => $items,
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
            'filters' => [
                'status' => $request->status ?? '',
                'type' => $request->type ?? '',
                'search' => $request->search ?? '',
            ],
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'cities' => LocationResolver::cities(),
        ]);
    }

    public function visualOne(): Response
    {
        return Inertia::render('Events/VisualOne', [
            'cities' => LocationResolver::cities(),
            'types' => self::TYPES,
        ]);
    }

    public function visualTwo(): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'cities' => LocationResolver::cities(),
            'types' => self::TYPES,
        ]);
    }

    public function visualData(Request $request): JsonResponse
    {
        $start = microtime(true);

        $query = Event::query()
            ->where('status', 'published')
            ->when($request->from, fn ($q, $from) => $q->where('created_time', '>=', strtotime($from)))
            ->when($request->to, fn ($q, $to) => $q->where('created_time', '<=', strtotime($to)))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->city, function ($q, $city) {
                $bounds = LocationResolver::bounds($city);
                if ($bounds) {
                    $q->whereBetween('latitude', [$bounds['lat_min'], $bounds['lat_max']])
                        ->whereBetween('longitude', [$bounds['lng_min'], $bounds['lng_max']]);
                }
            })
            ->orderBy('created_time');

        $events = $query->paginate(24)->withQueryString();

        $items = $events->getCollection()->map(function (Event $event) {
            $payload = $event->payload ?? [];
            $schedule = $payload['schedule'] ?? [];
            $startsAt = $schedule['starts_at'] ?? $event->created_time;

            return [
                'id' => $event->id,
                'name' => $payload['name'] ?? 'Untitled Event',
                'type' => $event->type,
                'status' => $event->status,
                'description' => $payload['description'] ?? '',
                'venue' => $payload['venue']['name'] ?? '',
                'starts_at' => (int) $startsAt,
                'ends_at' => isset($schedule['ends_at']) ? (int) $schedule['ends_at'] : null,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'location_name' => LocationResolver::resolve($event->latitude, $event->longitude),
                'images' => $this->defaultImages($event->type),
                'tags' => $payload['tags'] ?? [],
            ];
        });

        return response()->json([
            'data' => $items,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEventForm($request);

        [$lat, $lng] = $this->resolveCoords($data);
        $startsAt = strtotime($data['starts_at']);
        $endsAt = $data['ends_at'] ? strtotime($data['ends_at']) : $startsAt + 7200;

        Event::create([
            'type' => $data['type'],
            'status' => $data['status'],
            'created_time' => $startsAt,
            'latitude' => $lat,
            'longitude' => $lng,
            'payload' => $this->buildPayload($data, $lat, $lng, $startsAt, $endsAt),
        ]);

        return redirect()->route('events.index')->with('success', 'Event created.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validateEventForm($request);

        [$lat, $lng] = $this->resolveCoords($data);
        $startsAt = strtotime($data['starts_at']);
        $endsAt = $data['ends_at'] ? strtotime($data['ends_at']) : $startsAt + 7200;

        $event->update([
            'type' => $data['type'],
            'status' => $data['status'],
            'created_time' => $startsAt,
            'latitude' => $lat,
            'longitude' => $lng,
            'payload' => $this->buildPayload($data, $lat, $lng, $startsAt, $endsAt),
        ]);

        return redirect()->route('events.index')->with('success', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatForManage(Event $event): array
    {
        $payload = $event->payload ?? [];

        return [
            'id' => $event->id,
            'name' => $payload['name'] ?? 'Untitled',
            'type' => $event->type,
            'status' => $event->status,
            'starts_at' => $event->created_time,
            'ends_at' => $payload['schedule']['ends_at'] ?? null,
            'venue' => $payload['venue']['name'] ?? '',
            'location_name' => LocationResolver::resolve($event->latitude, $event->longitude),
            'city' => LocationResolver::resolve($event->latitude, $event->longitude),
            'description' => $payload['description'] ?? '',
            'organizer_name' => $payload['organizer']['name'] ?? '',
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
        ];
    }

    private function validateEventForm(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', self::TYPES),
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'venue_name' => 'required|string|max:255',
            'city' => 'required|string',
            'description' => 'nullable|string|max:3000',
            'organizer_name' => 'nullable|string|max:255',
        ]);
    }

    /** @return array{0: float, 1: float} */
    private function resolveCoords(array $data): array
    {
        $coords = LocationResolver::coordinates($data['city']);

        return $coords ?? [0.0, 0.0];
    }

    private function buildPayload(array $data, float $lat, float $lng, int $startsAt, int $endsAt): array
    {
        return [
            'name' => $data['name'],
            'category' => $data['type'],
            'description' => $data['description'] ?? "Join us for {$data['name']} — a {$data['type']} you won't want to miss.",
            'organizer' => [
                'name' => $data['organizer_name'] ?? '',
                'verified' => false,
            ],
            'venue' => [
                'name' => $data['venue_name'],
                'capacity' => 0,
            ],
            'location' => ['lat' => (string) $lat, 'lng' => (string) $lng],
            'schedule' => ['starts_at' => $startsAt, 'ends_at' => $endsAt],
            'tags' => [],
            'notes' => '',
        ];
    }

    /** @return string[] */
    private function defaultImages(string $type): array
    {
        return [
            asset("images/events/{$type}-1.svg"),
            asset("images/events/{$type}-2.svg"),
        ];
    }
}
