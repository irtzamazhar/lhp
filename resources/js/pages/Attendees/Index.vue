<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EventRow {
    id: string;
    name: string;
    type: string;
    status: string;
    starts_at: number | null;
    location_name: string;
    attendees_count: number;
}

interface AttendeeRow {
    id: number;
    name: string;
    email: string;
    registered_at: string | null;
}

interface SelectedEvent {
    id: string;
    name: string;
    type: string;
    status: string;
    starts_at: number | null;
    location_name: string;
}

interface Pagination {
    data: EventRow[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    events: Pagination;
    filters: { search: string; type: string; status: string; event: string };
    types: string[];
    statuses: string[];
    selected_event: SelectedEvent | null;
    attendees: AttendeeRow[];
}>();

// ─── Filters ──────────────────────────────────────────────────────────────────
const search = reactive({ ...props.filters });

function applyFilters() {
    router.get('/attendees', { search: search.search, type: search.type, status: search.status, event: search.event }, {
        preserveState: true,
        replace: true,
    });
}

function selectEvent(id: string) {
    router.get('/attendees', { ...search, event: id }, { preserveScroll: true, preserveState: true });
}

function goToPage(page: number) {
    router.get('/attendees', { ...search, page }, { preserveState: true });
}

// ─── Add attendee form ────────────────────────────────────────────────────────
const addForm = reactive({ name: '', email: '' });
const addLoading = ref(false);

async function submitAdd() {
    if (!props.selected_event) return;
    addLoading.value = true;

    try {
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch('/attendees', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ event_id: props.selected_event.id, name: addForm.name, email: addForm.email }),
        });

        const data = await res.json();

        if (!res.ok) {
            const errors = data.errors ?? {};
            const first = Object.values(errors).flat()[0] as string;
            toast.error(first ?? 'Registration failed.');
            return;
        }

        if (data.already_registered) {
            toast.info('This person is already registered for this event.');
        } else {
            toast.success('Attendee registered! Confirmation email sent.');
            addForm.name = '';
            addForm.email = '';
        }

        // Reload attendee list
        router.reload({ only: ['attendees'] });
    } finally {
        addLoading.value = false;
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatDate(ts: number | null): string {
    if (!ts) return '—';
    return new Date(ts * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

const statusVariant = (s: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (s === 'published') return 'default';
    if (s === 'cancelled') return 'destructive';
    if (s === 'sold_out') return 'secondary';
    return 'outline';
};
</script>

<template>
    <Head title="Attendees" />

    <div class="flex h-full flex-col gap-0">
        <!-- Page header -->
        <div class="border-b px-6 py-5">
            <h1 class="text-2xl font-bold">Attendees</h1>
            <p class="text-sm text-muted-foreground">Select an event to view and manage its attendees.</p>
        </div>

        <div class="flex flex-1 overflow-hidden">
            <!-- ── Left panel: Event list ─────────────────────────────────── -->
            <div class="flex w-80 shrink-0 flex-col border-r">
                <!-- Filter bar -->
                <div class="border-b p-4 space-y-2">
                    <Input
                        v-model="search.search"
                        placeholder="Search events…"
                        class="h-8 text-sm"
                        @keydown.enter="applyFilters"
                    />
                    <div class="flex gap-2">
                        <select
                            v-model="search.type"
                            class="h-8 flex-1 rounded-md border border-input bg-background px-2 text-xs"
                        >
                            <option value="">All types</option>
                            <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <select
                            v-model="search.status"
                            class="h-8 flex-1 rounded-md border border-input bg-background px-2 text-xs"
                        >
                            <option value="">All statuses</option>
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <Button size="sm" class="w-full h-8 text-xs" @click="applyFilters">Apply filters</Button>
                </div>

                <!-- Event rows -->
                <div class="flex-1 overflow-y-auto">
                    <button
                        v-for="event in events.data"
                        :key="event.id"
                        class="w-full border-b px-4 py-3 text-left transition-colors hover:bg-muted/50"
                        :class="filters.event === event.id ? 'bg-muted' : ''"
                        @click="selectEvent(event.id)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="line-clamp-1 text-sm font-medium">{{ event.name }}</span>
                            <span class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                {{ event.attendees_count }}
                            </span>
                        </div>
                        <div class="mt-0.5 flex items-center gap-2">
                            <Badge :variant="statusVariant(event.status)" class="text-[10px] px-1.5 py-0">{{ event.status }}</Badge>
                            <span class="text-xs text-muted-foreground capitalize">{{ event.type }}</span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ formatDate(event.starts_at) }} · {{ event.location_name }}</p>
                    </button>

                    <p v-if="events.data.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        No events found.
                    </p>
                </div>

                <!-- Pagination -->
                <div v-if="events.last_page > 1" class="flex items-center justify-between border-t px-3 py-2">
                    <button
                        class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted disabled:opacity-40"
                        :disabled="events.current_page <= 1"
                        @click="goToPage(events.current_page - 1)"
                    >← Prev</button>
                    <span class="text-xs text-muted-foreground">{{ events.current_page }} / {{ events.last_page }}</span>
                    <button
                        class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted disabled:opacity-40"
                        :disabled="events.current_page >= events.last_page"
                        @click="goToPage(events.current_page + 1)"
                    >Next →</button>
                </div>
            </div>

            <!-- ── Right panel: Attendees ─────────────────────────────────── -->
            <div class="flex flex-1 flex-col overflow-hidden">
                <!-- No event selected -->
                <div v-if="!selected_event" class="flex flex-1 items-center justify-center text-muted-foreground">
                    <div class="text-center">
                        <svg class="mx-auto mb-3 h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="text-sm font-medium">Select an event</p>
                        <p class="text-xs">Choose an event from the list to view and manage its attendees.</p>
                    </div>
                </div>

                <template v-else>
                    <!-- Event info header -->
                    <div class="border-b px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold">{{ selected_event.name }}</h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatDate(selected_event.starts_at) }} · {{ selected_event.location_name }} ·
                                    <span class="capitalize">{{ selected_event.type }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <Badge :variant="statusVariant(selected_event.status)">{{ selected_event.status }}</Badge>
                                <span class="text-sm text-muted-foreground">{{ attendees.length }} attendee{{ attendees.length !== 1 ? 's' : '' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-1 overflow-hidden">
                        <!-- Attendee table -->
                        <div class="flex-1 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 border-b bg-muted/60 text-left">
                                    <tr>
                                        <th class="px-6 py-3 font-medium">Name</th>
                                        <th class="px-6 py-3 font-medium">Email</th>
                                        <th class="px-6 py-3 font-medium">Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="attendee in attendees"
                                        :key="attendee.id"
                                        class="border-b transition-colors last:border-0 hover:bg-muted/30"
                                    >
                                        <td class="px-6 py-3 font-medium">{{ attendee.name }}</td>
                                        <td class="px-6 py-3 text-muted-foreground">{{ attendee.email }}</td>
                                        <td class="px-6 py-3 text-muted-foreground">{{ formatDateTime(attendee.registered_at) }}</td>
                                    </tr>
                                    <tr v-if="attendees.length === 0">
                                        <td colspan="3" class="px-6 py-10 text-center text-muted-foreground">
                                            No attendees yet. Add the first one below.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Add attendee side form -->
                        <div class="w-72 shrink-0 border-l">
                            <div class="p-5">
                                <h3 class="mb-4 text-sm font-semibold">Register an attendee</h3>
                                <form class="space-y-3" @submit.prevent="submitAdd">
                                    <div class="space-y-1">
                                        <Label for="att-name" class="text-xs">Full name</Label>
                                        <Input
                                            id="att-name"
                                            v-model="addForm.name"
                                            placeholder="Jane Smith"
                                            class="h-8 text-sm"
                                            required
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <Label for="att-email" class="text-xs">Email address</Label>
                                        <Input
                                            id="att-email"
                                            v-model="addForm.email"
                                            type="email"
                                            placeholder="jane@example.com"
                                            class="h-8 text-sm"
                                            required
                                        />
                                    </div>
                                    <Button type="submit" class="w-full" size="sm" :disabled="addLoading">
                                        {{ addLoading ? 'Registering…' : 'Register attendee' }}
                                    </Button>
                                    <p class="text-xs text-muted-foreground">
                                        A confirmation email will be sent to the attendee automatically.
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
