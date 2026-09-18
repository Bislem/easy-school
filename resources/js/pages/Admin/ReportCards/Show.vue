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
    AlertTriangle,
    ArrowLeft,
    Check,
    Eye,
    Lock,
    Pencil,
    Printer,
    RefreshCw,
    Save,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: AdminLayout });
type Subject = {
    id: number;
    subject_name: string;
    teacher_name?: string | null;
    average?: string | null;
    coefficient: string | number;
    class_average?: string | null;
    min_average?: string | null;
    max_average?: string | null;
    rank?: number | null;
    appreciation?: string | null;
};
type Card = {
    id: number;
    status: string;
    period_key: string;
    general_average?: string | null;
    class_average?: string | null;
    rank?: number | null;
    total_students?: number | null;
    absences_count?: number | null;
    late_count?: number | null;
    teacher_comment?: string | null;
    administration_comment?: string | null;
    student?: {
        first_name?: string;
        last_name?: string;
        registration_number?: string | null;
    };
    year?: { name: string };
    level?: { name: string; specialization?: string | null };
    group?: { name: string };
    subjects: Subject[];
};
type History = {
    id: number;
    event: string;
    description?: string | null;
    occurred_at: string;
    user?: { name: string };
};
const props = defineProps<{
    reportCard: Card;
    history: History[];
    readiness: {
        ready: boolean;
        blocking_errors: Array<{ message: string }>;
        warnings: Array<{ message: string }>;
    };
    previousCard?: {
        id: number;
        student?: { first_name: string; last_name: string };
    } | null;
    nextCard?: {
        id: number;
        student?: { first_name: string; last_name: string };
    } | null;
}>();
const tab = ref<'subjects' | 'general' | 'appreciations' | 'history'>(
    'subjects',
);
const editing = ref(false);
const showAll = ref(false);
const confirmation = ref<{
    title: string;
    description: string;
    label: string;
    action: string;
} | null>(null);
const reopenOpen = ref(false);
const reopenReason = ref('');
const subjects = computed(() =>
    showAll.value
        ? props.reportCard.subjects
        : props.reportCard.subjects.slice(0, 8),
);
const editable = computed(() => props.reportCard.status === 'draft');
const printable = computed(() =>
    ['validated', 'published', 'locked'].includes(props.reportCard.status),
);
const periodLabels: Record<string, string> = {
    trimester_1: '1er trimestre',
    trimester_2: '2e trimestre',
    trimester_3: '3e trimestre',
    annual: 'Bulletin annuel',
};
const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    validated: 'Validé',
    published: 'Publié',
    locked: 'Verrouillé',
};
const form = useForm({
    teacher_comment: props.reportCard.teacher_comment ?? '',
    administration_comment: props.reportCard.administration_comment ?? '',
    subjects: props.reportCard.subjects.map((subject) => ({
        id: subject.id,
        appreciation: subject.appreciation ?? '',
    })),
});
function save() {
    form.put(`/admin/report-cards/${props.reportCard.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}
function subjectForm(id: number) {
    return form.subjects.find((subject) => subject.id === id);
}
function ask(action: string) {
    const copy: Record<
        string,
        { title: string; description: string; label: string }
    > = {
        recalculate: {
            title: 'Recalculer le bulletin',
            description:
                'Les moyennes seront recalculées à partir des dernières notes enregistrées.',
            label: 'Recalculer',
        },
        validate: {
            title: 'Valider le bulletin',
            description:
                'Le bulletin ne pourra plus être modifié directement après validation.',
            label: 'Valider',
        },
        publish: {
            title: 'Publier le bulletin',
            description: 'Le bulletin deviendra visible aux familles.',
            label: 'Publier',
        },
        lock: {
            title: 'Verrouiller le bulletin',
            description:
                'Le bulletin sera définitivement protégé jusqu’à sa réouverture.',
            label: 'Verrouiller',
        },
    };
    confirmation.value = { ...copy[action], action };
}
function runAction() {
    const action = confirmation.value?.action;
    if (!action) return;
    confirmation.value = null;
    router.post(
        `/admin/report-cards/${props.reportCard.id}/${action}`,
        {},
        { preserveScroll: true },
    );
}
function reopen() {
    reopenReason.value = '';
    reopenOpen.value = true;
}
function submitReopen() {
    if (reopenReason.value.trim().length < 10) return;
    router.post(
        `/admin/report-cards/${props.reportCard.id}/reopen`,
        { reason: reopenReason.value },
        { preserveScroll: true, onSuccess: () => (reopenOpen.value = false) },
    );
}
function cancelEdit() {
    editing.value = false;
    form.reset();
}
function formatDate(value: string) {
    return new Date(value).toLocaleString('fr-DZ');
}
</script>

<template>
    <Head title="Détail du bulletin" />
    <div class="space-y-5 p-4 pb-24 md:p-6 md:pb-24">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Link
                href="/admin/report-cards"
                class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                ><ArrowLeft class="size-4" />Bulletins scolaires</Link
            >
            <div class="flex gap-2">
                <Button v-if="previousCard" as-child variant="outline" size="sm"
                    ><Link :href="`/admin/report-cards/${previousCard.id}`"
                        >← Élève précédent</Link
                    ></Button
                ><Button v-if="nextCard" as-child variant="outline" size="sm"
                    ><Link :href="`/admin/report-cards/${nextCard.id}`"
                        >Élève suivant →</Link
                    ></Button
                >
            </div>
        </div>

        <section
            class="grid overflow-hidden rounded-xl border bg-card shadow-sm md:grid-cols-[1.6fr_1fr_1fr_.8fr]"
        >
            <div
                class="flex items-center gap-4 border-b p-4 md:border-r md:border-b-0"
            >
                <div
                    class="flex size-20 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary"
                >
                    {{
                        `${reportCard.student?.first_name?.[0] ?? ''}${reportCard.student?.last_name?.[0] ?? ''}`
                    }}
                </div>
                <div>
                    <h1 class="text-lg font-bold">
                        {{ reportCard.student?.first_name }}
                        {{ reportCard.student?.last_name }}
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        Matricule :
                        <b>{{
                            reportCard.student?.registration_number ?? '—'
                        }}</b>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Niveau : {{ reportCard.level?.name
                        }}<span v-if="reportCard.level?.specialization">
                            · {{ reportCard.level.specialization }}</span
                        >
                        · Classe : {{ reportCard.group?.name ?? '—' }}
                    </p>
                </div>
            </div>
            <div class="border-b p-4 text-sm md:border-r md:border-b-0">
                <span class="text-muted-foreground">Année scolaire</span
                ><strong class="mt-2 block">{{ reportCard.year?.name }}</strong>
            </div>
            <div class="border-b p-4 text-sm md:border-r md:border-b-0">
                <span class="text-muted-foreground">Période</span
                ><strong class="mt-2 block">{{
                    periodLabels[reportCard.period_key] ?? reportCard.period_key
                }}</strong>
            </div>
            <div class="p-4">
                <span
                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                    >{{
                        statusLabels[reportCard.status] ?? reportCard.status
                    }}</span
                >
                <p
                    class="mt-3 text-xs"
                    :class="
                        readiness.ready ? 'text-emerald-600' : 'text-amber-600'
                    "
                >
                    {{
                        readiness.ready
                            ? 'Prêt pour validation'
                            : `${readiness.blocking_errors.length} anomalie(s) bloquante(s)`
                    }}
                </p>
            </div>
        </section>

        <div
            v-if="!readiness.ready && editable"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <b>Vérification requise :</b>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li
                    v-for="error in readiness.blocking_errors"
                    :key="error.message"
                >
                    {{ error.message }}
                </li>
            </ul>
        </div>
        <div
            v-if="readiness.warnings.length"
            class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
        >
            <b>Avertissements :</b>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li
                    v-for="warning in readiness.warnings"
                    :key="warning.message"
                >
                    {{ warning.message }}
                </li>
            </ul>
        </div>

        <div class="flex flex-wrap gap-1 border-b">
            <Button
                v-for="item in [
                    { key: 'subjects', label: 'Matières' },
                    { key: 'general', label: 'Informations générales' },
                    { key: 'appreciations', label: 'Appréciations' },
                    { key: 'history', label: 'Historique' },
                ]"
                :key="item.key"
                size="sm"
                :variant="tab === item.key ? 'default' : 'ghost'"
                @click="tab = item.key as typeof tab"
                >{{ item.label }}</Button
            >
        </div>

        <section
            v-if="tab === 'subjects'"
            class="overflow-x-auto rounded-xl border bg-card shadow-sm"
        >
            <table class="w-full min-w-[1020px] text-left text-sm">
                <thead
                    class="border-b bg-muted/40 text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="p-3">N°</th>
                        <th class="p-3">Matière</th>
                        <th class="p-3">Enseignant</th>
                        <th class="p-3">Moyenne</th>
                        <th class="p-3">Coef.</th>
                        <th class="p-3">Points pondérés</th>
                        <th class="p-3">Moy. classe</th>
                        <th class="p-3">Min.</th>
                        <th class="p-3">Max.</th>
                        <th class="p-3">Rang</th>
                        <th class="p-3">Appréciation</th>
                        <th class="p-3">État</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(subject, index) in subjects"
                        :key="subject.id"
                        class="border-b last:border-0"
                    >
                        <td class="p-3">{{ index + 1 }}</td>
                        <td class="p-3 font-semibold text-primary">
                            {{ subject.subject_name }}
                        </td>
                        <td class="p-3">{{ subject.teacher_name ?? '—' }}</td>
                        <td class="p-3">
                            <span
                                class="rounded bg-emerald-50 px-2 py-1 font-bold text-emerald-700"
                                >{{ subject.average ?? '—' }}</span
                            >
                        </td>
                        <td class="p-3">{{ subject.coefficient }}</td>
                        <td class="p-3">
                            {{
                                subject.average !== null &&
                                subject.average !== undefined
                                    ? (
                                          Number(subject.average) *
                                          Number(subject.coefficient)
                                      ).toFixed(2)
                                    : '—'
                            }}
                        </td>
                        <td class="p-3">{{ subject.class_average ?? '—' }}</td>
                        <td class="p-3">{{ subject.min_average ?? '—' }}</td>
                        <td class="p-3">{{ subject.max_average ?? '—' }}</td>
                        <td class="p-3">{{ subject.rank ?? '—' }}</td>
                        <td class="p-3">
                            <Input
                                v-if="editing"
                                v-model="subjectForm(subject.id)!.appreciation"
                                class="min-w-52"
                                placeholder="Appréciation de la matière"
                            /><span v-else>{{
                                subject.appreciation ?? '—'
                            }}</span>
                        </td>
                        <td class="p-3">
                            <Check
                                v-if="subject.average !== null"
                                class="size-4 text-emerald-600"
                            /><AlertTriangle
                                v-else
                                class="size-4 text-amber-500"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
            <Button
                v-if="!showAll && reportCard.subjects.length > 8"
                variant="ghost"
                class="mx-auto my-2 flex"
                @click="showAll = true"
                >Afficher toutes les matières</Button
            >
        </section>

        <section v-if="tab === 'general'" class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Assiduité</h2>
                <p class="mt-3 text-sm">
                    Absences : <b>{{ reportCard.absences_count ?? 0 }}</b> ·
                    Retards : <b>{{ reportCard.late_count ?? 0 }}</b>
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">État du calcul</h2>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        readiness.ready
                            ? 'Toutes les règles de validation sont respectées.'
                            : 'Ce bulletin nécessite encore une vérification académique.'
                    }}
                </p>
            </div>
        </section>
        <section
            v-if="tab === 'appreciations'"
            class="grid gap-4 md:grid-cols-2"
        >
            <div class="rounded-xl border bg-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <b>Appréciation du professeur principal</b
                    ><Pencil
                        v-if="editable"
                        class="size-4 text-muted-foreground"
                    />
                </div>
                <textarea
                    v-if="editing"
                    v-model="form.teacher_comment"
                    class="min-h-32 w-full rounded-md border bg-background p-3 text-sm"
                    placeholder="Appréciation générale"
                />
                <p v-else class="text-sm text-muted-foreground">
                    {{
                        reportCard.teacher_comment ??
                        'Aucune appréciation enregistrée.'
                    }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <b>Appréciation de l’administration</b
                    ><Pencil
                        v-if="editable"
                        class="size-4 text-muted-foreground"
                    />
                </div>
                <textarea
                    v-if="editing"
                    v-model="form.administration_comment"
                    class="min-h-32 w-full rounded-md border bg-background p-3 text-sm"
                    placeholder="Commentaire de l’administration"
                />
                <p v-else class="text-sm text-muted-foreground">
                    {{
                        reportCard.administration_comment ??
                        'Aucune appréciation enregistrée.'
                    }}
                </p>
            </div>
        </section>
        <section v-if="tab === 'history'" class="rounded-xl border bg-card p-4">
            <p v-if="!history.length" class="text-sm text-muted-foreground">
                Aucun événement enregistré.
            </p>
            <div
                v-for="item in history"
                :key="item.id"
                class="border-b py-3 text-sm last:border-0"
            >
                <b>{{ formatDate(item.occurred_at) }}</b> —
                {{ item.description ?? item.event }} —
                {{ item.user?.name ?? 'Système' }}
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div
                v-for="item in [
                    {
                        label: 'Moyenne générale',
                        value: `${reportCard.general_average ?? '—'} / 20`,
                    },
                    {
                        label: 'Moyenne de la classe',
                        value: `${reportCard.class_average ?? '—'} / 20`,
                    },
                    {
                        label: 'Rang',
                        value: `${reportCard.rank ?? '—'} / ${reportCard.total_students ?? '—'}`,
                    },
                    {
                        label: 'Élèves classés',
                        value: reportCard.total_students ?? '—',
                    },
                    {
                        label: 'Absences',
                        value: reportCard.absences_count ?? 0,
                    },
                    { label: 'Retards', value: reportCard.late_count ?? 0 },
                ]"
                :key="item.label"
                class="rounded-xl border bg-card p-4 shadow-sm"
            >
                <div class="text-xs text-muted-foreground">
                    {{ item.label }}
                </div>
                <strong class="mt-2 block text-lg">{{ item.value }}</strong>
            </div>
        </section>

        <footer
            class="fixed right-0 bottom-0 left-0 z-40 flex flex-wrap gap-2 border-t bg-background/95 px-4 py-3 shadow-[0_-8px_24px_-16px_rgb(0,0,0,0.45)] backdrop-blur md:left-64 md:px-6"
        >
            <Button
                v-if="editable"
                variant="outline"
                :disabled="form.processing"
                @click="editing ? save() : (editing = true)"
                ><Save class="mr-1 size-4" />{{
                    editing
                        ? 'Enregistrer les appréciations'
                        : 'Modifier les appréciations'
                }}</Button
            ><Button v-if="editing" variant="ghost" @click="cancelEdit"
                >Annuler</Button
            ><Button
                v-if="editable"
                variant="outline"
                @click="ask('recalculate')"
                ><RefreshCw class="mr-1 size-4" />Recalculer</Button
            ><Button
                v-if="editable && readiness.ready"
                class="bg-emerald-600 hover:bg-emerald-700"
                @click="ask('validate')"
                ><ShieldCheck class="mr-1 size-4" />Valider</Button
            ><Button
                v-if="reportCard.status === 'validated'"
                @click="ask('publish')"
                >Publier</Button
            ><Button
                v-if="['validated', 'published'].includes(reportCard.status)"
                variant="outline"
                @click="ask('lock')"
                ><Lock class="mr-1 size-4" />Verrouiller</Button
            ><Button
                v-if="
                    ['validated', 'published', 'locked'].includes(
                        reportCard.status,
                    )
                "
                variant="outline"
                @click="reopen"
                >Rouvrir</Button
            ><span class="ml-auto flex gap-2"
                ><Button v-if="printable" as-child variant="outline"
                    ><a
                        :href="`/admin/report-cards/${reportCard.id}/print?inline=1`"
                        target="_blank"
                        ><Eye class="mr-1 size-4" />Aperçu</a
                    ></Button
                ><Button v-if="printable" as-child variant="outline"
                    ><a :href="`/admin/report-cards/${reportCard.id}/print`"
                        ><Printer class="mr-1 size-4" />Télécharger le PDF</a
                    ></Button
                ></span
            >
        </footer>
    </div>

    <Dialog
        :open="Boolean(confirmation)"
        @update:open="(open) => !open && (confirmation = null)"
        ><DialogContent
            ><DialogHeader
                ><DialogTitle>{{ confirmation?.title }}</DialogTitle
                ><DialogDescription>{{
                    confirmation?.description
                }}</DialogDescription></DialogHeader
            ><DialogFooter
                ><Button variant="outline" @click="confirmation = null"
                    >Annuler</Button
                ><Button @click="runAction">{{
                    confirmation?.label
                }}</Button></DialogFooter
            ></DialogContent
        ></Dialog
    >
    <Dialog v-model:open="reopenOpen"
        ><DialogContent
            ><DialogHeader
                ><DialogTitle>Rouvrir le bulletin</DialogTitle
                ><DialogDescription
                    >Indiquez le motif de la réouverture. Il sera conservé dans
                    l’historique.</DialogDescription
                ></DialogHeader
            ><textarea
                v-model="reopenReason"
                rows="4"
                class="rounded-md border bg-background p-3 text-sm"
                placeholder="Motif de réouverture (au moins 10 caractères)"
            />
            <p
                v-if="reopenReason && reopenReason.trim().length < 10"
                class="text-xs text-destructive"
            >
                Le motif doit contenir au moins 10 caractères.
            </p>
            <DialogFooter
                ><Button variant="outline" @click="reopenOpen = false"
                    >Annuler</Button
                ><Button
                    :disabled="reopenReason.trim().length < 10"
                    @click="submitReopen"
                    >Rouvrir</Button
                ></DialogFooter
            ></DialogContent
        ></Dialog
    >
</template>
