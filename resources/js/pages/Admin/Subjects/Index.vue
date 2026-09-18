<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Beaker,
    BookMarked,
    Clock3,
    GraduationCap,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Teacher = { id: number; name: string; email?: string };
type Level = {
    id: number;
    name: string;
    code: string;
    specialization?: string | null;
    cycle?: { id: number; name: string };
    pivot?: {
        id?: number;
        school_stream_id?: number | null;
        curriculum_code?: string | null;
        is_optional?: boolean;
        is_active?: boolean;
        coefficient?: number | string;
    };
};
type Cycle = { id: number; name: string; code: string; levels: Level[] };
type RoomType = { value: string; label: string };
type Subject = {
    id: number;
    title: string;
    title_ar?: string | null;
    code: string;
    category?: string | null;
    color: string;
    weekly_hours: string | number;
    description?: string | null;
    required_room_types?: string[];
    is_specialized: boolean;
    is_active: boolean;
    school_levels: Level[];
    teachers: Teacher[];
    timetable_sessions_count: number;
};
type Page<T> = {
    data: T[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
const props = defineProps<{
    subjects: Page<Subject>;
    cycles: Cycle[];
    teachers: Teacher[];
    roomTypes: RoomType[];
    filters: Record<string, string>;
}>();
const modalOpen = ref(false);
const editing = ref<Subject | null>(null);
const search = ref(props.filters.search || '');
const levelFilter = ref(props.filters.level_id || '');
const teacherFilter = ref(props.filters.teacher_id || '');
const statusFilter = ref(props.filters.status || '');
const teacherSearch = ref('');
const form = useForm({
    title: '',
    title_ar: '',
    code: '',
    category: '',
    color: '#2563eb',
    weekly_hours: 2,
    description: '',
    required_room_types: [] as string[],
    is_specialized: false,
    is_active: true,
    level_assignments: [] as Array<{
        school_level_id: number;
        coefficient: number | string;
    }>,
    teacher_ids: [] as number[],
});
const allLevels = computed(() => props.cycles.flatMap((cycle) => cycle.levels));
const filteredTeachers = computed(() => {
    const query = teacherSearch.value.trim().toLocaleLowerCase();

    if (!query) return props.teachers;

    return props.teachers.filter((teacher) =>
        `${teacher.name} ${teacher.email ?? ''}`
            .toLocaleLowerCase()
            .includes(query),
    );
});
const selectedTeacherCount = computed(() => form.teacher_ids.length);
function applyFilters() {
    router.get(
        '/admin/subjects',
        {
            search: search.value,
            level_id: levelFilter.value,
            teacher_id: teacherFilter.value,
            status: statusFilter.value,
        },
        { preserveState: true, replace: true },
    );
}
function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    Object.assign(form, {
        color: '#2563eb',
        weekly_hours: 2,
        is_active: true,
        is_specialized: false,
        required_room_types: [],
        level_assignments: [],
        teacher_ids: [],
    });
    teacherSearch.value = '';
    modalOpen.value = true;
}
function openEdit(subject: Subject) {
    editing.value = subject;
    form.clearErrors();
    Object.assign(form, {
        title: subject.title,
        title_ar: subject.title_ar || '',
        code: subject.code,
        category: subject.category || '',
        color: subject.color || '#2563eb',
        weekly_hours: Number(subject.weekly_hours || 1),
        description: subject.description || '',
        required_room_types: subject.required_room_types || [],
        is_specialized: subject.is_specialized,
        is_active: subject.is_active,
        level_assignments: subject.school_levels
            .filter((level) => level.pivot?.is_active !== false)
            .map((level) => ({
                school_level_id: level.id,
                coefficient: Number(level.pivot?.coefficient ?? 1),
            })),
        teacher_ids: [...new Set(subject.teachers.map((t) => t.id))],
    });
    teacherSearch.value = '';
    modalOpen.value = true;
}
function toggle<T>(field: 'teacher_ids' | 'required_room_types', value: T) {
    const list = form[field] as T[];
    (form[field] as T[]) = list.includes(value)
        ? list.filter((item) => item !== value)
        : [...list, value];
}
function levelAssignment(levelId: number) {
    return form.level_assignments.find(
        (assignment) => assignment.school_level_id === levelId,
    );
}
function toggleLevel(levelId: number) {
    const existing = levelAssignment(levelId);
    form.level_assignments = existing
        ? form.level_assignments.filter(
              (assignment) => assignment.school_level_id !== levelId,
          )
        : [
              ...form.level_assignments,
              { school_level_id: levelId, coefficient: 1 },
          ];
}
function updateLevelCoefficient(levelId: number, value: string | number) {
    const assignment = levelAssignment(levelId);
    if (assignment) assignment.coefficient = value;
}
function cycleIsSelected(cycle: Cycle) {
    return cycle.levels.every((level) => Boolean(levelAssignment(level.id)));
}
function cycleHasSelection(cycle: Cycle) {
    return cycle.levels.some((level) => Boolean(levelAssignment(level.id)));
}
function toggleCycle(cycle: Cycle) {
    const levelIds = new Set(cycle.levels.map((level) => level.id));

    if (cycleIsSelected(cycle)) {
        form.level_assignments = form.level_assignments.filter(
            (assignment) => !levelIds.has(assignment.school_level_id),
        );
        return;
    }

    const missing = cycle.levels
        .filter((level) => !levelAssignment(level.id))
        .map((level) => ({ school_level_id: level.id, coefficient: 1 }));
    form.level_assignments = [...form.level_assignments, ...missing];
}
function selectVisibleTeachers() {
    form.teacher_ids = [
        ...new Set([
            ...form.teacher_ids,
            ...filteredTeachers.value.map((teacher) => teacher.id),
        ]),
    ];
}
function clearTeachers() {
    form.teacher_ids = [];
}
function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (modalOpen.value = false),
    };
    if (editing.value) {
        form.put('/admin/subjects/' + editing.value.id, options);
    } else {
        form.post('/admin/subjects', options);
    }
}
function toggleActive(subject: Subject) {
    router.patch(
        `/admin/subjects/${subject.id}/toggle`,
        {},
        { preserveScroll: true },
    );
}
function loadDefaultCurriculum() {
    if (
        confirm(
            'Charger le programme algérien 2026-2027 ? Vos matières personnalisées ne seront pas modifiées.',
        )
    )
        router.post(
            '/admin/subjects/load-default-curriculum',
            {},
            { preserveScroll: true },
        );
}
const assignmentLabel = (level: Level) => {
    return `${level.name}${level.specialization ? ` · ${level.specialization}` : ''}${level.pivot?.is_optional ? ' · Option' : ''}${level.pivot?.coefficient ? ` · Coef. ${level.pivot.coefficient}` : ''}`;
};
function destroySubject(subject: Subject) {
    if (confirm(`Supprimer la matière ${subject.title} ?`))
        router.delete(`/admin/subjects/${subject.id}`, {
            preserveScroll: true,
        });
}
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
const roomTypeLabel = (value: string) =>
    props.roomTypes.find((room) => room.value === value)?.label || value;
