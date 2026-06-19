<?php

namespace App\Http\Controllers;

use App\Mail\AttendeeRegistered;
use App\Models\Attendee;
use App\Models\Event;
use App\Services\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class AttendeeController extends Controller
{
    public function index(Request $request): Response
    {
        $events = Event::query()
            ->withCount('attendees')
            ->when($request->search, function ($q, $s) {
                $q->whereRaw("JSON_EXTRACT(payload, '$.name') LIKE ?", ["%{$s}%"]);
            })
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('created_time')
            ->paginate(20)
            ->withQueryString();

        $eventItems = $events->getCollection()->map(fn (Event $e) => [
            'id' => $e->id,
            'name' => $e->payload['name'] ?? 'Untitled',
            'type' => $e->type,
            'status' => $e->status,
            'starts_at' => $e->created_time,
            'location_name' => LocationResolver::resolve($e->latitude, $e->longitude),
            'attendees_count' => $e->attendees_count,
        ]);

        // Load attendees for the selected event
        $selectedId = $request->event;
        $selectedEvent = null;
        $attendees = collect();

        if ($selectedId) {
            $selectedEvent = Event::find($selectedId);
            if ($selectedEvent) {
                $attendees = $selectedEvent->attendees()
                    ->orderByDesc('registered_at')
                    ->get()
                    ->map(fn (Attendee $a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'email' => $a->email,
                        'registered_at' => $a->registered_at?->toISOString(),
                    ]);

                $selectedEvent = [
                    'id' => $selectedEvent->id,
                    'name' => $selectedEvent->payload['name'] ?? 'Untitled',
                    'type' => $selectedEvent->type,
                    'status' => $selectedEvent->status,
                    'starts_at' => $selectedEvent->created_time,
                    'location_name' => LocationResolver::resolve($selectedEvent->latitude, $selectedEvent->longitude),
                ];
            }
        }

        return Inertia::render('Attendees/Index', [
            'events' => [
                'data' => $eventItems,
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
            'filters' => [
                'search' => $request->search ?? '',
                'type' => $request->type ?? '',
                'status' => $request->status ?? '',
                'event' => $selectedId ?? '',
            ],
            'types' => ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
            'selected_event' => $selectedEvent,
            'attendees' => $attendees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => 'required|string|exists:events,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        $attendee = Attendee::firstOrCreate(
            ['event_id' => $data['event_id'], 'email' => $data['email']],
            ['name' => $data['name'], 'registered_at' => now()],
        );

        if ($attendee->wasRecentlyCreated) {
            $attendee->load('event');
            Mail::to($attendee->email)->send(new AttendeeRegistered($attendee));
        }

        return response()->json([
            'success' => true,
            'already_registered' => ! $attendee->wasRecentlyCreated,
        ]);
    }
}
