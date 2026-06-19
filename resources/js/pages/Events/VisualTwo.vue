<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
    latitude: number;
    longitude: number;
    location_name: string;
    images: string[];
    tags: string[];
}

interface CalendarDay {
    date: Date;
    dateKey: string;
    isCurrentMonth: boolean;
    isToday: boolean;
}

const props = defineProps<{
    cities: string[];
    types: string[];
}>();

// ─── Month navigation ─────────────────────────────────────────────────────────
const today = new Date();
const viewYear = ref(today.getFullYear());
const viewMonth = ref(today.getMonth()); // 0-indexed

const monthLabel = computed(() =>
    new Date(viewYear.value, viewMonth.value, 1).toLocaleDateString('en-US', {
        month: 'long', year: 'numeric',
    }),
);

function prevMonth() {
    if (viewMonth.value === 0) { viewMonth.value = 11; viewYear.value--; }
    else viewMonth.value--;
}
function nextMonth() {
    if (viewMonth.value === 11) { viewMonth.value = 0; viewYear.value++; }
    else viewMonth.value++;
}

// ─── Calendar grid ────────────────────────────────────────────────────────────
const calendarDays = computed<CalendarDay[]>(() => {
    const year = viewYear.value;
    const month = viewMonth.value;
    const firstDay = new Date(year, month, 1).getDay(); // 0 = Sun
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrev = new Date(year, month, 0).getDate();

    const todayKey = dateKey(today);
    const days: CalendarDay[] = [];

    // Padding from previous month
    for (let i = firstDay - 1; i >= 0; i--) {
        const d = new Date(year, month - 1, daysInPrev - i);
        days.push({ date: d, dateKey: dateKey(d), isCurrentMonth: false, isToday: false });
    }

    // Current month
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, month, d);
        const key = dateKey(date);
        days.push({ date, dateKey: key, isCurrentMonth: true, isToday: key === todayKey });
    }

    // Padding to next month (fill to complete 6-row grid = 42 cells)
    const remaining = 42 - days.length;
    for (let d = 1; d <= remaining; d++) {
        const date = new Date(year, month + 1, d);
        days.push({ date, dateKey: dateKey(date), isCurrentMonth: false, isToday: false });
    }

    return days;
});

function dateKey(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// ─── Filters ──────────────────────────────────────────────────────────────────
const filters = reactive({ city: '', type: '' });

// ─── Events data ──────────────────────────────────────────────────────────────
const monthEvents = ref<EventItem[]>([]);
const loading = ref(false);

const eventsByDate = computed(() => {
    const map = new Map<string, EventItem[]>();
    for (const event of monthEvents.value) {
        const d = new Date(event.starts_at * 1000);
        const key = dateKey(d);
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(event);
    }
    return map;
});

async function loadMonth() {
    loading.value = true;
    monthEvents.value = [];

    const year = viewYear.value;
    const month = viewMonth.value;
    // Fetch slightly wider window to cover padding days
    const from = new Date(year, month - 1, 20);
    const to = new Date(year, month + 1, 10);

    const fromStr = dateKey(from);
    const toStr = dateKey(to);

    let page = 1;
    try {
        while (true) {
            const params = new URLSearchParams({ page: String(page), from: fromStr, to: toStr });
            if (filters.city) params.set('city', filters.city);
            if (filters.type) params.set('type', filters.type);

            const res = await fetch(`/events/visual-data?${params}`, { headers: { Accept: 'application/json' } });
            const payload = await res.json();
            monthEvents.value.push(...payload.data);

            if (page >= payload.last_page) break;
            page++;
        }
    } finally {
        loading.value = false;
    }
}

watch([viewYear, viewMonth, () => filters.city, () => filters.type], loadMonth);

// ─── Selected event panel ─────────────────────────────────────────────────────
const selected = ref<EventItem | null>(null);
const activeImage = ref(0);
const panelVisible = ref(false);

// Selected day (to highlight the cell)
const selectedDateKey = ref<string | null>(null);

function openEvent(event: EventItem) {
    selected.value = event;
    activeImage.value = 0;
    panelVisible.value = true;
    selectedDateKey.value = dateKey(new Date(event.starts_at * 1000));
}

function closePanel() {
    panelVisible.value = false;
    selected.value = null;
    selectedDateKey.value = null;
}

function cycleImage(delta: number) {
    if (!selected.value) return;
    const len = selected.value.images.length;
    activeImage.value = (activeImage.value + delta + len) % len;
}

// ─── Formatting ───────────────────────────────────────────────────────────────
function formatTime(ts: number): string {
    return new Date(ts * 1000).toLocaleTimeString('en-US', {
        hour: 'numeric', minute: '2-digit', timeZoneName: 'short',
    });
}
function formatDate(ts: number): string {
    return new Date(ts * 1000).toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
    });
}

