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
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Eye,
    FileCheck2,
    Filter,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    Send,
    Users,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

defineOptions({ layout: AdminLayout });
type Subject = { average: number | string | null };
type Card = {
    id: number;
    status: string;
    generated_at?: string | null;
    general_average?: number | string | null;
    rank?: number | null;
    total_students?: number | null;
    absences_count?: number | null;
    source_data_changed?: boolean;
    subjects?: Subject[];
    student?: {
        first_name?: string;
        last_name?: string;
        registration_number?: string | null;
    };
};
type FilterState = {
    academic_year_id: string | number;
    period_key: string;
    cycle_id: string | number;
    specialization: string;
    level_id: string | number;
    group_id: string | number;
    search: string;
    status: string;
    average_min: string | number;
    average_max: string | number;
    problematic: boolean;
};
type Props = {
    filters: Record<string, any>;
    academicYear?: { id: number } | null;
    academicYears: { id: number; name: string }[];
    periods: Record<string, string>;
    cycles: { id: number; name: string }[];
    specializations: string[];
    levels: {
        id: number;
        name: string;
        specialization?: string | null;
        school_cycle_id: number;
    }[];
    groups: { id: number; name: string; school_level_id: number }[];
    cards: {
        data: Card[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
        from: number | null;
        to: number | null;
    };
    summary: Record<string, number>;
};
const props = defineProps<Props>();
const filters = reactive<FilterState>({
    academic_year_id:
        props.filters.academic_year_id ?? props.academicYear?.id ?? '',
    period_key: props.filters.period_key ?? 'trimester_1',
    cycle_id: props.filters.cycle_id ?? '',
    specialization: props.filters.specialization ?? '',
    level_id: props.filters.level_id ?? '',
    group_id: props.filters.group_id ?? '',
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    average_min: props.filters.average_min ?? '',
    average_max: props.filters.average_max ?? '',
    problematic: Boolean(props.filters.problematic),
});
const confirmation = ref<{
    title: string;
    description: string;
    label: string;
    url: string;
} | null>(null);
const filteredLevels = computed(() =>
    props.levels.filter(
        (level) =>
            (!filters.cycle_id ||
                level.school_cycle_id === Number(filters.cycle_id)) &&
            (!filters.specialization ||
                level.specialization === filters.specialization),
    ),
);
const filteredGroups = computed(() =>
    props.groups.filter(
        (group) =>
            !filters.level_id ||
            group.school_level_id === Number(filters.level_id),
    ),
);
const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    validated: 'Validé',
    published: 'Publié',
    locked: 'Verrouillé',
};
const statusClasses: Record<string, string> = {
    draft: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    validated: 'bg-violet-50 text-violet-700 ring-violet-600/20',
    published: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    locked: 'bg-slate-100 text-slate-700 ring-slate-600/20',
};
function apply() {
    router.get('/admin/report-cards', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function reset() {
    router.get('/admin/report-cards', {}, { replace: true });
}
function changeScope(scope: 'cycle' | 'specialization' | 'level') {
    if (scope !== 'level') {
        filters.level_id = '';
    }
    filters.group_id = '';
}
function ask(url: string, title: string, description: string, label: string) {
    if (!filters.group_id) return;
    confirmation.value = { url, title, description, label };
}
function runAction() {
    const item = confirmation.value;
    if (!item) return;
    confirmation.value = null;
    router.post(
        item.url,
        { group_id: filters.group_id, period_key: filters.period_key },
        { preserveScroll: true },
    );
}
function initials(card: Card) {
    return `${card.student?.first_name?.[0] ?? ''}${card.student?.last_name?.[0] ?? ''}`.toUpperCase();
}
function subjectProgress(card: Card) {
    return `${card.subjects?.filter((subject) => subject.average !== null).length ?? 0} / ${card.subjects?.length ?? 0}`;
}
function hasProblem(card: Card) {
    return (
        card.general_average === null ||
        card.general_average === undefined ||
        card.source_data_changed ||
        card.subjects?.some((subject) => subject.average === null)
    );
}
</script>

<template>
    <Head title="Bulletins scolaires" />
    <div class="space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Bulletins scolaires
            </h1>
            <p class="text-sm text-muted-foreground">
                Générez, contrôlez, validez et publiez les bulletins des élèves.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div
                v-for="item in [
                    {
                        label: 'Total',
                        value: summary.total ?? 0,
                        icon: Users,
                        color: 'text-primary',
                    },
                    {
                        label: 'Brouillons',
                        value: summary.draft ?? 0,
                        icon: FileCheck2,
                        color: 'text-amber-600',
                    },
                    {
                        label: 'Validés',
                        value: summary.validated ?? 0,
                        icon: CheckCircle2,
                        color: 'text-violet-600',
                    },
                    {
                        label: 'Publiés',
                        value: summary.published ?? 0,
                        icon: Send,
                        color: 'text-emerald-600',
                    },
                    {
                        label: 'À vérifier',
                        value: summary.problematic ?? 0,
                        icon: AlertTriangle,
                        color: 'text-red-600',
                    },
                ]"
                :key="item.label"
                class="rounded-xl border bg-card p-4 shadow-sm"
            >
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>{{ item.label }}</span
                    ><component
                        :is="item.icon"
                        class="size-5"
                        :class="item.color"
                    />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ item.value }}</p>
            </div>
        </div>

        <form
            class="rounded-xl border bg-card p-4 shadow-sm"
            @submit.prevent="apply"
        >
            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5"
            >
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground sm:col-span-2"
                    >Rechercher un élève
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        /><Input
                            v-model="filters.search"
                            class="pl-9"
                            placeholder="Nom, prénom ou matricule…"
                        /></div
                ></label>
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                    >Année scolaire<select
                        v-model="filters.academic_year_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
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
                        v-model="filters.period_key"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="(label, key) in periods"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                    >Cycle<select
                        v-model="filters.cycle_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        @change="changeScope('cycle')"
                    >
                        <option value="">Tous les cycles</option>
                        <option
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.name }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                    >Spécialité / filière<select
                        v-model="filters.specialization"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        @change="changeScope('specialization')"
                    >
                        <option value="">Toutes les spécialités</option>
                        <option
                            v-for="specialization in specializations"
                            :key="specialization"
                            :value="specialization"
                        >
                            {{ specialization }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                    >Niveau<select
                        v-model="filters.level_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        @change="changeScope('level')"
                    >
                        <option value="">Tous les niveaux</option>
                        <option
                            v-for="level in filteredLevels"
                            :key="level.id"
                            :value="level.id"
                        >
                            {{ level.name
                            }}{{
                                level.specialization
                                    ? ` — ${level.specialization}`
                                    : ''
                            }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                    >Classe<select
                        v-model="filters.group_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Toutes les classes</option>
                        <option
                            v-for="group in filteredGroups"
                            :key="group.id"
                            :value="group.id"
                        >
                            {{ group.name }}
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
                        <option
                            v-for="(label, status) in statusLabels"
                            :key="status"
                            :value="status"
                        >
                            {{ label }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                    >Moyenne minimale<Input
                        v-model="filters.average_min"
                        type="number"
                        min="0"
                        max="20"
                        step="0.01"
                        placeholder="0"
                /></label>
                <label
                    class="grid gap-1.5 text-xs font-semibold text-muted-foreground"
                    >Moyenne maximale<Input
                        v-model="filters.average_max"
                        type="number"
                        min="0"
                        max="20"
                        step="0.01"
                        placeholder="20"
                /></label>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t pt-4">
                <label class="mr-auto flex items-center gap-2 text-sm"
                    ><input
                        v-model="filters.problematic"
                        type="checkbox"
                    />Afficher uniquement les bulletins à vérifier</label
                ><Button
                    type="button"
                    variant="outline"
                    class="gap-2"
                    @click="reset"
                    ><RotateCcw class="size-4" />Réinitialiser</Button
                ><Button type="submit" class="gap-2"
                    ><Filter class="size-4" />Appliquer les filtres</Button
                >
            </div>
        </form>

        <div class="rounded-xl border bg-card p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div>
                    <h2 class="font-semibold">Actions pour une classe</h2>
                    <p class="text-xs text-muted-foreground">
                        Sélectionnez une classe pour activer les traitements
                        groupés.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 lg:ml-auto">
                    <Button
                        :disabled="!filters.group_id"
                        size="sm"
                        class="gap-2"
                        @click="
                            ask(
                                '/admin/report-cards/generate',
                                'Générer les bulletins manquants',
                                'Créer les bulletins absents pour tous les élèves inscrits dans cette classe et cette période.',
                                'Générer',
                            )
                        "
                        ><Plus class="size-4" />Générer les manquants</Button
                    ><Button
                        :disabled="!filters.group_id"
                        size="sm"
                        variant="outline"
                        class="gap-2"
                        @click="
                            ask(
                                '/admin/report-cards/recalculate-group',
                                'Recalculer les bulletins',
                                'Recalculer les moyennes à partir des dernières notes enregistrées.',
                                'Recalculer',
                            )
                        "
                        ><RefreshCw class="size-4" />Recalculer</Button
                    ><Button
                        :disabled="!filters.group_id"
                        size="sm"
                        class="gap-2 bg-emerald-600 hover:bg-emerald-700"
                        @click="
                            ask(
                                '/admin/report-cards/validate-group',
                                'Valider les bulletins prêts',
                                'Valider tous les brouillons qui respectent les règles académiques.',
                                'Valider',
                            )
                        "
                        ><CheckCircle2 class="size-4" />Valider les
                        prêts</Button
                    ><Button
                        :disabled="!filters.group_id"
                        size="sm"
                        class="gap-2 bg-violet-600 hover:bg-violet-700"
                        @click="
                            ask(
                                '/admin/report-cards/publish-group',
                                'Publier les bulletins validés',
                                'Rendre visibles aux familles tous les bulletins validés de cette classe.',
                                'Publier',
                            )
                        "
                        ><Send class="size-4" />Publier les validés</Button
                    >
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-card shadow-sm">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead
                    class="border-b bg-muted/40 text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="p-3">N°</th>
                        <th class="p-3">Élève</th>
                        <th class="p-3">Matricule</th>
                        <th class="p-3">Moyenne</th>
                        <th class="p-3">Rang</th>
                        <th class="p-3">Matières calculées</th>
                        <th class="p-3">Absences</th>
                        <th class="p-3">Statut</th>
                        <th class="p-3">Contrôle</th>
                        <th class="p-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(card, index) in cards.data"
                        :key="card.id"
                        class="border-b last:border-0 hover:bg-muted/20"
                    >
                        <td class="p-3 text-muted-foreground">
                            {{ (cards.from ?? 1) + index }}
                        </td>
                        <td class="p-3 font-medium">
                            <span
                                class="mr-2 inline-flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                                >{{ initials(card) }}</span
                            >{{ card.student?.first_name }}
                            {{ card.student?.last_name }}
                        </td>
                        <td class="p-3">
                            {{ card.student?.registration_number ?? '—' }}
                        </td>
                        <td
                            class="p-3 text-base font-bold"
                            :class="
                                Number(card.general_average) >= 10
                                    ? 'text-emerald-600'
                                    : 'text-amber-600'
                            "
                        >
                            {{ card.general_average ?? '—'
                            }}<span
                                v-if="card.general_average !== null"
                                class="text-xs font-normal text-muted-foreground"
                            >
                                / 20</span
                            >
                        </td>
                        <td class="p-3">
                            {{
                                card.rank
                                    ? `${card.rank} / ${card.total_students ?? '—'}`
                                    : '—'
                            }}
                        </td>
                        <td class="p-3">{{ subjectProgress(card) }}</td>
                        <td class="p-3">{{ card.absences_count ?? 0 }}</td>
                        <td class="p-3">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                :class="statusClasses[card.status]"
                                >{{
                                    statusLabels[card.status] ?? card.status
                                }}</span
                            >
                        </td>
                        <td class="p-3">
                            <span
                                v-if="hasProblem(card)"
                                class="inline-flex items-center gap-1 text-xs font-medium text-amber-700"
                                ><AlertTriangle class="size-4" />À
                                vérifier</span
                            ><span
                                v-else
                                class="inline-flex items-center gap-1 text-xs text-emerald-700"
                                ><CheckCircle2 class="size-4" />Complet</span
                            >
                        </td>
                        <td class="p-3 text-right">
                            <Button as-child size="sm" variant="outline"
                                ><Link :href="`/admin/report-cards/${card.id}`"
                                    ><Eye class="mr-1 size-4" />Consulter</Link
                                ></Button
                            >
                        </td>
                    </tr>
                    <tr v-if="!cards.data.length">
                        <td
                            colspan="10"
                            class="p-10 text-center text-muted-foreground"
                        >
                            <Search
                                class="mx-auto mb-3 size-8 opacity-50"
                            />Aucun bulletin ne correspond à ces critères.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="cards.links.length > 3"
            class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
        >
            <span
                >Résultats {{ cards.from }} à {{ cards.to }} sur
                {{ cards.total }}</span
            >
            <div class="flex flex-wrap gap-1">
                <Link
                    v-for="link in cards.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    class="rounded-md border bg-card px-3 py-1.5"
                    :class="[
                        link.active && 'bg-primary text-primary-foreground',
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
</template>