</script>

<template>
    <Head title="Matières" /><AdminLayout
        ><main class="flex-1 space-y-6 bg-slate-50/60 p-4 sm:p-6 lg:p-8">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <span class="rounded-xl bg-blue-600 p-2 text-white"
                            ><BookMarked class="size-5" /></span
                        >Matières
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Définissez les matières, niveaux concernés, enseignants
                        habilités et salles adaptées.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="loadDefaultCurriculum"
                        ><Sparkles class="mr-2 size-4" />Charger le programme
                        algérien</Button
                    ><Button @click="openCreate"
                        ><Plus class="mr-2 size-4" />Nouvelle matière</Button
                    >
                </div>
            </header>
            <section class="grid gap-3 sm:grid-cols-3">
                <div class="stat">
                    <BookMarked /><b>{{ subjects.total }}</b
                    ><span>Matières</span>
                </div>
                <div class="stat">
                    <Users /><b>{{
                        new Set(
                            subjects.data.flatMap((s) =>
                                s.teachers.map((t) => t.id),
                            ),
                        ).size
                    }}</b
                    ><span>Enseignants affectés</span>
                </div>
                <div class="stat">
                    <Clock3 /><b
                        >{{
                            subjects.data.reduce(
                                (sum, s) => sum + Number(s.weekly_hours || 0),
                                0,
                            )
                        }}
                        h</b
                    ><span>Volume hebdomadaire affiché</span>
                </div>
            </section>
            <form
                class="grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 xl:grid-cols-5"
                @submit.prevent="applyFilters"
            >
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-slate-400"
                    /><Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Nom ou code…"
                    />
                </div>
                <select v-model="levelFilter" class="control">
                    <option value="">Tous les niveaux</option>
                    <optgroup
                        v-for="cycle in cycles"
                        :key="cycle.id"
                        :label="cycle.name"
                    >
                        <option
                            v-for="level in cycle.levels"
                            :key="level.id"
                            :value="level.id"
                        >
                            {{ level.name
                            }}{{
                                level.specialization
                                    ? ` · ${level.specialization}`
                                    : ''
                            }}
                        </option>
                    </optgroup></select
                ><select v-model="teacherFilter" class="control">
                    <option value="">Tous les enseignants</option>
                    <option
                        v-for="teacher in teachers"
                        :key="teacher.id"
                        :value="teacher.id"
                    >
                        {{ teacher.name }}
                    </option></select
                ><select v-model="statusFilter" class="control">
                    <option value="">Tous les statuts</option>
                    <option value="active">Actives</option>
                    <option value="inactive">Inactives</option></select
                ><Button variant="outline">Filtrer</Button>
            </form>
            <section
                v-if="subjects.data.length"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="subject in subjects.data"
                    :key="subject.id"
                    class="overflow-hidden rounded-2xl border bg-white shadow-sm transition hover:shadow-md"
                >
                    <div class="h-1.5" :style="{ background: subject.color }" />
                    <div class="p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <span
                                    class="text-xs font-semibold tracking-wide text-slate-400 uppercase"
                                    >{{ subject.code }}</span
                                >
                                <h2 class="mt-1 text-lg font-semibold">
                                    {{ subject.title }}
                                </h2>
                                <p
                                    v-if="subject.title_ar"
                                    class="text-sm text-slate-600"
                                    dir="rtl"
                                >
                                    {{ subject.title_ar }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ subject.category || 'Matière générale' }}
                                </p>
                            </div>
                            <button
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    subject.is_active
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-slate-100 text-slate-500'
                                "
                                @click="toggleActive(subject)"
                            >
                                {{ subject.is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            <span
                                v-for="level in subject.school_levels.filter(
                                    (item) => item.pivot?.is_active !== false,
                                )"
                                :key="level.id"
                                class="rounded-full px-2 py-1 text-[11px] font-medium"
                                :class="
                                    level.pivot?.is_active === false
                                        ? 'bg-slate-100 text-slate-400 line-through'
                                        : 'bg-blue-50 text-blue-700'
                                "
                                >{{ assignmentLabel(level) }}</span
                            >
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <div class="metric">
                                <b>{{ subject.weekly_hours }}h</b
                                ><span>/ semaine</span>
                            </div>
                            <div class="metric">
                                <b>{{ subject.teachers.length }}</b
                                ><span>Enseignants</span>
                            </div>
                            <div class="metric">
                                <b>{{ subject.timetable_sessions_count }}</b
                                ><span>Séances</span>
                            </div>
                        </div>
                        <div
                            v-if="subject.required_room_types?.length"
                            class="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-800"
                        >
                            <Beaker class="size-4" />Salle requise :
                            {{
                                subject.required_room_types
                                    .map(roomTypeLabel)
                                    .join(', ')
                            }}
                        </div>
                        <div class="mt-5 flex justify-end gap-2 border-t pt-4">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="openEdit(subject)"
                                ><Pencil
                                    class="mr-1 size-3.5"
                                />Modifier</Button
                            ><Button
                                size="sm"
                                variant="outline"
                                class="text-red-600"
                                @click="destroySubject(subject)"
                                ><Trash2 class="size-3.5"
                            /></Button>
                        </div>
                    </div>
                </article>
            </section>
            <div
                v-else
                class="rounded-2xl border border-dashed bg-white py-16 text-center text-slate-500"
            >
                <BookMarked class="mx-auto mb-3 size-10 text-slate-300" />Aucune
                matière trouvée.
            </div>
            <nav
                v-if="subjects.links.length > 3"
                class="flex flex-wrap justify-center gap-1"
            >
                <Link
                    v-for="link in subjects.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-md border bg-white px-3 py-1.5 text-sm"
                    :class="{
                        'bg-blue-600 text-white': link.active,
                        'pointer-events-none opacity-40': !link.url,
                    }"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
            </nav>
        </main>
        <div v-if="modalOpen" class="overlay" @click.self="modalOpen = false">
            <form class="panel" @submit.prevent="submit">
                <div class="panel-head">
                    <div>
                        <h2>
                            {{
                                editing
                                    ? 'Modifier la matière'
                                    : 'Nouvelle matière'
                            }}
                        </h2>
                        <p>
                            Affectez la matière à chaque niveau ou spécialité et
                            définissez le coefficient utilisé dans les calculs.
                        </p>
                    </div>
                    <button type="button" @click="modalOpen = false">
                        <X />
                    </button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="field"
                        ><span>Nom de la matière</span
                        ><Input
                            v-model="form.title"
                            required
                            placeholder="Mathématiques" /><InputError
                            :message="form.errors.title" /></label
                    ><label class="field"
                        ><span>Nom arabe</span
                        ><Input
                            v-model="form.title_ar"
                            dir="rtl"
                            placeholder="الرياضيات" /><InputError
                            :message="form.errors.title_ar" /></label
                    ><label class="field"
                        ><span>Code</span
                        ><Input
                            v-model="form.code"
                            required
                            placeholder="MATH" /><InputError
                            :message="form.errors.code" /></label
                    ><label class="field"
                        ><span>Catégorie</span
                        ><Input
                            v-model="form.category"
                            placeholder="Sciences" /></label
                    ><label class="field"
                        ><span>Volume hebdomadaire</span
                        ><Input
                            v-model="form.weekly_hours"
                            type="number"
                            step="0.5"
                            min="0.5"
                            max="50"
                            required /><InputError
                            :message="form.errors.weekly_hours" /></label
                    ><label class="field"
                        ><span>Couleur planning</span>
                        <div class="flex gap-2">
                            <input
                                v-model="form.color"
                                type="color"
                                class="h-9 w-12 rounded border"
                            /><Input v-model="form.color" required /></div
                    ></label>
                </div>
                <label class="field"
                    ><span>Description</span
                    ><textarea v-model="form.description" rows="2" />
                </label>
                <div class="mt-4 grid gap-5 lg:grid-cols-[1.25fr_0.75fr]">
                    <section>
                        <div class="section-heading">
                            <div>
                                <p class="label">
                                    <GraduationCap />Niveaux et coefficients
                                </p>
                                <p class="section-help">
                                    Cochez les niveaux concernés puis indiquez
                                    leur coefficient.
                                </p>
                            </div>
                            <span class="selection-count"
                                >{{ form.level_assignments.length }} /
                                {{ allLevels.length }} sélectionnés</span
                            >
                        </div>
                        <div class="level-table">
                            <div class="level-table-head">
                                <span>Niveau / spécialité</span
                                ><span>Coefficient</span>
                            </div>
                            <div
                                v-for="cycle in cycles"
                                :key="cycle.id"
                                class="cycle-group"
                            >
                                <button
                                    type="button"
                                    class="cycle-heading"
                                    @click="toggleCycle(cycle)"
                                >
                                    <span class="cycle-check">
                                        <span v-if="cycleIsSelected(cycle)"
                                            >✓</span
                                        >
                                        <span
                                            v-else-if="cycleHasSelection(cycle)"
                                            >−</span
                                        >
                                    </span>
                                    <span>{{ cycle.name }}</span>
                                    <small
                                        >{{
                                            cycle.levels.filter((level) =>
                                                Boolean(
                                                    levelAssignment(level.id),
                                                ),
                                            ).length
                                        }}/{{ cycle.levels.length }}</small
                                    >
                                </button>
                                <label
                                    v-for="level in cycle.levels"
                                    :key="level.id"
                                    class="level-row"
                                    :class="{
                                        active: levelAssignment(level.id),
                                    }"
                                >
                                    <span class="level-name">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                Boolean(
                                                    levelAssignment(level.id),
                                                )
                                            "
                                            @change="toggleLevel(level.id)"
                                        />
                                        <span>
                                            <strong>{{ level.name }}</strong>
                                            <small
                                                v-if="level.specialization"
                                                >{{
                                                    level.specialization
                                                }}</small
                                            >
                                        </span>
                                    </span>
                                    <span class="coefficient-cell">
                                        <Input
                                            v-if="levelAssignment(level.id)"
                                            :model-value="
                                                levelAssignment(level.id)
                                                    ?.coefficient
                                            "
                                            @update:model-value="
                                                updateLevelCoefficient(
                                                    level.id,
                                                    $event,
                                                )
                                            "
                                            @click.stop
                                            type="number"
                                            min="0.01"
                                            max="100"
                                            step="0.01"
                                            class="h-8 w-24 text-center font-semibold"
                                            :aria-label="
                                                'Coefficient pour ' + level.name
                                            "
                                        />
                                        <span v-else class="coefficient-empty"
                                            >—</span
                                        >
                                    </span>
                                </label>
                            </div>
                        </div>
                        <InputError :message="form.errors.level_assignments" />
                    </section>
                    <section>
                        <div class="section-heading">
                            <div>
                                <p class="label">
                                    <Users />Enseignants habilités
                                </p>
                                <p class="section-help">
                                    Recherche par nom ou adresse e-mail.
                                </p>
                            </div>
                            <span class="selection-count"
                                >{{ selectedTeacherCount }} sélectionné{{
                                    selectedTeacherCount > 1 ? 's' : ''
                                }}</span
                            >
                        </div>
                        <div class="teacher-search">
                            <Search />
                            <Input
                                v-model="teacherSearch"
                                class="pl-9"
                                placeholder="Rechercher un enseignant…"
                            />
                        </div>
                        <div class="picker-actions">
                            <button
                                type="button"
                                @click="selectVisibleTeachers"
                            >
                                Sélectionner les résultats
                            </button>
                            <button type="button" @click="clearTeachers">
                                Tout effacer
                            </button>
                        </div>
                        <div class="teacher-list">
                            <label
                                v-for="teacher in filteredTeachers"
                                :key="teacher.id"
                                :class="{
                                    active: form.teacher_ids.includes(
                                        teacher.id,
                                    ),
                                }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        form.teacher_ids.includes(teacher.id)
                                    "
                                    @change="toggle('teacher_ids', teacher.id)"
                                />
                                <span class="teacher-avatar">{{
                                    teacher.name.charAt(0).toUpperCase()
                                }}</span>
                                <span class="min-w-0">
                                    <strong>{{ teacher.name }}</strong>
                                    <small>{{
                                        teacher.email || 'E-mail non renseigné'
                                    }}</small>
                                </span>
                            </label>
                            <div
                                v-if="!filteredTeachers.length"
                                class="empty-search"
                            >
                                <Search />
                                <span>Aucun enseignant trouvé</span>
                                <small>Essayez un autre nom ou e-mail.</small>
                            </div>
                        </div>
                        <InputError :message="form.errors.teacher_ids" />
                    </section>
                </div>
                <section class="mt-5">
                    <p class="label">
                        <Beaker />Types de salle requis
                        <small>(facultatif)</small>
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="room in roomTypes"
                            :key="room.value"
                            class="chip"
                            :class="
                                form.required_room_types.includes(room.value)
                                    ? 'selected'
                                    : ''
                            "
                            ><input
                                class="sr-only"
                                type="checkbox"
                                :checked="
                                    form.required_room_types.includes(
                                        room.value,
                                    )
                                "
                                @change="
                                    toggle('required_room_types', room.value)
                                "
                            />{{ room.label }}</label
                        >
                    </div>
                    <InputError :message="form.errors.required_room_types" />
                </section>
                <div class="mt-5 flex flex-wrap gap-5">
                    <label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="form.is_specialized"
                            type="checkbox"
                        />Matière spécialisée</label
                    ><label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                        />Matière active</label
                    >
                </div>
                <InputError :message="Object.values(form.errors)[0]" />
                <div class="panel-actions">
                    <Button
                        type="button"
                        variant="outline"
                        @click="modalOpen = false"
                        >Fermer</Button
                    ><Button :disabled="form.processing">Enregistrer</Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