const typeColors: Record<string, string> = {
    concert: 'bg-purple-500',
    conference: 'bg-blue-500',
    meetup: 'bg-green-500',
    workshop: 'bg-amber-500',
    festival: 'bg-orange-500',
    sports: 'bg-red-500',
    networking: 'bg-teal-500',
    exhibition: 'bg-indigo-500',
};

const typeBg: Record<string, string> = typeColors;

// ─── Registration dialog ──────────────────────────────────────────────────────
const dialogOpen = ref(false);
const registrationForm = reactive({ name: '', email: '' });
const registrationLoading = ref(false);

function openRegistration() {
    registrationForm.name = '';
    registrationForm.email = '';
    dialogOpen.value = true;
}

async function submitRegistration() {
    if (!selected.value) return;
    registrationLoading.value = true;
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
            body: JSON.stringify({
                event_id: selected.value.id,
                name: registrationForm.name,
                email: registrationForm.email,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            const first = Object.values(data.errors ?? {}).flat()[0] as string;
            toast.error(first ?? 'Registration failed.');
            return;
        }
        dialogOpen.value = false;
        toast[data.already_registered ? 'info' : 'success'](
            data.already_registered ? "You're already registered." : 'Registered! Check your email.',
        );
    } finally {
        registrationLoading.value = false;
    }
}

onMounted(loadMonth);
</script>

