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
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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
type Teacher = { id: number; name: string; email?: string | null };

const props = defineProps<{
    academicYear: { id: number; name: string } | null;
    cycles: Cycle[];
    groups: Group[];
    teachers: Teacher[];
    documentTypes: Array<{ value: string; label: string }>;
}>();

const page = usePage();
const cycleId = ref<number | ''>('');
const levelId = ref<number | ''>('');
const selectedGroups = ref<number[]>([]);
const selectedTeachers = ref<number[]>([]);
const audience = ref<'groups' | 'teachers'>('groups');
const language = ref<'fr' | 'ar'>('fr');
const documentType = ref(props.documentTypes[0]?.value || 'school_certificate');
const issueDate = ref(new Date().toISOString().slice(0, 10));
const isGenerating = ref(false);
const downloadProgress = ref<number | null>(null);
const downloadError = ref('');
let activeRequest: XMLHttpRequest | null = null;

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
const isGroupTimetable = computed(
    () => documentType.value === 'group_timetable',
);
const isTeacherTimetable = computed(
    () => documentType.value === 'teacher_timetable',
);
const isTimetable = computed(
    () => isGroupTimetable.value || isTeacherTimetable.value,
);
const availableDocumentTypes = computed(() =>
    props.documentTypes.filter((type) =>
        audience.value === 'teachers'
            ? type.value === 'teacher_timetable'
            : type.value !== 'teacher_timetable',
    ),
);
const generatedDocumentCount = computed(() =>
    isTeacherTimetable.value
        ? selectedTeachers.value.length
        : isGroupTimetable.value
          ? selectedGroups.value.length
          : selectedStudentCount.value,
);
const allVisibleSelected = computed(
    () =>
        visibleGroups.value.length > 0 &&
        visibleGroups.value.every((group) =>
            selectedGroups.value.includes(group.id),
        ),
);
const allTeachersSelected = computed(
    () =>
        props.teachers.length > 0 &&
        props.teachers.every((teacher) =>
            selectedTeachers.value.includes(teacher.id),
        ),
);