.stat {
    display: grid;
    grid-template-columns: auto auto 1fr;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    background: #fff;
    padding: 1rem;
}
.stat svg {
    grid-row: span 2;
    width: 2rem;
    height: 2rem;
    padding: 0.4rem;
    border-radius: 0.65rem;
    background: #eff6ff;
    color: #2563eb;
}
.stat b {
    font-size: 1.25rem;
}
.stat span {
    grid-column: 2/4;
    font-size: 0.75rem;
    color: #64748b;
}
.control,
.field select,
.field textarea {
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.5rem 0.7rem;
    font-size: 0.875rem;
}
.metric {
    display: flex;
    flex-direction: column;
    border-radius: 0.6rem;
    background: #f8fafc;
    padding: 0.6rem;
    text-align: center;
}
.metric b {
    font-size: 0.9rem;
}
.metric span {
    font-size: 0.65rem;
    color: #64748b;
}
.overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: grid;
    place-items: center;
    overflow: auto;
    background: rgb(15 23 42/0.4);
    padding: 1rem;
}
.panel {
    width: 100%;
    max-width: 980px;
    max-height: 95vh;
    overflow: auto;
    border-radius: 1rem;
    background: #fff;
    padding: 1.25rem;
    box-shadow: 0 20px 50px rgb(15 23 42/0.2);
}
.panel-head {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}
.panel-head h2 {
    font-size: 1.2rem;
    font-weight: 600;
}
.panel-head p {
    font-size: 0.78rem;
    color: #64748b;
}
.panel-head svg {
    width: 1.2rem;
}
.field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 0.8rem;
}
.field > span,
.label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
}
.label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.label svg {
    width: 1rem;
}
.section-heading {
    display: flex;
    min-height: 2.75rem;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}
