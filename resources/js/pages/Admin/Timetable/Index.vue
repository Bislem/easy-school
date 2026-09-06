<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Copy,
    Expand,
    FileDown,
    GraduationCap,
    Maximize2,
    Minimize2,
    Plus,
    Printer,
    RefreshCw,
    Users,
    X,
} from 'lucide-vue-next';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';

type Option = {
    id: number;
    name: string;
    title?: string;
    code?: string;
    type?: string;
    capacity?: number;
    is_active?: boolean;
    is_available?: boolean;
    classroom_id?: number | null;
    school_level_id?: number | null;
    level?: any;
    classroom?: Option | null;
    school_levels?: Array<{ id: number }>;
    teachers?: Array<{ id: number; name: string }>;
};
type Session = {
    id: number;
    series_id: string;
    occurrence_date: string;
    academic_period_id: number;
    start_time: string;
    end_time: string;
    status: string;
    change_type?: string | null;
    group: Option;
    subject: Option;
    teacher: Option;
    room: Option;
};
type Catalogue = {
    cycles: Array<{ id: number; name: string; levels: Option[] }>;
    groups: Option[];
    subjects: Option[];
    teachers: Option[];
    rooms: Option[];
    academic_periods: Array<{
        id: number;
        name: string;
        academic_year: string;
        is_current: boolean;
        starts_on: string;
        ends_on: string;
    }>;
};
type Settings = {
    working_days: number[];
    day_starts_at: string;
    day_ends_at: string;
    default_session_duration: number;
    breaks: Array<{ name: string; start_time: string; end_time: string }>;
    time_slots: Array<{ start_time: string; end_time: string }>;
};

const loading = ref(true);
const saving = ref(false);
const fullscreen = ref(false);
const compact = ref(false);
const drawerOpen = ref(false);
const copyOpen = ref(false);
const error = ref('');
const warnings = ref<string[]>([]);
const dragged = ref<Session | null>(null);
const selected = ref<Session | null>(null);
const conflictCount = ref(0);
const view = ref<'global' | 'group' | 'teacher' | 'room'>('global');
const catalogue = ref<Catalogue>({
    cycles: [],
    groups: [],
    subjects: [],
    teachers: [],
    rooms: [],
    academic_periods: [],
});
const settings = ref<Settings>({
    working_days: [1, 2, 3, 4, 5],
    day_starts_at: '08:00',
    day_ends_at: '17:00',
    default_session_duration: 60,
    breaks: [],
    time_slots: [],
});
const sessions = ref<Session[]>([]);
const weekStart = ref(startOfWeek(new Date()));
const filters = reactive({
    cycle: '',
    level: '',
    group: '',
    teacher: '',
    room: '',
});
const copyForm = reactive({ sourceGroup: '', targetGroup: '' });
const form = reactive({
    group: '',
    subject: '',
    teacher: '',
    room: '',
    day: 1,
    start: '08:00',
    end: '09:00',
    status: 'published',
    notes: '',
    scope: 'one',
});

