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
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CircleSlash2,
    Clock3,
    History as HistoryIcon,
    LockKeyhole,
    Save,
    Search,
    Users,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

defineOptions({ layout: AdminLayout });

type Row = {
    enrollment_id: number;
    first_name: string;
    last_name: string;
    registration_number?: string | null;
    grade: number | string | null;
    status: string;
    comment?: string | null;
};
type History = {
    id: number;
    event: string;
    description?: string;
    occurred_at: string;
    user?: { name: string };
};
type Assessment = {
    id: number;
    name: string;
    status: string;
    maximum_grade: number;
    weight: number;
    assessment_date?: string | null;
    subject?: { title: string };
    year?: { name: string };
    period?: { name: string };
    level?: { name: string };
    teacher?: { name: string };
    groups?: Array<{ name: string }>;
};

const props = defineProps<{
    assessment: Assessment;
    students: Row[];
    history: History[];
}>();
const page = usePage();
const rows = reactive(
    props.students.map((row) => ({
        ...row,
        grade: row.grade ?? '',
        comment: row.comment ?? '',
    })),
);
const selected = ref<number[]>([]);
const dirty = ref(false);
const saving = ref(false);
const correctionReason = ref('');
const showHistory = ref(false);
const search = ref('');
const bulkStatus = ref('not_graded');
const rowErrors = reactive<Record<number, string>>({});
const notice = ref<string | null>(null);
const confirmation = ref<{
    title: string;
    description: string;
    confirmLabel: string;
    action: () => void;
} | null>(null);
const reopenDialogOpen = ref(false);
const reopenReason = ref('');

const editable = computed(() =>
    ['open', 'completed'].includes(props.assessment.status),
);
const graded = computed(
    () => rows.filter((row) => row.status === 'graded').length,
);
const absent = computed(
    () =>
        rows.filter((row) =>
            ['absent', 'absent_with_zero'].includes(row.status),
        ).length,
);
const exempted = computed(
    () => rows.filter((row) => row.status === 'exempted').length,
);
const ungraded = computed(
    () => rows.filter((row) => row.status === 'not_graded').length,
);
const completedCount = computed(
    () => graded.value + absent.value + exempted.value,
);
const progress = computed(() =>
    rows.length ? Math.round((completedCount.value / rows.length) * 100) : 0,
);
const selectedCount = computed(() => selected.value.length);
const filteredRows = computed(() => {
    const term = search.value.toLocaleLowerCase('fr').trim();
    if (!term) return rows;
    return rows.filter((row) =>
        `${row.first_name} ${row.last_name} ${row.registration_number ?? ''}`
            .toLocaleLowerCase('fr')
            .includes(term),
    );
});
const serverErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    open: 'Ouverte',
    completed: 'Terminée',
    locked: 'Verrouillée',
};
const gradeStatusLabels: Record<string, string> = {
    not_graded: 'Non noté',
    graded: 'Noté',
    absent: 'Absent',
    exempted: 'Dispensé',
    absent_with_zero: 'Absent avec zéro',
};

function validate(row: Row) {
    if (row.status !== 'graded') {
        delete rowErrors[row.enrollment_id];
        return true;
    }
    const value = Number(row.grade);
    if (
        row.grade === '' ||
        row.grade === null ||
        Number.isNaN(value) ||
        value < 0 ||
        value > Number(props.assessment.maximum_grade)
    ) {
        rowErrors[row.enrollment_id] =
            `Saisissez une note comprise entre 0 et ${props.assessment.maximum_grade}.`;
        return false;
    }
    delete rowErrors[row.enrollment_id];
    return true;
}

function focus(index: number) {
    document.querySelector<HTMLInputElement>(`#grade-${index}`)?.focus();
}

function key(event: KeyboardEvent, index: number) {
    if (event.key === 'Enter' && event.shiftKey) {
        event.preventDefault();
        focus(Math.max(index - 1, 0));
    } else if (event.key === 'Enter' || event.key === 'ArrowDown') {
        event.preventDefault();
        focus(Math.min(index + 1, rows.length - 1));
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        focus(Math.max(index - 1, 0));
    }
}