.section-help {
    margin-top: 0.15rem;
    font-size: 0.7rem;
    color: #64748b;
}
.selection-count {
    flex-shrink: 0;
    border-radius: 999px;
    background: #eff6ff;
    padding: 0.25rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: #1d4ed8;
}
.level-table,
.teacher-list {
    max-height: 290px;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #fff;
}
.level-table-head {
    position: sticky;
    top: 0;
    z-index: 2;
    display: grid;
    grid-template-columns: 1fr 7rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 0.55rem 0.75rem;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #64748b;
    text-transform: uppercase;
}
.level-table-head span:last-child {
    text-align: center;
}
.cycle-group + .cycle-group {
    border-top: 1px solid #e2e8f0;
}
.cycle-heading {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.5rem;
    background: #f8fafc;
    padding: 0.5rem 0.75rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
}
.cycle-heading:hover {
    background: #f1f5f9;
}
.cycle-heading small {
    margin-left: auto;
    color: #94a3b8;
}
.cycle-check {
    display: grid;
    width: 1rem;
    height: 1rem;
    place-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 0.25rem;
    background: #fff;
    font-size: 0.7rem;
    color: #2563eb;
}
.level-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 7rem;
    min-height: 3rem;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding: 0.4rem 0.75rem;
    transition: background 150ms ease;
}
.level-row:hover,
.level-row.active {
    background: #f8fbff;
}
.level-name {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.65rem;
}
.level-name > span {
    display: flex;
    min-width: 0;
    flex-direction: column;
}
.level-name strong {
    overflow: hidden;
    font-size: 0.78rem;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.level-name small {
    overflow: hidden;
    font-size: 0.66rem;
    color: #64748b;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.coefficient-cell {
    display: flex;
    justify-content: center;
}
.coefficient-empty {
    color: #cbd5e1;
}
.teacher-search {
    position: relative;
    margin-bottom: 0.45rem;
}
.teacher-search > svg {
    position: absolute;
    top: 0.65rem;
    left: 0.75rem;
    z-index: 1;
    width: 1rem;
    color: #94a3b8;
}
.picker-actions {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.45rem;
    font-size: 0.68rem;
}
.picker-actions button {
    color: #2563eb;
}
.picker-actions button:hover {
    text-decoration: underline;
}
.teacher-list label {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1fr);
    align-items: center;
    gap: 0.6rem;
    border-bottom: 1px solid #f1f5f9;
    padding: 0.55rem 0.65rem;
    cursor: pointer;
}
.teacher-list label:hover,
.teacher-list label.active {
    background: #f8fbff;
}
.teacher-list strong,
.teacher-list small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.teacher-list strong {
    font-size: 0.78rem;
}
.teacher-list small {
    font-size: 0.66rem;
    color: #94a3b8;
}
.teacher-avatar {
    display: grid;
    width: 1.8rem;
    height: 1.8rem;
    place-items: center;
    border-radius: 999px;
    background: #dbeafe;
    font-size: 0.7rem;
    font-weight: 700;
    color: #1d4ed8;
}
.empty-search {
    display: flex;
    min-height: 9rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #64748b;
}
.empty-search svg {
    margin-bottom: 0.4rem;
    width: 1.25rem;
    color: #94a3b8;
}
.empty-search span {
    font-size: 0.8rem;
    font-weight: 600;
}
.empty-search small {
    margin-top: 0.15rem;
    font-size: 0.68rem;
    color: #94a3b8;
}
.selection {
    margin-top: 0.5rem;
    display: flex;
    max-height: 220px;
    flex-direction: column;
    gap: 0.25rem;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 0.6rem;
}
.selection div {
    display: flex;
    flex-direction: column;
}
.selection b {
    padding: 0.35rem;
    font-size: 0.72rem;
    color: #2563eb;
}
.selection label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.4rem;
    padding: 0.4rem;
    font-size: 0.8rem;
}
.selection label:hover {
    background: #eff6ff;
}
.selection small {
    display: block;
    color: #94a3b8;
}
.chip {
    cursor: pointer;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 0.4rem 0.7rem;
    font-size: 0.75rem;
}
.chip.selected {
    border-color: #60a5fa;
    background: #eff6ff;
    color: #1d4ed8;
}
.panel-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    border-top: 1px solid #e2e8f0;
    margin-top: 1.25rem;
    padding-top: 1rem;
}
</style>
