<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarCheck,
    Check,
    Clock3,
    FileText,
    UserRoundCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

type Exception = {
    id: number;
    reason?: string;
    justification?: string;
    minutes_late?: number;
};
type Student = {
    id: number;
    first_name: string;
    last_name: string;
    attendance_status: string;
    attendance_exception?: Exception;
};
type Session = {
    id: number;
    start_time: string;
    end_time: string;
    subject?: { title: string };
    teacher?: { id: number; name: string };
    group?: { name: string; level?: { name: string } };
    attendance_status: string;
    attendance_exception?: Exception;
    students?: Student[];
};
type Level = { id: number; name: string; specialization?: string };
type Cycle = { id: number; name: string; levels: Level[] };
type Group = {
    id: number;
    name: string;
    level: Level & { cycle?: { name: string } };
};
type Year = { id: number; name: string; start_date: string; end_date: string };
const props = defineProps<{
    academicYears: Year[];
    academicYear: Year;
    date: string;
    view: 'students' | 'teachers';
    filters: { cycle_id?: number; level_id?: number; group_id?: number };
    cycles: Cycle[];
    groups: Group[];
    sessions: Session[];
    dashboard: {
        students_absent: number;
        students_late: number;
        teachers_absent: number;
        affected_classes: number;
    };
    warnings: Array<{
        student_id: number;
        student: string;
        monthly_absences: number;
        consecutive_days: number;
    }>;
    attendanceSettings: {
        monthly_absence_threshold: number;
        consecutive_days_threshold: number;
    };
    teacherImpacts: Array<{
        teacher: string;
        sessions: Array<{
            id: number;
            group: string;
            subject: string;
            start_time: string;
        }>;
    }>;
}>();

const filters = reactive({
    academic_year_id: props.academicYear.id,
    date: props.date,
    view: props.view,
    cycle_id: props.filters.cycle_id || '',
    level_id: props.filters.level_id || '',
    group_id: props.filters.group_id || '',
});
const selectedSessionId = ref<number | null>(props.sessions[0]?.id || null);
const selectedStudents = ref<number[]>([]);
const rowInputs = reactive<
    Record<
        number,
        { reason: string; justification: string; minutes_late: number }
    >
>({});
const selectedSession = computed(() =>
    props.sessions.find((session) => session.id === selectedSessionId.value),
);
const levels = computed(
    () =>
        props.cycles.find((cycle) => cycle.id === Number(filters.cycle_id))
            ?.levels || props.cycles.flatMap((cycle) => cycle.levels),
);
const statusLabels: Record<string, string> = {
    PRESENT: 'Présent',
    ABSENT: 'Absent',
    LATE: 'En retard',
    EXCUSED: 'Excusé',
    LEFT_EARLY: 'Parti tôt',
};
const statusClasses: Record<string, string> = {
    PRESENT: 'bg-emerald-50 text-emerald-700',
    ABSENT: 'bg-red-50 text-red-700',
    LATE: 'bg-amber-50 text-amber-700',
    EXCUSED: 'bg-blue-50 text-blue-700',
    LEFT_EARLY: 'bg-violet-50 text-violet-700',
};

