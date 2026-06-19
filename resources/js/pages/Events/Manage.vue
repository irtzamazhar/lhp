<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EventRow {
    id: string;
    name: string;
    type: string;
    status: string;
    starts_at: number | null;
    ends_at: number | null;
    venue: string;
    location_name: string;
    city: string;

    description: string;
    organizer_name: string;
    latitude: number | null;
    longitude: number | null;
}

interface Pagination {
    data: EventRow[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    events: Pagination;
    filters: { status: string; type: string; search: string };
    types: string[];
    statuses: string[];
    cities: string[];
}>();

// ─── Filters ──────────────────────────────────────────────────────────────────
const search = reactive({ ...props.filters });

function applyFilters() {
    router.get('/events', { ...search }, { preserveState: true, replace: true });
}

function goToPage(page: number) {
    router.get('/events', { ...search, page }, { preserveState: true });
}

// ─── Form state ───────────────────────────────────────────────────────────────
const dialogOpen = ref(false);
const isEditing = ref(false);
const deleteTarget = ref<EventRow | null>(null);
const deleteDialogOpen = ref(false);

function toLocalDatetimeString(ts: number | null): string {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const form = useForm({
    id: '',
    name: '',
    type: 'concert',
    status: 'draft',
    starts_at: '',
    ends_at: '',
    venue_name: '',
    city: '',

    description: '',
    organizer_name: '',
});

function openCreate() {
    form.reset();
    form.type = 'concert';
    form.status = 'draft';
    isEditing.value = false;
    dialogOpen.value = true;
}

function openEdit(event: EventRow) {
    form.id = event.id;
    form.name = event.name;
    form.type = event.type;
    form.status = event.status;
    form.starts_at = toLocalDatetimeString(event.starts_at);
    form.ends_at = toLocalDatetimeString(event.ends_at);
    form.venue_name = event.venue;
    form.city = event.city;
    form.description = event.description;
    form.organizer_name = event.organizer_name;
    isEditing.value = true;
    dialogOpen.value = true;
}

function submitForm() {
    if (isEditing.value) {
        form.put(`/events/${form.id}`, {
            onSuccess: () => {
                dialogOpen.value = false;
                toast.success('Event updated.');
            },
        });
    } else {
        form.post('/events', {
            onSuccess: () => {
                dialogOpen.value = false;
                toast.success('Event created.');
            },
        });
    }
}

function confirmDelete(event: EventRow) {
    deleteTarget.value = event;
    deleteDialogOpen.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(`/events/${deleteTarget.value.id}`, {
        onSuccess: () => {
            deleteDialogOpen.value = false;
            toast.success('Event deleted.');
        },
    });
}

// ─── Formatting helpers ───────────────────────────────────────────────────────
function formatDate(ts: number | null): string {
    if (!ts) return '—';
    return new Date(ts * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

const statusVariant = (s: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (s === 'published') return 'default';
    if (s === 'cancelled') return 'destructive';
    if (s === 'sold_out') return 'secondary';
    return 'outline';
};

const pages = computed(() => {
    const { current_page, last_page } = props.events;
    const range: number[] = [];
    for (let p = Math.max(1, current_page - 2); p <= Math.min(last_page, current_page + 2); p++) {
        range.push(p);
    }
    return range;
});
</script>

<template>
    <Head title="Manage Events" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Manage Events</h1>
                <p class="text-sm text-muted-foreground">{{ events.total.toLocaleString() }} events total</p>
            </div>
            <Button @click="openCreate">+ New Event</Button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 p-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Search name</label>
                <Input v-model="search.search" placeholder="Event name…" class="h-9 w-52" @keydown.enter="applyFilters" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Status</label>
                <select v-model="search.status" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">All</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Type</label>
                <select v-model="search.type" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">All</option>
                    <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                </select>
            </div>
            <Button variant="secondary" class="h-9" @click="applyFilters">Filter</Button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Location</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="event in events.data"
                        :key="event.id"
                        class="border-b transition-colors last:border-0 hover:bg-muted/30"
                    >
                        <td class="max-w-xs px-4 py-3">
                            <span class="line-clamp-1 font-medium">{{ event.name }}</span>
                            <span v-if="event.venue" class="block truncate text-xs text-muted-foreground">{{ event.venue }}</span>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ event.type }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(event.status)">{{ event.status }}</Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ formatDate(event.starts_at) }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ event.location_name }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Button variant="ghost" size="sm" @click="openEdit(event)">Edit</Button>
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="confirmDelete(event)">Delete</Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="events.data.length === 0">
                        <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">No events found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="events.last_page > 1" class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">
                Page {{ events.current_page }} of {{ events.last_page }}
            </span>
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="events.current_page <= 1"
                    @click="goToPage(events.current_page - 1)"
                >←</Button>
                <Button
                    v-for="p in pages"
                    :key="p"
                    variant="outline"
                    size="sm"
                    :class="p === events.current_page ? 'bg-primary text-primary-foreground' : ''"
                    @click="goToPage(p)"
                >{{ p }}</Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="events.current_page >= events.last_page"
                    @click="goToPage(events.current_page + 1)"
                >→</Button>
            </div>
        </div>
    </div>

    <!-- Create / Edit dialog -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit Event' : 'New Event' }}</DialogTitle>
                <DialogDescription>Fill in the event details below.</DialogDescription>
            </DialogHeader>

            <form class="grid gap-5 py-2" @submit.prevent="submitForm">
                <!-- Row 1: Name -->
                <div class="grid gap-1.5">
                    <Label for="ev-name">Event name <span class="text-destructive">*</span></Label>
                    <Input id="ev-name" v-model="form.name" placeholder="Summer Jazz Festival" required />
                    <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <!-- Row 2: Type + Status -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="ev-type">Type <span class="text-destructive">*</span></Label>
                        <select id="ev-type" v-model="form.type" class="h-9 rounded-md border border-input bg-background px-3 text-sm" required>
                            <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <p v-if="form.errors.type" class="text-xs text-destructive">{{ form.errors.type }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ev-status">Status <span class="text-destructive">*</span></Label>
                        <select id="ev-status" v-model="form.status" class="h-9 rounded-md border border-input bg-background px-3 text-sm" required>
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <p v-if="form.errors.status" class="text-xs text-destructive">{{ form.errors.status }}</p>
                    </div>
                </div>

                <!-- Row 3: Start + End datetime -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="ev-starts">Start date & time <span class="text-destructive">*</span></Label>
                        <Input id="ev-starts" v-model="form.starts_at" type="datetime-local" required />
                        <p v-if="form.errors.starts_at" class="text-xs text-destructive">{{ form.errors.starts_at }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ev-ends">End date & time</Label>
                        <Input id="ev-ends" v-model="form.ends_at" type="datetime-local" />
                        <p v-if="form.errors.ends_at" class="text-xs text-destructive">{{ form.errors.ends_at }}</p>
                    </div>
                </div>

                <!-- Row 4: Venue + City -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="ev-venue">Venue <span class="text-destructive">*</span></Label>
                        <Input id="ev-venue" v-model="form.venue_name" placeholder="The Grand Hall" required />
                        <p v-if="form.errors.venue_name" class="text-xs text-destructive">{{ form.errors.venue_name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ev-city">City <span class="text-destructive">*</span></Label>
                        <select id="ev-city" v-model="form.city" class="h-9 rounded-md border border-input bg-background px-3 text-sm" required>
                            <option value="">Select a city…</option>
                            <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <p v-if="form.errors.city" class="text-xs text-destructive">{{ form.errors.city }}</p>
                    </div>
                </div>

                <!-- Row 5: Organizer -->
                <div class="grid gap-1.5">
                    <Label for="ev-org">Organizer name</Label>
                    <Input id="ev-org" v-model="form.organizer_name" placeholder="Acme Events" />
                </div>

                <!-- Row 7: Description -->
                <div class="grid gap-1.5">
                    <Label for="ev-desc">Description</Label>
                    <textarea
                        id="ev-desc"
                        v-model="form.description"
                        rows="3"
                        placeholder="Tell attendees what to expect…"
                        class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : (isEditing ? 'Save changes' : 'Create event') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete confirm dialog -->
    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete event?</DialogTitle>
                <DialogDescription>
                    <strong>{{ deleteTarget?.name }}</strong> will be permanently deleted along with all its attendees. This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
                <Button variant="destructive" @click="executeDelete">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