function changed(row: Row) {
    dirty.value = true;
    validate(row);
}

function activateGrade(row: Row) {
    if (!editable.value || row.status === 'graded') return;
    row.status = 'graded';
    dirty.value = true;
    delete rowErrors[row.enrollment_id];
}

function setStatus(row: Row, status: string) {
    row.status = status;
    if (status !== 'graded') row.grade = null;
    changed(row);
}

function paste(event: ClipboardEvent, index: number) {
    const values =
        event.clipboardData
            ?.getData('text')
            .split(/\r?\n|\t/)
            .map((value) => value.trim())
            .filter(Boolean) ?? [];
    if (values.length < 2) return;
    event.preventDefault();
    values.forEach((value, offset) => {
        const row = rows[index + offset];
        if (row) {
            row.grade = value;
            row.status = 'graded';
            changed(row);
        }
    });
}

function toggleAll() {
    const visibleIds = filteredRows.value.map((row) => row.enrollment_id);
    const allSelected = visibleIds.every((id) => selected.value.includes(id));
    selected.value = allSelected
        ? selected.value.filter((id) => !visibleIds.includes(id))
        : [...new Set([...selected.value, ...visibleIds])];
}

function applyBulkStatus() {
    if (!selected.value.length) return;
    const status = bulkStatus.value;
    const label = gradeStatusLabels[status].toLocaleLowerCase('fr');
    const selectedRows = rows.filter((row) =>
        selected.value.includes(row.enrollment_id),
    );
    const rowsWithGrade = selectedRows.filter((row) => {
        const grade = Number(row.grade);

        return (
            row.grade !== '' &&
            row.grade !== null &&
            !Number.isNaN(grade) &&
            grade >= 0 &&
            grade <= Number(props.assessment.maximum_grade)
        );
    });
    if (status === 'graded' && !rowsWithGrade.length) {
        notice.value =
            'Saisissez d’abord une note individuelle pour au moins un élève sélectionné.';
        return;
    }
    confirmation.value = {
        title: 'Appliquer un statut groupé',
        description:
            status === 'graded'
                ? `Marquer comme noté(s) les ${rowsWithGrade.length} élève(s) sélectionné(s) ayant déjà une note saisie ?`
                : `Marquer ${selected.value.length} élève(s) sélectionné(s) comme « ${label} » ?`,
        confirmLabel: 'Appliquer',
        action: () => {
            (status === 'graded' ? rowsWithGrade : selectedRows).forEach(
                (row) => {
                    row.status = status;
                    if (status !== 'graded') row.grade = '';
                    changed(row);
                },
            );
        },
    };
}

function save(andComplete = false) {
    if (!editable.value || !rows.every(validate)) return;
    if (
        props.assessment.status === 'completed' &&
        correctionReason.value.trim().length < 10
    ) {
        notice.value =
            'Indiquez un motif de correction d’au moins 10 caractères.';
        return;
    }
    saving.value = true;
    router.post(
        `/admin/assessments/${props.assessment.id}/grades`,
        {
            grades: rows.map((row) => ({
                enrollment_id: row.enrollment_id,
                value: row.grade,
                status: row.status,
                comment: row.comment,
            })),
            correction_reason: correctionReason.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                dirty.value = false;
                correctionReason.value = '';
                if (andComplete) complete(false);
            },
            onFinish: () => (saving.value = false),
        },
    );
}

function lifecycle(action: 'open' | 'lock' | 'publish') {
    const message =
        action === 'open'
            ? 'Ouvrir cette évaluation pour la saisie des notes ?'
            : action === 'lock'
              ? 'Valider cette évaluation ? Les notes passeront en lecture seule.'
              : 'Publier maintenant les résultats validés aux parents ?';
    confirmation.value = {
        title:
            action === 'open'
                ? 'Ouvrir la saisie des notes'
                : action === 'lock'
                  ? 'Valider les notes'
                  : 'Publier aux parents',
        description: message,
        confirmLabel:
            action === 'open'
                ? 'Ouvrir'
                : action === 'lock'
                  ? 'Valider'
                  : 'Publier',
        action: () =>
            router.post(
                `/admin/assessments/${props.assessment.id}/${action}`,
                {},
                { preserveScroll: true },
            ),
    };
}

