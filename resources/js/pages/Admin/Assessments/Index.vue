<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardCheck,
    Filter,
    LockKeyhole,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

defineOptions({ layout: AdminLayout });

type Option = {
    id: number;
    name: string;
    school_level_id?: number;
    academic_year_id?: number;
};
type AssessmentRow = {
    id: number;
    academic_year_id: number;
    academic_period_id: number;
    school_level_id: number;
    subject_id: number;
    teacher_id?: number | null;
    name: string;
    assessment_type: string;
    assessment_date: string | null;
    maximum_grade: number;
    weight: number;
    status: string;
    published_at?: string | null;
    description?: string | null;
    subject?: { title: string };
    level?: { name: string };
    teacher?: { name: string };
    groups?: Array<{ id: number; name: string }>;
};
type AssessmentType = { label: string; weight: number };
type FilterState = {
    search: string | number;
    academic_year_id: string | number;
    academic_period_id: string | number;
    school_level_id: string | number;
    school_group_id: string | number;
    subject_id: string | number;
    teacher_id: string | number;
    assessment_type: string | number;
    status: string | number;
};
type Props = {
    filters: Record<string, string | number | null | undefined>;
    academicYear?: { id: number; name: string } | null;
    academicYears: Option[];
    periods: Option[];
    levels: Option[];
    groups: Option[];
    subjects: Array<{ id: number; title: string }>;
    subjectIdsByLevel: Record<number, number[]>;
    teachers: Option[];
    types: Record<string, AssessmentType>;
    assessments: {
        data: AssessmentRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
        from: number | null;
        to: number | null;
    };
};

const props = defineProps<Props>();
const filters = reactive<FilterState>({
    search: props.filters.search ?? '',
    academic_year_id:
        props.filters.academic_year_id ?? props.academicYear?.id ?? '',
    academic_period_id: props.filters.academic_period_id ?? '',
    school_level_id: props.filters.school_level_id ?? '',
    school_group_id: props.filters.school_group_id ?? '',
    subject_id: props.filters.subject_id ?? '',
    teacher_id: props.filters.teacher_id ?? '',
    assessment_type: props.filters.assessment_type ?? '',
    status: props.filters.status ?? '',
});
const createOpen = ref(false);
const editingId = ref<number | null>(null);
const confirmation = ref<{
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    action: () => void;
} | null>(null);
const reopenDialogOpen = ref(false);
const reopenReason = ref('');
const reopenTarget = ref<AssessmentRow | null>(null);
const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    open: 'Ouverte',
    completed: 'Terminée',
    locked: 'Verrouillée',
};
const statusClasses: Record<string, string> = {
    draft: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    open: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    locked: 'bg-slate-100 text-slate-700 ring-slate-600/20',
};
const dashboard = computed(() => ({
    total: props.assessments.total,
    open: props.assessments.data.filter((item) => item.status === 'open')
        .length,
    completed: props.assessments.data.filter(
        (item) => item.status === 'completed',
    ).length,
    locked: props.assessments.data.filter((item) => item.status === 'locked')
        .length,
}));