const dayNames: Record<number, string> = {
    1: 'Lundi',
    2: 'Mardi',
    3: 'Mercredi',
    4: 'Jeudi',
    5: 'Vendredi',
    6: 'Samedi',
    7: 'Dimanche',
};
const days = computed(() =>
    settings.value.working_days.map((number) => {
        const date = addDays(weekStart.value, number - 1);
        return { number, name: dayNames[number], date, iso: iso(date) };
    }),
);
const levels = computed(() =>
    catalogue.value.cycles
        .filter((cycle) => !filters.cycle || cycle.id === Number(filters.cycle))
        .flatMap((cycle) => cycle.levels),
);
const filteredGroups = computed(() =>
    catalogue.value.groups.filter(
        (group) =>
            (!filters.level ||
                group.school_level_id === Number(filters.level)) &&
            (!filters.cycle ||
                levels.value.some(
                    (level) => level.id === group.school_level_id,
                )),
    ),
);
const slots = computed(() =>
    settings.value.time_slots?.length
        ? settings.value.time_slots
        : buildSlots(
              settings.value.day_starts_at,
              settings.value.day_ends_at,
              settings.value.default_session_duration,
          ),
);
const visibleSessions = computed(() =>
    sessions.value.filter((session) => {
        const levelId =
            session.group?.school_level_id ?? session.group?.level?.id;
        const cycleId = session.group?.level?.cycle?.id;
        return (
            (!filters.cycle || cycleId === Number(filters.cycle)) &&
            (!filters.level || levelId === Number(filters.level)) &&
            (!filters.group || session.group.id === Number(filters.group)) &&
            (!filters.teacher ||
                session.teacher.id === Number(filters.teacher)) &&
            (!filters.room || session.room.id === Number(filters.room))
        );
    }),
);
const currentPeriod = computed(
    () =>
        catalogue.value.academic_periods.find((item) => item.is_current) ??
        catalogue.value.academic_periods[0],
);
const weekLabel = computed(
    () =>
        `${formatDate(weekStart.value)} – ${formatDate(addDays(weekStart.value, 6))}`,
);
const unresolved = computed(() => conflictCount.value);
const todayIso = iso(new Date());
const todaySessions = computed(() =>
    sessions.value.filter((item) => item.occurrence_date === todayIso),
);
const nowMinutes = computed(
    () => new Date().getHours() * 60 + new Date().getMinutes(),
);
const teachingNow = computed(
    () =>
        new Set(
            todaySessions.value
                .filter(
                    (item) =>
                        timeMinutes(item.start_time) <= nowMinutes.value &&
                        timeMinutes(item.end_time) > nowMinutes.value,
                )
                .map((item) => item.teacher.id),
        ).size,
);
const occupiedRooms = computed(
    () => new Set(todaySessions.value.map((item) => item.room.id)).size,
);
const availableRooms = computed(
    () =>
        catalogue.value.rooms.filter(
            (room) => room.is_active && room.is_available !== false,
        ).length - occupiedRooms.value,
);
const upcoming = computed(() =>
    todaySessions.value
        .filter((item) => timeMinutes(item.start_time) > nowMinutes.value)
        .sort((a, b) => a.start_time.localeCompare(b.start_time))
        .slice(0, 3),
);
const availableSubjects = computed(() => {
    const group = catalogue.value.groups.find(
        (item) => item.id === Number(form.group),
    );
    const level = group?.school_level_id;
    return catalogue.value.subjects.filter(
        (subject) =>
            !level ||
            !subject.school_levels?.length ||
            subject.school_levels.some((item) => item.id === level),
    );
});
const availableTeachers = computed(() => {
    const group = catalogue.value.groups.find(
        (item) => item.id === Number(form.group),
    );
    const subject = catalogue.value.subjects.find(
        (item) => item.id === Number(form.subject),
    );
    return catalogue.value.teachers.filter(
        (teacher) =>
            (!group?.teachers?.length ||
                group.teachers.some((item) => item.id === teacher.id)) &&
            (!subject?.teachers?.length ||
                subject.teachers.some((item) => item.id === teacher.id)),
    );
});

watch(
    () => filters.group,
    (value) => {
        if (value) view.value = 'group';
    },
);
watch(
    () => filters.teacher,
    (value) => {
        if (value) view.value = 'teacher';
    },
);
watch(
    () => filters.room,
    (value) => {
        if (value) view.value = 'room';
    },
);
watch(
    () => form.group,
    () => {
        const group = catalogue.value.groups.find(
            (item) => item.id === Number(form.group),
        );
        form.room = group?.classroom_id ? String(group.classroom_id) : '';
        if (
            form.subject &&
            !availableSubjects.value.some(
                (subject) => subject.id === Number(form.subject),
            )
        ) {
            form.subject = '';
        }
        if (
            form.teacher &&
            !availableTeachers.value.some(
                (teacher) => teacher.id === Number(form.teacher),
            )
        ) {
            form.teacher = '';
        }
    },
);
watch(
    () => form.subject,
    () => {
        if (
            form.teacher &&
            !availableTeachers.value.some(
                (teacher) => teacher.id === Number(form.teacher),
            )
        )
            form.teacher = '';
    },
);
watch(
    () => form.start,
    () => {
        form.end = addMinutesToTime(
            form.start,
            settings.value.default_session_duration,
        );
    },
);

onMounted(async () => {
    await loadFoundation();
    await loadWeek();
});
onBeforeUnmount(() => {
    if (document.fullscreenElement) document.exitFullscreen();
});

async function loadFoundation() {
    loading.value = true;
    try {
        [catalogue.value, settings.value] = await Promise.all([
            api('/api/v1/timetable/catalogue'),
            api('/api/v1/timetable/settings'),
        ]);
    } catch (e: any) {
        error.value = e.message;
    }
}
async function loadWeek() {
    loading.value = true;
    error.value = '';
    try {
        const response = await api(
            `/api/v1/timetable/calendar?from=${iso(weekStart.value)}&to=${iso(addDays(weekStart.value, 6))}`,
        );
        sessions.value = response.data;
        void auditConflicts();
    } catch (e: any) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}
