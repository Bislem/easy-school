<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    Check,
    Download,
    FileArchive,
    FileText,
    Languages,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Level = {
    id: number;
    name: string;
    code: string;
    specialization?: string;
};
type Cycle = { id: number; name: string; levels: Level[] };
type Group = {
    id: number;
    name: string;
    students_count: number;
    school_level_id: number;
    level: Level & { cycle?: { id: number; name: string } };
};

const props = defineProps<{
    academicYear: { id: number; name: string } | null;
    cycles: Cycle[];
    groups: Group[];
    documentTypes: Array<{ value: string; label: string }>;
}>();

const page = usePage();
const cycleId = ref<number | ''>('');
const levelId = ref<number | ''>('');
const selectedGroups = ref<number[]>([]);
const language = ref<'fr' | 'ar'>('fr');
const documentType = ref(props.documentTypes[0]?.value || 'school_certificate');
const issueDate = ref(new Date().toISOString().slice(0, 10));

const availableLevels = computed(() => {
    if (!cycleId.value) return props.cycles.flatMap((cycle) => cycle.levels);
    return (
        props.cycles.find((cycle) => cycle.id === Number(cycleId.value))
            ?.levels || []
    );
});
const visibleGroups = computed(() =>
    props.groups.filter((group) => {
        if (levelId.value)
            return group.school_level_id === Number(levelId.value);
        if (cycleId.value)
            return group.level?.cycle?.id === Number(cycleId.value);
        return true;
    }),
);
const selectedStudentCount = computed(() =>
    props.groups
        .filter((group) => selectedGroups.value.includes(group.id))
        .reduce((sum, group) => sum + group.students_count, 0),
);
const allVisibleSelected = computed(
    () =>
        visibleGroups.value.length > 0 &&
        visibleGroups.value.every((group) =>
            selectedGroups.value.includes(group.id),
        ),
);

watch(cycleId, () => {
    levelId.value = '';
});

function toggleGroup(id: number) {
    selectedGroups.value = selectedGroups.value.includes(id)
        ? selectedGroups.value.filter((groupId) => groupId !== id)
        : [...selectedGroups.value, id];
}

function toggleAllVisible() {
    const visibleIds = visibleGroups.value.map((group) => group.id);
    selectedGroups.value = allVisibleSelected.value
        ? selectedGroups.value.filter((id) => !visibleIds.includes(id))
        : [...new Set([...selectedGroups.value, ...visibleIds])];
}
</script>

