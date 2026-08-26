<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appAlert, appConfirm } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    CalendarPlus,
    Check,
    ChevronDown,
    CircleCheck,
    ClipboardCheck,
    Clock3,
    DoorOpen,
    FolderOpen,
    Pencil,
    Plus,
    Search,
    Settings,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Option {
    id: number;
    name: string;
    code?: string;
    capacity?: number;
}
interface Session {
    id: number;
    title: string;
    classroom_id: number;
    teacher_id: number;
    starts_at: string;
    ends_at: string;
    notes?: string | null;
    status: 'planned' | 'completed' | 'cancelled';
    attendance_status: 'pending' | 'completed' | 'validated';
    classroom: Option;
    teacher: Option;
    attendances: {
        student_id: number;
        status: string;
        notes?: string | null;
    }[];
}
interface Student {
    id: number;
    first_name: string;
    last_name: string;
    email?: string | null;
    phone?: string | null;
    parent_phone?: string | null;
    birth_date?: string | null;
    school_level?: string | null;
    notes?: string | null;
    photo_url?: string | null;
}
interface Enrollment {
    id: number;
    student_id: number;
    student: Student;
}
interface Group {
    id: number;
    group_number: number;
    name: string;
    classroom_id?: number | null;
    capacity?: number | null;
    classroom?: Option | null;
    planned_hours: number;
    sessions: Session[];
    enrollments: Enrollment[];
    attendance_stats: {
        rate: number | null;
        repeated_absences: number;
        missing_sessions: number;
    };
}
interface Plan {
    id: number;
    title: string;
    status: string;
    teacher_id: number;
    notes?: string | null;
    level: {
        name: string;
        code: string;
        duration_hours: number;
        course: { title: string; code: string };
    };
    teacher: Option;
    enrollment_form?: {
        title: string;
        start_date: string;
        end_date: string;
    } | null;
    groups: Group[];
}
const props = defineProps<{
    plan: Plan;
    teachers: Option[];
    classrooms: Option[];
    students: Student[];
    access: {
        is_admin: boolean;
        manage_groups: boolean;
        add_sessions: boolean;
        record_attendance: boolean;
    };
}>();
const groupModal = ref(false);
const editingGroup = ref<Group | null>(null);
const sessionModal = ref(false);
const sessionGroup = ref<Group | null>(null);
const editingSession = ref<Session | null>(null);
const settingsModal = ref(false);
const rosterDialog = ref(false);
const rosterGroup = ref<Group | null>(null);
const editingEnrollment = ref<Enrollment | null>(null);
const attendanceDialog = ref(false);
const attendanceSession = ref<Session | null>(null);
const attendanceReadOnly = computed(
    () =>
        !props.access.record_attendance ||
        attendanceSession.value?.attendance_status !== 'pending',
);
const studentSearch = ref('');
const groupForm = useForm({ name: '', classroom_id: '', capacity: '' });
const sessionForm = useForm({
    title: '',
    classroom_id: '',
    teacher_id: '',
    starts_at: '',
    ends_at: '',
    notes: '',
});
const settingsForm = useForm({
    title: props.plan.title,
    teacher_id: String(props.plan.teacher_id),
    status: props.plan.status,
    notes: props.plan.notes ?? '',
});
const addStudentForm = useForm<{ student_ids: number[] }>({ student_ids: [] });
const studentForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    parent_phone: '',
    school_level: '',
    notes: '',
});
const moveForm = useForm({ training_plan_group_id: '' });
const attendanceForm = useForm<{
    attendances: Record<number, string>;
    validate_session: boolean;
}>({ attendances: {}, validate_session: false });
const attendanceSelectedIds = ref<number[]>([]);
const filteredStudents = computed(() => {
    const search = studentSearch.value.trim().toLocaleLowerCase('fr');
    return props.students
        .filter((student) => {
            if (isInPlan(student.id)) return false;
            if (!search) return true;
            return [
                student.first_name,
                student.last_name,
                student.email,
                student.phone,
                student.birth_date,
            ]
                .filter(Boolean)
                .join(' ')
                .toLocaleLowerCase('fr')
                .includes(search);
        })
        .slice(0, 50);
});
const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    scheduled: 'Planifiée',
    in_progress: 'En cours',
    completed: 'Terminée',
    cancelled: 'Annulée',
};
const statusTone: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    scheduled: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-violet-100 text-violet-700',
    cancelled: 'bg-red-100 text-red-700',
};
function formatDate(value: string) {
    return new Date(value).toLocaleDateString('fr-FR', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
function formatTime(value: string) {
    return new Date(value).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    });
}
function inputDate(value: string) {
    const date = new Date(value);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
function openGroup(group?: Group) {
    editingGroup.value = group ?? null;
    groupForm.clearErrors();
    groupForm.name = group?.name ?? `Groupe ${props.plan.groups.length + 1}`;
    groupForm.classroom_id = group?.classroom_id
        ? String(group.classroom_id)
        : '';
    groupForm.capacity = group?.capacity ? String(group.capacity) : '';
    groupModal.value = true;
}
function submitGroup() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (groupModal.value = false),
    };
    if (editingGroup.value)
        groupForm.put(
            `/admin/planifications/${props.plan.id}/groupes/${editingGroup.value.id}`,
            options,
        );
    else
        groupForm.post(
            `/admin/planifications/${props.plan.id}/groupes`,
            options,
        );
}
async function deleteGroup(group: Group) {
    if (
        await appConfirm(`Supprimer ${group.name} et toutes ses séances ?`, {
            title: 'Supprimer le groupe',
            tone: 'danger',
            confirmText: 'Supprimer',
        })
    )
        router.delete(
            `/admin/planifications/${props.plan.id}/groupes/${group.id}`,
        );
}
function openSession(group: Group, session?: Session) {
    sessionGroup.value = group;
    editingSession.value = session ?? null;
    sessionForm.clearErrors();
    sessionForm.title = session?.title ?? props.plan.level.course.title;
    sessionForm.classroom_id = session?.classroom_id
        ? String(session.classroom_id)
        : group.classroom_id
          ? String(group.classroom_id)
          : '';
    sessionForm.teacher_id = String(
        session?.teacher_id ?? props.plan.teacher_id,
    );
    sessionForm.starts_at = session ? inputDate(session.starts_at) : '';
    sessionForm.ends_at = session ? inputDate(session.ends_at) : '';
    sessionForm.notes = session?.notes ?? '';
    sessionModal.value = true;
}
function submitSession() {
    if (!sessionGroup.value) return;
    const base = `/admin/planifications/${props.plan.id}/groupes/${sessionGroup.value.id}/seances`;
    const options = {
        preserveScroll: true,
        onSuccess: () => (sessionModal.value = false),
    };
    if (editingSession.value)
        sessionForm.put(`${base}/${editingSession.value.id}`, options);
    else sessionForm.post(base, options);
}
async function deleteSession(group: Group, session: Session) {
    if (
        await appConfirm(`Supprimer la séance « ${session.title} » ?`, {
            title: 'Supprimer la séance',
            tone: 'danger',
            confirmText: 'Supprimer',
        })
    )
        router.delete(
            `/admin/planifications/${props.plan.id}/groupes/${group.id}/seances/${session.id}`,
        );
}
async function completeSession(group: Group, session: Session) {
    if (
        !(await appConfirm(
            'Cette validation est définitive. Confirmer la séance et les présences ?',
            {
                title: 'Valider définitivement la séance',
                tone: 'warning',
                confirmText: 'Valider la séance',
            },
        ))
    )
        return;
    router.patch(
        `/admin/planifications/${props.plan.id}/groupes/${group.id}/seances/${session.id}/complete`,
        {},
        {
            preserveScroll: true,
            onError: (errors) =>
                void appAlert(
                    String(
                        Object.values(errors)[0] ??
                            'La validation de la séance a échoué.',
                    ),
                    { title: 'Validation impossible', tone: 'danger' },
                ),
        },
    );
}
function submitSettings() {
    settingsForm.put(`/admin/planifications/${props.plan.id}`, {
        preserveScroll: true,
        onSuccess: () => (settingsModal.value = false),
    });
}
function openRoster(group: Group) {
    rosterGroup.value = group;
    editingEnrollment.value = null;
    addStudentForm.reset();
    studentSearch.value = '';
    rosterDialog.value = true;
}
function addStudent() {
    if (!rosterGroup.value) return;
    addStudentForm.post(
        `/admin/planifications/${props.plan.id}/groupes/${rosterGroup.value.id}/etudiants`,
        {
            preserveScroll: true,
            onSuccess: () => {
                addStudentForm.reset();
                studentSearch.value = '';
            },
        },
    );
}
function toggleStudentSelection(studentId: number) {
    addStudentForm.student_ids = addStudentForm.student_ids.includes(studentId)
        ? addStudentForm.student_ids.filter((id) => id !== studentId)
        : [...addStudentForm.student_ids, studentId];
}
function selectAllAvailableStudents() {
    if (!rosterGroup.value) return;
    const remaining = rosterGroup.value.capacity
        ? Math.max(
              0,
              rosterGroup.value.capacity - rosterGroup.value.enrollments.length,
          )
        : filteredStudents.value.length;
    addStudentForm.student_ids = filteredStudents.value
        .slice(0, remaining)
        .map((student) => student.id);
}
function editStudent(enrollment: Enrollment) {
    editingEnrollment.value = enrollment;
    const student = enrollment.student;
    studentForm.defaults({
        first_name: student.first_name,
        last_name: student.last_name,
        email: student.email ?? '',
        phone: student.phone ?? '',
        parent_phone: student.parent_phone ?? '',
        school_level: student.school_level ?? '',
        notes: student.notes ?? '',
    });
    studentForm.reset();
    moveForm.training_plan_group_id = String(rosterGroup.value?.id ?? '');
}
function saveStudent() {
    if (!rosterGroup.value || !editingEnrollment.value) return;
    studentForm.put(
        `/admin/planifications/${props.plan.id}/groupes/${rosterGroup.value.id}/inscriptions/${editingEnrollment.value.id}/etudiant`,
        {
            preserveScroll: true,
            onSuccess: () => (editingEnrollment.value = null),
        },
    );
}
function moveStudent() {
    if (!rosterGroup.value || !editingEnrollment.value) return;
    moveForm.patch(
        `/admin/planifications/${props.plan.id}/groupes/${rosterGroup.value.id}/inscriptions/${editingEnrollment.value.id}/deplacer`,
        {
            preserveScroll: true,
            onSuccess: () => {
                editingEnrollment.value = null;
                rosterDialog.value = false;
            },
        },
    );
}
function quickMove(enrollment: Enrollment, target: Group) {
    if (!rosterGroup.value || target.id === rosterGroup.value.id) return;
    router.patch(
        `/admin/planifications/${props.plan.id}/groupes/${rosterGroup.value.id}/inscriptions/${enrollment.id}/deplacer`,
        {
            training_plan_group_id: target.id,
        },
        { preserveScroll: true },
    );
}
function openAttendance(group: Group, session: Session) {
    rosterGroup.value = group;
    attendanceSession.value = session;
    const existing = Object.fromEntries(
        session.attendances.map((item) => [item.student_id, item.status]),
    );
    attendanceForm.attendances = Object.fromEntries(
        group.enrollments.map((item) => [
            item.student_id,
            existing[item.student_id] ?? 'present',
        ]),
    );
    attendanceSelectedIds.value = group.enrollments.map(
        (item) => item.student_id,
    );
    attendanceDialog.value = true;
}
function toggleAttendanceStudent(studentId: number) {
    attendanceSelectedIds.value = attendanceSelectedIds.value.includes(
        studentId,
    )
        ? attendanceSelectedIds.value.filter((id) => id !== studentId)
        : [...attendanceSelectedIds.value, studentId];
}
function toggleAllAttendance() {
    if (!rosterGroup.value) return;
    attendanceSelectedIds.value =
        attendanceSelectedIds.value.length ===
        rosterGroup.value.enrollments.length
            ? []
            : rosterGroup.value.enrollments.map((item) => item.student_id);
}
function applyAttendanceStatus(status: string) {
    attendanceSelectedIds.value.forEach((studentId) => {
        attendanceForm.attendances[studentId] = status;
    });
}
async function saveAttendance() {
    if (!rosterGroup.value || !attendanceSession.value) return;
    attendanceForm.validate_session = await appConfirm(
        'Voulez-vous aussi valider définitivement cette séance ? Le formateur sera marqué présent et les présences seront verrouillées.',
        {
            title: 'Valider la séance ?',
            tone: 'warning',
            confirmText: 'Enregistrer et valider',
            cancelText: 'Présences uniquement',
        },
    );
    attendanceForm.put(
        `/admin/planifications/${props.plan.id}/groupes/${rosterGroup.value.id}/seances/${attendanceSession.value.id}/presences`,
        {
            preserveScroll: true,
            onSuccess: () => (attendanceDialog.value = false),
        },
    );
}
function studentName(student: Student) {
    return `${student.first_name} ${student.last_name}`;
}
function isInPlan(studentId: number) {
    return props.plan.groups.some((group) =>
        group.enrollments.some((item) => item.student_id === studentId),
    );
}
function formatBirthDate(value?: string | null) {
    return value
        ? new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR')
        : 'Date de naissance inconnue';
}
watch(
    () => props.plan.groups,
    (groups) => {
        if (rosterGroup.value)
            rosterGroup.value =
                groups.find((group) => group.id === rosterGroup.value?.id) ??
                null;
        if (sessionGroup.value)
            sessionGroup.value =
                groups.find((group) => group.id === sessionGroup.value?.id) ??
                null;
    },
    { deep: true },
);
</script>

