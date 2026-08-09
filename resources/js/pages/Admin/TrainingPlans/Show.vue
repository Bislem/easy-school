<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarPlus,
    Clock3,
    DoorOpen,
    Pencil,
    Plus,
    Settings,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Option {
    id: number;
    name: string;
    code?: string;
    capacity?: number;
}
interface Session {
    id: number;
    title: string;
    classroom_id: number;
    teacher_id: number;
    starts_at: string;
    ends_at: string;
    notes?: string | null;
    classroom: Option;
    teacher: Option;
}
interface Group {
    id: number;
    group_number: number;
    name: string;
    classroom_id?: number | null;
    capacity?: number | null;
    classroom?: Option | null;
    planned_hours: number;
    sessions: Session[];
}
interface Plan {
    id: number;
    title: string;
    status: string;
    teacher_id: number;
    notes?: string | null;
    course: { title: string; code: string; duration_hours: number };
    teacher: Option;
    enrollment_form?: {
        title: string;
        start_date: string;
        end_date: string;
    } | null;
    groups: Group[];
}
const props = defineProps<{
    plan: Plan;
    teachers: Option[];
    classrooms: Option[];
}>();
const groupModal = ref(false);
const editingGroup = ref<Group | null>(null);
const sessionModal = ref(false);
const sessionGroup = ref<Group | null>(null);
const editingSession = ref<Session | null>(null);
const settingsModal = ref(false);
const groupForm = useForm({ name: '', classroom_id: '', capacity: '' });
const sessionForm = useForm({
    title: '',
    classroom_id: '',
    teacher_id: '',
    starts_at: '',
    ends_at: '',
    notes: '',
});
const settingsForm = useForm({
    title: props.plan.title,
    teacher_id: String(props.plan.teacher_id),
    status: props.plan.status,
    notes: props.plan.notes ?? '',
});
const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    scheduled: 'Planifiée',
    in_progress: 'En cours',
    completed: 'Terminée',
    cancelled: 'Annulée',
};
const statusTone: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    scheduled: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-violet-100 text-violet-700',
    cancelled: 'bg-red-100 text-red-700',
};
function formatDate(value: string) {
    return new Date(value).toLocaleDateString('fr-FR', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
function formatTime(value: string) {
    return new Date(value).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    });
}
function inputDate(value: string) {
    const date = new Date(value);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
function openGroup(group?: Group) {
    editingGroup.value = group ?? null;
    groupForm.clearErrors();
    groupForm.name = group?.name ?? `Groupe ${props.plan.groups.length + 1}`;
    groupForm.classroom_id = group?.classroom_id
        ? String(group.classroom_id)
        : '';
    groupForm.capacity = group?.capacity ? String(group.capacity) : '';
    groupModal.value = true;
}
function submitGroup() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (groupModal.value = false),
    };
    if (editingGroup.value)
        groupForm.put(
            `/admin/planifications/${props.plan.id}/groupes/${editingGroup.value.id}`,
            options,
        );
    else
        groupForm.post(
            `/admin/planifications/${props.plan.id}/groupes`,
            options,
        );
}
function deleteGroup(group: Group) {
    if (window.confirm(`Supprimer ${group.name} et toutes ses séances ?`))
        router.delete(
            `/admin/planifications/${props.plan.id}/groupes/${group.id}`,
        );
}
function openSession(group: Group, session?: Session) {
    sessionGroup.value = group;
    editingSession.value = session ?? null;
    sessionForm.clearErrors();
    sessionForm.title = session?.title ?? props.plan.course.title;
    sessionForm.classroom_id = session?.classroom_id
        ? String(session.classroom_id)
        : group.classroom_id
          ? String(group.classroom_id)
          : '';
    sessionForm.teacher_id = String(
        session?.teacher_id ?? props.plan.teacher_id,
    );
    sessionForm.starts_at = session ? inputDate(session.starts_at) : '';
    sessionForm.ends_at = session ? inputDate(session.ends_at) : '';
    sessionForm.notes = session?.notes ?? '';
    sessionModal.value = true;
}
function submitSession() {
    if (!sessionGroup.value) return;
    const base = `/admin/planifications/${props.plan.id}/groupes/${sessionGroup.value.id}/seances`;
    const options = {
        preserveScroll: true,
        onSuccess: () => (sessionModal.value = false),
    };
    if (editingSession.value)
        sessionForm.put(`${base}/${editingSession.value.id}`, options);
    else sessionForm.post(base, options);
}
function deleteSession(group: Group, session: Session) {
    if (window.confirm(`Supprimer la séance « ${session.title} » ?`))
        router.delete(
            `/admin/planifications/${props.plan.id}/groupes/${group.id}/seances/${session.id}`,
        );
}
function submitSettings() {
    settingsForm.put(`/admin/planifications/${props.plan.id}`, {
        preserveScroll: true,
        onSuccess: () => (settingsModal.value = false),
    });
}
</script>