function complete(confirmAction = true) {
    const action = () =>
        router.post(
            `/admin/assessments/${props.assessment.id}/complete`,
            {},
            { preserveScroll: true },
        );
    if (!confirmAction) {
        action();
        return;
    }
    confirmation.value = {
        title: 'Terminer l’évaluation',
        description: 'Les notes manquantes resteront non notées.',
        confirmLabel: 'Terminer',
        action,
    };
}

function reopen() {
    reopenReason.value = '';
    reopenDialogOpen.value = true;
}

function submitReopen() {
    if (reopenReason.value.trim().length < 10) {
        notice.value =
            'Indiquez un motif de réouverture d’au moins 10 caractères.';
        return;
    }
    router.post(
        `/admin/assessments/${props.assessment.id}/reopen`,
        { reason: reopenReason.value },
        {
            preserveScroll: true,
            onSuccess: () => (reopenDialogOpen.value = false),
        },
    );
}

function confirmDialogAction() {
    const action = confirmation.value?.action;
    confirmation.value = null;
    action?.();
}

function formatDate(value?: string | null) {
    if (!value) return 'Date non renseignée';
    return new Intl.DateTimeFormat('fr-DZ', { dateStyle: 'medium' }).format(
        new Date(`${value}T00:00:00`),
    );
}