<template>
    <Head :title="plan.title" /><AdminLayout
        ><main
            class="min-w-0 flex-1 space-y-5 overflow-x-hidden p-3 sm:space-y-6 sm:p-6 lg:p-8"
        >
            <div>
                <Link
                    href="/admin/planifications"
                    class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                    ><ArrowLeft class="mr-2 size-4" />Retour aux
                    planifications</Link
                >
            </div>
            <header class="rounded-2xl border bg-card p-4 shadow-sm sm:p-6">
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="statusTone[plan.status]"
                                >{{ statusLabels[plan.status] }}</span
                            ><span
                                v-if="plan.enrollment_form"
                                class="rounded-full bg-primary/10 px-2.5 py-1 text-xs text-primary"
                                >Liée à une inscription</span
                            ><span
                                v-else
                                class="rounded-full bg-muted px-2.5 py-1 text-xs"
                                >Formation libre</span
                            >
                        </div>
                        <h1
                            class="mt-3 text-xl font-bold break-words sm:text-2xl"
                        >
                            {{ plan.title }}
                        </h1>
                        <p class="mt-1 text-muted-foreground">
                            {{ plan.level.course.title }} ·
                            {{ plan.level.name }} ({{ plan.level.code }}) ·
                            {{ plan.level.duration_hours }} heures par groupe
                        </p>
                        <p
                            v-if="plan.enrollment_form"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            {{ plan.enrollment_form.title }} · du
                            {{ plan.enrollment_form.start_date }} au
                            {{ plan.enrollment_form.end_date }}
                        </p>
                    </div>
                    <Button v-if="access.is_admin" variant="outline" as-child>
                        <Link
                            :href="`/admin/planifications/${plan.id}/parametres`"
                            ><Settings class="mr-2 size-4" />Paramètres</Link
                        >
                    </Button>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-2 sm:mt-6 sm:gap-3">
                    <div class="min-w-0 rounded-xl bg-muted/50 p-3 sm:p-4">
                        <Users class="size-5 text-primary" />
                        <p class="mt-2 text-xl font-bold sm:text-2xl">
                            {{ plan.groups.length }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Groupes planifiés
                        </p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-muted/50 p-3 sm:p-4">
                        <CalendarPlus class="size-5 text-primary" />
                        <p class="mt-2 text-xl font-bold sm:text-2xl">
                            {{
                                plan.groups.reduce(
                                    (total, group) =>
                                        total + group.sessions.length,
                                    0,
                                )
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Séances au total
                        </p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-muted/50 p-3 sm:p-4">
                        <Clock3 class="size-5 text-primary" />
                        <p class="mt-2 text-xl font-bold sm:text-2xl">
                            {{ plan.level.duration_hours }}h
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Durée requise par groupe
                        </p>
                    </div>
                </div>
            </header>
            <div
                v-if="$page.props.errors.group"
                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            >
                {{ $page.props.errors.group }}
            </div>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-xl font-semibold">Groupes et séances</h2>
                    <p class="text-sm text-muted-foreground">
                        Chaque groupe possède son propre calendrier.
                    </p>
                </div>
                <Button
                    v-if="access.manage_groups"
                    class="w-full sm:w-auto"
                    variant="outline"
                    @click="openGroup()"
                    ><Plus class="mr-2 size-4" />Ajouter un groupe</Button
                >
            </div>
            <section class="space-y-5">
                <article
                    v-for="group in plan.groups"
                    :key="group.id"
                    class="overflow-hidden rounded-2xl border bg-card shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 border-b bg-muted/20 p-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-semibold">
                                    {{ group.name }}
                                </h3>
                                <span
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                    >Groupe {{ group.group_number }}</span
                                >
                            </div>
                            <div
                                class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground"
                            >
                                <span class="flex items-center gap-1.5"
                                    ><DoorOpen class="size-4" />{{
                                        group.classroom?.name ||
                                        'Aucune salle par défaut'
                                    }}</span
                                ><span class="flex items-center gap-1.5"
                                    ><Users class="size-4" />{{
                                        group.enrollments.length
                                    }}
                                    /
                                    {{ group.capacity || '∞' }} étudiants</span
                                ><span class="flex items-center gap-1.5"
                                    ><Clock3 class="size-4" />{{
                                        group.planned_hours
                                    }}h / {{ plan.level.duration_hours }}h</span
                                ><span class="flex items-center gap-1.5"
                                    ><ClipboardCheck class="size-4" />{{
                                        group.attendance_stats.rate ?? '—'
                                    }}% présence ·
                                    {{
                                        group.attendance_stats.missing_sessions
                                    }}
                                    feuille(s) manquante(s)</span
                                >
                            </div>
                            <div
                                class="mt-3 h-1.5 w-full max-w-sm rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="
                                        group.planned_hours >=
                                        plan.level.duration_hours
                                            ? 'bg-emerald-500'
                                            : 'bg-primary'
                                    "
                                    :style="{
                                        width: `${Math.min(100, (group.planned_hours / plan.level.duration_hours) * 100)}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                class="flex-1 sm:flex-none"
                                size="sm"
                                variant="outline"
                                @click="openRoster(group)"
                                ><Users class="mr-2 size-4" />Étudiants</Button
                            >
                            <Button
                                v-if="access.manage_groups"
                                class="flex-1 sm:flex-none"
                                size="sm"
                                variant="outline"
                                @click="openGroup(group)"
                                ><Pencil class="mr-2 size-4" />Modifier</Button
                            ><Button
                                v-if="access.add_sessions"
                                class="w-full sm:w-auto"
                                size="sm"
                                @click="openSession(group)"
                                ><CalendarPlus class="mr-2 size-4" />Ajouter une
                                séance</Button
                            ><Button
                                v-if="access.manage_groups"
                                size="icon"
                                variant="ghost"
                                class="text-destructive"
                                @click="deleteGroup(group)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </div>
                    <div class="divide-y">
                        <div
                            v-for="session in group.sessions"
                            :key="session.id"
                            class="grid gap-3 p-4 transition hover:bg-muted/20 sm:grid-cols-[150px_1fr_auto] sm:items-center"
                        >
                            <div>
                                <p class="text-sm font-semibold capitalize">
                                    {{ formatDate(session.starts_at) }}
                                </p>
                                <span
                                    v-if="session.status === 'completed'"
                                    class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700"
                                    >Terminée</span
                                >
                                <p class="text-xs text-primary">
                                    {{ formatTime(session.starts_at) }} –
                                    {{ formatTime(session.ends_at) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    {{ session.title }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ session.classroom.name }} ·
                                    {{ session.teacher.name }}
                                </p>
                            </div>
                            <div
                                class="flex justify-start border-t pt-2 sm:justify-end sm:border-0 sm:pt-0"
                            >
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    :title="
                                        session.attendance_status === 'pending'
                                            ? 'Saisir les présences'
                                            : 'Voir les présences'
                                    "
                                    @click="openAttendance(group, session)"
                                    ><ClipboardCheck class="size-4"
                                /></Button>
                                <Button
                                    v-if="
                                        access.record_attendance &&
                                        session.status !== 'completed' &&
                                        session.attendance_status ===
                                            'completed'
                                    "
                                    size="icon"
                                    variant="ghost"
                                    title="Marquer terminée"
                                    @click="completeSession(group, session)"
                                    ><CircleCheck class="size-4 text-green-600"
                                /></Button>
                                <Button
                                    v-if="
                                        access.is_admin &&
                                        session.attendance_status !==
                                            'validated' &&
                                        session.status !== 'completed'
                                    "
                                    size="icon"
                                    variant="ghost"
                                    @click="openSession(group, session)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    v-if="
                                        access.is_admin &&
                                        session.attendance_status !==
                                            'validated' &&
                                        session.status !== 'completed'
                                    "
                                    size="icon"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="deleteSession(group, session)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                        </div>
                        <div
                            v-if="!group.sessions.length"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            Aucune séance planifiée pour ce groupe.
                        </div>
                    </div>
                </article>
            </section>
        </main>

        <div
            v-if="groupModal && access.manage_groups"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="groupModal = false"
        >
            <div
                class="w-full rounded-t-2xl bg-background p-5 sm:max-w-lg sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">
                        {{
                            editingGroup
                                ? 'Modifier le groupe'
                                : 'Ajouter un groupe'
                        }}
                    </h2>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="groupModal = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitGroup">
                    <div>
                        <Label>Nom du groupe</Label
                        ><Input
                            v-model="groupForm.name"
                            class="mt-1"
                            required
                        /><InputError
                            :message="groupForm.errors.name"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label>Salle par défaut</Label
                        ><select
                            v-model="groupForm.classroom_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Aucune salle</option>
                            <option
                                v-for="room in classrooms"
                                :key="room.id"
                                :value="String(room.id)"
                            >
                                {{ room.name }} — {{ room.capacity }} places
                            </option></select
                        ><InputError
                            :message="groupForm.errors.classroom_id"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label>Capacité du groupe</Label
                        ><Input
                            v-model="groupForm.capacity"
                            class="mt-1"
                            type="number"
                            min="1"
                        /><InputError
                            :message="groupForm.errors.capacity"
                            class="mt-1"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="groupModal = false"
                            >Annuler</Button
                        ><Button :disabled="groupForm.processing"
                            >Enregistrer</Button
                        >
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="
                sessionModal &&
                sessionGroup &&
                (access.add_sessions || access.is_admin)
            "
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="sessionModal = false"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editingSession
                                    ? 'Modifier la séance'
                                    : 'Planifier une séance'
                            }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ sessionGroup.name }} ·
                            {{ sessionGroup.planned_hours }}h déjà planifiées
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="sessionModal = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitSession">
                    <div>
                        <Label>Titre de la séance</Label
                        ><Input
                            v-model="sessionForm.title"
                            class="mt-1"
                            required
                        /><InputError
                            :message="sessionForm.errors.title"
                            class="mt-1"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Début</Label
                            ><Input
                                v-model="sessionForm.starts_at"
                                class="mt-1"
                                type="datetime-local"
                                required
                            /><InputError
                                :message="sessionForm.errors.starts_at"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Fin</Label
                            ><Input
                                v-model="sessionForm.ends_at"
                                class="mt-1"
                                type="datetime-local"
                                required
                            /><InputError
                                :message="sessionForm.errors.ends_at"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Salle</Label
                            ><select
                                v-model="sessionForm.classroom_id"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                required
                            >
                                <option value="" disabled>Sélectionner</option>
                                <option
                                    v-for="room in classrooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }} — {{ room.capacity }} places
                                </option></select
                            ><InputError
                                :message="sessionForm.errors.classroom_id"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Formateur</Label
                            ><select
                                v-model="sessionForm.teacher_id"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                required
                            >
                                <option
                                    v-for="teacher in teachers"
                                    :key="teacher.id"
                                    :value="String(teacher.id)"
                                >
                                    {{ teacher.name }}
                                </option></select
                            ><InputError
                                :message="sessionForm.errors.teacher_id"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label>Notes</Label
                        ><textarea
                            v-model="sessionForm.notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <div
                        class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800"
                    >
                        Les conflits de salle et de formateur sont vérifiés
                        automatiquement. La durée cumulée ne peut pas dépasser
                        {{ plan.level.duration_hours }} heures par groupe.
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="sessionModal = false"
                            >Annuler</Button
                        ><Button :disabled="sessionForm.processing"
                            >Enregistrer la séance</Button
                        >
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="settingsModal && access.is_admin"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="settingsModal = false"
        >
            <div
                class="w-full rounded-t-2xl bg-background p-5 sm:max-w-lg sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">Paramètres</h2>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="settingsModal = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitSettings">
                    <div>
                        <Label>Titre</Label
                        ><Input
                            v-model="settingsForm.title"
                            class="mt-1"
                            required
                        />
                    </div>
                    <div>
                        <Label>Formateur principal</Label
                        ><select
                            v-model="settingsForm.teacher_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option
                                v-for="teacher in teachers"
                                :key="teacher.id"
                                :value="String(teacher.id)"
                            >
                                {{ teacher.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label>Statut</Label
                        ><select
                            v-model="settingsForm.status"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option
                                v-for="(label, key) in statusLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label>Notes</Label
                        ><textarea
                            v-model="settingsForm.notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="settingsModal = false"
                            >Annuler</Button
                        ><Button :disabled="settingsForm.processing"
                            >Enregistrer</Button
                        >
                    </div>
                </form>
            </div>
        </div>

        <Dialog v-model:open="rosterDialog">
            <DialogScrollContent
                class="max-h-[92dvh] w-[calc(100vw-1rem)] max-w-4xl"
            >
                <DialogHeader>
                    <DialogTitle
                        >Étudiants · {{ rosterGroup?.name }}</DialogTitle
                    >
                    <DialogDescription
                        >Ajoutez, consultez, modifiez ou déplacez les étudiants
                        pendant toute la planification.</DialogDescription
                    >
                </DialogHeader>
                <div v-if="rosterGroup" class="space-y-5">
                    <form
                        v-if="access.is_admin"
                        class="space-y-3 rounded-lg border bg-muted/20 p-3"
                        @submit.prevent="addStudent"
                    >
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            />
                            <Input
                                v-model="studentSearch"
                                class="pl-9"
                                placeholder="Rechercher par nom, e-mail, téléphone ou date de naissance…"
                                autocomplete="off"
                            />
                        </div>
                        <div
                            class="flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span class="text-muted-foreground"
                                >{{
                                    addStudentForm.student_ids.length
                                }}
                                étudiant(s) sélectionné(s)</span
                            >
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    @click="addStudentForm.student_ids = []"
                                    >Désélectionner</Button
                                ><Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="selectAllAvailableStudents"
                                    >Tout sélectionner</Button
                                >
                            </div>
                        </div>
                        <div
                            class="max-h-60 overflow-y-auto rounded-md border bg-background"
                        >
                            <button
                                v-for="student in filteredStudents"
                                :key="student.id"
                                type="button"
                                class="flex w-full items-center gap-3 border-b p-3 text-left transition last:border-0 hover:bg-muted/50"
                                :class="
                                    addStudentForm.student_ids.includes(
                                        student.id,
                                    )
                                        ? 'bg-primary/10 ring-1 ring-primary ring-inset'
                                        : ''
                                "
                                @click="toggleStudentSelection(student.id)"
                            >
                                <img
                                    v-if="student.photo_url"
                                    :src="student.photo_url"
                                    :alt="studentName(student)"
                                    class="size-10 shrink-0 rounded-full object-cover"
                                />
                                <span
                                    v-else
                                    class="grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                    >{{ student.first_name[0]
                                    }}{{ student.last_name[0] }}</span
                                >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{
                                        studentName(student)
                                    }}</span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >Né(e) le
                                        {{
                                            formatBirthDate(student.birth_date)
                                        }}
                                        ·
                                        {{
                                            student.email ||
                                            student.phone ||
                                            'Sans coordonnées'
                                        }}</span
                                    >
                                </span>
                                <span
                                    class="grid size-5 shrink-0 place-items-center rounded border"
                                    :class="
                                        addStudentForm.student_ids.includes(
                                            student.id,
                                        )
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : ''
                                    "
                                    ><Check
                                        v-if="
                                            addStudentForm.student_ids.includes(
                                                student.id,
                                            )
                                        "
                                        class="size-3.5"
                                /></span>
                            </button>
                            <p
                                v-if="!filteredStudents.length"
                                class="p-6 text-center text-sm text-muted-foreground"
                            >
                                Aucun étudiant disponible ne correspond à cette
                                recherche.
                            </p>
                        </div>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <InputError
                                :message="addStudentForm.errors.student_ids"
                            />
                            <Button
                                :disabled="
                                    addStudentForm.processing ||
                                    !addStudentForm.student_ids.length
                                "
                                ><UserPlus class="mr-2 size-4" />Ajouter
                                {{
                                    addStudentForm.student_ids.length || ''
                                }}
                                étudiant(s)</Button
                            >
                        </div>
                    </form>

                    <div
                        v-if="!editingEnrollment"
                        class="overflow-hidden rounded-lg border"
                    >
                        <div
                            v-for="enrollment in rosterGroup.enrollments"
                            :key="enrollment.id"
                            class="flex flex-col gap-3 border-b p-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <img
                                    v-if="enrollment.student.photo_url"
                                    :src="enrollment.student.photo_url"
                                    :alt="studentName(enrollment.student)"
                                    class="size-10 shrink-0 rounded-full object-cover"
                                />
                                <span
                                    v-else
                                    class="grid size-10 shrink-0 place-items-center rounded-full bg-muted text-xs font-semibold"
                                    >{{ enrollment.student.first_name[0]
                                    }}{{
                                        enrollment.student.last_name[0]
                                    }}</span
                                >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ studentName(enrollment.student) }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{
                                            enrollment.student.email ||
                                            'Sans e-mail'
                                        }}
                                        ·
                                        {{
                                            enrollment.student.phone ||
                                            'Sans téléphone'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <DropdownMenu v-if="access.is_admin">
                                <DropdownMenuTrigger as-child
                                    ><Button size="sm" variant="outline"
                                        >Actions<ChevronDown
                                            class="ml-2 size-4" /></Button
                                ></DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-56">
                                    <DropdownMenuLabel>{{
                                        studentName(enrollment.student)
                                    }}</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem as-child>
                                        <a
                                            :href="`/admin/students/${enrollment.student_id}`"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            ><FolderOpen
                                                class="mr-2 size-4"
                                            />Ouvrir le dossier</a
                                        >
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @select="editStudent(enrollment)"
                                        ><Pencil class="mr-2 size-4" />Modifier
                                        rapidement</DropdownMenuItem
                                    >
                                    <DropdownMenuSub>
                                        <DropdownMenuSubTrigger
                                            ><ArrowRightLeft
                                                class="mr-2 size-4"
                                            />Changer de
                                            groupe</DropdownMenuSubTrigger
                                        >
                                        <DropdownMenuSubContent>
                                            <DropdownMenuItem
                                                v-for="target in plan.groups.filter(
                                                    (item) =>
                                                        item.id !==
                                                        rosterGroup?.id,
                                                )"
                                                :key="target.id"
                                                :disabled="
                                                    Boolean(
                                                        target.capacity &&
                                                            target.enrollments
                                                                .length >=
                                                                target.capacity,
                                                    )
                                                "
                                                @select="
                                                    quickMove(
                                                        enrollment,
                                                        target,
                                                    )
                                                "
                                            >
                                                {{ target.name
                                                }}<span
                                                    class="ml-auto text-xs text-muted-foreground"
                                                    >{{
                                                        target.enrollments
                                                            .length
                                                    }}/{{
                                                        target.capacity || '∞'
                                                    }}</span
                                                >
                                            </DropdownMenuItem>
                                        </DropdownMenuSubContent>
                                    </DropdownMenuSub>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <p
                            v-if="!rosterGroup.enrollments.length"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            Aucun étudiant dans ce groupe.
                        </p>
                    </div>

                    <form
                        v-else
                        class="space-y-4 rounded-lg border p-4"
                        @submit.prevent="saveStudent"
                    >
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">Modifier le dossier</h3>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="editingEnrollment = null"
                                >Retour à la liste</Button
                            >
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Prénom</Label
                                ><Input
                                    v-model="studentForm.first_name"
                                    class="mt-1"
                                    required
                                />
                            </div>
                            <div>
                                <Label>Nom</Label
                                ><Input
                                    v-model="studentForm.last_name"
                                    class="mt-1"
                                    required
                                />
                            </div>
                            <div>
                                <Label>E-mail</Label
                                ><Input
                                    v-model="studentForm.email"
                                    class="mt-1"
                                    type="email"
                                />
                            </div>
                            <div>
                                <Label>Téléphone</Label
                                ><Input
                                    v-model="studentForm.phone"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label>Téléphone parent</Label
                                ><Input
                                    v-model="studentForm.parent_phone"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label>Niveau scolaire</Label
                                ><Input
                                    v-model="studentForm.school_level"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                        <div>
                            <Label>Notes</Label
                            ><textarea
                                v-model="studentForm.notes"
                                rows="2"
                                class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                            />
                        </div>
                        <InputError
                            :message="Object.values(studentForm.errors)[0]"
                        />
                        <div
                            class="flex flex-col justify-between gap-3 border-t pt-4 sm:flex-row"
                        >
                            <div class="flex flex-1 gap-2">
                                <select
                                    v-model="moveForm.training_plan_group_id"
                                    class="h-9 flex-1 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option
                                        v-for="group in plan.groups"
                                        :key="group.id"
                                        :value="String(group.id)"
                                    >
                                        {{ group.name }}
                                    </option>
                                </select>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="
                                        moveForm.processing ||
                                        Number(
                                            moveForm.training_plan_group_id,
                                        ) === rosterGroup.id
                                    "
                                    @click="moveStudent"
                                    ><ArrowRightLeft
                                        class="mr-2 size-4"
                                    />Déplacer</Button
                                >
                            </div>
                            <Button :disabled="studentForm.processing"
                                >Enregistrer</Button
                            >
                        </div>
                    </form>
                </div>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="attendanceDialog">
            <DialogScrollContent
                class="max-h-[92dvh] w-[calc(100vw-1rem)] max-w-2xl"
            >
                <DialogHeader>
                    <DialogTitle
                        >{{
                            attendanceReadOnly
                                ? 'Présences enregistrées'
                                : 'Saisir les présences'
                        }}
                        · {{ attendanceSession?.title }}</DialogTitle
                    >
                    <DialogDescription
                        >{{ rosterGroup?.name }} ·
                        {{
                            attendanceReadOnly
                                ? 'consultation en lecture seule'
                                : 'sélectionnez le statut de chaque étudiant'
                        }}.</DialogDescription
                    >
                </DialogHeader>
                <form
                    v-if="rosterGroup"
                    class="space-y-4"
                    @submit.prevent="saveAttendance"
                >
                    <div
                        v-if="!attendanceReadOnly"
                        class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/20 p-3"
                    >
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="toggleAllAttendance"
                            >{{
                                attendanceSelectedIds.length ===
                                rosterGroup.enrollments.length
                                    ? 'Tout désélectionner'
                                    : 'Tout sélectionner'
                            }}</Button
                        >
                        <span class="mr-auto text-sm text-muted-foreground"
                            >{{
                                attendanceSelectedIds.length
                            }}
                            sélectionné(s)</span
                        >
                        <Button
                            type="button"
                            size="sm"
                            class="bg-emerald-600 hover:bg-emerald-700"
                            :disabled="!attendanceSelectedIds.length"
                            @click="applyAttendanceStatus('present')"
                            >Présents</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            :disabled="!attendanceSelectedIds.length"
                            @click="applyAttendanceStatus('absent')"
                            >Absents</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="!attendanceSelectedIds.length"
                            @click="applyAttendanceStatus('late')"
                            >En retard</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="!attendanceSelectedIds.length"
                            @click="applyAttendanceStatus('excused')"
                            >Excusés</Button
                        >
                    </div>
                    <div class="overflow-hidden rounded-lg border">
                        <div
                            v-for="enrollment in rosterGroup.enrollments"
                            :key="enrollment.id"
                            class="grid grid-cols-[28px_1fr] items-center gap-3 border-b p-3 last:border-0 sm:grid-cols-[32px_1fr_150px]"
                            :class="
                                attendanceSelectedIds.includes(
                                    enrollment.student_id,
                                )
                                    ? 'bg-primary/5'
                                    : ''
                            "
                        >
                            <input
                                type="checkbox"
                                :checked="
                                    attendanceSelectedIds.includes(
                                        enrollment.student_id,
                                    )
                                "
                                :disabled="attendanceReadOnly"
                                class="size-4 disabled:opacity-40"
                                @change="
                                    toggleAttendanceStudent(
                                        enrollment.student_id,
                                    )
                                "
                            />
                            <span class="flex items-center gap-2 font-medium"
                                ><img
                                    v-if="enrollment.student.photo_url"
                                    :src="enrollment.student.photo_url"
                                    class="size-8 rounded-full object-cover"
                                />{{ studentName(enrollment.student) }}</span
                            >
                            <select
                                v-model="
                                    attendanceForm.attendances[
                                        enrollment.student_id
                                    ]
                                "
                                :disabled="attendanceReadOnly"
                                class="col-span-2 h-10 w-full rounded-md border bg-background px-2 text-sm disabled:cursor-default disabled:opacity-100 sm:col-span-1 sm:h-9"
                            >
                                <option value="present">Présent</option>
                                <option value="absent">Absent</option>
                                <option value="late">En retard</option>
                                <option value="excused">Excusé</option>
                            </select>
                        </div>
                        <p
                            v-if="!rosterGroup.enrollments.length"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            Ajoutez d’abord des étudiants au groupe.
                        </p>
                    </div>
                    <template v-if="!attendanceReadOnly">
                        <InputError
                            :message="Object.values(attendanceForm.errors)[0]"
                        />
                        <DialogFooter
                            ><Button
                                :disabled="
                                    attendanceForm.processing ||
                                    !rosterGroup.enrollments.length
                                "
                                >Enregistrer les présences</Button
                            ></DialogFooter
                        >
                    </template>
                </form>
            </DialogScrollContent>
        </Dialog>
    </AdminLayout>
</template>