const defaultForm = () => ({
    academic_year_id:
        props.academicYear?.id ?? props.academicYears[0]?.id ?? '',
    academic_period_id: props.periods[0]?.id ?? '',
    school_level_id: '',
    group_ids: [] as number[],
    subject_id: '',
    teacher_id: '',
    name: '',
    assessment_type: 'test',
    assessment_date: new Date().toISOString().slice(0, 10),
    maximum_grade: 20,
    weight: props.types.test?.weight ?? 2,
    description: '',
});
const form = useForm(defaultForm());
const availablePeriods = computed(() =>
    props.periods.filter(
        (period) =>
            Number(period.academic_year_id) === Number(form.academic_year_id),
    ),
);
const availableGroups = computed(() => {
    return props.groups.filter(
        (group) =>
            Number(group.academic_year_id) === Number(form.academic_year_id) &&
            (!form.school_level_id ||
                Number(group.school_level_id) === Number(form.school_level_id)),
    );
});
const filterGroups = computed(() =>
    props.groups.filter(
        (group) =>
            (!filters.academic_year_id ||
                Number(group.academic_year_id) ===
                    Number(filters.academic_year_id)) &&
            (!filters.school_level_id ||
                Number(group.school_level_id) ===
                    Number(filters.school_level_id)),
    ),
);
const filterPeriods = computed(() =>
    props.periods.filter(
        (period) =>
            !filters.academic_year_id ||
            Number(period.academic_year_id) ===
                Number(filters.academic_year_id),
    ),
);
const availableSubjects = computed(() => {
    if (!form.school_level_id) return [];
    const ids = props.subjectIdsByLevel[Number(form.school_level_id)] ?? [];
    return props.subjects.filter((subject) => ids.includes(subject.id));
});