function beforeUnload(event: BeforeUnloadEvent) {
    if (dirty.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}

onMounted(() => window.addEventListener('beforeunload', beforeUnload));
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
    <Head :title="`${assessment.name} · Saisie des notes`" />
    <div class="space-y-5 p-4 pb-24 md:p-6 md:pb-24">
        <div
            class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start"
        >
            <div>
                <Link
                    href="/admin/assessments"
                    class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    ><ArrowLeft class="size-4" /> Évaluations</Link
                >
                <h1 class="mt-2 text-2xl font-bold">{{ assessment.name }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ assessment.subject?.title }} ·
                    {{ assessment.year?.name }} ·
                    {{ assessment.period?.name }} ·
                    {{ assessment.level?.name }} ·
                    {{
                        assessment.groups?.map((group) => group.name).join(', ')
                    }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ assessment.teacher?.name ?? 'Aucun enseignant affecté' }}
                    · {{ formatDate(assessment.assessment_date) }} · note sur
                    {{ assessment.maximum_grade }} · coefficient
                    {{ assessment.weight }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="assessment.status === 'draft'"
                    @click="lifecycle('open')"
                    >Ouvrir la saisie</Button
                >
                <Button
                    v-else-if="assessment.status === 'open'"
                    @click="complete()"
                    >Terminer</Button
                >
                <Button
                    v-else-if="assessment.status === 'completed'"
                    variant="secondary"
                    class="gap-2"
                    @click="lifecycle('lock')"
                    ><LockKeyhole class="size-4" /> Valider les notes</Button
                >
                <Button
                    v-else-if="
                        assessment.status === 'locked' &&
                        !assessment.published_at
                    "
                    class="gap-2"
                    @click="lifecycle('publish')"
                    >Publier aux parents</Button
                >
                <Button v-else variant="outline" @click="reopen"
                    >Rouvrir</Button
                >
                <Button
                    variant="outline"
                    class="gap-2"
                    @click="showHistory = !showHistory"
                    ><HistoryIcon class="size-4" />
                    {{
                        showHistory ? 'Masquer l’historique' : 'Historique'
                    }}</Button
                >
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>Progression</span
                    ><Users class="size-5 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-bold">
                    {{ completedCount }} / {{ rows.length }}
                </p>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary transition-all"
                        :style="{ width: `${progress}%` }"
                    />
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ progress }} % renseigné
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>Notés</span
                    ><CheckCircle2 class="size-5 text-emerald-600" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ graded }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>Absents</span
                    ><CircleSlash2 class="size-5 text-red-600" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ absent }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>Dispensés</span
                    ><CircleSlash2 class="size-5 text-blue-600" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ exempted }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>Non notés</span
                    ><Clock3 class="size-5 text-amber-600" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ ungraded }}</p>
                <span
                    class="mt-2 inline-block rounded-full bg-muted px-2 py-1 text-xs"
                    >{{
                        statusLabels[assessment.status] ?? assessment.status
                    }}</span
                >
            </div>
        </div>

        <div v-if="showHistory" class="rounded-xl border bg-card p-4 shadow-sm">
            <h2 class="mb-3 font-semibold">Historique de l’évaluation</h2>
            <ol class="space-y-2 text-sm">
                <li
                    v-for="item in history"
                    :key="item.id"
                    class="border-l-2 border-primary/40 pl-3"
                >
                    <span class="font-medium">{{
                        item.description || item.event
                    }}</span
                    ><span class="ml-2 text-xs text-muted-foreground"
                        >{{ item.user?.name ?? 'Système' }} ·
                        {{
                            new Date(item.occurred_at).toLocaleString('fr-DZ')
                        }}</span
                    >
                </li>
                <li v-if="!history.length" class="text-muted-foreground">
                    Aucun historique enregistré.
                </li>
            </ol>
        </div>

        <div
            v-if="editable"
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-sm lg:flex-row lg:items-center"
        >
            <div class="relative min-w-64 flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                /><Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Rechercher un élève ou un matricule…"
                />
            </div>
            <span class="text-sm text-muted-foreground"
                >{{ selectedCount }} sélectionné(s)</span
            ><select
                v-model="bulkStatus"
                class="h-9 rounded-md border bg-background px-3 text-sm"
                aria-label="Statut à appliquer aux élèves sélectionnés"
            >
                <option
                    v-for="(label, value) in gradeStatusLabels"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </option>
            </select>
            <Button
                size="sm"
                :disabled="!selectedCount"
                @click="applyBulkStatus"
                >Appliquer le statut</Button
            >
        </div>

        <div
            v-if="assessment.status === 'draft'"
            class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800"
        >
            Ouvrez l’évaluation pour commencer la saisie des notes.
        </div>
        <div
            v-if="assessment.status === 'locked'"
            class="rounded-xl border border-slate-300 bg-slate-50 p-4 text-sm text-slate-700"
        >
            Les notes sont validées et verrouillées.
            <span v-if="!assessment.published_at"
                >Vous pouvez maintenant les publier aux parents.</span
            >
            <span v-else>Les résultats sont publiés aux parents.</span>
        </div>
        <div
            v-if="assessment.status === 'completed'"
            class="rounded-xl border border-amber-300 bg-amber-50 p-4"
        >
            <label class="grid gap-1 text-sm font-medium"
                >Motif de correction
                <span class="font-normal text-amber-800"
                    >Obligatoire, au moins 10 caractères</span
                ><Input
                    v-model="correctionReason"
                    placeholder="Expliquez la raison de cette correction"
            /></label>
            <p
                v-if="serverErrors.correction_reason"
                class="mt-1 text-xs text-destructive"
            >
                {{ serverErrors.correction_reason }}
            </p>
        </div>

        <div
            v-if="
                Object.keys(serverErrors).some((key) =>
                    key.startsWith('grades.'),
                )
            "
            class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
        >
            Certaines notes n’ont pas pu être enregistrées. Vérifiez les valeurs
            signalées dans le tableau.
        </div>

        <div class="overflow-x-auto rounded-xl border bg-card shadow-sm">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b bg-muted/40 text-muted-foreground">
                    <tr>
                        <th class="p-3">
                            <input
                                type="checkbox"
                                :checked="
                                    filteredRows.length > 0 &&
                                    filteredRows.every((row) =>
                                        selected.includes(row.enrollment_id),
                                    )
                                "
                                :disabled="!editable"
                                aria-label="Sélectionner tous les élèves visibles"
                                @change="toggleAll"
                            />
                        </th>
                        <th class="p-3">N°</th>
                        <th class="p-3">Élève</th>
                        <th class="p-3">Matricule</th>
                        <th class="w-48 p-3">
                            Note / {{ assessment.maximum_grade }}
                        </th>
                        <th class="p-3">Statut</th>
                        <th class="p-3">Observation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in filteredRows"
                        :key="row.enrollment_id"
                        class="border-b last:border-0 hover:bg-muted/20"
                    >
                        <td class="p-3">
                            <input
                                v-model="selected"
                                :disabled="!editable"
                                type="checkbox"
                                :value="row.enrollment_id"
                                :aria-label="`Sélectionner ${row.first_name} ${row.last_name}`"
                            />
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ rows.indexOf(row) + 1 }}
                        </td>
                        <td class="p-3 font-medium">
                            {{ row.first_name }} {{ row.last_name }}
                        </td>
                        <td class="p-3">
                            {{ row.registration_number ?? '—' }}
                        </td>
                        <td class="p-3">
                            <Input
                                :id="`grade-${rows.indexOf(row)}`"
                                v-model="row.grade"
                                :disabled="!editable"
                                type="number"
                                step="0.01"
                                min="0"
                                :max="assessment.maximum_grade"
                                :class="
                                    rowErrors[row.enrollment_id]
                                        ? 'border-destructive'
                                        : ''
                                "
                                class="h-10 text-center text-base font-semibold"
                                @keydown="key($event, rows.indexOf(row))"
                                @paste="paste($event, rows.indexOf(row))"
                                @focus="activateGrade(row)"
                                @input="changed(row)"
                            /><span
                                v-if="rowErrors[row.enrollment_id]"
                                class="text-xs text-destructive"
                                >{{ rowErrors[row.enrollment_id] }}</span
                            >
                        </td>
                        <td class="p-3">
                            <select
                                :value="row.status"
                                :disabled="!editable"
                                class="h-10 rounded-md border bg-background px-3"
                                @change="
                                    setStatus(
                                        row,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option
                                    v-for="(label, value) in gradeStatusLabels"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </td>
                        <td class="p-3">
                            <Input
                                v-model="row.comment"
                                :disabled="!editable"
                                placeholder="Observation facultative"
                                @input="changed(row)"
                            />
                        </td>
                    </tr>
                    <tr v-if="!filteredRows.length">
                        <td
                            colspan="7"
                            class="p-10 text-center text-muted-foreground"
                        >
                            <Search class="mx-auto mb-3 size-8 opacity-50" />{{
                                rows.length
                                    ? 'Aucun élève ne correspond à la recherche.'
                                    : 'Aucune inscription scolaire admissible n’a été trouvée.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="fixed right-0 bottom-0 left-0 z-40 flex flex-wrap justify-end gap-3 border-t bg-background/95 px-4 py-3 shadow-[0_-8px_24px_-16px_rgb(0,0,0,0.45)] backdrop-blur md:left-64 md:px-6"
        >
            <span
                v-if="dirty"
                class="mr-auto self-center text-sm font-medium text-amber-600"
                >Modifications non enregistrées</span
            ><Button
                :disabled="!editable || !dirty || saving"
                variant="outline"
                class="gap-2"
                @click="save(false)"
                ><Save class="size-4" />
                {{
                    saving ? 'Enregistrement…' : 'Enregistrer les notes'
                }}</Button
            ><Button
                v-if="assessment.status === 'open'"
                :disabled="!editable || saving"
                @click="dirty ? save(true) : complete()"
                >{{
                    saving
                        ? 'Enregistrement…'
                        : dirty
                          ? 'Enregistrer et terminer'
                          : 'Terminer l’évaluation'
                }}</Button
            >
        </div>
    </div>

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
                <Button @click="confirmDialogAction">{{
                    confirmation?.confirmLabel
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="reopenDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Rouvrir l’évaluation</DialogTitle>
                <DialogDescription>
                    Indiquez le motif de la réouverture. Il sera conservé dans
                    l’historique.
                </DialogDescription>
            </DialogHeader>
            <textarea
                v-model="reopenReason"
                rows="4"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                placeholder="Motif de réouverture (au moins 10 caractères)"
            />
            <DialogFooter>
                <Button variant="outline" @click="reopenDialogOpen = false"
                    >Annuler</Button
                >
                <Button @click="submitReopen">Rouvrir</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="Boolean(notice)"
        @update:open="(open) => !open && (notice = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Information requise</DialogTitle>
                <DialogDescription>{{ notice }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button @click="notice = null">Compris</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