async function auditConflicts() {
    const checks = await Promise.allSettled(
        sessions.value.map((session) =>
            api('/api/v1/timetable/sessions/check-conflicts', {
                method: 'POST',
                body: JSON.stringify({
                    training_plan_group_id: session.group.id,
                    course_id: session.subject.id,
                    teacher_id: session.teacher.id,
                    classroom_id: session.room.id,
                    academic_period_id: session.academic_period_id,
                    day: dayOfWeek(session.occurrence_date),
                    effective_date: session.occurrence_date,
                    start_time: session.start_time.slice(0, 5),
                    end_time: session.end_time.slice(0, 5),
                    status: session.status,
                    except_session_id: session.id,
                }),
            }),
        ),
    );
    conflictCount.value = checks.filter(
        (result) => result.status === 'fulfilled' && !result.value.available,
    ).length;
}
function changeWeek(amount: number) {
    weekStart.value = addDays(weekStart.value, amount * 7);
    loadWeek();
}
function goToday() {
    weekStart.value = startOfWeek(new Date());
    loadWeek();
}
function sessionsAt(
    day: string,
    slot: { start_time: string; end_time: string },
) {
    return visibleSessions.value.filter(
        (item) =>
            item.occurrence_date === day &&
            item.start_time.slice(0, 5) >= slot.start_time &&
            item.start_time.slice(0, 5) < slot.end_time,
    );
}
function openCreate(
    day = days.value[0]?.number ?? 1,
    start = slots.value[0]?.start_time ?? '08:00',
) {
    selected.value = null;
    error.value = '';
    warnings.value = [];
    Object.assign(form, {
        group: filters.group || '',
        subject: '',
        teacher: filters.teacher || '',
        room: filters.room || '',
        day,
        start,
        end: addMinutesToTime(start, settings.value.default_session_duration),
        status: 'published',
        notes: '',
        scope: 'one',
    });
    const group = catalogue.value.groups.find(
        (item) => item.id === Number(form.group),
    );
    if (group?.classroom_id) form.room = String(group.classroom_id);
    drawerOpen.value = true;
}
function openSession(session: Session) {
    selected.value = session;
    error.value = '';
    warnings.value = [];
    Object.assign(form, {
        group: String(session.group.id),
        subject: String(session.subject.id),
        teacher: String(session.teacher.id),
        room: String(session.room.id),
        day: dayOfWeek(session.occurrence_date),
        start: session.start_time.slice(0, 5),
        end: session.end_time.slice(0, 5),
        status: session.status,
        notes: '',
        scope: 'one',
    });
    drawerOpen.value = true;
}
function payload(date?: string) {
    return {
        training_plan_group_id: Number(form.group),
        course_id: Number(form.subject),
        teacher_id: Number(form.teacher),
        classroom_id: Number(form.room) || null,
        academic_period_id: currentPeriod.value?.id,
        day: form.day,
        effective_date: date,
        start_time: form.start,
        end_time: form.end,
        recurrence: selected.value ? undefined : 'weekly',
        status: form.status,
        notes: form.notes || null,
        except_session_id: selected.value?.id,
    };
}
async function validateForm(date?: string) {
    error.value = '';
    warnings.value = [];
    const result = await api('/api/v1/timetable/sessions/check-conflicts', {
        method: 'POST',
        body: JSON.stringify(payload(date)),
    });
    warnings.value = result.warnings.map((item: any) => item.message);
    if (!result.available) {
        error.value = result.conflicts
            .map((item: any) => item.message)
            .join('\n');
        return false;
    }
    return true;
}
async function saveSession() {
    saving.value = true;
    error.value = '';
    try {
        const date = selected.value?.occurrence_date;
        if (!(await validateForm(date))) return;
        if (selected.value)
            await api(`/api/v1/timetable/sessions/${selected.value.id}`, {
                method: 'PUT',
                body: JSON.stringify({ ...payload(date), scope: form.scope }),
            });
        else
            await api('/api/v1/timetable/sessions', {
                method: 'POST',
                body: JSON.stringify(payload()),
            });
        drawerOpen.value = false;
        await loadWeek();
    } catch (e: any) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
async function cancelSession() {
    if (!selected.value || !confirm('Annuler cette séance ?')) return;
    saving.value = true;
    try {
        await api(`/api/v1/timetable/sessions/${selected.value.id}/cancel`, {
            method: 'PATCH',
            body: JSON.stringify({
                effective_date:
                    form.scope === 'one'
                        ? selected.value.occurrence_date
                        : null,
                scope: form.scope,
            }),
        });
        drawerOpen.value = false;
        await loadWeek();
    } catch (e: any) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
async function duplicateSession() {
    if (!selected.value) return;
    saving.value = true;
    try {
        if (!(await validateForm(selected.value.occurrence_date))) return;
        await api(`/api/v1/timetable/sessions/${selected.value.id}/duplicate`, {
            method: 'POST',
            body: JSON.stringify({
                ...payload(selected.value.occurrence_date),
                recurrence: 'once',
            }),
        });
        drawerOpen.value = false;
        await loadWeek();
    } catch (e: any) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
async function dropSession(
    day: { number: number; iso: string },
    slot: { start_time: string; end_time: string },
) {
    const session = dragged.value;
    dragged.value = null;
    if (!session) return;
    const duration =
        timeMinutes(session.end_time) - timeMinutes(session.start_time);
    const move = {
        training_plan_group_id: session.group.id,
        course_id: session.subject.id,
        teacher_id: session.teacher.id,
        classroom_id: session.room.id,
        academic_period_id: currentPeriod.value?.id,
        day: day.number,
        effective_date: day.iso,
        start_time: slot.start_time,
        end_time: addMinutesToTime(slot.start_time, duration),
        status: session.status,
        except_session_id: session.id,
    };
    try {
        const check = await api('/api/v1/timetable/sessions/check-conflicts', {
            method: 'POST',
            body: JSON.stringify(move),
        });
        if (!check.available)
            throw new Error(
                check.conflicts.map((item: any) => item.message).join('\n'),
            );
        await api(`/api/v1/timetable/sessions/${session.id}/move`, {
            method: 'PATCH',
            body: JSON.stringify(move),
        });
        await loadWeek();
    } catch (e: any) {
        error.value = e.message;
    }
}
async function copyGroupWeek() {
    const source = Number(copyForm.sourceGroup),
        target = Number(copyForm.targetGroup);
    if (!source || !target || source === target) return;
    saving.value = true;
    error.value = '';
    const sourceItems = sessions.value.filter(
        (item) => item.group.id === source,
    );
    let copied = 0;
    for (const item of sourceItems) {
        try {
            const targetGroup = catalogue.value.groups.find(
                (group) => group.id === target,
            );
            await api(`/api/v1/timetable/sessions/${item.id}/duplicate`, {
                method: 'POST',
                body: JSON.stringify({
                    training_plan_group_id: target,
                    classroom_id: targetGroup?.classroom_id || item.room.id,
                    effective_date: item.occurrence_date,
                    day: dayOfWeek(item.occurrence_date),
                    start_time: item.start_time.slice(0, 5),
                    end_time: item.end_time.slice(0, 5),
                    recurrence: 'once',
                }),
            });
            copied++;
        } catch (e: any) {
            error.value += `${item.subject.title || item.subject.name}: ${e.message}\n`;
        }
    }
    saving.value = false;
    if (copied) {
        copyOpen.value = false;
        await loadWeek();
    }
}
async function duplicateWeek() {
    if (
        !confirm(
            'Dupliquer les séances ponctuelles de cette semaine vers la semaine suivante ?',
        )
    )
        return;
    saving.value = true;
    let count = 0;
    for (const item of visibleSessions.value) {
        const next = iso(
            addDays(new Date(`${item.occurrence_date}T12:00:00`), 7),
        );
        try {
            await api(`/api/v1/timetable/sessions/${item.id}/duplicate`, {
                method: 'POST',
                body: JSON.stringify({
                    effective_date: next,
                    day: dayOfWeek(next),
                    recurrence: 'once',
                }),
            });
            count++;
        } catch (e: any) {
            error.value = e.message;
        }
    }
    saving.value = false;
    if (count) changeWeek(1);
}
function setView(value: typeof view.value) {
    view.value = value;
    if (value !== 'group') filters.group = '';
    if (value !== 'teacher') filters.teacher = '';
    if (value !== 'room') filters.room = '';
}
async function toggleFullscreen() {
    if (!document.fullscreenElement) {
        await document.documentElement.requestFullscreen();
        fullscreen.value = true;
    } else {
        await document.exitFullscreen();
        fullscreen.value = false;
    }
}
function printTimetable() {
    window.print();
}

async function api(url: string, init: RequestInit = {}) {
    const csrf = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            ...(init.headers || {}),
        },
        ...init,
    });
    const data = response.status === 204 ? null : await response.json();
    if (!response.ok)
        throw new Error(
            data?.errors?.conflicts?.join('\n') ||
                Object.values(data?.errors || {})
                    .flat()
                    .join('\n') ||
                data?.message ||
                'Une erreur est survenue.',
        );
    return data;
}
function startOfWeek(date: Date) {
    const result = new Date(date);
    const day = result.getDay() || 7;
    result.setDate(result.getDate() - day + 1);
    result.setHours(12, 0, 0, 0);
    return result;
}
function addDays(date: Date, days: number) {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
}
function iso(date: Date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
function formatDate(date: Date) {
    return new Intl.DateTimeFormat('fr-DZ', {
        day: '2-digit',
        month: 'short',
    }).format(date);
}
function dayOfWeek(date: string) {
    return new Date(`${date}T12:00:00`).getDay() || 7;
}
function timeMinutes(value: string) {
    const [h, m] = value.slice(0, 5).split(':').map(Number);
    return h * 60 + m;
}
function addMinutesToTime(value: string, amount: number) {
    const total = timeMinutes(value) + amount;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}
function buildSlots(start: string, end: string, duration: number) {
    const result = [];
    for (
        let cursor = timeMinutes(start);
        cursor < timeMinutes(end);
        cursor += duration
    )
        result.push({
            start_time: addMinutesToTime('00:00', cursor),
            end_time: addMinutesToTime(
                '00:00',
                Math.min(cursor + duration, timeMinutes(end)),
            ),
        });
    return result;
}
</script>

<template>
    <Head title="Emploi du temps" />
    <AdminLayout>
        <main
            class="timetable-page flex-1 space-y-5 bg-slate-50/60 p-3 sm:p-5 lg:p-7"
            :class="{ 'timetable-compact': compact }"
        >
            <header
                class="no-print flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-semibold text-slate-950"
                    >
                        <span class="rounded-xl bg-blue-600 p-2 text-white"
                            ><CalendarDays class="size-5" /></span
                        >Emploi du temps
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Planification hebdomadaire des groupes, enseignants et
                        salles.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="copyOpen = true"
                        ><Copy class="mr-2 size-4" />Copier un groupe</Button
                    ><Button variant="outline" @click="duplicateWeek"
                        ><RefreshCw class="mr-2 size-4" />Dupliquer la
                        semaine</Button
                    ><Button variant="outline" @click="printTimetable"
                        ><Printer class="mr-2 size-4" />Imprimer</Button
                    ><Button variant="outline" @click="printTimetable"
                        ><FileDown class="mr-2 size-4" />PDF</Button
                    ><Button @click="openCreate()"
                        ><Plus class="mr-2 size-4" />Séance</Button
                    >
                </div>
            </header>

            <section
                class="no-print grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5"
            >
                <div class="summary-card">
                    <CalendarDays />
                    <div>
                        <b>{{ todaySessions.length }}</b
                        ><span>Séances aujourd’hui</span>
                    </div>
                </div>
                <div class="summary-card">
                    <Building2 />
                    <div>
                        <b>{{ occupiedRooms }}</b
                        ><span>Salles occupées</span>
                    </div>
                </div>
                <div class="summary-card">
                    <Expand />
                    <div>
                        <b>{{ Math.max(0, availableRooms) }}</b
                        ><span>Salles disponibles</span>
                    </div>
                </div>
                <div class="summary-card">
                    <Users />
                    <div>
                        <b>{{ teachingNow }}</b
                        ><span>Enseignants en cours</span>
                    </div>
                </div>
                <div
                    class="summary-card sm:col-span-2 lg:col-span-4 xl:col-span-1"
                >
                    <Clock3 />
                    <div class="min-w-0">
                        <b>{{ upcoming[0]?.start_time?.slice(0, 5) || '—' }}</b
                        ><span class="truncate">{{
                            upcoming[0]?.subject?.title ||
                            'Aucune séance à venir'
                        }}</span>
                    </div>
                </div>
            </section>

            <div
                v-if="error"
                class="no-print rounded-xl border border-red-200 bg-red-50 p-3 text-sm whitespace-pre-line text-red-800"
            >
                <div class="flex items-start gap-2">
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" /><span>{{
                        error
                    }}</span
                    ><button class="ml-auto" @click="error = ''">
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <section
                class="no-print rounded-2xl border bg-white p-3 shadow-sm sm:p-4"
            >
                <div
                    class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
                >
                    <div
                        class="flex overflow-x-auto rounded-lg bg-slate-100 p-1"
                    >
                        <button
                            v-for="item in [
                                { key: 'global', label: 'Global' },
                                { key: 'group', label: 'Par groupe' },
                                { key: 'teacher', label: 'Par enseignant' },
                                { key: 'room', label: 'Par salle' },
                            ]"
                            :key="item.key"
                            class="rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap"
                            :class="
                                view === item.key
                                    ? 'bg-white text-blue-700 shadow-sm'
                                    : 'text-slate-600'
                            "
                            @click="setView(item.key as any)"
                        >
                            {{ item.label }}
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="changeWeek(-1)"
                            ><ChevronLeft class="size-4" /></Button
                        ><Button size="sm" variant="outline" @click="goToday"
                            >Aujourd’hui</Button
                        ><strong class="min-w-36 text-center text-sm">{{
                            weekLabel
                        }}</strong
                        ><Button
                            size="sm"
                            variant="outline"
                            @click="changeWeek(1)"
                            ><ChevronRight class="size-4"
                        /></Button>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="compact = !compact"
                            >{{ compact ? 'Confort' : 'Compact' }}</Button
                        ><Button
                            size="sm"
                            variant="outline"
                            @click="toggleFullscreen"
                            ><Minimize2
                                v-if="fullscreen"
                                class="size-4" /><Maximize2
                                v-else
                                class="size-4" /></Button
                        ><span
                            class="flex items-center rounded-md px-3 text-xs font-semibold"
                            :class="
                                unresolved
                                    ? 'bg-amber-100 text-amber-800'
                                    : 'bg-emerald-100 text-emerald-700'
                            "
                            ><AlertTriangle class="mr-1 size-3.5" />{{
                                unresolved
                            }}
                            conflit</span
                        >
                    </div>
                </div>
                <div
                    class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
                >
                    <select v-model="filters.cycle" class="filter-select">
                        <option value="">Tous les cycles</option>
                        <option
                            v-for="cycle in catalogue.cycles"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                    <select v-model="filters.level" class="filter-select">
                        <option value="">Tous les niveaux</option>
                        <option
                            v-for="level in levels"
                            :key="level.id"
                            :value="level.id"
                        >
                            {{ level.name }}
                        </option>
                    </select>
                    <select v-model="filters.group" class="filter-select">
                        <option value="">Tous les groupes</option>
                        <option
                            v-for="group in filteredGroups"
                            :key="group.id"
                            :value="group.id"
                        >
                            {{ group.name }}
                        </option>
                    </select>
                    <select v-model="filters.teacher" class="filter-select">
                        <option value="">Tous les enseignants</option>
                        <option
                            v-for="teacher in catalogue.teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.name }}
                        </option>
                    </select>
                    <select v-model="filters.room" class="filter-select">
                        <option value="">Toutes les salles</option>
                        <option
                            v-for="room in catalogue.rooms"
                            :key="room.id"
                            :value="room.id"
                        >
                            {{ room.name }}
                        </option>
                    </select>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-2xl border bg-white shadow-sm"
            >
                <div
                    v-if="loading"
                    class="flex h-80 items-center justify-center text-sm text-slate-500"
                >
                    <RefreshCw class="mr-2 size-4 animate-spin" />Chargement du
                    planning…
                </div>
                <div v-else class="matrix-scroll overflow-auto">
                    <div
                        class="matrix"
                        :style="{
                            gridTemplateColumns: `92px repeat(${days.length}, minmax(170px, 1fr))`,
                        }"
                    >
                        <div class="matrix-corner sticky top-0 left-0 z-30">
                            <Clock3 class="size-4" />Heure
                        </div>
                        <div
                            v-for="day in days"
                            :key="day.iso"
                            class="day-head sticky top-0 z-20"
                            :class="
                                day.iso === todayIso
                                    ? 'bg-blue-600 text-white'
                                    : ''
                            "
                        >
                            <strong>{{ day.name }}</strong
                            ><span>{{ formatDate(day.date) }}</span>
                        </div>
                        <template v-for="slot in slots" :key="slot.start_time">
                            <div class="time-cell sticky left-0 z-10">
                                <strong>{{ slot.start_time }}</strong
                                ><span>{{ slot.end_time }}</span>
                            </div>
                            <div
                                v-for="day in days"
                                :key="`${day.iso}-${slot.start_time}`"
                                class="slot-cell"
                                @dblclick="
                                    openCreate(day.number, slot.start_time)
                                "
                                @dragover.prevent
                                @drop="dropSession(day, slot)"
                            >
                                <button
                                    v-for="session in sessionsAt(day.iso, slot)"
                                    :key="`${session.id}-${session.occurrence_date}`"
                                    draggable="true"
                                    class="session-card"
                                    :title="`${session.subject.title || session.subject.name} · ${session.teacher.name}`"
                                    @dragstart="dragged = session"
                                    @click="openSession(session)"
                                >
                                    <strong>{{
                                        session.subject.title ||
                                        session.subject.name
                                    }}</strong
                                    ><span
                                        ><Users />{{ session.group.name }}</span
                                    ><span
                                        ><GraduationCap />{{
                                            session.teacher.name
                                        }}</span
                                    ><span
                                        ><Building2 />{{
                                            session.room.name
                                        }}</span
                                    ><small
                                        >{{ session.start_time.slice(0, 5) }}–{{
                                            session.end_time.slice(0, 5)
                                        }}</small
                                    >
                                </button>
                                <button
                                    v-if="!sessionsAt(day.iso, slot).length"
                                    class="add-cell no-print"
                                    @click="
                                        openCreate(day.number, slot.start_time)
                                    "
                                >
                                    <Plus class="size-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>
        </main>

        <div
            v-if="drawerOpen"
            class="fixed inset-0 z-50 bg-slate-950/35"
            @click.self="drawerOpen = false"
        >
            <aside
                class="absolute top-0 right-0 h-full w-full max-w-md overflow-y-auto bg-white p-5 shadow-2xl"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                selected
                                    ? 'Modifier la séance'
                                    : 'Nouvelle séance'
                            }}
                        </h2>
                        <p class="text-sm text-slate-500">
                            Les conflits sont contrôlés avant l’enregistrement.
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="drawerOpen = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="saveSession">
                    <label class="field"
                        ><span>Groupe</span
                        ><select v-model="form.group" required>
                            <option value="">Sélectionner</option>
                            <option
                                v-for="group in catalogue.groups"
                                :key="group.id"
                                :value="group.id"
                            >
                                {{ group.name }}
                            </option>
                        </select></label
                    ><label class="field"
                        ><span>Matière</span
                        ><select v-model="form.subject" required>
                            <option value="">Sélectionner</option>
                            <option
                                v-for="subject in availableSubjects"
                                :key="subject.id"
                                :value="subject.id"
                            >
                                {{ subject.title }}
                            </option>
                        </select></label
                    ><label class="field"
                        ><span>Enseignant</span
                        ><select v-model="form.teacher" required>
                            <option value="">Sélectionner</option>
                            <option
                                v-for="teacher in availableTeachers"
                                :key="teacher.id"
                                :value="teacher.id"
                            >
                                {{ teacher.name }}
                            </option>
                        </select></label
                    ><label class="field"
                        ><span>Salle</span
                        ><select v-model="form.room" required>
                            <option value="">Salle du groupe</option>
                            <option
                                v-for="room in catalogue.rooms"
                                :key="room.id"
                                :value="room.id"
                                :disabled="
                                    !room.is_active ||
                                    room.is_available === false
                                "
                            >
                                {{ room.name }} · {{ room.type }}
                            </option>
                        </select></label
                    >
                    <div class="grid grid-cols-3 gap-3">
                        <label class="field"
                            ><span>Jour</span
                            ><select v-model="form.day">
                                <option
                                    v-for="day in days"
                                    :key="day.number"
                                    :value="day.number"
                                >
                                    {{ day.name }}
                                </option>
                            </select></label
                        ><label class="field"
                            ><span>Début</span
                            ><Input
                                v-model="form.start"
                                type="time"
                                required /></label
                        ><label class="field"
                            ><span>Fin</span
                            ><Input v-model="form.end" type="time" required
                        /></label>
                    </div>
                    <label class="field"
                        ><span>Notes</span
                        ><textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Informations complémentaires…"
                        /></label
                    ><label v-if="selected" class="field"
                        ><span>Appliquer à</span
                        ><select v-model="form.scope">
                            <option value="one">
                                Cette occurrence uniquement
                            </option>
                            <option value="all">
                                Toute la série récurrente
                            </option>
                        </select></label
                    >
                    <div
                        v-if="warnings.length"
                        class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800"
                    >
                        <p v-for="warning in warnings" :key="warning">
                            {{ warning }}
                        </p>
                    </div>
                    <div
                        v-if="error"
                        class="rounded-lg bg-red-50 p-3 text-sm whitespace-pre-line text-red-700"
                    >
                        {{ error }}
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                        <Button
                            v-if="selected"
                            type="button"
                            variant="destructive"
                            :disabled="saving"
                            @click="cancelSession"
                            >Annuler la séance</Button
                        ><Button
                            v-if="selected"
                            type="button"
                            variant="outline"
                            :disabled="saving"
                            @click="duplicateSession"
                            ><Copy class="mr-2 size-4" />Dupliquer</Button
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="saving"
                            @click="validateForm(selected?.occurrence_date)"
                            >Vérifier</Button
                        ><Button :disabled="saving">{{
                            saving ? 'Enregistrement…' : 'Enregistrer'
                        }}</Button>
                    </div>
                </form>
            </aside>
        </div>

        <div
            v-if="copyOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/35 p-4"
            @click.self="copyOpen = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <div class="flex justify-between">
                    <h2 class="text-lg font-semibold">
                        Copier l’emploi du temps
                    </h2>
                    <button @click="copyOpen = false">
                        <X class="size-5" />
                    </button>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Copie les séances visibles de cette semaine vers un autre
                    groupe. Chaque conflit bloque uniquement la séance
                    concernée.
                </p>
                <div class="mt-5 space-y-4">
                    <label class="field"
                        ><span>Groupe source</span
                        ><select v-model="copyForm.sourceGroup">
                            <option value="">Sélectionner</option>
                            <option
                                v-for="group in catalogue.groups"
                                :key="group.id"
                                :value="group.id"
                            >
                                {{ group.name }}
                            </option>
                        </select></label
                    ><label class="field"
                        ><span>Groupe cible</span
                        ><select v-model="copyForm.targetGroup">
                            <option value="">Sélectionner</option>
                            <option
                                v-for="group in catalogue.groups"
                                :key="group.id"
                                :value="group.id"
                            >
                                {{ group.name }}
                            </option>
                        </select></label
                    >
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <Button variant="outline" @click="copyOpen = false"
                        >Fermer</Button
                    ><Button
                        :disabled="
                            saving ||
                            !copyForm.sourceGroup ||
                            !copyForm.targetGroup
                        "
                        @click="copyGroupWeek"
                        >Copier</Button
                    >
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.summary-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    border: 1px solid rgb(226 232 240);
    border-radius: 1rem;
    background: white;
    padding: 0.9rem 1rem;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}