<template>
    <Head title="Events — Calendar" />

    <div class="flex h-[calc(100vh-4rem)] flex-col overflow-hidden bg-gray-950">

        <!-- Top bar -->
        <div class="shrink-0 border-b border-white/10 bg-gray-950/95 backdrop-blur-md">
            <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                <!-- Month navigation -->
                <div class="flex items-center gap-2">
                    <button
                        class="rounded-lg border border-white/10 bg-gray-900 p-1.5 text-gray-400 transition-colors hover:border-white/20 hover:text-white"
                        @click="prevMonth"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <span class="min-w-40 text-center text-sm font-semibold text-white">{{ monthLabel }}</span>
                    <button
                        class="rounded-lg border border-white/10 bg-gray-900 p-1.5 text-gray-400 transition-colors hover:border-white/20 hover:text-white"
                        @click="nextMonth"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <button
                        class="ml-1 rounded-lg border border-white/10 bg-gray-900 px-3 py-1.5 text-xs text-gray-400 transition-colors hover:text-white"
                        @click="() => { viewYear = today.getFullYear(); viewMonth = today.getMonth(); }"
                    >Today</button>
                </div>

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <!-- Loading indicator -->
                    <div v-if="loading" class="flex items-center gap-1.5 text-xs text-gray-500">
                        <div class="h-3 w-3 animate-spin rounded-full border border-white/20 border-t-white" />
                        Loading…
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <label class="text-xs text-gray-500">City</label>
                        <select v-model="filters.city" class="h-8 rounded-lg border border-white/10 bg-gray-900 px-2 text-xs text-gray-100 focus:border-white/30 focus:outline-none">
                            <option value="">All cities</option>
                            <option v-for="city in props.cities" :key="city" :value="city">{{ city }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-xs text-gray-500">Type</label>
                        <select v-model="filters.type" class="h-8 rounded-lg border border-white/10 bg-gray-900 px-2 text-xs text-gray-100 focus:border-white/30 focus:outline-none">
                            <option value="">All types</option>
                            <option v-for="t in props.types" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar + panel -->
        <div class="relative flex flex-1 overflow-hidden">

            <!-- Calendar area -->
            <div class="flex flex-1 flex-col overflow-hidden">

                <!-- Weekday headers -->
                <div class="grid shrink-0 grid-cols-7 border-b border-white/10">
                    <div
                        v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
                        :key="day"
                        class="py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500"
                    >{{ day }}</div>
                </div>

                <!-- Calendar grid -->
                <div class="grid flex-1 grid-cols-7 grid-rows-6 overflow-hidden">
                    <div
                        v-for="day in calendarDays"
                        :key="day.dateKey"
                        class="calendar-cell group relative flex flex-col gap-1 overflow-hidden border-b border-r border-white/5 p-1.5 transition-colors"
                        :class="{
                            'bg-white/5': day.isToday,
                            'opacity-30': !day.isCurrentMonth,
                            'bg-white/[0.03]': day.dateKey === selectedDateKey,
                        }"
                    >
                        <!-- Day number -->
                        <div class="flex items-start justify-between">
                            <span
                                class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium"
                                :class="day.isToday ? 'bg-white text-gray-900 font-bold' : 'text-gray-400'"
                            >{{ day.date.getDate() }}</span>
                        </div>

                        <!-- Event pills -->
                        <template v-if="eventsByDate.has(day.dateKey)">
                            <button
                                v-for="event in eventsByDate.get(day.dateKey)!.slice(0, 3)"
                                :key="event.id"
                                class="event-pill w-full truncate rounded px-1.5 py-0.5 text-left text-[10px] font-medium text-white transition-all hover:brightness-110 hover:scale-[1.02]"
                                :class="typeColors[event.type] ?? 'bg-gray-600'"
                                @click="openEvent(event)"
                            >
                                {{ event.name }}
                            </button>

                            <!-- Overflow indicator -->
                            <button
                                v-if="(eventsByDate.get(day.dateKey)?.length ?? 0) > 3"
                                class="w-full rounded px-1.5 py-0.5 text-left text-[10px] text-gray-400 hover:text-gray-200"
                                @click="openEvent(eventsByDate.get(day.dateKey)![3])"
                            >
                                +{{ (eventsByDate.get(day.dateKey)?.length ?? 0) - 3 }} more
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Event detail panel -->
            <Transition name="panel">
                <div
                    v-if="panelVisible && selected"
                    class="absolute right-0 top-0 z-20 flex h-full w-80 shrink-0 flex-col overflow-y-auto border-l border-white/10 bg-gray-900 shadow-2xl"
                >
                    <!-- Close -->
                    <button
                        class="absolute right-3 top-3 z-10 rounded-full bg-black/40 p-1.5 text-gray-400 backdrop-blur-sm transition-colors hover:text-white"
                        @click="closePanel"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Image carousel -->
                    <div class="relative h-52 shrink-0 overflow-hidden bg-gray-800">
                        <div class="relative h-full">
                            <img
                                v-for="(img, idx) in selected.images"
                                :key="img"
                                :src="img"
                                :alt="selected.name"
                                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-400"
                                :class="activeImage === idx ? 'opacity-100' : 'opacity-0'"
                            />
                        </div>

                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-gray-900/70 via-transparent to-transparent" />

                        <template v-if="selected.images.length > 1">
                            <button
                                class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-1.5 backdrop-blur-sm transition-colors hover:bg-black/70"
                                @click="cycleImage(-1)"
                            >
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-1.5 backdrop-blur-sm transition-colors hover:bg-black/70"
                                @click="cycleImage(1)"
                            >
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>

                            <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
                                <button
                                    v-for="(_, i) in selected.images"
                                    :key="i"
                                    class="rounded-full transition-all duration-300"
                                    :class="activeImage === i ? 'w-5 h-1.5 bg-white' : 'w-1.5 h-1.5 bg-white/40'"
                                    @click="activeImage = i"
                                />
                            </div>
                        </template>

                        <div class="absolute bottom-3 right-3 rounded-full bg-black/50 px-2 py-0.5 text-xs text-white backdrop-blur-sm">
                            {{ activeImage + 1 }}/{{ selected.images.length }}
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="flex flex-1 flex-col gap-4 p-5">
                        <span
                            class="inline-flex w-fit rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize text-white"
                            :class="typeBg[selected.type] ?? 'bg-gray-600'"
                        >{{ selected.type }}</span>

                        <h2 class="text-base font-bold leading-snug text-white">{{ selected.name }}</h2>
                        <p class="text-sm leading-relaxed text-gray-400">{{ selected.description }}</p>

                        <div class="space-y-2.5 border-t border-white/10 pt-3">
                            <div class="flex items-start gap-2 text-sm text-gray-400">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>{{ selected.venue ? `${selected.venue} · ` : '' }}{{ selected.location_name }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>{{ formatDate(selected.starts_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ formatTime(selected.starts_at) }}</span>
                            </div>
                        </div>

                        <Button class="mt-auto w-full" @click="openRegistration">Register Interest</Button>
                    </div>
                </div>
            </Transition>
        </div>
    </div>

    <!-- Registration dialog -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Register Interest</DialogTitle>
                <DialogDescription v-if="selected">
                    {{ selected.name }} · {{ formatDate(selected.starts_at) }}
                </DialogDescription>
            </DialogHeader>
            <form class="grid gap-4 py-2" @submit.prevent="submitRegistration">
                <div class="grid gap-1.5">
                    <Label for="cal-name">Your name</Label>
                    <Input id="cal-name" v-model="registrationForm.name" placeholder="Jane Smith" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cal-email">Email address</Label>
                    <Input id="cal-email" v-model="registrationForm.email" type="email" placeholder="jane@example.com" required />
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
.panel-enter-active,
.panel-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.panel-enter-from,
.panel-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

.event-pill {
    animation: pill-in 0.2s ease both;
}

@keyframes pill-in {
    from { opacity: 0; transform: translateY(4px); }
    to   { opacity: 1; transform: translateY(0); }
}

.calendar-cell:hover {
    background: rgba(255, 255, 255, 0.04);
}
</style>