function applyFilters(reset: 'cycle' | 'level' | null = null) {
    if (reset === 'cycle') {
        filters.level_id = '';
        filters.group_id = '';
    }
    if (reset === 'level') filters.group_id = '';
    router.get('/admin/school-attendance', filters, {
        preserveState: true,
        replace: true,
    });
}
function inputFor(id: number, exception?: Exception) {
    return (rowInputs[id] ||= {
        reason: exception?.reason || '',
        justification: exception?.justification || '',
        minutes_late: exception?.minutes_late || 10,
    });
}
function mark(
    person: Student | NonNullable<Session['teacher']>,
    type: 'STUDENT' | 'TEACHER',
    status: string,
    fullDay = false,
    session = selectedSession.value,
) {
    const exception =
        type === 'STUDENT'
            ? (person as Student).attendance_exception
            : session?.attendance_exception;
    if (status === 'PRESENT') {
        if (exception?.id)
            router.delete(
                `/admin/school-attendance/exceptions/${exception.id}`,
                { preserveScroll: true },
            );
        return;
    }
    const values = inputFor(person.id, exception);
    router.post(
        '/admin/school-attendance/exceptions',
        {
            academic_year_id: props.academicYear.id,
            date: props.date,
            timetable_session_id: fullDay ? null : session?.id,
            person_type: type,
            student_id: type === 'STUDENT' ? person.id : null,
            teacher_id: type === 'TEACHER' ? person.id : null,
            status,
            minutes_late: status === 'LATE' ? values.minutes_late : null,
            reason: values.reason,
            justification: values.justification,
        },
        { preserveScroll: true },
    );
}
function bulkAbsent(fullDay = false) {
    if (!selectedStudents.value.length) return;
    router.post(
        '/admin/school-attendance/students/bulk',
        {
            academic_year_id: props.academicYear.id,
            date: props.date,
            timetable_session_id: fullDay ? null : selectedSessionId.value,
            student_ids: selectedStudents.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => (selectedStudents.value = []),
        },
    );
}

const teacherModal = ref(false);
const teacherForm = reactive({
    teacher_id: 0,
    teacher_name: '',
    date: props.date,
    end_date: props.date,
    status: 'ABSENT',
    reason: '',
    justification: '',
    minutes_late: 10,
});
const preview = ref<
    Array<{
        id: number;
        date: string;
        start_time: string;
        end_time: string;
        subject?: string;
        group?: string;
    }>
>([]);
const previewing = ref(false);
const warningSettings = reactive({ ...props.attendanceSettings });
function saveWarningSettings() {
    router.put('/admin/school-attendance/settings', warningSettings, {
        preserveScroll: true,
    });
}
async function openTeacherRange(session: Session) {
    if (!session.teacher) return;
    Object.assign(teacherForm, {
        teacher_id: session.teacher.id,
        teacher_name: session.teacher.name,
        date: props.date,
        end_date: props.date,
        status: 'ABSENT',
        reason: '',
        justification: '',
        minutes_late: 10,
    });
    teacherModal.value = true;
    await loadPreview();
}
async function loadPreview() {
    previewing.value = true;
    const query = new URLSearchParams({
        academic_year_id: String(props.academicYear.id),
        teacher_id: String(teacherForm.teacher_id),
        date: teacherForm.date,
        end_date: teacherForm.end_date,
    });
    const response = await fetch(
        `/admin/school-attendance/teacher-preview?${query}`,
        { headers: { Accept: 'application/json' } },
    );
    preview.value = response.ok ? (await response.json()).sessions : [];
    previewing.value = false;
}
function saveTeacherRange() {
    router.post(
        '/admin/school-attendance/exceptions',
        {
            academic_year_id: props.academicYear.id,
            ...teacherForm,
            timetable_session_id: null,
            person_type: 'TEACHER',
            minutes_late:
                teacherForm.status === 'LATE' ? teacherForm.minutes_late : null,
        },
        { preserveScroll: true, onSuccess: () => (teacherModal.value = false) },
    );
}
</script>

