<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    Check,
    Clock3,
    Filter,
    Plus,
    ShieldCheck,
    TriangleAlert,
    UserRoundCheck,
    UserRoundX,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

type Exception = {
    id: number;
    reason?: string;
    justification?: string;
    minutes_late?: number;
    notes?: string;
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
type AbsenceRecord = {
    id: number;
    person_type: 'STUDENT' | 'TEACHER';
    person: string;
    status: string;
    reason?: string;
    justification?: string;
    is_justified: boolean;
    parent_justification_pending: boolean;
    parent_justification_attachment_name?: string;
    parent_justification_attachment_url?: string;
    scope: 'day' | 'session';
    sessions: Array<{
        id: number;
        group?: string;
        subject?: string;
        start_time: string;
    }>;
};
type Year = { id: number; name: string; start_date: string; end_date: string };
const props = defineProps<{
    academicYears: Year[];
    academicYear: Year;
    date: string;
    view: 'students' | 'teachers';
    filters: {
        cycle_id?: number;
        level_id?: number;
        group_id?: number;
        site_id?: number;
        student_id?: number;
        teacher_id?: number;
        justification_status?: string;
    };
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
    absenceRecords: AbsenceRecord[];
    students: Array<{ id: number; first_name: string; last_name: string; photo_url?: string; group?: string; level?: string }>;
    teachers: Array<{ id: number; name: string; photo_url?: string; subjects?: string[] }>;
    sites: Array<{ id: number; name: string }>;
}>();

const permissions = computed(
    () => (usePage().props.auth as any)?.permissions || [],
);
const can = (permission: string) => permissions.value.includes(permission);
const canManageStudents = computed(
    () => can('student_absences.manage') && can('absences.create'),
);
const canManageTeachers = computed(
    () => can('teacher_absences.manage') && can('absences.create'),
);
const justifiedCount = computed(
    () => props.absenceRecords.filter((item) => item.is_justified).length,
);
const unjustifiedCount = computed(
    () => props.absenceRecords.length - justifiedCount.value,
);

const filters = reactive({
    academic_year_id: props.academicYear.id,
    date: props.date,
    view: props.view,
    cycle_id: props.filters.cycle_id || '',
    level_id: props.filters.level_id || '',
    group_id: props.filters.group_id || '',
    site_id: props.filters.site_id || '',
    student_id: props.filters.student_id || '',
    teacher_id: props.filters.teacher_id || '',
    justification_status: props.filters.justification_status || '',
});
const selectedSessionId = ref<number | null>(props.sessions[0]?.id || null);
const absenceDialog = ref(false);
const personSearch = ref('');
const personPickerOpen = ref(false);
const absenceForm = reactive({
    person_type: 'STUDENT' as 'STUDENT' | 'TEACHER',
    student_id: '',
    teacher_id: '',
    date: props.date,
    scope: 'session' as 'session' | 'day',
    timetable_session_id: '',
    status: 'ABSENT',
    minutes_late: 10,
    reason: '',
    justification: '',
    notes: '',
});
type SessionOption = {
    id: number;
    start_time: string;
    end_time: string;
    subject?: string;
    group?: string;
    room?: string;
};
const dialogSessions = ref<SessionOption[]>([]);
const dialogSessionsLoading = ref(false);
const dialogSessionsError = ref('');
let sessionOptionsRequest = 0;
const selectedStudents = ref<number[]>([]);
const rowInputs = reactive<
    Record<
        number,
        {
            reason: string;
            justification: string;
            notes: string;
            minutes_late: number;
        }
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
    router.get('/admin/school-absence', filters, {
        preserveState: true,
        replace: true,
    });
}
function openAbsenceDialog() {
    personSearch.value = '';
    personPickerOpen.value = false;
    absenceForm.date = props.date;
    absenceForm.person_type = 'STUDENT';
    absenceForm.student_id = '';
    absenceForm.teacher_id = '';
    absenceForm.scope = 'session';
    absenceForm.timetable_session_id = '';
    absenceForm.status = 'ABSENT';
    absenceForm.minutes_late = 10;
    absenceForm.reason = '';
    absenceForm.justification = '';
    absenceForm.notes = '';
    absenceDialog.value = true;
}
const filteredDialogStudents = computed(() => {
    return props.students.filter((student) => matchesPersonSearch(`${student.first_name} ${student.last_name} ${student.group ?? ''} ${student.level ?? ''}`));
});
const filteredDialogTeachers = computed(() => {
    return props.teachers.filter((teacher) => matchesPersonSearch(`${teacher.name} ${(teacher.subjects ?? []).join(' ')}`));
});
function matchesPersonSearch(value: string) {
    const normalizedValue = value.toLocaleLowerCase('fr');
    return personSearch.value
        .toLocaleLowerCase('fr')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .every((term) => normalizedValue.includes(term));
}
function initials(name: string) {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}
const selectedDialogPerson = computed(() => {
    if (absenceForm.person_type === 'STUDENT') return props.students.find((item) => String(item.id) === absenceForm.student_id);
    return props.teachers.find((item) => String(item.id) === absenceForm.teacher_id);
});
async function loadDialogSessions() {
    const personId = absenceForm.person_type === 'STUDENT' ? absenceForm.student_id : absenceForm.teacher_id;
    const requestId = ++sessionOptionsRequest;
    dialogSessions.value = [];
    absenceForm.timetable_session_id = '';
    dialogSessionsError.value = '';
    dialogSessionsLoading.value = false;
    if (!personId || !absenceForm.date) return;

    dialogSessionsLoading.value = true;
    const query = new URLSearchParams({
        academic_year_id: String(props.academicYear.id),
        person_type: absenceForm.person_type,
        date: absenceForm.date,
    });
    query.set(absenceForm.person_type === 'STUDENT' ? 'student_id' : 'teacher_id', personId);

    try {
        const response = await fetch(`/admin/school-absence/session-options?${query}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (requestId !== sessionOptionsRequest) return;
        if (!response.ok) {
            dialogSessionsError.value = Object.values(payload.errors ?? {})[0]?.[0] ?? 'Impossible de charger l’emploi du temps.';
            return;
        }
        dialogSessions.value = payload.sessions ?? [];
        if (dialogSessions.value.length) {
            absenceForm.scope = 'session';
            absenceForm.timetable_session_id = String(dialogSessions.value[0].id);
        } else {
            absenceForm.scope = 'day';
        }
    } catch {
        if (requestId === sessionOptionsRequest) dialogSessionsError.value = 'Impossible de charger l’emploi du temps.';
    } finally {
        if (requestId === sessionOptionsRequest) dialogSessionsLoading.value = false;
    }
}
watch(
    () => [absenceForm.person_type, absenceForm.student_id, absenceForm.teacher_id, absenceForm.date],
    ([type], previous) => {
        if (previous && type !== previous[0]) {
            absenceForm.student_id = '';
            absenceForm.teacher_id = '';
            personSearch.value = '';
            personPickerOpen.value = false;
        }
        loadDialogSessions();
    },
);
function saveAbsence() {
    router.post('/admin/school-absence/exceptions', {
        ...absenceForm,
        student_id: absenceForm.person_type === 'STUDENT' ? absenceForm.student_id : null,
        teacher_id: absenceForm.person_type === 'TEACHER' ? absenceForm.teacher_id : null,
        timetable_session_id: absenceForm.scope === 'session' ? absenceForm.timetable_session_id : null,
        minutes_late: absenceForm.status === 'LATE' ? absenceForm.minutes_late : null,
    }, {
        preserveScroll: true,
        onSuccess: () => (absenceDialog.value = false),
    });
}
function inputFor(id: number, exception?: Exception) {
    return (rowInputs[id] ||= {
        reason: exception?.reason || '',
        justification: exception?.justification || '',
        notes: exception?.notes || '',
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
                `/admin/school-absence/exceptions/${exception.id}`,
                { preserveScroll: true },
            );
        return;
    }
    const values = inputFor(person.id, exception);
    router.post(
        '/admin/school-absence/exceptions',
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
            notes: values.notes,
        },
        { preserveScroll: true },
    );
}
function bulkAbsent(fullDay = false) {
    if (!selectedStudents.value.length) return;
    router.post(
        '/admin/school-absence/students/bulk',
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
    notes: '',
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
        notes: '',
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
        `/admin/school-absence/teacher-preview?${query}`,
        { headers: { Accept: 'application/json' } },
    );
    preview.value = response.ok ? (await response.json()).sessions : [];
    previewing.value = false;
}
function saveTeacherRange() {
    router.post(
        '/admin/school-absence/exceptions',
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
    <Head title="Absences" />
    <AdminLayout>
        <main
            class="min-h-full flex-1 space-y-5 bg-slate-50/60 p-4 sm:p-6 lg:p-8"
        >
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <span class="rounded-xl bg-blue-600 p-2 text-white"
                            ><CalendarCheck class="size-5" /></span
                        >Absences
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Enregistrez uniquement les absences. Sans absence
                        déclarée, la personne est considérée présente.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
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
                    <a v-if="can('absence_justifications.manage')" href="/admin/school-absence/settings">
                        <Button variant="outline" class="h-10 rounded-xl">Configurer les seuils</Button>
                    </a>
                </div>
                <Button
                    v-if="can('absences.create')"
                    class="h-12 gap-2 rounded-xl bg-blue-600 px-5 text-base font-semibold shadow-md shadow-blue-200 transition hover:bg-blue-700 hover:shadow-lg"
                    @click="openAbsenceDialog"
                >
                    <Plus class="size-5" />
                    Ajouter une absence
                </Button>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="indicator in [
                        {
                            label: 'Élèves absents',
                            value: dashboard.students_absent,
                            hint: 'Élèves concernés aujourd’hui',
                            color: 'text-rose-700',
                            icon: UserRoundX,
                            tone: 'bg-rose-50 text-rose-600',
                        },
                        {
                            label: 'Absences justifiées',
                            value: justifiedCount,
                            hint: 'Motif ou justificatif renseigné',
                            color: 'text-blue-700',
                            icon: ShieldCheck,
                            tone: 'bg-blue-50 text-blue-600',
                        },
                        {
                            label: 'Enseignants absents',
                            value: dashboard.teachers_absent,
                            hint: 'Cours potentiellement impactés',
                            color: 'text-orange-700',
                            icon: UserRoundCheck,
                            tone: 'bg-orange-50 text-orange-600',
                        },
                        {
                            label: 'Non justifiées',
                            value: unjustifiedCount,
                            hint: 'À vérifier par l’administration',
                            color: 'text-amber-700',
                            icon: TriangleAlert,
                            tone: 'bg-amber-50 text-amber-600',
                        },
                    ]"
                    :key="indicator.label"
                    class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Aujourd’hui</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ indicator.label }}</p>
                        </div>
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl" :class="indicator.tone">
                            <component :is="indicator.icon" class="size-5" />
                        </span>
                    </div>
                    <div class="mt-5 flex items-end justify-between gap-2">
                        <p class="text-4xl font-bold tracking-tight" :class="indicator.color">{{ indicator.value }}</p>
                        <Clock3 class="mb-1 size-4 text-slate-300 transition group-hover:text-slate-400" />
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ indicator.hint }}</p>
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

            <section class="overflow-hidden rounded-2xl border bg-white">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Absences du {{ date }}</h2>
                    <p class="text-xs text-muted-foreground">
                        Élèves et enseignants · séances affectées
                    </p>
                </div>
                <div v-if="absenceRecords.length" class="divide-y">
                    <article
                        v-for="absence in absenceRecords"
                        :key="absence.id"
                        class="flex flex-wrap items-start justify-between gap-3 p-4"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <strong>{{ absence.person }}</strong>
                                <span
                                    class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700"
                                    >{{
                                        absence.person_type === 'STUDENT'
                                            ? 'Élève'
                                    : 'Enseignant'
                                    }}</span
                                >
                                <span
                                    v-if="absence.parent_justification_pending"
                                    class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700"
                                    >Justification parent à valider</span
                                >
                                <span
                                    v-if="absence.status === 'LATE'"
                                    class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700"
                                    >En retard</span
                                >
                                <span
                                    v-else
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        absence.is_justified
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'bg-amber-50 text-amber-700'
                                    "
                                    >{{
                                        absence.is_justified
                                            ? 'Justifiée'
                                            : 'Non justifiée'
                                    }}</span
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    absence.scope === 'day'
                                        ? 'Journée entière'
                                        : 'Séance'
                                }}</span>
                            </div>
                            <p v-if="absence.reason" class="mt-1 text-sm">
                                {{ absence.reason }}
                            </p>
                            <p
                                v-if="absence.justification"
                                class="mt-2 rounded-lg bg-violet-50 px-3 py-2 text-sm text-violet-900"
                            >
                                <b>{{ absence.parent_justification_pending ? 'Justification du parent :' : 'Justification :' }}</b>
                                {{ absence.justification }}
                            </p>
                            <a
                                v-if="absence.parent_justification_attachment_url"
                                :href="absence.parent_justification_attachment_url"
                                target="_blank"
                                class="mt-2 inline-block text-sm font-medium text-violet-700 hover:underline"
                            >Voir la pièce jointe<span v-if="absence.parent_justification_attachment_name"> · {{ absence.parent_justification_attachment_name }}</span></a>
                            <p class="mt-2 text-xs text-muted-foreground">
                                <template v-if="absence.sessions.length">
                                    {{
                                        absence.sessions
                                            .map(
                                                (item) =>
                                                    `${item.start_time} · ${item.group} · ${item.subject}`,
                                            )
                                            .join(' | ')
                                    }}
                                </template>
                                <template v-else
                                    >Aucune séance affectée</template
                                >
                            </p>
                        </div>
                        <Button
                            v-if="can('absences.delete')"
                            variant="outline"
                            size="sm"
                            @click="
                                router.delete(
                                    `/admin/school-absence/exceptions/${absence.id}`,
                                    { preserveScroll: true },
                                )
                            "
                            >Supprimer</Button
                        >
                    </article>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    Aucune absence enregistrée avec ces filtres.
                </p>
            </section>

            <form
                class="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"
                @submit.prevent="applyFilters()"
            >
                <label class="text-xs font-medium text-slate-600"
                    >Année scolaire<select
                        v-model="filters.academic_year_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
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
                /></label>
                <label class="text-xs font-medium text-slate-600"
                    >Site<select
                        v-model="filters.site_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                    >
                        <option value="">Tous</option>
                        <option
                            v-for="site in sites"
                            :key="site.id"
                            :value="site.id"
                        >
                            {{ site.name }}
                        </option>
                    </select></label
                >
                <label class="text-xs font-medium text-slate-600"
                    >Cycle<select
                        v-model="filters.cycle_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                        @change="
                            filters.level_id = '';
                            filters.group_id = '';
                        "
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
                        @change="filters.group_id = ''"
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
                <label class="text-xs font-medium text-slate-600"
                    >Élève<select
                        v-model="filters.student_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                    >
                        <option value="">Tous</option>
                        <option
                            v-for="student in students"
                            :key="student.id"
                            :value="student.id"
                        >
                            {{ student.last_name }} {{ student.first_name }}
                        </option>
                    </select></label
                >
                <label class="text-xs font-medium text-slate-600"
                    >Enseignant<select
                        v-model="filters.teacher_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                    >
                        <option value="">Tous</option>
                        <option
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.name }}
                        </option>
                    </select></label
                >
                <label class="text-xs font-medium text-slate-600"
                    >Justification<select
                        v-model="filters.justification_status"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"
                    >
                        <option value="">Toutes</option>
                        <option value="justified">Justifiées</option>
                        <option value="unjustified">Non justifiées</option>
                    </select></label
                >
                <Button type="submit" class="self-end">
                    <Filter class="size-4" />Filtrer
                </Button>
            </form>

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
                                v-if="canManageStudents"
                                variant="outline"
                                size="sm"
                                :disabled="!selectedStudents.length"
                                @click="bulkAbsent(true)"
                                >Absents journée</Button
                            ><Button
                                v-if="canManageStudents"
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
                                    <th class="p-3">Note</th>
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
                                    <td class="p-3">
                                        <Input
                                            v-model="
                                                inputFor(
                                                    student.id,
                                                    student.attendance_exception,
                                                ).notes
                                            "
                                            placeholder="Facultatif"
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
                                                v-if="
                                                    canManageStudents &&
                                                    student.attendance_status ===
                                                        'PRESENT'
                                                "
                                                class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                                @click="
                                                    mark(
                                                        student,
                                                        'STUDENT',
                                                        'ABSENT',
                                                    )
                                                "
                                            >
                                                Marquer absent</button
                                            ><button
                                                v-if="
                                                    canManageStudents &&
                                                    student.attendance_status ===
                                                        'PRESENT'
                                                "
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
                                                Journée entière</button
                                            ><button
                                                v-if="
                                                    can('absences.delete') &&
                                                    student.attendance_status !==
                                                        'PRESENT'
                                                "
                                                class="rounded-md border px-2 py-1 text-xs"
                                                @click="
                                                    mark(
                                                        student,
                                                        'STUDENT',
                                                        'PRESENT',
                                                    )
                                                "
                                            >
                                                Annuler l’absence
                                            </button>
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
                                v-if="
                                    can('absences.delete') &&
                                    session.attendance_status !== 'PRESENT'
                                "
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
                                >Annuler l’absence</Button
                            ><Button
                                v-if="canManageTeachers"
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
                                >Absent pour la séance</Button
                            ><Button
                                v-if="canManageTeachers"
                                size="sm"
                                @click="openTeacherRange(session)"
                                >Déclarer une journée / plage</Button
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
                v-if="absenceDialog"
                class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"
                @click.self="absenceDialog = false"
            >
                <form
                    class="max-h-[90vh] w-full max-w-xl space-y-4 overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
                    @submit.prevent="saveAbsence"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Ajouter une absence</h2>
                            <p class="text-sm text-muted-foreground">Déclarez une absence ou un retard.</p>
                        </div>
                        <button type="button" @click="absenceDialog = false"><X class="size-5" /></button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Type<select v-model="absenceForm.person_type" class="mt-1 h-10 w-full rounded-md border px-3">
                            <option value="STUDENT">Élève</option><option value="TEACHER">Enseignant</option>
                        </select></label>
                        <label class="text-sm">Date<Input v-model="absenceForm.date" type="date" class="mt-1" required /></label>
                        <div class="text-sm">
                            <span class="block">{{ absenceForm.person_type === 'STUDENT' ? 'Élève' : 'Enseignant' }}</span>
                            <button type="button" class="mt-1 flex w-full items-center gap-3 rounded-lg border p-2 text-left hover:border-blue-400" @click="personPickerOpen = !personPickerOpen">
                                <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                                    <img v-if="selectedDialogPerson?.photo_url" :src="selectedDialogPerson.photo_url" class="size-full object-cover" />
                                    <span v-else>{{ selectedDialogPerson ? initials(absenceForm.person_type === 'STUDENT' ? `${(selectedDialogPerson as any).first_name} ${(selectedDialogPerson as any).last_name}` : (selectedDialogPerson as any).name) : '?' }}</span>
                                </span>
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ selectedDialogPerson ? (absenceForm.person_type === 'STUDENT' ? `${(selectedDialogPerson as any).last_name} ${(selectedDialogPerson as any).first_name}` : (selectedDialogPerson as any).name) : 'Sélectionner une personne' }}</span><span class="block truncate text-xs text-muted-foreground">{{ selectedDialogPerson ? (absenceForm.person_type === 'STUDENT' ? `${(selectedDialogPerson as any).group || 'Groupe non renseigné'} · ${(selectedDialogPerson as any).level || 'Niveau non renseigné'}` : ((selectedDialogPerson as any).subjects?.join(' · ') || 'Matières non renseignées')) : 'Cliquez pour rechercher' }}</span></span>
                            </button>
                            <div v-if="personPickerOpen" class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-md border bg-white p-1 shadow-lg">
                                <Input v-model="personSearch" placeholder="Rechercher..." class="mb-1" />
                                <button v-for="student in filteredDialogStudents" v-if="absenceForm.person_type === 'STUDENT'" :key="student.id" type="button" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-blue-50" @click="absenceForm.student_id = String(student.id); personPickerOpen = false"><span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-blue-100 text-xs font-bold text-blue-700"><img v-if="student.photo_url" :src="student.photo_url" class="size-full object-cover" /><span v-else>{{ initials(`${student.first_name} ${student.last_name}`) }}</span></span><span class="truncate">{{ student.last_name }} {{ student.first_name }}<small class="block text-xs text-muted-foreground">{{ student.group || 'Groupe non renseigné' }} · {{ student.level || 'Niveau non renseigné' }}</small></span></button>
                                <button v-for="teacher in filteredDialogTeachers" v-if="absenceForm.person_type === 'TEACHER'" :key="teacher.id" type="button" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-blue-50" @click="absenceForm.teacher_id = String(teacher.id); personPickerOpen = false"><span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-violet-100 text-xs font-bold text-violet-700"><img v-if="teacher.photo_url" :src="teacher.photo_url" class="size-full object-cover" /><span v-else>{{ initials(teacher.name) }}</span></span><span class="truncate">{{ teacher.name }}<small class="block text-xs text-muted-foreground">{{ teacher.subjects?.join(' · ') || 'Matières non renseignées' }}</small></span></button>
                            </div>
                        </div>
                        <label class="text-sm">Statut<select v-model="absenceForm.status" class="mt-1 h-10 w-full rounded-md border px-3">
                            <option value="ABSENT">Absent</option><option value="LATE">En retard</option><option value="EXCUSED">Excusé</option>
                        </select></label>
                        <label v-if="absenceForm.status === 'LATE'" class="text-sm">Minutes de retard<Input v-model="absenceForm.minutes_late" type="number" min="1" max="1440" class="mt-1" required /></label>
                        <div class="space-y-2 sm:col-span-2">
                            <span class="block text-sm font-medium">Période concernée</span>
                            <div class="grid grid-cols-2 rounded-lg bg-slate-100 p-1">
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-2 text-sm font-medium"
                                    :class="absenceForm.scope === 'session' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600'"
                                    :disabled="!dialogSessions.length"
                                    @click="absenceForm.scope = 'session'"
                                >Une séance</button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-2 text-sm font-medium"
                                    :class="absenceForm.scope === 'day' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600'"
                                    @click="absenceForm.scope = 'day'"
                                >Journée entière</button>
                            </div>
                            <p v-if="dialogSessionsLoading" class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                                Chargement de l’emploi du temps…
                            </p>
                            <p v-else-if="dialogSessionsError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                {{ dialogSessionsError }}
                            </p>
                            <div v-else-if="dialogSessions.length" class="space-y-2">
                                <label
                                    v-for="session in dialogSessions"
                                    :key="session.id"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                                    :class="absenceForm.scope === 'session' && absenceForm.timetable_session_id === String(session.id) ? 'border-blue-500 bg-blue-50' : 'hover:border-blue-300'"
                                >
                                    <input
                                        v-model="absenceForm.timetable_session_id"
                                        type="radio"
                                        :value="String(session.id)"
                                        :disabled="absenceForm.scope !== 'session'"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <b>{{ session.start_time }}–{{ session.end_time }}</b>
                                        <span class="ml-2">{{ session.subject || 'Séance' }}</span>
                                        <small class="block text-muted-foreground">
                                            {{ session.group || 'Groupe non renseigné' }}<template v-if="session.room"> · {{ session.room }}</template>
                                        </small>
                                    </span>
                                </label>
                            </div>
                            <p v-else-if="selectedDialogPerson" class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                                Aucune séance dans l’emploi du temps à cette date. Vous pouvez enregistrer une absence pour la journée entière.
                            </p>
                        </div>
                    </div>
                    <label class="block text-sm">Raison<Input v-model="absenceForm.reason" class="mt-1" /></label>
                    <label class="block text-sm">Justification<Input v-model="absenceForm.justification" class="mt-1" /></label>
                    <label class="block text-sm">Note<Input v-model="absenceForm.notes" class="mt-1" /></label>
                    <div class="flex justify-end gap-2"><Button type="button" variant="outline" @click="absenceDialog = false">Annuler</Button><Button type="submit" :disabled="dialogSessionsLoading || !selectedDialogPerson || (absenceForm.scope === 'session' && !absenceForm.timetable_session_id)">Enregistrer</Button></div>
                </form>
            </div>

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
                            >Raison<Input
                                v-model="teacherForm.reason"
                                class="mt-1" /></label
                        ><label class="text-sm"
                            >Justification<Input
                                v-model="teacherForm.justification"
                                class="mt-1" /></label
                        ><label class="text-sm"
                            >Note<Input
                                v-model="teacherForm.notes"
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
