<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarRange, Eye, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

type Period = { id?: number; name: string; starts_on: string; ends_on: string };
type CalendarEvent = {
    id: number;
    name: string;
    type: 'winter_break' | 'spring_break' | 'closure';
    starts_on: string;
    ends_on: string;
    applies_to: 'both' | 'teachers' | 'students';
    is_paid_for_teachers: boolean;
    notes?: string;
};
type Year = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: string;
    notes?: string;
    periods: Period[];
    calendar_events: CalendarEvent[];
};
defineProps<{ years: Year[] }>();
const open = ref(false);
const editing = ref<Year | null>(null);
const viewing = ref<Year | null>(null);
const calendarOpen = ref(false);
const calendarYear = ref<Year | null>(null);
const editingCalendarEvent = ref<CalendarEvent | null>(null);
const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    notes: '',
    periods: [] as Array<{
        name: string;
        start_date: string;
        end_date: string;
    }>,
});
const labels: Record<string, string> = {
    draft: 'BROUILLON',
    active: 'ACTIVE',
    closed: 'CLÔTURÉE',
    archived: 'ARCHIVÉE',
};
const audienceLabels: Record<string, string> = {
    both: 'Enseignants et élèves',
    teachers: 'Enseignants',
    students: 'Élèves',
};
const calendarForm = useForm({
    name: '',
    type: 'winter_break' as CalendarEvent['type'],
    starts_on: '',
    ends_on: '',
    applies_to: 'both' as CalendarEvent['applies_to'],
    is_paid_for_teachers: true,
    notes: '',
});
function create() {
    editing.value = null;
    form.reset();
    form.periods = [];
    form.clearErrors();
    open.value = true;
}
function edit(year: Year) {
    editing.value = year;
    Object.assign(form, {
        name: year.name,
        start_date: year.start_date,
        end_date: year.end_date,
        notes: year.notes || '',
        periods: year.periods.map((p) => ({
            name: p.name,
            start_date: p.starts_on,
            end_date: p.ends_on,
        })),
    });
    form.clearErrors();
    open.value = true;
}
function submit() {
    if (form.processing) return;

    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };
    editing.value
        ? form.put(`/admin/academic-years/${editing.value.id}`, options)
        : form.post('/admin/academic-years', options);
}
function action(year: Year, operation: string) {
    if (confirm(`Confirmer l’action « ${operation} » pour ${year.name} ?`))
        router.patch(
            `/admin/academic-years/${year.id}/${operation}`,
            {},
            { preserveScroll: true },
        );
}
function addCalendarEvent(year: Year) {
    calendarYear.value = year;
    editingCalendarEvent.value = null;
    calendarForm.reset();
    Object.assign(calendarForm, {
        type: 'winter_break',
        applies_to: 'both',
        is_paid_for_teachers: true,
    });
    calendarForm.clearErrors();
    calendarOpen.value = true;
}
function editCalendarEvent(year: Year, event: CalendarEvent) {
    calendarYear.value = year;
    editingCalendarEvent.value = event;
    Object.assign(calendarForm, {
        name: event.name,
        type: event.type,
        starts_on: event.starts_on,
        ends_on: event.ends_on,
        applies_to: event.applies_to,
        is_paid_for_teachers: event.is_paid_for_teachers,
        notes: event.notes || '',
    });
    calendarForm.clearErrors();
    calendarOpen.value = true;
}
function submitCalendarEvent() {
    if (!calendarYear.value || calendarForm.processing) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => (calendarOpen.value = false),
    };
    const base = `/admin/academic-years/${calendarYear.value.id}/calendar-events`;
    editingCalendarEvent.value
        ? calendarForm.put(`${base}/${editingCalendarEvent.value.id}`, options)
        : calendarForm.post(base, options);
}
function deleteCalendarEvent(year: Year, event: CalendarEvent) {
    if (confirm(`Supprimer « ${event.name} » ?`))
        router.delete(
            `/admin/academic-years/${year.id}/calendar-events/${event.id}`,
            { preserveScroll: true },
        );
}
</script>