<template>
    <Head title="Présences" />
    <AdminLayout>
        <main
            class="min-h-full flex-1 space-y-5 bg-slate-50/60 p-4 sm:p-6 lg:p-8"
        >
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <span class="rounded-xl bg-blue-600 p-2 text-white"
                            ><CalendarCheck class="size-5" /></span
                        >Présences
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        La présence est automatique : enregistrez uniquement les
                        exceptions.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="/admin/school-attendance/reports"
                        ><Button variant="outline"
                            ><FileText class="size-4" />Rapports</Button
                        ></a
                    >
                    <div class="flex rounded-xl border bg-white p-1">
                        <button
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm"
                            :class="
                                view === 'students'
                                    ? 'bg-blue-600 text-white'
                                    : ''
                            "
                            @click="
                                filters.view = 'students';
                                applyFilters();
                            "
                        >
                            <Users class="size-4" />Élèves</button
                        ><button
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm"
                            :class="
                                view === 'teachers'
                                    ? 'bg-blue-600 text-white'
                                    : ''
                            "
                            @click="
                                filters.view = 'teachers';
                                applyFilters();
                            "
                        >
                            <UserRoundCheck class="size-4" />Enseignants
                        </button>
                    </div>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="indicator in [
                        {
                            label: 'Élèves absents',
                            value: dashboard.students_absent,
                            color: 'text-red-700',
                        },
                        {
                            label: 'Élèves en retard',
                            value: dashboard.students_late,
                            color: 'text-amber-700',
                        },
                        {
                            label: 'Enseignants absents',
                            value: dashboard.teachers_absent,
                            color: 'text-red-700',
                        },
                        {
                            label: 'Classes affectées',
                            value: dashboard.affected_classes,
                            color: 'text-violet-700',
                        },
                    ]"
                    :key="indicator.label"
                    class="rounded-2xl border bg-white p-4"
                >
                    <p
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        {{ date }} · {{ indicator.label }}
                    </p>
                    <p
                        class="mt-2 text-3xl font-semibold"
                        :class="indicator.color"
                    >
                        {{ indicator.value }}
                    </p>
                </article>
            </section>

            <section
                class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-amber-900">
                            Alertes d’absences répétées
                        </h2>
                        <p class="text-xs text-amber-800">
                            Seuils :
                            {{ attendanceSettings.monthly_absence_threshold }}
                            absences/mois ou
                            {{ attendanceSettings.consecutive_days_threshold }}
                            jours consécutifs.
                        </p>
                    </div>
                    <details class="relative">
                        <summary
                            class="cursor-pointer text-sm font-medium text-amber-900"
                        >
                            Configurer les seuils
                        </summary>
                        <div
                            class="mt-3 grid gap-2 rounded-xl border border-amber-200 bg-white p-3 sm:grid-cols-3"
                        >
                            <label class="text-xs"
                                >Absences par mois<Input
                                    v-model="
                                        warningSettings.monthly_absence_threshold
                                    "
                                    type="number"
                                    min="1" /></label
                            ><label class="text-xs"
                                >Jours consécutifs<Input
                                    v-model="
                                        warningSettings.consecutive_days_threshold
                                    "
                                    type="number"
                                    min="1" /></label
                            ><Button
                                class="self-end"
                                size="sm"
                                @click="saveWarningSettings"
                                >Enregistrer</Button
                            >
                        </div>
                    </details>
                </div>
                <p v-if="!warnings.length" class="mt-3 text-sm text-amber-800">
                    Aucune alerte pour la période actuelle.
                </p>
                <div v-else class="mt-3 grid gap-2 md:grid-cols-2">
                    <div
                        v-for="warning in warnings"
                        :key="warning.student_id"
                        class="rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm"
                    >
                        <strong>{{ warning.student }}</strong
                        ><span class="ml-2 text-amber-800"
                            >{{ warning.monthly_absences }} absence(s) ce mois ·
                            {{ warning.consecutive_days }} jour(s)
                            consécutif(s)</span
                        >
                    </div>
                </div>
            </section>

            <section
                class="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-5"
            >
                <label class="text-xs font-medium text-slate-600"
                    >Année scolaire<select
                        v-model="filters.academic_year_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                        @change="applyFilters()"
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
                <label class="text-xs font-medium text-slate-600"
                    >Date<Input
                        v-model="filters.date"
                        type="date"
                        class="mt-1"
                        @change="applyFilters()"
                /></label>
                <label class="text-xs font-medium text-slate-600"
                    >Cycle<select
                        v-model="filters.cycle_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                        @change="applyFilters('cycle')"
                    >
                        <option value="">Tous</option>
                        <option
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.name }}
                        </option>
                    </select></label
                >
                <label class="text-xs font-medium text-slate-600"
                    >Niveau<select
                        v-model="filters.level_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                        @change="applyFilters('level')"
                    >
                        <option value="">Tous</option>
                        <option
                            v-for="level in levels"
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
                    </select></label
                >
                <label class="text-xs font-medium text-slate-600"
                    >Groupe<select
                        v-model="filters.group_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                        @change="applyFilters()"
                    >
                        <option value="">
                            {{ view === 'teachers' ? 'Tous' : 'Sélectionner' }}
                        </option>
                        <option
                            v-for="group in groups"
                            :key="group.id"
                            :value="group.id"
                        >
                            {{ group.name }} · {{ group.level.name }}
                        </option>
                    </select></label
                >
            </section>

            <template v-if="view === 'students'">
                <section
                    v-if="sessions.length"
                    class="flex gap-2 overflow-x-auto pb-1"
                >
                    <button
                        v-for="session in sessions"
                        :key="session.id"
                        class="min-w-fit rounded-xl border bg-white px-4 py-3 text-left text-sm transition hover:border-blue-400"
                        :class="
                            selectedSessionId === session.id
                                ? 'border-blue-600 ring-2 ring-blue-100'
                                : ''
                        "
                        @click="
                            selectedSessionId = session.id;
                            selectedStudents = [];
                        "
                    >
                        <strong
                            >{{ session.start_time.slice(0, 5) }}–{{
                                session.end_time.slice(0, 5)
                            }}</strong
                        ><span class="ml-2 text-muted-foreground">{{
                            session.subject?.title
                        }}</span>
                    </button>
                </section>
                <section
                    v-if="selectedSession"
                    class="overflow-hidden rounded-2xl border bg-white"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b p-4"
                    >
                        <div>
                            <h2 class="font-semibold">
                                {{ selectedSession.subject?.title }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedSession.start_time.slice(0, 5) }}–{{
                                    selectedSession.end_time.slice(0, 5)
                                }}
                                · Tous présents par défaut
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!selectedStudents.length"
                                @click="bulkAbsent(true)"
                                >Absents journée</Button
                            ><Button
                                size="sm"
                                :disabled="!selectedStudents.length"
                                @click="bulkAbsent()"
                                >Marquer sélection absente</Button
                            >
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[850px] text-sm">
                            <thead
                                class="bg-slate-50 text-left text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="p-3">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedStudents.length ===
                                                selectedSession.students?.length
                                            "
                                            @change="
                                                selectedStudents = (
                                                    $event.target as HTMLInputElement
                                                ).checked
                                                    ? selectedSession.students!.map(
                                                          (s) => s.id,
                                                      )
                                                    : []
                                            "
                                        />
                                    </th>
                                    <th class="p-3">Élève</th>
                                    <th class="p-3">Statut</th>
                                    <th class="p-3">Raison</th>
                                    <th class="p-3">Justification</th>
                                    <th class="p-3">Actions rapides</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="student in selectedSession.students"
                                    :key="student.id"
                                    class="border-t"
                                >
                                    <td class="p-3">
                                        <input
                                            v-model="selectedStudents"
                                            type="checkbox"
                                            :value="student.id"
                                        />
                                    </td>
                                    <td class="p-3 font-medium">
                                        {{ student.last_name }}
                                        {{ student.first_name }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="
                                                statusClasses[
                                                    student.attendance_status
                                                ]
                                            "
                                            ><Check
                                                v-if="
                                                    student.attendance_status ===
                                                    'PRESENT'
                                                "
                                                class="mr-1 size-3.5"
                                            />{{
                                                statusLabels[
                                                    student.attendance_status
                                                ]
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-3">
                                        <Input
                                            v-model="
                                                inputFor(
                                                    student.id,
                                                    student.attendance_exception,
                                                ).reason
                                            "
                                            placeholder="Facultatif"
                                        />
                                    </td>
                                    <td class="p-3">
                                        <Input
                                            v-model="
                                                inputFor(
                                                    student.id,
                                                    student.attendance_exception,
                                                ).justification
                                            "
                                            placeholder="Facultatif"
                                        />
                                    </td>
                                    <td class="p-3">
                                        <div class="flex flex-wrap gap-1">
                                            <button
                                                v-for="status in [
                                                    'PRESENT',
                                                    'ABSENT',
                                                    'LATE',
                                                    'EXCUSED',
                                                    'LEFT_EARLY',
                                                ]"
                                                :key="status"
                                                class="rounded-md border px-2 py-1 text-xs hover:bg-slate-50"
                                                @click="
                                                    mark(
                                                        student,
                                                        'STUDENT',
                                                        status,
                                                    )
                                                "
                                            >
                                                {{
                                                    statusLabels[status]
                                                }}</button
                                            ><button
                                                class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                                @click="
                                                    mark(
                                                        student,
                                                        'STUDENT',
                                                        'ABSENT',
                                                        true,
                                                    )
                                                "
                                            >
                                                Journée</button
                                            ><Input
                                                v-if="
                                                    student.attendance_status ===
                                                    'LATE'
                                                "
                                                v-model="
                                                    inputFor(
                                                        student.id,
                                                        student.attendance_exception,
                                                    ).minutes_late
                                                "
                                                type="number"
                                                min="1"
                                                class="h-7 w-20"
                                                title="Minutes de retard"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div
                    v-else
                    class="rounded-2xl border border-dashed bg-white p-12 text-center text-muted-foreground"
                >
                    {{
                        filters.group_id
                            ? 'Aucune séance planifiée à cette date.'
                            : 'Sélectionnez un groupe pour afficher ses séances et ses élèves.'
                    }}
                </div>
            </template>

            <template v-else>
                <section
                    v-if="teacherImpacts.length"
                    class="grid gap-3 lg:grid-cols-2"
                >
                    <article
                        v-for="impact in teacherImpacts"
                        :key="impact.teacher"
                        class="rounded-2xl border border-red-200 bg-red-50/60 p-4"
                    >
                        <h2 class="font-semibold text-red-800">
                            {{ impact.teacher }} absent
                        </h2>
                        <div class="mt-2 space-y-1">
                            <p
                                v-for="session in impact.sessions"
                                :key="session.id"
                                class="text-sm text-red-900"
                            >
                                → {{ session.group }} · {{ session.subject }} ·
                                {{ session.start_time }}
                            </p>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Remplacement : non assigné — fonctionnalité à venir
                        </p>
                    </article>
                </section>
                <section
                    v-if="sessions.length"
                    class="grid gap-3 lg:grid-cols-2"
                >
                    <article
                        v-for="session in sessions"
                        :key="session.id"
                        class="rounded-2xl border bg-white p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">
                                    {{ session.teacher?.name }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ session.start_time.slice(0, 5) }}–{{
                                        session.end_time.slice(0, 5)
                                    }}
                                    · {{ session.subject?.title }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ session.group?.name }} ·
                                    {{ session.group?.level?.name }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="
                                    statusClasses[session.attendance_status]
                                "
                                ><Check
                                    v-if="
                                        session.attendance_status === 'PRESENT'
                                    "
                                    class="mr-1 inline size-3.5"
                                />{{
                                    statusLabels[session.attendance_status]
                                }}</span
                            >
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                v-if="session.attendance_status !== 'PRESENT'"
                                variant="outline"
                                size="sm"
                                @click="
                                    mark(
                                        session.teacher!,
                                        'TEACHER',
                                        'PRESENT',
                                        false,
                                        session,
                                    )
                                "
                                >Présent</Button
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="
                                    mark(
                                        session.teacher!,
                                        'TEACHER',
                                        'ABSENT',
                                        false,
                                        session,
                                    )
                                "
                                >Absent séance</Button
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="
                                    mark(
                                        session.teacher!,
                                        'TEACHER',
                                        'LATE',
                                        false,
                                        session,
                                    )
                                "
                                ><Clock3 class="size-3.5" />Retard</Button
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="
                                    mark(
                                        session.teacher!,
                                        'TEACHER',
                                        'EXCUSED',
                                        false,
                                        session,
                                    )
                                "
                                >Excusé</Button
                            ><Button
                                size="sm"
                                @click="openTeacherRange(session)"
                                >Journée / plage</Button
                            >
                        </div>
                    </article>
                </section>
                <div
                    v-else
                    class="rounded-2xl border border-dashed bg-white p-12 text-center text-muted-foreground"
                >
                    Aucun enseignant planifié à cette date.
                </div>
            </template>

            <div
                v-if="teacherModal"
                class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"
                @click.self="teacherModal = false"
            >
                <div
                    class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
                >
                    <div class="flex justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">
                                Absence de {{ teacherForm.teacher_name }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Vérifiez les séances affectées avant
                                d’enregistrer.
                            </p>
                        </div>
                        <button @click="teacherModal = false">
                            <X class="size-5" />
                        </button>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <label class="text-sm"
                            >Du<Input
                                v-model="teacherForm.date"
                                type="date"
                                class="mt-1"
                                @change="loadPreview" /></label
                        ><label class="text-sm"
                            >Au<Input
                                v-model="teacherForm.end_date"
                                type="date"
                                class="mt-1"
                                @change="loadPreview" /></label
                        ><label class="text-sm"
                            >Statut<select
                                v-model="teacherForm.status"
                                class="mt-1 h-10 w-full rounded-md border bg-white px-3"
                            >
                                <option value="ABSENT">Absent</option>
                                <option value="EXCUSED">Excusé</option>
                                <option value="LATE">En retard</option>
                            </select></label
                        ><label
                            v-if="teacherForm.status === 'LATE'"
                            class="text-sm"
                            >Minutes<Input
                                v-model="teacherForm.minutes_late"
                                type="number"
                                min="1"
                                class="mt-1" /></label
                        ><label class="text-sm"
                            >Raison<Input
                                v-model="teacherForm.reason"
                                class="mt-1" /></label
                        ><label class="text-sm"
                            >Justification<Input
                                v-model="teacherForm.justification"
                                class="mt-1"
                        /></label>
                    </div>
                    <div class="mt-5 rounded-xl border">
                        <div
                            class="border-b bg-slate-50 px-4 py-2 text-sm font-medium"
                        >
                            Séances affectées ({{ preview.length }})
                        </div>
                        <div class="max-h-52 divide-y overflow-y-auto">
                            <p
                                v-if="previewing"
                                class="p-4 text-sm text-muted-foreground"
                            >
                                Chargement…
                            </p>
                            <p
                                v-else-if="!preview.length"
                                class="p-4 text-sm text-muted-foreground"
                            >
                                Aucune séance planifiée sur cette plage.
                            </p>
                            <div
                                v-for="item in preview"
                                :key="`${item.date}-${item.id}`"
                                class="flex justify-between gap-4 p-3 text-sm"
                            >
                                <span
                                    >{{ item.date }} ·
                                    {{ item.start_time.slice(0, 5) }}–{{
                                        item.end_time.slice(0, 5)
                                    }}</span
                                ><span class="text-right text-muted-foreground"
                                    >{{ item.subject }} · {{ item.group }}</span
                                >
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <Button variant="outline" @click="teacherModal = false"
                            >Annuler</Button
                        ><Button @click="saveTeacherRange"
                            >Enregistrer l’exception</Button
                        >
                    </div>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>
