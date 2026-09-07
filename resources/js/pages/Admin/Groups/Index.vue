<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    GraduationCap,
    Layers3,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Person = { id: number; name: string; email?: string };
type Level = {
    id: number;
    school_cycle_id: number;
    name: string;
    code: string;
    specialization?: string | null;
    is_active: boolean;
    cycle?: { id: number; name: string };
};
type Cycle = { id: number; name: string; code: string; levels: Level[] };
type Room = {
    id: number;
    name: string;
    code: string;
    capacity: number;
    is_active: boolean;
    is_available: boolean;
};
type Student = {
    id: number;
    first_name: string;
    last_name: string;
    email?: string;
    school_level?: string;
    is_active?: boolean;
    school_group_id?: number | null;
    group?: { id: number; name: string } | null;
};
type AcademicYear = { id: number; name: string };
type Group = {
    id: number;
    name: string;
    code: string;
    school_level_id: number;
    academic_period_id?: number | null;
    classroom_id?: number | null;
    principal_teacher_id?: number | null;
    capacity: number;
    is_active: boolean;
    level: Level;
    academic_year?: AcademicYear;
    classroom?: Room | null;
    principal_teacher?: Person | null;
    teachers: Person[];
    students_count: number;
    timetable_sessions_count: number;
};
type Page<T> = {
    data: T[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    groups: Page<Group>;
    cycles: Cycle[];
    academicYear?: AcademicYear;
    classrooms: Room[];
    teachers: Person[];
    students: Student[];
    filters: Record<string, string>;
}>();
const modalOpen = ref(false);
const levelModal = ref(false);
const detailsOpen = ref(false);
const rosterOpen = ref(false);
const editing = ref<Group | null>(null);
const editingLevel = ref<Level | null>(null);
const current = ref<(Group & { students: Student[] }) | null>(null);
const search = ref(props.filters.search || '');
const cycleFilter = ref(props.filters.cycle_id || '');
const levelFilter = ref(props.filters.level_id || '');
const statusFilter = ref(props.filters.status || '');
const studentSearch = ref('');
const form = useForm({
    name: '',
    code: '',
    school_level_id: '',
    classroom_id: '',
    capacity: 30,
    is_active: true,
    teacher_ids: [] as number[],
    principal_teacher_id: '',
});
const levelForm = useForm({
    school_cycle_id: '',
    name: '',
    code: '',
    specialization: '',
    is_active: true,
});
const rosterForm = useForm({
    student_ids: [] as number[],
    move_existing: false,
});
const levels = computed(() => props.cycles.flatMap((cycle) => cycle.levels));
const filteredLevels = computed(() =>
    levels.value.filter(
        (level) =>
            !cycleFilter.value ||
            level.school_cycle_id === Number(cycleFilter.value),
    ),
);
const selectedRoom = computed(() =>
    props.classrooms.find((room) => room.id === Number(form.classroom_id)),
);
const assignableStudents = computed(() =>
    props.students.filter(
        (student) =>
            student.school_group_id !== current.value?.id &&
            (!student.school_group_id || rosterForm.move_existing),
    ),
);
const visibleAssignableStudents = computed(() => {
    const query = studentSearch.value.trim().toLocaleLowerCase('fr');
    if (!query) return assignableStudents.value;

    return assignableStudents.value.filter((student) =>
        [
            student.first_name,
            student.last_name,
            student.first_name + ' ' + student.last_name,
            student.last_name + ' ' + student.first_name,
            student.email,
            student.school_level,
            student.group?.name,
        ].some((value) => value?.toLocaleLowerCase('fr').includes(query)),
    );
});
watch(
    () => rosterForm.move_existing,
    (canMove) => {
        if (!canMove) {
            rosterForm.student_ids = rosterForm.student_ids.filter((id) =>
                props.students.some(
                    (student) => student.id === id && !student.school_group_id,
                ),
            );
        }
    },
);

function applyFilters() {
    router.get(
        '/admin/groups',
        {
            search: search.value,
            cycle_id: cycleFilter.value,
            level_id: levelFilter.value,
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
        capacity: 30,
        is_active: true,
        teacher_ids: [],
        principal_teacher_id: '',
    });
    modalOpen.value = true;
}
function openEdit(group: Group) {
    editing.value = group;
    form.clearErrors();
    Object.assign(form, {
        name: group.name,
        code: group.code,
        school_level_id: String(group.school_level_id || ''),
        classroom_id: String(group.classroom_id || ''),
        capacity: group.capacity,
        is_active: group.is_active,
        teacher_ids: group.teachers.map((t) => t.id),
        principal_teacher_id: String(group.principal_teacher_id || ''),
    });
    modalOpen.value = true;
}
function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (modalOpen.value = false),
    };
    editing.value
        ? form.put(`/admin/groups/${editing.value.id}`, options)
        : form.post('/admin/groups', options);
}
function toggleTeacher(id: number) {
    form.teacher_ids = form.teacher_ids.includes(id)
        ? form.teacher_ids.filter((item) => item !== id)
        : [...form.teacher_ids, id];
    if (
        form.principal_teacher_id &&
        !form.teacher_ids.includes(Number(form.principal_teacher_id))
    )
        form.principal_teacher_id = '';
}
async function showGroup(group: Group) {
    const response = await fetch(`/admin/groups/${group.id}`, {
        headers: { Accept: 'application/json' },
    });
    current.value = await response.json();
    detailsOpen.value = true;
}
function destroyGroup(group: Group) {
    if (confirm(`Supprimer le groupe ${group.name} ?`))
        router.delete(`/admin/groups/${group.id}`, {
            preserveScroll: true,
            onError: (errors) =>
                alert(
                    String(
                        errors.group || 'Impossible de supprimer ce groupe.',
                    ),
                ),
        });
}
function openRoster() {
    rosterForm.reset();
    rosterForm.clearErrors();
    studentSearch.value = '';
    rosterOpen.value = true;
}
function assignStudents() {
    if (!current.value) return;
    rosterForm.post(`/admin/groups/${current.value.id}/students`, {
        preserveScroll: true,
        onSuccess: async () => {
            rosterOpen.value = false;
            const source = props.groups.data.find(
                (g) => g.id === current.value?.id,
            );
            if (source) await showGroup(source);
        },
    });
}
function removeStudent(student: Student) {
    if (
        !current.value ||
        !confirm(
            `Retirer ${student.first_name} ${student.last_name} du groupe ?`,
        )
    )
        return;
    router.delete(`/admin/groups/${current.value.id}/students/${student.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (current.value)
                current.value.students = current.value.students.filter(
                    (item) => item.id !== student.id,
                );
        },
    });
}
function openLevel(level: Level | null = null) {
    editingLevel.value = level;
    levelForm.reset();
    levelForm.clearErrors();
    Object.assign(levelForm, {
        school_cycle_id: String(
            level?.school_cycle_id || props.cycles[0]?.id || '',
        ),
        name: level?.name || '',
        code: level?.code || '',
        specialization: level?.specialization || '',
        is_active: level?.is_active ?? true,
    });
    levelModal.value = true;
}
function submitLevel() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (levelModal.value = false),
    };
    editingLevel.value
        ? levelForm.put(
              `/admin/school-levels/${editingLevel.value.id}`,
              options,
          )
        : levelForm.post('/admin/school-levels', options);
}
function deleteLevel(level: Level) {
    if (confirm(`Supprimer le niveau ${level.name} ?`))
        router.delete(`/admin/school-levels/${level.id}`, {
            preserveScroll: true,
        });
}
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
</script>

<template>
    <Head title="Groupes et niveaux" /><AdminLayout
        ><main class="flex-1 space-y-6 bg-slate-50/60 p-4 sm:p-6 lg:p-8">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <span class="rounded-xl bg-blue-600 p-2 text-white"
                            ><Users class="size-5" /></span
                        >Groupes & niveaux
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Structurez les classes, affectez les élèves et
                        définissez les équipes pédagogiques.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="openLevel()"
                        ><Layers3 class="mr-2 size-4" />Nouveau niveau</Button
                    ><Button @click="openCreate"
                        ><Plus class="mr-2 size-4" />Nouveau groupe</Button
                    >
                </div>
            </header>
            <section class="grid gap-3 sm:grid-cols-3">
                <div class="stat">
                    <Users /><b>{{ groups.total }}</b
                    ><span>Groupes</span>
                </div>
                <div class="stat">
                    <GraduationCap /><b>{{
                        groups.data.reduce(
                            (sum, g) => sum + g.students_count,
                            0,
                        )
                    }}</b
                    ><span>Élèves affichés</span>
                </div>
                <div class="stat">
                    <UserCheck /><b>{{
                        groups.data.filter((g) => g.principal_teacher).length
                    }}</b
                    ><span>Professeurs principaux</span>
                </div>
            </section>
            <form
                class="grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-3 xl:grid-cols-7"
                @submit.prevent="applyFilters"
            >
                <div class="relative md:col-span-2">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-slate-400"
                    /><Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Nom ou code du groupe…"
                    />
                </div>
                <select v-model="cycleFilter" class="control">
                    <option value="">Tous les cycles</option>
                    <option
                        v-for="cycle in cycles"
                        :key="cycle.id"
                        :value="cycle.id"
                    >
                        {{ cycle.name }}
                    </option></select
                ><select v-model="levelFilter" class="control">
                    <option value="">Tous les niveaux</option>
                    <option
                        v-for="level in filteredLevels"
                        :key="level.id"
                        :value="level.id"
                    >
                        {{ level.name }} {{ level.specialization || '' }}
                    </option></select
                ><select v-model="statusFilter" class="control">
                    <option value="">Tous les statuts</option>
                    <option value="active">Groupes actifs</option>
                    <option value="inactive">Groupes inactifs</option></select
                ><Button variant="outline">Filtrer</Button>
            </form>
            <section
                v-if="groups.data.length"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="group in groups.data"
                    :key="group.id"
                    class="rounded-2xl border bg-white p-5 shadow-sm transition hover:border-blue-200 hover:shadow-md"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div
                                class="inline-flex flex-col rounded-xl bg-blue-50 px-3 py-1.5 text-blue-700"
                            >
                                <span class="text-xs font-semibold"
                                    >{{ group.level?.cycle?.name }} ·
                                    {{ group.level?.name }}</span
                                >
                                <span
                                    v-if="group.level?.specialization"
                                    class="mt-0.5 text-[11px] font-medium text-blue-600"
                                >
                                    {{ group.level.specialization }}
                                </span>
                            </div>
                            <h2 class="mt-3 text-lg font-semibold">
                                {{ group.name }}
                            </h2>
                            <p class="text-xs text-slate-500">
                                {{ group.code }} ·
                                {{ group.academic_year?.name }}
                            </p>
                        </div>
                        <span
                            class="size-2.5 rounded-full"
                            :class="
                                group.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-slate-300'
                            "
                        />
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="metric">
                            <b
                                >{{ group.students_count }}/{{
                                    group.capacity
                                }}</b
                            ><span>Élèves</span>
                        </div>
                        <div class="metric">
                            <b>{{ group.teachers.length }}</b
                            ><span>Enseignants</span>
                        </div>
                        <div class="metric">
                            <b>{{ group.timetable_sessions_count }}</b
                            ><span>Séances</span>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2 text-sm">
                        <p class="flex items-center gap-2">
                            <UserCheck class="size-4 text-blue-600" /><span
                                class="truncate"
                                >{{
                                    group.principal_teacher?.name ||
                                    'Professeur principal non défini'
                                }}</span
                            >
                        </p>
                        <p class="flex items-center gap-2 text-slate-500">
                            <Building2 class="size-4" /><span
                                class="truncate"
                                >{{
                                    group.classroom?.name ||
                                    'Aucune salle par défaut'
                                }}</span
                            >
                        </p>
                    </div>
                    <div class="mt-5 flex gap-2 border-t pt-4">
                        <Button
                            size="sm"
                            variant="outline"
                            class="flex-1"
                            @click="showGroup(group)"
                            ><Users class="mr-1.5 size-3.5" />Effectif</Button
                        ><Button
                            size="sm"
                            variant="outline"
                            @click="openEdit(group)"
                            ><Pencil class="size-3.5" /></Button
                        ><Button
                            size="sm"
                            variant="outline"
                            class="text-red-600"
                            @click="destroyGroup(group)"
                            ><Trash2 class="size-3.5"
                        /></Button>
                    </div>
                </article>
            </section>
            <div
                v-else
                class="rounded-2xl border border-dashed bg-white py-16 text-center text-slate-500"
            >
                <Users class="mx-auto mb-3 size-10 text-slate-300" />
                <p>Aucun groupe ne correspond aux critères.</p>
            </div>
            <nav
                v-if="groups.links.length > 3"
                class="flex flex-wrap justify-center gap-1"
            >
                <Link
                    v-for="link in groups.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-md border bg-white px-3 py-1.5 text-sm"
                    :class="{
                        'bg-blue-600 text-white': link.active,
                        'pointer-events-none opacity-40': !link.url,
                    }"
                    v-html="paginationLabel(link.label)"
                />
            </nav>
            <section class="rounded-2xl border bg-white p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold">Niveaux scolaires</h2>
                        <p class="text-sm text-slate-500">
                            Les spécialisations restent libres, particulièrement
                            au lycée.
                        </p>
                    </div>
                    <Button size="sm" @click="openLevel()"
                        ><Plus class="mr-1 size-4" />Ajouter</Button
                    >
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div
                        v-for="cycle in cycles"
                        :key="cycle.id"
                        class="rounded-xl bg-slate-50 p-4"
                    >
                        <h3 class="font-semibold text-blue-700">
                            {{ cycle.name }}
                        </h3>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="level in cycle.levels"
                                :key="level.id"
                                class="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm"
                            >
                                <span
                                    >{{ level.name
                                    }}<small
                                        v-if="level.specialization"
                                        class="ml-1 text-slate-400"
                                        >· {{ level.specialization }}</small
                                    ></span
                                ><span class="flex gap-1"
                                    ><button
                                        class="p-1 text-slate-500"
                                        @click="openLevel(level)"
                                    >
                                        <Pencil class="size-3.5" /></button
                                    ><button
                                        class="p-1 text-red-500"
                                        @click="deleteLevel(level)"
                                    >
                                        <Trash2 class="size-3.5" /></button
                                ></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        <div v-if="modalOpen" class="overlay" @click.self="modalOpen = false">
            <form class="panel max-w-2xl" @submit.prevent="submit">
                <div class="panel-head">
                    <div>
                        <h2>
                            {{
                                editing
                                    ? 'Modifier le groupe'
                                    : 'Créer un groupe'
                            }}
                        </h2>
                        <p>
                            Un groupe appartient à un niveau et une année
                            scolaire.
                        </p>
                    </div>
                    <button type="button" @click="modalOpen = false">
                        <X />
                    </button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="field"
                        ><span>Nom</span
                        ><Input
                            v-model="form.name"
                            required
                            placeholder="2AM-B" /><InputError
                            :message="form.errors.name" /></label
                    ><label class="field"
                        ><span>Code</span
                        ><Input
                            v-model="form.code"
                            required
                            placeholder="2AM-B-26" /><InputError
                            :message="form.errors.code" /></label
                    ><label class="field"
                        ><span>Niveau</span
                        ><select v-model="form.school_level_id" required>
                            <option value="">Sélectionner</option>
                            <optgroup
                                v-for="cycle in cycles"
                                :key="cycle.id"
                                :label="cycle.name"
                            >
                                <option
                                    v-for="level in cycle.levels.filter(
                                        (l) => l.is_active,
                                    )"
                                    :key="level.id"
                                    :value="level.id"
                                >
                                    {{ level.name }}
                                    {{ level.specialization || '' }}
                                </option>
                            </optgroup></select
                        ><InputError
                            :message="form.errors.school_level_id" /></label
                    ><label class="field"
                        ><span>Salle par défaut</span
                        ><select v-model="form.classroom_id">
                            <option value="">Aucune</option>
                            <option
                                v-for="room in classrooms"
                                :key="room.id"
                                :value="room.id"
                                :disabled="
                                    !room.is_active || !room.is_available
                                "
                            >
                                {{ room.name }} ({{ room.capacity }})
                            </option></select
                        ><InputError
                            :message="form.errors.classroom_id" /></label
                    ><label class="field"
                        ><span>Capacité</span
                        ><Input
                            v-model="form.capacity"
                            type="number"
                            min="1"
                            :max="selectedRoom?.capacity || 1000"
                            required /><InputError
                            :message="form.errors.capacity"
                    /></label>
                </div>
                <div class="mt-5">
                    <p class="label">Équipe enseignante</p>
                    <div
                        class="mt-2 grid max-h-40 gap-2 overflow-auto rounded-xl border p-3 sm:grid-cols-2"
                    >
                        <label
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            class="flex cursor-pointer items-center gap-2 rounded-lg p-2 text-sm hover:bg-blue-50"
                            ><input
                                type="checkbox"
                                :checked="form.teacher_ids.includes(teacher.id)"
                                @change="toggleTeacher(teacher.id)"
                            />{{ teacher.name }}</label
                        >
                    </div>
                    <InputError :message="form.errors.teacher_ids" />
                </div>
                <label class="field mt-4"
                    ><span>Professeur principal</span
                    ><select v-model="form.principal_teacher_id">
                        <option value="">Non défini</option>
                        <option
                            v-for="teacher in teachers.filter((t) =>
                                form.teacher_ids.includes(t.id),
                            )"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.name }}
                        </option></select
                    ><InputError
                        :message="form.errors.principal_teacher_id" /></label
                ><label class="mt-4 flex items-center gap-2 text-sm"
                    ><input v-model="form.is_active" type="checkbox" />Groupe
                    actif</label
                >
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
        <div v-if="levelModal" class="overlay" @click.self="levelModal = false">
            <form class="panel max-w-md" @submit.prevent="submitLevel">
                <div class="panel-head">
                    <div>
                        <h2>
                            {{
                                editingLevel
                                    ? 'Modifier le niveau'
                                    : 'Nouveau niveau'
                            }}
                        </h2>
                        <p>Ajoutez librement une filière ou spécialisation.</p>
                    </div>
                    <button type="button" @click="levelModal = false">
                        <X />
                    </button>
                </div>
                <label class="field"
                    ><span>Cycle</span
                    ><select v-model="levelForm.school_cycle_id" required>
                        <option
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.name }}
                        </option>
                    </select></label
                ><label class="field"
                    ><span>Nom</span
                    ><Input
                        v-model="levelForm.name"
                        required
                        placeholder="2AS Sciences" /></label
                ><label class="field"
                    ><span>Code</span
                    ><Input
                        v-model="levelForm.code"
                        required
                        placeholder="2AS-SCI" /></label
                ><label class="field"
                    ><span>Filière / spécialisation (facultatif)</span
                    ><Input
                        v-model="levelForm.specialization"
                        placeholder="Sciences expérimentales" /></label
                ><label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="levelForm.is_active"
                        type="checkbox"
                    />Niveau actif</label
                ><InputError :message="Object.values(levelForm.errors)[0]" />
                <div class="panel-actions">
                    <Button
                        type="button"
                        variant="outline"
                        @click="levelModal = false"
                        >Fermer</Button
                    ><Button :disabled="levelForm.processing"
                        >Enregistrer</Button
                    >
                </div>
            </form>
        </div>
        <div
            v-if="detailsOpen && current"
            class="overlay"
            @click.self="detailsOpen = false"
        >
            <div class="panel max-w-3xl">
                <div class="panel-head">
                    <div>
                        <h2>{{ current.name }} · Effectif</h2>
                        <p>
                            {{ current.students.length }} élève(s) sur
                            {{ current.capacity }}
                        </p>
                    </div>
                    <button @click="detailsOpen = false"><X /></button>
                </div>
                <div class="flex justify-end">
                    <Button size="sm" @click="openRoster"
                        ><Plus class="mr-1 size-4" />Affecter des élèves</Button
                    >
                </div>
                <div class="mt-4 max-h-[55vh] overflow-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left">
                            <tr>
                                <th class="p-3">Élève</th>
                                <th class="p-3">Niveau précédent</th>
                                <th class="p-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="student in current.students"
                                :key="student.id"
                                class="border-t"
                            >
                                <td class="p-3 font-medium">
                                    {{ student.first_name }}
                                    {{ student.last_name
                                    }}<small class="block text-slate-400">{{
                                        student.email
                                    }}</small>
                                </td>
                                <td class="p-3 text-slate-500">
                                    {{ student.school_level || '—' }}
                                </td>
                                <td class="p-3 text-right">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="text-red-600"
                                        @click="removeStudent(student)"
                                        >Retirer</Button
                                    >
                                </td>
                            </tr>
                            <tr v-if="!current.students.length">
                                <td
                                    colspan="3"
                                    class="p-10 text-center text-slate-400"
                                >
                                    Aucun élève affecté.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div
            v-if="rosterOpen"
            class="overlay z-[60]"
            @click.self="rosterOpen = false"
        >
            <form class="panel max-w-lg" @submit.prevent="assignStudents">
                <div class="panel-head">
                    <div>
                        <h2>Affecter des élèves</h2>
                        <p>Un élève ne peut appartenir qu’à un seul groupe.</p>
                    </div>
                    <button type="button" @click="rosterOpen = false">
                        <X />
                    </button>
                </div>
                <div class="relative mb-3">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-slate-400"
                    />
                    <Input
                        v-model="studentSearch"
                        class="pl-9"
                        placeholder="Rechercher par nom, e-mail ou niveau…"
                        autofocus
                    />
                </div>
                <div
                    class="max-h-80 space-y-1 overflow-auto rounded-xl border p-2"
                >
                    <label
                        v-for="student in visibleAssignableStudents"
                        :key="student.id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-blue-50"
                        ><input
                            v-model="rosterForm.student_ids"
                            type="checkbox"
                            :value="student.id"
                        /><span class="text-sm"
                            ><b
                                >{{ student.first_name }}
                                {{ student.last_name }}</b
                            ><small class="block text-slate-400">{{
                                student.group
                                    ? `Groupe actuel : ${student.group.name}`
                                    : student.school_level ||
                                      'Niveau non renseigné'
                            }}</small></span
                        ></label
                    >
                    <p
                        v-if="!visibleAssignableStudents.length"
                        class="p-6 text-center text-sm text-slate-400"
                    >
                        {{
                            studentSearch
                                ? 'Aucun élève ne correspond à la recherche.'
                                : 'Aucun élève disponible.'
                        }}
                    </p>
                </div>
                <InputError :message="rosterForm.errors.student_ids" />
                <label
                    class="mt-3 flex items-start gap-2 text-sm text-slate-600"
                >
                    <input
                        v-model="rosterForm.move_existing"
                        type="checkbox"
                        class="mt-0.5"
                    />
                    Autoriser le déplacement depuis un autre groupe
                </label>
                <div class="panel-actions">
                    <Button
                        type="button"
                        variant="outline"
                        @click="rosterOpen = false"
                        >Fermer</Button
                    ><Button
                        :disabled="
                            rosterForm.processing ||
                            !rosterForm.student_ids.length
                        "
                        >Affecter</Button
                    >
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
.field select {
    height: 2.3rem;
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0 0.7rem;
    font-size: 0.875rem;
}
.metric {
    display: flex;
    flex-direction: column;
    border-radius: 0.6rem;
    background: #f8fafc;
    padding: 0.6rem;
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
    max-height: 95vh;
    overflow: auto;
    border-radius: 1rem;
    background: #fff;
    padding: 1.25rem;
    box-shadow: 0 20px 50px rgb(15 23 42/0.2);
}
.panel-head {
    display: flex;
    align-items: flex-start;
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
.panel-head button svg {
    width: 1.2rem;
}
.field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 0.85rem;
}
.field > span,
.label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
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