.summary-card > svg {
    width: 2rem;
    height: 2rem;
    padding: 0.45rem;
    border-radius: 0.65rem;
    background: rgb(239 246 255);
    color: rgb(37 99 235);
}
.summary-card div {
    display: flex;
    min-width: 0;
    flex-direction: column;
}
.summary-card b {
    font-size: 1.25rem;
    line-height: 1.25;
}
.summary-card span {
    font-size: 0.72rem;
    color: rgb(100 116 139);
}
.filter-select,
.field select,
.field textarea {
    width: 100%;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.5rem;
    background: white;
    padding: 0.55rem 0.7rem;
    font-size: 0.875rem;
    outline: none;
}
.filter-select:focus,
.field select:focus,
.field textarea:focus {
    border-color: rgb(59 130 246);
    box-shadow: 0 0 0 3px rgb(59 130 246 / 0.12);
}
.field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.field > span {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgb(51 65 85);
}
.matrix-scroll {
    max-height: calc(100vh - 305px);
}
.matrix {
    display: grid;
    min-width: 820px;
}
.matrix-corner,
.day-head,
.time-cell {
    display: flex;
    border-right: 1px solid rgb(226 232 240);
    border-bottom: 1px solid rgb(226 232 240);
    background: white;
}
.matrix-corner {
    height: 58px;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: rgb(100 116 139);
}
.day-head {
    height: 58px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.day-head strong {
    font-size: 0.82rem;
}
.day-head span {
    font-size: 0.7rem;
    opacity: 0.75;
}
.time-cell {
    min-height: 112px;
    flex-direction: column;
    align-items: center;
    padding-top: 0.8rem;
    background: rgb(248 250 252);
}
.time-cell strong {
    font-size: 0.78rem;
}
.time-cell span {
    font-size: 0.65rem;
    color: rgb(100 116 139);
}
.slot-cell {
    position: relative;
    min-height: 112px;
    border-right: 1px solid rgb(226 232 240);
    border-bottom: 1px solid rgb(226 232 240);
    padding: 0.28rem;
    background: linear-gradient(180deg, #fff, #fbfdff);
}
.slot-cell:hover {
    background: rgb(239 246 255 / 0.6);
}
.session-card {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 0.16rem;
    border-left: 3px solid rgb(37 99 235);
    border-radius: 0.55rem;
    background: rgb(239 246 255);
    padding: 0.48rem;
    text-align: left;
    color: rgb(30 64 175);
    box-shadow: 0 1px 2px rgb(30 64 175 / 0.08);
    transition: 0.15s;
}
.session-card:hover {
    transform: translateY(-1px);
    background: rgb(219 234 254);
    box-shadow: 0 4px 8px rgb(30 64 175 / 0.12);
}
.session-card strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.78rem;
}
.session-card span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.66rem;
    color: rgb(71 85 105);
}
.session-card span svg {
    width: 0.68rem;
    height: 0.68rem;
    flex: none;
}
.session-card small {
    margin-top: 0.15rem;
    font-size: 0.65rem;
    font-weight: 700;
}
.add-cell {
    position: absolute;
    right: 0.3rem;
    bottom: 0.3rem;
    display: none;
    border-radius: 0.4rem;
    padding: 0.25rem;
    color: rgb(37 99 235);
}
.slot-cell:hover .add-cell {
    display: block;
}
.timetable-compact .slot-cell,
.timetable-compact .time-cell {
    min-height: 78px;
}
.timetable-compact .session-card span:nth-of-type(2),
.timetable-compact .session-card span:nth-of-type(3) {
    display: none;
}
@media (max-width: 640px) {
    .matrix-scroll {
        max-height: calc(100vh - 250px);
    }
    .matrix {
        min-width: 950px;
    }
    .summary-card {
        padding: 0.7rem;
    }
    .timetable-page {
        padding: 0.65rem;
    }
}
@media print {
    .no-print {
        display: none !important;
    }
    .timetable-page {
        padding: 0 !important;
        background: white !important;
    }
    .matrix-scroll {
        max-height: none !important;
        overflow: visible !important;
    }
    .matrix {
        min-width: 100% !important;
    }
    .slot-cell,
    .time-cell {
        min-height: 74px !important;
    }
    .session-card {
        break-inside: avoid;
        box-shadow: none;
    }
    .matrix-corner,
    .day-head {
        position: static !important;
    }
}
</style>
