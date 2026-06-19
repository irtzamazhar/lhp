<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EventItem {
    id: string;
    name: string;
    type: string;
    status: string;
    description: string;
    venue: string;
    starts_at: number;
    ends_at: number | null;

    location_name: string;
    images: string[];
    tags: string[];
}

const props = defineProps<{
    cities: string[];
    types: string[];
}>();

const events = ref<EventItem[]>([]);
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const hasLoadedOnce = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;
let revealObserver: IntersectionObserver | null = null;

const filters = reactive({
    city: '',
    type: '',
    from: '',
    to: '',
});

// Attendee registration dialog
const dialogOpen = ref(false);
const selectedEvent = ref<EventItem | null>(null);
const registrationForm = reactive({ name: '', email: '' });
const registrationLoading = ref(false);

// Image carousel state per event
const activeImageIndex = ref<Record<string, number>>({});

const hasMore = computed(() => lastPage.value === null || page.value < lastPage.value);

function formatDate(ts: number): string {
    return new Date(ts * 1000).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(ts: number): string {
    return new Date(ts * 1000).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    });
}

const typeColors: Record<string, string> = {
    concert: 'bg-purple-500/20 text-purple-300 border-purple-500/30',
    conference: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    meetup: 'bg-green-500/20 text-green-300 border-green-500/30',
    workshop: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
    festival: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
    sports: 'bg-red-500/20 text-red-300 border-red-500/30',
    networking: 'bg-teal-500/20 text-teal-300 border-teal-500/30',
    exhibition: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
};

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;

    const params = new URLSearchParams({ page: String(page.value + 1) });
    if (filters.city) params.set('city', filters.city);
    if (filters.type) params.set('type', filters.type);
    if (filters.from) params.set('from', filters.from);
    if (filters.to) params.set('to', filters.to);

    try {
        const response = await fetch(`/events/visual-data?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        events.value.push(...payload.data);
        page.value = payload.current_page;
        lastPage.value = payload.last_page;
        total.value = payload.total;
        hasLoadedOnce.value = true;

        // Trigger reveal animation on new cards
        requestAnimationFrame(attachRevealObserver);
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    events.value = [];
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoadedOnce.value = false;
    loadMore();
}

function cycleImage(eventId: string, delta: number, total: number) {
    const current = activeImageIndex.value[eventId] ?? 0;
    activeImageIndex.value[eventId] = (current + delta + total) % total;
}

function openRegistration(event: EventItem) {
    selectedEvent.value = event;
    registrationForm.name = '';
    registrationForm.email = '';
    dialogOpen.value = true;
}

async function submitRegistration() {
    if (!selectedEvent.value) return;
    registrationLoading.value = true;

    try {
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch('/attendees', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ event_id: selectedEvent.value.id, name: registrationForm.name, email: registrationForm.email }),
        });

        const data = await res.json();

        if (!res.ok) {
            const errors = data.errors ?? {};
            const first = Object.values(errors).flat()[0] as string;
            toast.error(first ?? 'Registration failed. Please try again.');
            return;
        }

        dialogOpen.value = false;

        if (data.already_registered) {
            toast.info("You're already registered for this event.");
        } else {
            toast.success('Registered! Check your email for confirmation.');
        }
    } finally {
        registrationLoading.value = false;
    }
}

function attachRevealObserver() {
    document.querySelectorAll('.event-card:not(.revealed)').forEach((el) => {
        revealObserver?.observe(el);
    });
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => { if (entries[0]?.isIntersecting) loadMore(); },
        { rootMargin: '400px' },
    );
    if (sentinel.value) observer.observe(sentinel.value);

    revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    revealObserver?.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1 },
    );

    loadMore();
});

onBeforeUnmount(() => {
    observer?.disconnect();
    revealObserver?.disconnect();
});
</script>

<template>
    <Head title="Events — Card View" />

    <div class="min-h-screen bg-gray-950 text-gray-100">
        <!-- Header -->
        <div class="sticky top-0 z-20 border-b border-white/10 bg-gray-950/90 backdrop-blur-md">
            <div class="mx-auto max-w-7xl px-4 py-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1">
                        <h1 class="text-xl font-bold text-white">Events</h1>
                        <p class="text-sm text-gray-400">
                            {{ total !== null ? `${total.toLocaleString()} published events` : 'Loading…' }}
                        </p>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400">City</label>
                            <select
                                v-model="filters.city"
                                class="h-9 rounded-lg border border-white/10 bg-gray-900 px-3 text-sm text-gray-100 focus:border-white/30 focus:outline-none"
                            >
                                <option value="">All cities</option>
                                <option v-for="city in props.cities" :key="city" :value="city">{{ city }}</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400">Type</label>
                            <select
                                v-model="filters.type"
                                class="h-9 rounded-lg border border-white/10 bg-gray-900 px-3 text-sm text-gray-100 focus:border-white/30 focus:outline-none"
                            >
                                <option value="">All types</option>
                                <option v-for="t in props.types" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400">From</label>
                            <input
                                v-model="filters.from"
                                type="date"
                                class="h-9 rounded-lg border border-white/10 bg-gray-900 px-3 text-sm text-gray-100 focus:border-white/30 focus:outline-none"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400">To</label>
                            <input
                                v-model="filters.to"
                                type="date"
                                class="h-9 rounded-lg border border-white/10 bg-gray-900 px-3 text-sm text-gray-100 focus:border-white/30 focus:outline-none"
                            />
                        </div>

                        <Button class="h-9" @click="applyFilters">Apply</Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Grid -->
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="event in events"
                    :key="event.id"
                    class="event-card group relative flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-gray-900 opacity-0 transition-all duration-500"
                >
                    <!-- Image carousel -->
                    <div class="relative h-52 overflow-hidden bg-gray-800">
                        <img
                            v-for="(img, idx) in event.images"
                            :key="img"
                            :src="img"
                            :alt="event.name"
                            class="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
                            :class="(activeImageIndex[event.id] ?? 0) === idx ? 'opacity-100' : 'opacity-0'"
                        />
                        <!-- Gradient overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-transparent to-transparent" />

                        <!-- Image nav (only if multiple images) -->
                        <template v-if="event.images.length > 1">
                            <button
                                class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-1.5 opacity-0 backdrop-blur-sm transition-opacity group-hover:opacity-100"
                                @click.stop="cycleImage(event.id, -1, event.images.length)"
                            >
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-1.5 opacity-0 backdrop-blur-sm transition-opacity group-hover:opacity-100"
                                @click.stop="cycleImage(event.id, 1, event.images.length)"
                            >
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <!-- Dots -->
                            <div class="absolute bottom-2 left-0 right-0 flex justify-center gap-1">
                                <span
                                    v-for="(_, i) in event.images"
                                    :key="i"
                                    class="block h-1.5 w-1.5 rounded-full transition-all"
                                    :class="(activeImageIndex[event.id] ?? 0) === i ? 'bg-white w-4' : 'bg-white/40'"
                                />
                            </div>
                        </template>

                        <!-- Type badge overlaid on image -->
                        <div class="absolute left-3 top-3">
                            <span
                                class="rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize backdrop-blur-sm"
                                :class="typeColors[event.type] ?? 'bg-gray-500/20 text-gray-300 border-gray-500/30'"
                            >{{ event.type }}</span>
                        </div>
                    </div>

                    <!-- Card body -->
                    <div class="flex flex-1 flex-col gap-3 p-5">
                        <h2 class="line-clamp-2 text-base font-bold leading-snug text-white">{{ event.name }}</h2>
                        <p class="line-clamp-2 text-sm leading-relaxed text-gray-400">{{ event.description }}</p>

                        <div class="mt-auto space-y-1.5 text-sm">
                            <!-- Venue + Location -->
                            <div class="flex items-start gap-2 text-gray-400">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="line-clamp-1">{{ event.venue ? `${event.venue} · ` : '' }}{{ event.location_name }}</span>
                            </div>

                            <!-- Date & Time -->
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>{{ formatDate(event.starts_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ formatTime(event.starts_at) }}</span>
                            </div>
                        </div>

                        <!-- Footer: register button -->
                        <div class="flex items-center justify-end border-t border-white/10 pt-3">
                            <button
                                class="rounded-lg bg-white px-4 py-1.5 text-sm font-semibold text-gray-900 transition-transform hover:scale-105 active:scale-95"
                                @click="openRegistration(event)"
                            >
                                Register
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="hasLoadedOnce && events.length === 0" class="py-24 text-center text-gray-500">
                No published events found for the selected filters.
            </div>

            <!-- Loading indicator -->
            <div v-if="loading" class="mt-8 flex justify-center">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-white/20 border-t-white" />
            </div>

            <div ref="sentinel" class="h-1" />
        </div>
    </div>

    <!-- Registration dialog -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Register Interest</DialogTitle>
                <DialogDescription v-if="selectedEvent">
                    {{ selectedEvent.name }} · {{ selectedEvent.location_name }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4 py-2" @submit.prevent="submitRegistration">
                <div class="grid gap-1.5">
                    <Label for="reg-name">Your name</Label>
                    <Input id="reg-name" v-model="registrationForm.name" placeholder="Jane Smith" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="reg-email">Email address</Label>
                    <Input id="reg-email" v-model="registrationForm.email" type="email" placeholder="jane@example.com" required />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="registrationLoading" class="w-full">
                        {{ registrationLoading ? 'Registering…' : "I'm interested" }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.event-card {
    transform: translateY(20px);
    transition: opacity 0.5s ease, transform 0.5s ease, box-shadow 0.3s ease;
}
.event-card.revealed {
    opacity: 1;
    transform: translateY(0);
}
.event-card:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}
</style>