watch(cycleId, () => {
    levelId.value = '';
});
watch(audience, (value) => {
    if (value === 'teachers') {
        documentType.value = 'teacher_timetable';
    } else if (documentType.value === 'teacher_timetable') {
        documentType.value = 'group_timetable';
    }
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

function toggleTeacher(id: number) {
    selectedTeachers.value = selectedTeachers.value.includes(id)
        ? selectedTeachers.value.filter((teacherId) => teacherId !== id)
        : [...selectedTeachers.value, id];
}

function toggleAllTeachers() {
    selectedTeachers.value = allTeachersSelected.value
        ? []
        : props.teachers.map((teacher) => teacher.id);
}

function filenameFromDisposition(disposition: string | null) {
    const encoded = disposition?.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const plain = disposition?.match(/filename="?([^";]+)"?/i)?.[1];

    return decodeURIComponent(encoded || plain || 'certificats-scolarite.zip');
}

async function downloadArchive() {
    if (isGenerating.value) return;

    isGenerating.value = true;
    downloadProgress.value = null;
    downloadError.value = '';

    const data = new FormData();
    data.append('document_type', documentType.value);
    data.append('language', language.value);
    data.append('issue_date', issueDate.value);
    if (isTeacherTimetable.value) {
        selectedTeachers.value.forEach((id) =>
            data.append('teacher_ids[]', String(id)),
        );
    } else {
        selectedGroups.value.forEach((id) =>
            data.append('group_ids[]', String(id)),
        );
    }

    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const request = new XMLHttpRequest();
    activeRequest = request;
    request.open('POST', '/admin/school-documents/download');
    request.responseType = 'blob';
    request.setRequestHeader('Accept', 'application/json');
    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    if (csrfToken) request.setRequestHeader('X-CSRF-TOKEN', csrfToken);

    request.onprogress = (event) => {
        if (event.lengthComputable) {
            downloadProgress.value = Math.min(
                99,
                Math.round((event.loaded / event.total) * 100),
            );
        }
    };
    request.onload = async () => {
        if (request.status >= 200 && request.status < 300) {
            downloadProgress.value = 100;
            const url = URL.createObjectURL(request.response);
            const link = document.createElement('a');
            link.href = url;
            link.download = filenameFromDisposition(
                request.getResponseHeader('Content-Disposition'),
            );
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } else {
            try {
                const payload = JSON.parse(await request.response.text());
                downloadError.value =
                    payload.message ||
                    Object.values(payload.errors || {})
                        .flat()
                        .join(' ') ||
                    'La génération du fichier a échoué.';
            } catch {
                downloadError.value = 'La génération du fichier a échoué.';
            }
        }
        isGenerating.value = false;
        activeRequest = null;
    };
    request.onerror = () => {
        downloadError.value = 'La connexion au serveur a échoué.';
        isGenerating.value = false;
        activeRequest = null;
    };
    request.send(data);
}

onBeforeUnmount(() => activeRequest?.abort());
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
                class="grid gap-6 lg:grid-cols-[1fr_340px]"
                @submit.prevent="downloadArchive"
            >
                <section class="rounded-2xl border bg-white p-5 shadow-sm">
                    <label
                        class="mb-5 block text-sm font-medium text-slate-700"
                    >
                        Documents pour
                        <select
                            v-model="audience"
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 sm:max-w-sm"
                        >
                            <option value="groups">Élèves / groupes</option>
                            <option value="teachers">Enseignants</option>
                        </select>
                    </label>
                    <div
                        class="mb-5 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2 class="font-semibold text-slate-900">
                                1. Choisir
                                {{
                                    audience === 'teachers'
                                        ? 'les enseignants'
                                        : 'les groupes'
                                }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                {{
                                    audience === 'teachers'
                                        ? 'Sélectionnez un ou plusieurs enseignants.'
                                        : 'Filtrez par niveau, puis sélectionnez un ou plusieurs groupes.'
                                }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="
                                audience === 'teachers'
                                    ? !teachers.length
                                    : !visibleGroups.length
                            "
                            @click="
                                audience === 'teachers'
                                    ? toggleAllTeachers()
                                    : toggleAllVisible()
                            "
                        >
                            <Check class="mr-2 h-4 w-4" />{{
                                (
                                    audience === 'teachers'
                                        ? allTeachersSelected
                                        : allVisibleSelected
                                )
                                    ? 'Tout désélectionner'
                                    : audience === 'teachers'
                                      ? 'Sélectionner tous les enseignants'
                                      : 'Sélectionner les groupes affichés'
                            }}
                        </Button>
                    </div>

                    <div
                        v-if="audience === 'groups'"
                        class="mb-5 grid gap-3 sm:grid-cols-2"
                    >
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

                    <div
                        v-if="audience === 'groups'"
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                    >
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
                    <div
                        v-else
                        class="overflow-hidden rounded-xl border border-slate-200"
                    >
                        <button
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            type="button"
                            class="flex w-full min-w-0 items-center gap-3 border-b border-slate-200 px-3 py-3 text-left transition last:border-b-0 sm:px-4"
                            :class="
                                selectedTeachers.includes(teacher.id)
                                    ? 'bg-blue-50'
                                    : 'bg-white hover:bg-slate-50'
                            "
                            @click="toggleTeacher(teacher.id)"
                        >
                            <span
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded border"
                                :class="
                                    selectedTeachers.includes(teacher.id)
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-slate-300 bg-white text-transparent'
                                "
                            >
                                <Check class="h-3.5 w-3.5" />
                            </span>
                            <div
                                class="min-w-0 flex-1 sm:flex sm:items-center sm:gap-4"
                            >
                                <div
                                    class="truncate font-medium text-slate-900 sm:w-2/5"
                                >
                                    {{ teacher.name }}
                                </div>
                                <div
                                    class="mt-0.5 truncate text-xs text-slate-500 sm:mt-0 sm:min-w-0 sm:flex-1 sm:text-sm"
                                    :title="
                                        teacher.email || 'Sans adresse e-mail'
                                    "
                                >
                                    {{ teacher.email || 'Sans adresse e-mail' }}
                                </div>
                            </div>
                            <span
                                class="shrink-0 text-xs font-medium text-slate-500"
                            >
                                {{
                                    selectedTeachers.includes(teacher.id)
                                        ? 'Sélectionné'
                                        : 'Sélectionner'
                                }}
                            </span>
                        </button>
                        <div
                            v-if="!teachers.length"
                            class="p-8 text-center text-sm text-slate-500"
                        >
                            Aucun enseignant actif.
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
                                    v-for="type in availableDocumentTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </label>

                        <div
                            v-if="!isTimetable"
                            class="mt-4 text-sm font-medium text-slate-700"
                        >
                            Langue
                        </div>
                        <div
                            v-if="!isTimetable"
                            class="mt-2 grid grid-cols-2 gap-2"
                        >
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
                            v-if="!isTimetable"
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
                                isTeacherTimetable
                                    ? 'Un emploi du temps par enseignant'
                                    : isGroupTimetable
                                      ? 'Un emploi du temps par groupe'
                                      : language === 'ar'
                                        ? 'Version arabe'
                                        : 'Version française'
                            }}
                        </div>
                        <div class="mt-4 text-3xl font-bold">
                            {{ generatedDocumentCount }}
                        </div>
                        <div class="text-sm text-slate-300">
                            {{
                                isTeacherTimetable
                                    ? `emploi(s) du temps pour ${selectedTeachers.length} enseignant(s)`
                                    : isGroupTimetable
                                      ? `emploi(s) du temps pour ${selectedGroups.length} groupe(s)`
                                      : `certificat(s) dans ${selectedGroups.length} groupe(s)`
                            }}
                        </div>
                        <Button
                            type="submit"
                            class="mt-5 w-full bg-blue-500 hover:bg-blue-600"
                            :disabled="
                                !academicYear ||
                                (isTeacherTimetable
                                    ? !selectedTeachers.length
                                    : !selectedGroups.length) ||
                                (!isTimetable && !selectedStudentCount) ||
                                isGenerating
                            "
                        >
                            <Download class="mr-2 h-4 w-4" />{{
                                isGenerating
                                    ? 'Génération en cours…'
                                    : isTimetable
                                      ? 'Télécharger les emplois du temps'
                                      : 'Télécharger le fichier ZIP'
                            }}
                        </Button>
                        <div
                            v-if="isGenerating || downloadProgress !== null"
                            class="mt-3"
                        >
                            <div
                                class="h-2 overflow-hidden rounded-full bg-slate-700"
                                role="progressbar"
                                :aria-valuenow="downloadProgress ?? undefined"
                                aria-label="Progression de la génération"
                            >
                                <div
                                    v-if="downloadProgress === null"
                                    class="h-full w-1/3 animate-pulse rounded-full bg-blue-400"
                                />
                                <div
                                    v-else
                                    class="h-full rounded-full bg-blue-400 transition-all"
                                    :style="{ width: `${downloadProgress}%` }"
                                />
                            </div>
                            <p class="mt-2 text-xs text-slate-300">
                                {{
                                    downloadProgress === null
                                        ? 'Création des certificats…'
                                        : downloadProgress < 100
                                          ? `Téléchargement : ${downloadProgress}%`
                                          : 'Téléchargement prêt.'
                                }}
                            </p>
                        </div>
                        <p
                            v-if="downloadError"
                            class="mt-3 text-xs text-red-300"
                            role="alert"
                        >
                            {{ downloadError }}
                        </p>
                        <p class="mt-3 flex gap-2 text-xs text-slate-400">
                            <FileText class="h-4 w-4 shrink-0" />{{
                                isTeacherTimetable
                                    ? 'Un PDF par enseignant, nommé avec son nom et l’année scolaire.'
                                    : isGroupTimetable
                                      ? 'Un PDF par groupe, nommé avec la classe et l’année scolaire.'
                                      : 'Un PDF par élève, classé dans un dossier par groupe.'
                            }}
                        </p>
                    </section>
                </aside>
            </form>
        </div>
    </AdminLayout>
</template>