function applyFilters() {
    router.get('/admin/assessments', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    router.get('/admin/assessments', {}, { replace: true });
}

function changeFilterYear() {
    filters.academic_period_id = '';
    filters.school_group_id = '';
}

function openCreate() {
    editingId.value = null;
    form.defaults(defaultForm());
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function openEdit(assessment: AssessmentRow) {
    editingId.value = assessment.id;
    form.defaults({
        academic_year_id: assessment.academic_year_id,
        academic_period_id: assessment.academic_period_id,
        school_level_id: String(assessment.school_level_id),
        group_ids: assessment.groups?.map((group) => group.id) ?? [],
        subject_id: String(assessment.subject_id),
        teacher_id: assessment.teacher_id ? String(assessment.teacher_id) : '',
        name: assessment.name,
        assessment_type: assessment.assessment_type,
        assessment_date: assessment.assessment_date ?? '',
        maximum_grade: assessment.maximum_grade,
        weight: assessment.weight,
        description: assessment.description ?? '',
    });
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function closeCreate() {
    if (!form.processing) {
        createOpen.value = false;
        editingId.value = null;
    }
}

function updateTypeWeight() {
    const type = props.types[form.assessment_type];
    if (type) form.weight = type.weight;
}

function changeLevel() {
    form.group_ids = form.group_ids.filter((id) =>
        availableGroups.value.some((group) => group.id === Number(id)),
    );
    if (
        !availableSubjects.value.some(
            (subject) => subject.id === Number(form.subject_id),
        )
    )
        form.subject_id = '';
}

watch(
    () => form.academic_year_id,
    () => {
        if (
            !availablePeriods.value.some(
                (period) => period.id === Number(form.academic_period_id),
            )
        )
            form.academic_period_id = availablePeriods.value[0]?.id ?? '';
        changeLevel();
    },
);

function submit() {
    const request = editingId.value
        ? form.put(`/admin/assessments/${editingId.value}`, {
              preserveScroll: true,
              onSuccess: () => closeCreate(),
          })
        : form.post('/admin/assessments', {
              preserveScroll: true,
              onSuccess: () => {
                  createOpen.value = false;
              },
          });
    return request;
}

function transition(
    assessment: AssessmentRow,
    action: 'open' | 'complete' | 'lock' | 'publish',
) {
    const messages = {
        open: 'Ouvrir cette évaluation pour la saisie des notes ?',
        complete:
            'Terminer cette évaluation ? Les notes manquantes resteront visibles et ne seront pas converties en zéro.',
        lock: 'Valider cette évaluation ? Toute modification des notes sera bloquée jusqu’à sa réouverture.',
        publish:
            'Publier les résultats de cette évaluation aux parents ? Seules les notes enregistrées seront visibles.',
    };
    const titles = {
        open: 'Ouvrir la saisie des notes',
        complete: 'Terminer l’évaluation',
        lock: 'Valider les notes',
        publish: 'Publier les résultats',
    };
    const labels = {
        open: 'Ouvrir',
        complete: 'Terminer',
        lock: 'Valider',
        publish: 'Publier',
    };
    confirmation.value = {
        title: titles[action],
        description: messages[action],
        confirmLabel: labels[action],
        action: () =>
            router.post(
                `/admin/assessments/${assessment.id}/${action}`,
                {},
                { preserveScroll: true },
            ),
    };
}

function reopen(assessment: AssessmentRow) {
    reopenTarget.value = assessment;
    reopenReason.value = '';
    reopenDialogOpen.value = true;
}

function submitReopen() {
    if (!reopenTarget.value || reopenReason.value.trim().length < 10) return;
    router.post(
        `/admin/assessments/${reopenTarget.value.id}/reopen`,
        { reason: reopenReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                reopenDialogOpen.value = false;
                reopenTarget.value = null;
            },
        },
    );
}

function remove(assessment: AssessmentRow) {
    confirmation.value = {
        title: 'Supprimer l’évaluation',
        description: `Supprimer le brouillon vide « ${assessment.name} » ? Cette action est irréversible.`,
        confirmLabel: 'Supprimer',
        destructive: true,
        action: () =>
            router.delete(`/admin/assessments/${assessment.id}`, {
                preserveScroll: true,
            }),
    };
}

function confirmDialogAction() {
    const action = confirmation.value?.action;
    confirmation.value = null;
    action?.();
}
</script>

<template>
    <Head title="Évaluations" />

    <div class="space-y-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Évaluations et examens
                </h1>
                <p class="text-sm text-muted-foreground">
                    Planifiez les évaluations et suivez leur avancement par
                    classe.
                </p>
            </div>
            <Button type="button" class="gap-2" @click="openCreate"
                ><Plus class="size-4" /> Nouvelle évaluation</Button
            >
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground"
                        >Total des évaluations</span
                    ><ClipboardCheck class="size-5 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ dashboard.total }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground"
                        >Ouvertes sur cette page</span
                    ><CalendarDays class="size-5 text-blue-600" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ dashboard.open }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground"
                        >Terminées sur cette page</span
                    ><ClipboardCheck class="size-5 text-emerald-600" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ dashboard.completed }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground"
                        >Verrouillées sur cette page</span
                    ><LockKeyhole class="size-5 text-slate-600" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ dashboard.locked }}</p>
            </div>
        </div>

        <form
            class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4"
            @submit.prevent="applyFilters"
        >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground sm:col-span-2 lg:col-span-2"
                >Rechercher une évaluation
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    /><Input
                        v-model="filters.search"
                        class="h-10 pl-9"
                        placeholder="Intitulé, matière, classe, niveau ou enseignant…"
                    />
                </div>
            </label>
            <div class="hidden lg:block" />
            <div class="hidden lg:block" />
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Année scolaire<select
                    v-model="filters.academic_year_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                    @change="changeFilterYear"
                >
                    <option value="">Année scolaire</option>
                    <option
                        v-for="year in academicYears"
                        :key="year.id"
                        :value="year.id"
                    >
                        {{ year.name }}
                    </option>
                </select></label
            >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Période<select
                    v-model="filters.academic_period_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Toutes les périodes</option>
                    <option
                        v-for="period in filterPeriods"
                        :key="period.id"
                        :value="period.id"
                    >
                        {{ period.name }}
                    </option>
                </select></label
            >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Niveau<select
                    v-model="filters.school_level_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tous les niveaux</option>
                    <option
                        v-for="level in levels"
                        :key="level.id"
                        :value="level.id"
                    >
                        {{ level.name }}
                    </option>
                </select></label
            >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Classe<select
                    v-model="filters.school_group_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Toutes les classes</option>
                    <option
                        v-for="group in filterGroups"
                        :key="group.id"
                        :value="group.id"
                    >
                        {{ group.name }}
                    </option>
                </select></label
            >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Matière<select
                    v-model="filters.subject_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Toutes les matières</option>
                    <option
                        v-for="subject in subjects"
                        :key="subject.id"
                        :value="subject.id"
                    >
                        {{ subject.title }}
                    </option>
                </select></label
            >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Enseignant<select
                    v-model="filters.teacher_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tous les enseignants</option>
                    <option
                        v-for="teacher in teachers"
                        :key="teacher.id"
                        :value="teacher.id"
                    >
                        {{ teacher.name }}
                    </option>
                </select></label
            >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Statut<select
                    v-model="filters.status"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tous les statuts</option>
                    <option value="draft">Brouillon</option>
                    <option value="open">Ouverte</option>
                    <option value="completed">Terminée</option>
                    <option value="locked">Verrouillée</option>
                </select></label
            >
            <label
                class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                >Type d’évaluation<select
                    v-model="filters.assessment_type"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tous les types</option>
                    <option
                        v-for="(type, key) in types"
                        :key="key"
                        :value="key"
                    >
                        {{ type.label }}
                    </option>
                </select></label
            >
            <div class="flex gap-2">
                <Button type="submit" class="flex-1 gap-2"
                    ><Filter class="size-4" /> Filtrer</Button
                ><Button
                    type="button"
                    variant="outline"
                    class="gap-2"
                    @click="resetFilters"
                    ><RotateCcw class="size-4" /> Réinitialiser</Button
                >
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border bg-card">
            <table class="w-full min-w-[850px] text-sm">
                <thead
                    class="border-b bg-muted/40 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="p-3">Évaluation</th>
                        <th class="p-3">Matière</th>
                        <th class="p-3">Classe</th>
                        <th class="p-3">Date</th>
                        <th class="p-3">Barème</th>
                        <th class="p-3">Statut</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="assessment in assessments.data"
                        :key="assessment.id"
                        class="border-b last:border-0"
                    >
                        <td class="p-3">
                            <div class="font-medium">{{ assessment.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    types[assessment.assessment_type]?.label ??
                                    assessment.assessment_type
                                }}
                                · coefficient {{ assessment.weight }}
                            </div>
                        </td>
                        <td class="p-3">
                            {{ assessment.subject?.title ?? '—' }}
                        </td>
                        <td class="p-3">
                            {{
                                assessment.groups
                                    ?.map((group) => group.name)
                                    .join(', ') ||
                                assessment.level?.name ||
                                '—'
                            }}
                        </td>
                        <td class="p-3">
                            {{ assessment.assessment_date ?? '—' }}
                        </td>
                        <td class="p-3">/ {{ assessment.maximum_grade }}</td>
                        <td class="p-3">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                :class="statusClasses[assessment.status]"
                                >{{
                                    statusLabels[assessment.status] ??
                                    assessment.status
                                }}</span
                            >
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button as-child size="sm" variant="outline"
                                    ><Link
                                        :href="`/admin/assessments/${assessment.id}/grades`"
                                        >Notes</Link
                                    ></Button
                                ><Button
                                    v-if="assessment.status === 'draft'"
                                    size="sm"
                                    variant="outline"
                                    @click="openEdit(assessment)"
                                    >Modifier</Button
                                ><Button
                                    v-if="assessment.status === 'draft'"
                                    size="sm"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="remove(assessment)"
                                    >Supprimer</Button
                                ><Button
                                    v-if="assessment.status === 'draft'"
                                    size="sm"
                                    @click="transition(assessment, 'open')"
                                    >Ouvrir</Button
                                ><Button
                                    v-else-if="assessment.status === 'open'"
                                    size="sm"
                                    @click="transition(assessment, 'complete')"
                                    >Terminer</Button
                                ><Button
                                    v-else-if="
                                        assessment.status === 'completed'
                                    "
                                    size="sm"
                                    variant="secondary"
                                    @click="transition(assessment, 'lock')"
                                    >Valider les notes</Button
                                ><Button
                                    v-else-if="assessment.status === 'locked'"
                                    size="sm"
                                    variant="outline"
                                    @click="reopen(assessment)"
                                    >Rouvrir</Button
                                ><Button
                                    v-if="
                                        assessment.status === 'locked' &&
                                        !assessment.published_at
                                    "
                                    size="sm"
                                    @click="transition(assessment, 'publish')"
                                    >Publier aux parents</Button
                                ><span
                                    v-if="assessment.published_at"
                                    class="self-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                                    >Publié</span
                                >
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!assessments.data.length">
                        <td
                            colspan="7"
                            class="p-10 text-center text-muted-foreground"
                        >
                            <Search
                                class="mx-auto mb-3 size-8 opacity-50"
                            />Aucune évaluation ne correspond à ces filtres.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="assessments.links.length > 3"
            class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
        >
            <span
                >Résultats {{ assessments.from }} à {{ assessments.to }} sur
                {{ assessments.total }}</span
            >
            <div class="flex flex-wrap gap-1">
                <Link
                    v-for="link in assessments.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    class="rounded-md border px-3 py-1.5"
                    :class="[
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-card',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    v-html="
                        link.label
                            .replace('Previous', 'Précédent')
                            .replace('Next', 'Suivant')
                    "
                />
            </div>
        </div>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        editingId
                            ? 'Modifier l’évaluation'
                            : 'Nouvelle évaluation'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Configurez l’évaluation avant de saisir les notes.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Année scolaire<select
                            v-model="form.academic_year_id"
                            class="h-10 rounded-md border bg-background px-3"
                        >
                            <option value="">Sélectionner une année</option>
                            <option
                                v-for="year in academicYears"
                                :key="year.id"
                                :value="year.id"
                            >
                                {{ year.name }}
                            </option></select
                        ><span
                            v-if="form.errors.academic_year_id"
                            class="text-xs text-destructive"
                            >{{ form.errors.academic_year_id }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Période<select
                            v-model="form.academic_period_id"
                            class="h-10 rounded-md border bg-background px-3"
                        >
                            <option value="">Sélectionner une période</option>
                            <option
                                v-for="period in availablePeriods"
                                :key="period.id"
                                :value="period.id"
                            >
                                {{ period.name }}
                            </option></select
                        ><span
                            v-if="form.errors.academic_period_id"
                            class="text-xs text-destructive"
                            >{{ form.errors.academic_period_id }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Niveau<select
                            v-model="form.school_level_id"
                            class="h-10 rounded-md border bg-background px-3"
                            @change="changeLevel"
                        >
                            <option value="">Sélectionner un niveau</option>
                            <option
                                v-for="level in levels"
                                :key="level.id"
                                :value="level.id"
                            >
                                {{ level.name }}
                            </option></select
                        ><span
                            v-if="form.errors.school_level_id"
                            class="text-xs text-destructive"
                            >{{ form.errors.school_level_id }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Classes
                        <span class="font-normal text-muted-foreground"
                            >(une ou plusieurs)</span
                        ><select
                            v-model="form.group_ids"
                            multiple
                            class="h-24 rounded-md border bg-background px-3 py-2"
                        >
                            <option
                                v-for="group in availableGroups"
                                :key="group.id"
                                :value="group.id"
                            >
                                {{ group.name }}
                            </option></select
                        ><span
                            v-if="form.errors.group_ids"
                            class="text-xs text-destructive"
                            >{{ form.errors.group_ids }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Matière<select
                            v-model="form.subject_id"
                            :disabled="!form.school_level_id"
                            class="h-10 rounded-md border bg-background px-3"
                        >
                            <option value="">
                                {{
                                    form.school_level_id
                                        ? 'Sélectionner une matière'
                                        : 'Sélectionnez d’abord un niveau'
                                }}
                            </option>
                            <option
                                v-for="subject in availableSubjects"
                                :key="subject.id"
                                :value="subject.id"
                            >
                                {{ subject.title }}
                            </option></select
                        ><span
                            v-if="form.errors.subject_id"
                            class="text-xs text-destructive"
                            >{{ form.errors.subject_id }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Enseignant
                        <span class="font-normal text-muted-foreground"
                            >(facultatif)</span
                        ><select
                            v-model="form.teacher_id"
                            class="h-10 rounded-md border bg-background px-3"
                        >
                            <option value="">Aucun enseignant affecté</option>
                            <option
                                v-for="teacher in teachers"
                                :key="teacher.id"
                                :value="teacher.id"
                            >
                                {{ teacher.name }}
                            </option>
                        </select></label
                    >
                    <label
                        class="grid gap-1.5 text-sm font-medium sm:col-span-2"
                        >Intitulé de l’évaluation<Input
                            v-model="form.name"
                            placeholder="Ex. : Devoir de mathématiques n° 1"
                        /><span
                            v-if="form.errors.name"
                            class="text-xs text-destructive"
                            >{{ form.errors.name }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Type<select
                            v-model="form.assessment_type"
                            class="h-10 rounded-md border bg-background px-3"
                            @change="updateTypeWeight"
                        >
                            <option
                                v-for="(type, key) in types"
                                :key="key"
                                :value="key"
                            >
                                {{ type.label }}
                            </option></select
                        ><span
                            v-if="form.errors.assessment_type"
                            class="text-xs text-destructive"
                            >{{ form.errors.assessment_type }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Date<Input
                            v-model="form.assessment_date"
                            type="date"
                        /><span
                            v-if="form.errors.assessment_date"
                            class="text-xs text-destructive"
                            >{{ form.errors.assessment_date }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Note maximale<Input
                            v-model="form.maximum_grade"
                            type="number"
                            min="0.01"
                            step="0.01"
                        /><span
                            v-if="form.errors.maximum_grade"
                            class="text-xs text-destructive"
                            >{{ form.errors.maximum_grade }}</span
                        ></label
                    >
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Coefficient<Input
                            v-model="form.weight"
                            type="number"
                            min="0.01"
                            step="0.01"
                        /><span
                            v-if="form.errors.weight"
                            class="text-xs text-destructive"
                            >{{ form.errors.weight }}</span
                        ></label
                    >
                    <label
                        class="grid gap-1.5 text-sm font-medium sm:col-span-2"
                        >Description
                        <span class="font-normal text-muted-foreground"
                            >(facultatif)</span
                        ><textarea
                            v-model="form.description"
                            rows="3"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="Consignes ou contexte de l’évaluation"
                        />
                    </label>
                </div>
                <DialogFooter class="border-t pt-5">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="closeCreate"
                        >Annuler</Button
                    ><Button type="submit" :disabled="form.processing">{{
                        form.processing
                            ? 'Enregistrement…'
                            : editingId
                              ? 'Enregistrer les modifications'
                              : 'Créer l’évaluation'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="Boolean(confirmation)"
        @update:open="(open) => !open && (confirmation = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ confirmation?.title }}</DialogTitle>
                <DialogDescription>{{
                    confirmation?.description
                }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="confirmation = null"
                    >Annuler</Button
                >
                <Button
                    :variant="
                        confirmation?.destructive ? 'destructive' : 'default'
                    "
                    @click="confirmDialogAction"
                    >{{ confirmation?.confirmLabel }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="reopenDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Rouvrir l’évaluation</DialogTitle>
                <DialogDescription
                    >Le motif sera conservé dans l’historique de
                    l’évaluation.</DialogDescription
                >
            </DialogHeader>
            <textarea
                v-model="reopenReason"
                rows="4"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                placeholder="Motif de réouverture (au moins 10 caractères)"
            />
            <p
                v-if="
                    reopenReason.length > 0 && reopenReason.trim().length < 10
                "
                class="text-xs text-destructive"
            >
                Le motif doit contenir au moins 10 caractères.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="reopenDialogOpen = false"
                    >Annuler</Button
                >
                <Button
                    :disabled="reopenReason.trim().length < 10"
                    @click="submitReopen"
                    >Rouvrir</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
