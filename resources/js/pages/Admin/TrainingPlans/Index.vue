<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Clock3,
    Layers3,
    Plus,
    Search,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Course {
    id: number;
    title: string;
    code: string;
    duration_hours: number;
}
interface FormOption {
    id: number;
    title: string;
    teacher_id: number;
    classroom_id?: number | null;
    groups_count: number;
    start_date: string;
    end_date: string;
    course: Course;
}
interface Option {
    id: number;
    name: string;
    code?: string;
    capacity?: number;
}
interface Plan {
    id: number;
    title: string;
    status: string;
    course: Course;
    teacher: Option;
    enrollment_form?: FormOption | null;
    groups: any[];
    sessions_count: number;
    planned_hours: number;
}
const props = defineProps<{
    plans: {
        data: Plan[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    courses: Course[];
    forms: FormOption[];
    teachers: Option[];
    classrooms: Option[];
    filters: { search?: string; status?: string };
}>();
const modalOpen = ref(false);
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const form = useForm({
    source_type: 'form',
    enrollment_form_id: '',
    course_id: '',
    teacher_id: '',
    title: '',
    groups_count: 1,
    classroom_id: '',
    notes: '',
});
const selectedForm = computed(() =>
    props.forms.find((item) => item.id === Number(form.enrollment_form_id)),
);
const selectedCourse = computed(() =>
    props.courses.find((item) => item.id === Number(form.course_id)),
);
watch(
    () => form.enrollment_form_id,
    () => {
        if (selectedForm.value)
            form.title = `Planification — ${selectedForm.value.title}`;
    },
);
watch(
    () => form.course_id,
    () => {
        if (form.source_type === 'course' && selectedCourse.value)
            form.title = `Planification — ${selectedCourse.value.title}`;
    },
);
function openCreate() {
    form.reset();
    form.clearErrors();
    form.source_type = 'form';
    form.groups_count = 1;
    modalOpen.value = true;
}
function closeModal() {
    modalOpen.value = false;
    form.clearErrors();
}
function submit() {
    form.post('/admin/planifications', { onSuccess: closeModal });
}
function applyFilters() {
    router.get(
        '/admin/planifications',
        { search: search.value, status: status.value },
        { preserveState: true, replace: true },
    );
}
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
</script>

<template>
    <Head title="Planifications" /><AdminLayout
        ><main class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">Planifications</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Organisez les groupes, salles, formateurs et séances de
                        chaque formation.
                    </p>
                </div>
                <Button @click="openCreate"
                    ><Plus class="mr-2 size-4" />Nouvelle planification</Button
                >
            </header>
            <form
                class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-[1fr_180px_auto]"
                @submit.prevent="applyFilters"
            >
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                    /><Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Rechercher une planification…"
                    />
                </div>
                <select
                    v-model="status"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tous les statuts</option>
                    <option
                        v-for="(label, key) in statusLabels"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option></select
                ><Button variant="outline">Rechercher</Button>
            </form>
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="plan in plans.data"
                    :key="plan.id"
                    :href="`/admin/planifications/${plan.id}`"
                    class="group rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    ><div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="statusTone[plan.status]"
                                >{{ statusLabels[plan.status] }}</span
                            >
                            <h2
                                class="mt-3 truncate text-lg font-semibold group-hover:text-primary"
                            >
                                {{ plan.title }}
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ plan.course.title }} · {{ plan.course.code }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-primary/10 p-3 text-primary">
                            <CalendarDays class="size-5" />
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-3 gap-2">
                        <div class="rounded-lg bg-muted/50 p-3 text-center">
                            <Layers3
                                class="mx-auto size-4 text-muted-foreground"
                            />
                            <p class="mt-1 font-bold">
                                {{ plan.groups.length }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Groupes
                            </p>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3 text-center">
                            <CalendarDays
                                class="mx-auto size-4 text-muted-foreground"
                            />
                            <p class="mt-1 font-bold">
                                {{ plan.sessions_count }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Séances
                            </p>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3 text-center">
                            <Clock3
                                class="mx-auto size-4 text-muted-foreground"
                            />
                            <p class="mt-1 font-bold">
                                {{ plan.planned_hours }}h
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Planifiées
                            </p>
                        </div>
                    </div>
                    <div
                        class="mt-4 flex items-center justify-between border-t pt-4 text-sm"
                    >
                        <span
                            class="flex items-center gap-2 text-muted-foreground"
                            ><Users class="size-4" />{{
                                plan.teacher.name
                            }}</span
                        ><span class="font-medium text-primary"
                            >Configurer →</span
                        >
                    </div></Link
                >
                <div
                    v-if="!plans.data.length"
                    class="col-span-full rounded-2xl border border-dashed p-12 text-center text-muted-foreground"
                >
                    Aucune planification trouvée.
                </div>
            </section>
            <nav v-if="plans.links.length > 3" class="flex flex-wrap gap-1">
                <Link
                    v-for="link in plans.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-md border px-3 py-2 text-sm"
                    :class="{
                        'bg-primary text-primary-foreground': link.active,
                        'pointer-events-none opacity-40': !link.url,
                    }"
                    ><span v-html="link.label"
                /></Link>
            </nav>
        </main>
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="closeModal"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            Nouvelle planification
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Choisissez son origine pour préparer automatiquement
                            les groupes.
                        </p>
                    </div>
                    <Button size="icon" variant="ghost" @click="closeModal"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="rounded-xl border p-4 text-left"
                            :class="
                                form.source_type === 'form'
                                    ? 'border-primary bg-primary/5'
                                    : ''
                            "
                            @click="form.source_type = 'form'"
                        >
                            <p class="font-medium">Formulaire d’inscription</p>
                            <p class="text-xs text-muted-foreground">
                                Reprendre ses groupes et affectations
                            </p></button
                        ><button
                            type="button"
                            class="rounded-xl border p-4 text-left"
                            :class="
                                form.source_type === 'course'
                                    ? 'border-primary bg-primary/5'
                                    : ''
                            "
                            @click="form.source_type = 'course'"
                        >
                            <p class="font-medium">Formation seule</p>
                            <p class="text-xs text-muted-foreground">
                                Créer librement les groupes
                            </p>
                        </button>
                    </div>
                    <div v-if="form.source_type === 'form'">
                        <Label>Formulaire</Label
                        ><select
                            v-model="form.enrollment_form_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option
                                v-for="item in forms"
                                :key="item.id"
                                :value="String(item.id)"
                            >
                                {{ item.title }} — {{ item.course.title }} ({{
                                    item.groups_count
                                }}
                                groupes)
                            </option></select
                        ><InputError
                            :message="form.errors.enrollment_form_id"
                            class="mt-1"
                        />
                        <p
                            v-if="selectedForm"
                            class="mt-2 rounded-lg bg-muted p-3 text-xs"
                        >
                            Du {{ selectedForm.start_date }} au
                            {{ selectedForm.end_date }} ·
                            {{ selectedForm.course.duration_hours }} heures
                        </p>
                    </div>
                    <template v-else
                        ><div>
                            <Label>Formation</Label
                            ><select
                                v-model="form.course_id"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                required
                            >
                                <option value="" disabled>Sélectionner</option>
                                <option
                                    v-for="course in courses"
                                    :key="course.id"
                                    :value="String(course.id)"
                                >
                                    {{ course.title }} —
                                    {{ course.duration_hours }}h
                                </option></select
                            ><InputError
                                :message="form.errors.course_id"
                                class="mt-1"
                            />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Formateur principal</Label
                                ><select
                                    v-model="form.teacher_id"
                                    class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                    required
                                >
                                    <option value="" disabled>
                                        Sélectionner
                                    </option>
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
                                <Label>Nombre de groupes</Label
                                ><Input
                                    v-model="form.groups_count"
                                    class="mt-1"
                                    type="number"
                                    min="1"
                                    max="100"
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <Label>Salle initiale (facultative)</Label
                            ><select
                                v-model="form.classroom_id"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Aucune</option>
                                <option
                                    v-for="room in classrooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }} — {{ room.capacity }} places
                                </option>
                            </select>
                        </div></template
                    >
                    <div>
                        <Label>Titre</Label
                        ><Input
                            v-model="form.title"
                            class="mt-1"
                            required
                        /><InputError
                            :message="form.errors.title"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label>Notes</Label
                        ><textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModal"
                            >Annuler</Button
                        ><Button :disabled="form.processing"
                            >Créer et configurer</Button
                        >
                    </div>
                </form>
            </div>
        </div></AdminLayout
    >
</template>