<template>
    <Head title="Années scolaires" />
    <AdminLayout
        ><main class="flex-1 space-y-6 bg-slate-50/60 p-4 sm:p-6 lg:p-8">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <span class="rounded-xl bg-blue-600 p-2 text-white"
                            ><CalendarRange class="size-5" /></span
                        >Années scolaires
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Cycle annuel, trimestres et historique académique de
                        l’école privée.
                    </p>
                </div>
                <Button @click="create"
                    ><Plus class="mr-2 size-4" />Nouvelle année</Button
                >
            </header>
            <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="year in years"
                    :key="year.id"
                    class="rounded-2xl border bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-xl font-semibold">
                                {{ year.name }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                {{ year.start_date }} → {{ year.end_date }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="
                                year.status === 'active'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-slate-100 text-slate-600'
                            "
                            >{{ labels[year.status] }}</span
                        >
                    </div>
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="period in year.periods"
                            :key="period.id"
                            class="flex justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs"
                        >
                            <b>{{ period.name }}</b
                            ><span
                                >{{ period.starts_on }} →
                                {{ period.ends_on }}</span
                            >
                        </div>
                    </div>
                    <div class="mt-4 border-t pt-4">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">
                                Vacances et jours non travaillés
                            </h3>
                            <Button
                                v-if="
                                    year.status === 'draft' ||
                                    year.status === 'active'
                                "
                                size="sm"
                                variant="outline"
                                @click="addCalendarEvent(year)"
                                ><Plus class="mr-1 size-3.5" />Ajouter</Button
                            >
                        </div>
                        <div
                            v-for="event in year.calendar_events"
                            :key="event.id"
                            class="mb-2 rounded-lg bg-amber-50 p-3 text-xs"
                        >
                            <div class="flex justify-between gap-2">
                                <div>
                                    <b>{{ event.name }}</b>
                                    <p class="text-slate-600">
                                        {{ event.starts_on }} →
                                        {{ event.ends_on }} ·
                                        {{ audienceLabels[event.applies_to] }}
                                    </p>
                                    <p
                                        v-if="event.applies_to !== 'students'"
                                        :class="
                                            event.is_paid_for_teachers
                                                ? 'text-emerald-700'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{
                                            event.is_paid_for_teachers
                                                ? 'Payé aux enseignants'
                                                : 'Non payé'
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="
                                        year.status === 'draft' ||
                                        year.status === 'active'
                                    "
                                    class="flex gap-1"
                                >
                                    <button
                                        type="button"
                                        @click="editCalendarEvent(year, event)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            deleteCalendarEvent(year, event)
                                        "
                                    >
                                        <Trash2 class="size-3.5 text-red-600" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p
                            v-if="!year.calendar_events.length"
                            class="text-xs text-slate-400"
                        >
                            Aucune période configurée.
                        </p>
                    </div>
                    <div
                        class="mt-5 flex flex-wrap justify-end gap-2 border-t pt-4"
                    >
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="viewing = year"
                            ><Eye class="mr-1 size-3.5" />Voir</Button
                        >
                        <Button
                            v-if="
                                year.status === 'draft' ||
                                year.status === 'active'
                            "
                            size="sm"
                            variant="outline"
                            @click="edit(year)"
                            ><Pencil class="mr-1 size-3.5" />Modifier</Button
                        ><Button
                            v-if="year.status === 'draft'"
                            size="sm"
                            @click="action(year, 'activate')"
                            >Activer</Button
                        ><Button
                            v-if="year.status === 'active'"
                            size="sm"
                            variant="destructive"
                            @click="action(year, 'close')"
                            >Clôturer</Button
                        ><Button
                            v-if="year.status === 'closed'"
                            size="sm"
                            variant="outline"
                            @click="action(year, 'archive')"
                            >Archiver</Button
                        >
                    </div>
                </article>
            </section>
            <div
                v-if="!years.length"
                class="rounded-2xl border border-dashed bg-white py-16 text-center text-slate-500"
            >
                Aucune année scolaire configurée.
            </div>
        </main>
        <div
            v-if="viewing"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"
            @click.self="viewing = null"
        >
            <section class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-primary">
                            ANNÉE SCOLAIRE
                        </p>
                        <h2 class="text-2xl font-semibold">
                            {{ viewing.name }}
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ viewing.start_date }} → {{ viewing.end_date }} ·
                            {{ labels[viewing.status] }}
                        </p>
                    </div>
                    <button type="button" @click="viewing = null"><X /></button>
                </div>
                <div class="mt-5 space-y-2">
                    <div
                        v-for="period in viewing.periods"
                        :key="period.id"
                        class="flex justify-between rounded-lg bg-slate-50 p-3 text-sm"
                    >
                        <b>{{ period.name }}</b>
                        <span
                            >{{ period.starts_on }} → {{ period.ends_on }}</span
                        >
                    </div>
                </div>
                <p v-if="viewing.notes" class="mt-5 text-sm text-slate-600">
                    {{ viewing.notes }}
                </p>
            </section>
        </div>
        <div
            v-if="open"
            class="fixed inset-0 z-50 grid place-items-center overflow-auto bg-slate-950/40 p-4"
            @click.self="open = false"
        >
            <form
                class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="submit"
            >
                <div class="mb-5 flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{ editing ? 'Modifier' : 'Créer' }} une année
                            scolaire
                        </h2>
                        <p class="text-sm text-slate-500">
                            Les trois trimestres restent modifiables avant
                            activation.
                        </p>
                    </div>
                    <button type="button" @click="open = false"><X /></button>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="field"
                        ><span>Nom</span
                        ><Input
                            v-model="form.name"
                            required
                            placeholder="2027-2028" /></label
                    ><label class="field"
                        ><span>Date début</span
                        ><Input
                            v-model="form.start_date"
                            required
                            type="date" /></label
                    ><label class="field"
                        ><span>Date fin</span
                        ><Input v-model="form.end_date" required type="date"
                    /></label>
                </div>
                <div v-if="editing" class="mt-5 space-y-3">
                    <div
                        v-for="(period, index) in form.periods"
                        :key="index"
                        class="grid gap-3 rounded-xl border p-3 sm:grid-cols-3"
                    >
                        <Input v-model="period.name" required /><Input
                            v-model="period.start_date"
                            type="date"
                            required
                        /><Input
                            v-model="period.end_date"
                            type="date"
                            required
                        />
                    </div>
                </div>
                <label class="field mt-4"
                    ><span>Notes</span
                    ><textarea v-model="form.notes" rows="3" />
                </label>
                <div
                    v-if="Object.keys(form.errors).length"
                    class="mt-4 rounded-lg bg-destructive/10 p-3"
                >
                    <InputError
                        v-for="(message, field) in form.errors"
                        :key="field"
                        :message="message"
                    />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Annuler</Button
                    ><Button type="submit" :disabled="form.processing">{{
                        form.processing ? 'Enregistrement…' : 'Enregistrer'
                    }}</Button>
                </div>
            </form>
        </div>
        <div
            v-if="calendarOpen"
            class="fixed inset-0 z-50 grid place-items-center overflow-auto bg-slate-950/40 p-4"
            @click.self="calendarOpen = false"
        >
            <form
                class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="submitCalendarEvent"
            >
                <div class="mb-5 flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{ editingCalendarEvent ? 'Modifier' : 'Ajouter' }}
                            une période
                        </h2>
                        <p class="text-sm text-slate-500">
                            Vacances ou fermeture sans cours ni travail.
                        </p>
                    </div>
                    <button type="button" @click="calendarOpen = false">
                        <X />
                    </button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="field sm:col-span-2"
                        ><span>Nom</span
                        ><Input
                            v-model="calendarForm.name"
                            required
                            placeholder="Vacances d'hiver"
                    /></label>
                    <label class="field"
                        ><span>Type</span
                        ><select v-model="calendarForm.type" class="control">
                            <option value="winter_break">
                                Vacances d'hiver
                            </option>
                            <option value="spring_break">
                                Vacances de printemps
                            </option>
                            <option value="closure">
                                Fermeture exceptionnelle
                            </option>
                        </select></label
                    >
                    <label class="field"
                        ><span>Concerne</span
                        ><select
                            v-model="calendarForm.applies_to"
                            class="control"
                        >
                            <option value="both">Enseignants et élèves</option>
                            <option value="teachers">Enseignants</option>
                            <option value="students">Élèves</option>
                        </select></label
                    >
                    <label class="field"
                        ><span>Date début</span
                        ><Input
                            v-model="calendarForm.starts_on"
                            type="date"
                            required
                    /></label>
                    <label class="field"
                        ><span>Date fin</span
                        ><Input
                            v-model="calendarForm.ends_on"
                            type="date"
                            required
                    /></label>
                </div>
                <label
                    v-if="calendarForm.applies_to !== 'students'"
                    class="mt-4 flex items-center gap-2 text-sm font-medium"
                    ><input
                        v-model="calendarForm.is_paid_for_teachers"
                        type="checkbox"
                    />Jours payés aux enseignants</label
                >
                <label class="field mt-4"
                    ><span>Notes</span
                    ><textarea v-model="calendarForm.notes" rows="3" />
                </label>
                <div
                    v-if="Object.keys(calendarForm.errors).length"
                    class="mt-4 rounded-lg bg-destructive/10 p-3"
                >
                    <InputError
                        v-for="(message, field) in calendarForm.errors"
                        :key="field"
                        :message="message"
                    />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="calendarOpen = false"
                        >Annuler</Button
                    ><Button
                        type="submit"
                        :disabled="calendarForm.processing"
                        >{{
                            calendarForm.processing
                                ? 'Enregistrement…'
                                : 'Enregistrer'
                        }}</Button
                    >
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
.field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
}
.field textarea {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.6rem;
    font-weight: 400;
}
</style>
