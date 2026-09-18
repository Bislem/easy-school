<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { BookOpenCheck, Search, UsersRound } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

defineOptions({ layout: AdminLayout });

type Level = {
    id: number;
    name: string;
    specialization?: string | null;
};
type Subject = {
    id: number;
    title: string;
    coefficient: number;
};

const props = defineProps<{
    academicYears: Array<{ id: number; name: string }>;
    academicYear: { id: number; name: string } | null;
    periods: Array<{ id: number; name: string }>;
    levels: Level[];
    groups: Array<{ id: number; name: string; school_level_id: number }>;
    subjects: Subject[];
    assessments: Array<{ id: number; subject_id: number }>;
    matrix: Record<string, any>;
    summary: Record<string, number | null>;
    filters: Record<string, any>;
}>();

const filters = reactive({ ...props.filters });
const rows = computed(() => Object.values(props.matrix));
const summaryCards = [
    { key: 'students', label: 'Élèves' },
    { key: 'subjects', label: 'Matières' },
    { key: 'assessments', label: 'Évaluations' },
    { key: 'grades_percent', label: 'Notes saisies', suffix: '%' },
    { key: 'complete', label: 'Dossiers complets' },
    { key: 'incomplete', label: 'Dossiers incomplets' },
];

function applyFilters() {
    router.get('/admin/gradebook', filters, {
        preserveState: true,
        replace: true,
    });
}
function selectLevel() {
    filters.school_group_id = '';
    applyFilters();
}
function levelLabel(level: Level) {
    return `${level.name}${level.specialization ? ` · ${level.specialization}` : ''}`;
}
</script>

<template>
    <Head title="Carnet de notes" />
    <main class="flex-1 space-y-6 bg-slate-50/60 p-4 sm:p-6 lg:p-8">
        <header>
            <h1 class="flex items-center gap-3 text-2xl font-semibold">
                <span class="rounded-xl bg-blue-600 p-2 text-white">
                    <BookOpenCheck class="size-5" />
                </span>
                Carnet de notes
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Moyennes par matière et moyenne générale pondérée selon les
                coefficients du niveau ou de la spécialité.
            </p>
        </header>

        <form
            class="grid gap-4 rounded-2xl border bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-6"
            @submit.prevent="applyFilters"
        >
            <label class="field">
                <span>Année scolaire</span>
                <select
                    v-model="filters.academic_year_id"
                    @change="applyFilters"
                >
                    <option
                        v-for="year in academicYears"
                        :key="year.id"
                        :value="year.id"
                    >
                        {{ year.name }}
                    </option>
                </select>
            </label>
            <label class="field">
                <span>Période</span>
                <select
                    v-model="filters.academic_period_id"
                    @change="applyFilters"
                >
                    <option value="">Choisir une période</option>
                    <option
                        v-for="period in periods"
                        :key="period.id"
                        :value="period.id"
                    >
                        {{ period.name }}
                    </option>
                </select>
            </label>
            <label class="field">
                <span>Niveau / spécialité</span>
                <select v-model="filters.school_level_id" @change="selectLevel">
                    <option value="">Tous les niveaux</option>
                    <option
                        v-for="level in levels"
                        :key="level.id"
                        :value="level.id"
                    >
                        {{ levelLabel(level) }}
                    </option>
                </select>
            </label>
            <label class="field">
                <span>Groupe</span>
                <select
                    v-model="filters.school_group_id"
                    @change="applyFilters"
                >
                    <option value="">Choisir un groupe</option>
                    <option
                        v-for="group in groups"
                        :key="group.id"
                        :value="group.id"
                    >
                        {{ group.name }}
                    </option>
                </select>
            </label>
            <label class="field xl:col-span-2">
                <span>Rechercher un élève</span>
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-slate-400"
                    />
                    <Input
                        v-model="filters.search"
                        class="pl-9"
                        placeholder="Nom ou matricule…"
                    />
                </div>
            </label>
            <div class="flex items-end gap-3 xl:col-span-6">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="filters.incomplete"
                        type="checkbox"
                        true-value="1"
                        false-value=""
                    />
                    Afficher uniquement les dossiers incomplets
                </label>
                <Button class="ml-auto" variant="outline">Appliquer</Button>
            </div>
        </form>

        <div
            v-if="!filters.school_group_id || !filters.academic_period_id"
            class="rounded-2xl border border-dashed bg-white p-12 text-center text-muted-foreground"
        >
            <UsersRound class="mx-auto mb-3 size-10 text-slate-300" />
            Choisissez une période et un groupe pour afficher le carnet.
        </div>

        <template v-else>
            <section
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
            >
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="rounded-xl border bg-white p-4 shadow-sm"
                >
                    <strong class="text-2xl text-slate-900">
                        {{ summary[card.key] ?? 0 }}{{ card.suffix || '' }}
                    </strong>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                </article>
            </section>

            <section
                class="overflow-x-auto rounded-2xl border bg-white shadow-sm"
            >
                <table class="w-full min-w-max text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 p-3">
                                Élève
                            </th>
                            <th
                                v-for="subject in subjects"
                                :key="subject.id"
                                class="min-w-36 p-3 text-center"
                            >
                                {{ subject.title }}
                                <div
                                    class="mt-1 text-[11px] font-normal text-muted-foreground"
                                >
                                    Coef. {{ subject.coefficient }} ·
                                    {{
                                        assessments.filter(
                                            (item) =>
                                                item.subject_id === subject.id,
                                        ).length
                                    }}
                                    évaluation(s)
                                </div>
                            </th>
                            <th
                                class="sticky right-0 bg-slate-50 p-3 text-center"
                            >
                                Moyenne pondérée
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.student?.id"
                            class="border-t"
                        >
                            <td
                                class="sticky left-0 z-10 bg-white p-3 font-medium"
                            >
                                {{ row.student?.first_name }}
                                {{ row.student?.last_name }}
                                <div
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    {{
                                        row.student?.registration_number ?? '—'
                                    }}
                                </div>
                            </td>
                            <td
                                v-for="subject in subjects"
                                :key="subject.id"
                                class="p-3 text-center"
                            >
                                <span
                                    :class="
                                        row.cells[subject.id]?.average === null
                                            ? 'text-amber-600'
                                            : 'font-semibold text-slate-900'
                                    "
                                >
                                    {{ row.cells[subject.id]?.state ?? '—' }}
                                </span>
                                <div
                                    v-if="row.cells[subject.id]?.incomplete"
                                    class="text-[10px] text-amber-600"
                                >
                                    Saisie incomplète
                                </div>
                            </td>
                            <td
                                class="sticky right-0 bg-white p-3 text-center font-bold"
                            >
                                {{ row.average ?? '—' }}
                                <span
                                    v-if="row.incomplete"
                                    class="text-amber-600"
                                    title="Moyenne provisoire"
                                    >*</span
                                >
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td
                                :colspan="subjects.length + 2"
                                class="p-12 text-center text-muted-foreground"
                            >
                                Aucun élève ou aucune évaluation ne correspond
                                aux filtres.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <p class="text-xs text-muted-foreground">
                * Moyenne provisoire : une ou plusieurs évaluations ne sont pas
                encore renseignées.
            </p>
        </template>
    </main>
</template>

<style scoped>
.field {
    display: grid;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(71 85 105);
}
.field select {
    height: 2.25rem;
    width: 100%;
    border-radius: 0.375rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    padding: 0 0.75rem;
    font-size: 0.875rem;
    font-weight: 400;
    color: rgb(15 23 42);
}
</style>