<template>
    <Head title="Documents scolaires" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl space-y-6 p-4 md:p-8">
            <div>
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-blue-100 p-2 text-blue-700">
                        <FileArchive class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">
                            Documents scolaires
                        </h1>
                        <p class="text-sm text-slate-500">
                            Générez les documents des élèves par groupes ·
                            {{ academicYear?.name || 'Aucune année scolaire' }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="Object.keys(page.props.errors || {}).length"
                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                <div v-for="(error, field) in page.props.errors" :key="field">
                    {{ error }}
                </div>
            </div>

            <form
                method="post"
                action="/admin/school-documents/download"
                class="grid gap-6 lg:grid-cols-[1fr_340px]"
            >
                <input
                    type="hidden"
                    name="_token"
                    :value="page.props.csrf_token as string"
                />
                <input
                    type="hidden"
                    name="document_type"
                    :value="documentType"
                />
                <input type="hidden" name="language" :value="language" />
                <input
                    v-for="id in selectedGroups"
                    :key="id"
                    type="hidden"
                    name="group_ids[]"
                    :value="id"
                />

                <section class="rounded-2xl border bg-white p-5 shadow-sm">
                    <div
                        class="mb-5 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2 class="font-semibold text-slate-900">
                                1. Choisir les groupes
                            </h2>
                            <p class="text-sm text-slate-500">
                                Filtrez par niveau, puis sélectionnez un ou
                                plusieurs groupes.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="!visibleGroups.length"
                            @click="toggleAllVisible"
                        >
                            <Check class="mr-2 h-4 w-4" />{{
                                allVisibleSelected
                                    ? 'Tout désélectionner'
                                    : 'Sélectionner les groupes affichés'
                            }}
                        </Button>
                    </div>

                    <div class="mb-5 grid gap-3 sm:grid-cols-2">
                        <label class="text-sm font-medium text-slate-700"
                            >Cycle
                            <select
                                v-model="cycleId"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                            >
                                <option value="">Tous les cycles</option>
                                <option
                                    v-for="cycle in cycles"
                                    :key="cycle.id"
                                    :value="cycle.id"
                                >
                                    {{ cycle.name }}
                                </option>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-slate-700"
                            >Niveau
                            <select
                                v-model="levelId"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                            >
                                <option value="">Tous les niveaux</option>
                                <option
                                    v-for="level in availableLevels"
                                    :key="level.id"
                                    :value="level.id"
                                >
                                    {{ level.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <button
                            v-for="group in visibleGroups"
                            :key="group.id"
                            type="button"
                            class="rounded-xl border p-4 text-left transition"
                            :class="
                                selectedGroups.includes(group.id)
                                    ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                                    : 'border-slate-200 hover:border-slate-300'
                            "
                            @click="toggleGroup(group.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">
                                        {{ group.name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ group.level.name }}
                                    </div>
                                </div>
                                <span
                                    class="rounded-full bg-white px-2 py-1 text-xs font-medium text-slate-600"
                                    >{{ group.students_count }} élève(s)</span
                                >
                            </div>
                        </button>
                        <div
                            v-if="!visibleGroups.length"
                            class="col-span-full rounded-xl border border-dashed p-8 text-center text-sm text-slate-500"
                        >
                            Aucun groupe actif pour ce filtre.
                        </div>
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-2xl border bg-white p-5 shadow-sm">
                        <h2 class="mb-4 font-semibold text-slate-900">
                            2. Préparer les documents
                        </h2>
                        <label class="block text-sm font-medium text-slate-700"
                            >Type de document
                            <select
                                v-model="documentType"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                            >
                                <option
                                    v-for="type in documentTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </label>

                        <div class="mt-4 text-sm font-medium text-slate-700">
                            Langue
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="rounded-lg border px-3 py-2 text-sm"
                                :class="
                                    language === 'fr'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-slate-200'
                                "
                                @click="language = 'fr'"
                            >
                                Français
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border px-3 py-2 text-sm"
                                :class="
                                    language === 'ar'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-slate-200'
                                "
                                @click="language = 'ar'"
                            >
                                العربية
                            </button>
                        </div>

                        <label
                            class="mt-4 block text-sm font-medium text-slate-700"
                            >Date de délivrance
                            <input
                                v-model="issueDate"
                                name="issue_date"
                                type="date"
                                required
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                        </label>
                    </section>

                    <section
                        class="rounded-2xl bg-slate-900 p-5 text-white shadow-sm"
                    >
                        <div
                            class="flex items-center gap-2 text-sm text-slate-300"
                        >
                            <Languages class="h-4 w-4" />{{
                                language === 'ar'
                                    ? 'Version arabe'
                                    : 'Version française'
                            }}
                        </div>
                        <div class="mt-4 text-3xl font-bold">
                            {{ selectedStudentCount }}
                        </div>
                        <div class="text-sm text-slate-300">
                            certificat(s) dans
                            {{ selectedGroups.length }} groupe(s)
                        </div>
                        <Button
                            type="submit"
                            class="mt-5 w-full bg-blue-500 hover:bg-blue-600"
                            :disabled="
                                !academicYear ||
                                !selectedGroups.length ||
                                !selectedStudentCount
                            "
                        >
                            <Download class="mr-2 h-4 w-4" />Télécharger le
                            fichier ZIP
                        </Button>
                        <p class="mt-3 flex gap-2 text-xs text-slate-400">
                            <FileText class="h-4 w-4 shrink-0" />Un PDF par
                            élève, classé dans un dossier par groupe.
                        </p>
                    </section>
                </aside>
            </form>
        </div>
    </AdminLayout>
</template>