<template>
    <Head :title="plan.title" /><AdminLayout
        ><main class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <div>
                <Link
                    href="/admin/planifications"
                    class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                    ><ArrowLeft class="mr-2 size-4" />Retour aux
                    planifications</Link
                >
            </div>
            <header class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="statusTone[plan.status]"
                                >{{ statusLabels[plan.status] }}</span
                            ><span
                                v-if="plan.enrollment_form"
                                class="rounded-full bg-primary/10 px-2.5 py-1 text-xs text-primary"
                                >Liée à une inscription</span
                            ><span
                                v-else
                                class="rounded-full bg-muted px-2.5 py-1 text-xs"
                                >Formation libre</span
                            >
                        </div>
                        <h1 class="mt-3 text-2xl font-bold">
                            {{ plan.title }}
                        </h1>
                        <p class="mt-1 text-muted-foreground">
                            {{ plan.course.title }} · {{ plan.course.code }} ·
                            {{ plan.course.duration_hours }} heures par groupe
                        </p>
                        <p
                            v-if="plan.enrollment_form"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            {{ plan.enrollment_form.title }} · du
                            {{ plan.enrollment_form.start_date }} au
                            {{ plan.enrollment_form.end_date }}
                        </p>
                    </div>
                    <Button variant="outline" @click="settingsModal = true"
                        ><Settings class="mr-2 size-4" />Paramètres</Button
                    >
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-muted/50 p-4">
                        <Users class="size-5 text-primary" />
                        <p class="mt-2 text-2xl font-bold">
                            {{ plan.groups.length }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Groupes planifiés
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/50 p-4">
                        <CalendarPlus class="size-5 text-primary" />
                        <p class="mt-2 text-2xl font-bold">
                            {{
                                plan.groups.reduce(
                                    (total, group) =>
                                        total + group.sessions.length,
                                    0,
                                )
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Séances au total
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/50 p-4">
                        <Clock3 class="size-5 text-primary" />
                        <p class="mt-2 text-2xl font-bold">
                            {{ plan.course.duration_hours }}h
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Durée requise par groupe
                        </p>
                    </div>
                </div>
            </header>
            <div
                v-if="$page.props.errors.group"
                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            >
                {{ $page.props.errors.group }}
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Groupes et séances</h2>
                    <p class="text-sm text-muted-foreground">
                        Chaque groupe possède son propre calendrier.
                    </p>
                </div>
                <Button variant="outline" @click="openGroup()"
                    ><Plus class="mr-2 size-4" />Ajouter un groupe</Button
                >
            </div>
            <section class="space-y-5">
                <article
                    v-for="group in plan.groups"
                    :key="group.id"
                    class="overflow-hidden rounded-2xl border bg-card shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 border-b bg-muted/20 p-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-semibold">
                                    {{ group.name }}
                                </h3>
                                <span
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                    >Groupe {{ group.group_number }}</span
                                >
                            </div>
                            <div
                                class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground"
                            >
                                <span class="flex items-center gap-1.5"
                                    ><DoorOpen class="size-4" />{{
                                        group.classroom?.name ||
                                        'Aucune salle par défaut'
                                    }}</span
                                ><span class="flex items-center gap-1.5"
                                    ><Users class="size-4" />{{
                                        group.capacity || '—'
                                    }}
                                    places</span
                                ><span class="flex items-center gap-1.5"
                                    ><Clock3 class="size-4" />{{
                                        group.planned_hours
                                    }}h /
                                    {{ plan.course.duration_hours }}h</span
                                >
                            </div>
                            <div
                                class="mt-3 h-1.5 w-full max-w-sm rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="
                                        group.planned_hours >=
                                        plan.course.duration_hours
                                            ? 'bg-emerald-500'
                                            : 'bg-primary'
                                    "
                                    :style="{
                                        width: `${Math.min(100, (group.planned_hours / plan.course.duration_hours) * 100)}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="openGroup(group)"
                                ><Pencil class="mr-2 size-4" />Modifier</Button
                            ><Button size="sm" @click="openSession(group)"
                                ><CalendarPlus class="mr-2 size-4" />Ajouter une
                                séance</Button
                            ><Button
                                size="icon"
                                variant="ghost"
                                class="text-destructive"
                                @click="deleteGroup(group)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </div>
                    <div class="divide-y">
                        <div
                            v-for="session in group.sessions"
                            :key="session.id"
                            class="grid gap-3 p-4 transition hover:bg-muted/20 sm:grid-cols-[150px_1fr_auto] sm:items-center"
                        >
                            <div>
                                <p class="text-sm font-semibold capitalize">
                                    {{ formatDate(session.starts_at) }}
                                </p>
                                <p class="text-xs text-primary">
                                    {{ formatTime(session.starts_at) }} –
                                    {{ formatTime(session.ends_at) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    {{ session.title }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ session.classroom.name }} ·
                                    {{ session.teacher.name }}
                                </p>
                            </div>
                            <div class="flex justify-end">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    @click="openSession(group, session)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    size="icon"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="deleteSession(group, session)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                        </div>
                        <div
                            v-if="!group.sessions.length"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            Aucune séance planifiée pour ce groupe.
                        </div>
                    </div>
                </article>
            </section>
        </main>

        <div
            v-if="groupModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="groupModal = false"
        >
            <div
                class="w-full rounded-t-2xl bg-background p-5 sm:max-w-lg sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">
                        {{
                            editingGroup
                                ? 'Modifier le groupe'
                                : 'Ajouter un groupe'
                        }}
                    </h2>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="groupModal = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitGroup">
                    <div>
                        <Label>Nom du groupe</Label
                        ><Input
                            v-model="groupForm.name"
                            class="mt-1"
                            required
                        /><InputError
                            :message="groupForm.errors.name"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label>Salle par défaut</Label
                        ><select
                            v-model="groupForm.classroom_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Aucune salle</option>
                            <option
                                v-for="room in classrooms"
                                :key="room.id"
                                :value="String(room.id)"
                            >
                                {{ room.name }} — {{ room.capacity }} places
                            </option></select
                        ><InputError
                            :message="groupForm.errors.classroom_id"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label>Capacité du groupe</Label
                        ><Input
                            v-model="groupForm.capacity"
                            class="mt-1"
                            type="number"
                            min="1"
                        /><InputError
                            :message="groupForm.errors.capacity"
                            class="mt-1"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="groupModal = false"
                            >Annuler</Button
                        ><Button :disabled="groupForm.processing"
                            >Enregistrer</Button
                        >
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="sessionModal && sessionGroup"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="sessionModal = false"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editingSession
                                    ? 'Modifier la séance'
                                    : 'Planifier une séance'
                            }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ sessionGroup.name }} ·
                            {{ sessionGroup.planned_hours }}h déjà planifiées
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="sessionModal = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitSession">
                    <div>
                        <Label>Titre de la séance</Label
                        ><Input
                            v-model="sessionForm.title"
                            class="mt-1"
                            required
                        /><InputError
                            :message="sessionForm.errors.title"
                            class="mt-1"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Début</Label
                            ><Input
                                v-model="sessionForm.starts_at"
                                class="mt-1"
                                type="datetime-local"
                                required
                            /><InputError
                                :message="sessionForm.errors.starts_at"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Fin</Label
                            ><Input
                                v-model="sessionForm.ends_at"
                                class="mt-1"
                                type="datetime-local"
                                required
                            /><InputError
                                :message="sessionForm.errors.ends_at"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Salle</Label
                            ><select
                                v-model="sessionForm.classroom_id"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                required
                            >
                                <option value="" disabled>Sélectionner</option>
                                <option
                                    v-for="room in classrooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }} — {{ room.capacity }} places
                                </option></select
                            ><InputError
                                :message="sessionForm.errors.classroom_id"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Formateur</Label
                            ><select
                                v-model="sessionForm.teacher_id"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                required
                            >
                                <option
                                    v-for="teacher in teachers"
                                    :key="teacher.id"
                                    :value="String(teacher.id)"
                                >
                                    {{ teacher.name }}
                                </option></select
                            ><InputError
                                :message="sessionForm.errors.teacher_id"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label>Notes</Label
                        ><textarea
                            v-model="sessionForm.notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <div
                        class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800"
                    >
                        Les conflits de salle et de formateur sont vérifiés
                        automatiquement. La durée cumulée ne peut pas dépasser
                        {{ plan.course.duration_hours }} heures par groupe.
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="sessionModal = false"
                            >Annuler</Button
                        ><Button :disabled="sessionForm.processing"
                            >Enregistrer la séance</Button
                        >
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="settingsModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="settingsModal = false"
        >
            <div
                class="w-full rounded-t-2xl bg-background p-5 sm:max-w-lg sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">Paramètres</h2>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="settingsModal = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitSettings">
                    <div>
                        <Label>Titre</Label
                        ><Input
                            v-model="settingsForm.title"
                            class="mt-1"
                            required
                        />
                    </div>
                    <div>
                        <Label>Formateur principal</Label
                        ><select
                            v-model="settingsForm.teacher_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option
                                v-for="teacher in teachers"
                                :key="teacher.id"
                                :value="String(teacher.id)"
                            >
                                {{ teacher.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label>Statut</Label
                        ><select
                            v-model="settingsForm.status"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option
                                v-for="(label, key) in statusLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label>Notes</Label
                        ><textarea
                            v-model="settingsForm.notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="settingsModal = false"
                            >Annuler</Button
                        ><Button :disabled="settingsForm.processing"
                            >Enregistrer</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
