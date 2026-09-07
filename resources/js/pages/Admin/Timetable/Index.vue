<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    Clock3,
    Coffee,
    Copy,
    Expand,
    FileDown,
    GraduationCap,
    Maximize2,
    Minimize2,
    Plus,
    Printer,
    RefreshCw,
    Search,
    Settings2,
    Users,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
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
    title_ar?: string | null;
    code?: string;
    type?: string;
    capacity?: number;
    is_active?: boolean;
    is_available?: boolean;
    classroom_id?: number | null;
    principal_teacher_id?: number | null;
    school_level_id?: number | null;
    level?: any;
    classroom?: Option | null;
    principal_teacher?: Option | null;
    school_levels?: Array<{ id: number }>;
    teachers?: Array<{ id: number; name: string }>;
    email?: string;
};
type Session = {
    id: number;
    series_id: string;
    occurrence_date: string;
    academic_period_id?: number | null;
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
    academic_year?: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
    };
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
    breaks: Array<{
        name: string;
        type?: 'break' | 'meal';
        start_time: string;
        end_time: string;
        start?: string;
        end?: string;
    }>;
    time_slots: Array<{
        start_time: string;
        end_time: string;
        start?: string;
        end?: string;
    }>;
};

const loading = ref(true);
const page = usePage();
const schoolName = computed(
    () =>
        (page.props.school as { trading_name?: string })?.trading_name ||
        (page.props.auth as { tenant?: { name?: string } })?.tenant?.name ||
        'Établissement scolaire',
);
const saving = ref(false);
const printing = ref(false);
const fullscreen = ref(false);
const compact = ref(false);
const drawerOpen = ref(false);
const copyOpen = ref(false);
const settingsOpen = ref(false);
const groupSettingsOpen = ref(false);
const teacherPickerOpen = ref(false);
const teacherSearch = ref('');
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
    working_days: [7, 1, 2, 3, 4],
    day_starts_at: '08:00',
    day_ends_at: '17:00',
    default_session_duration: 60,
    breaks: [],
    time_slots: [],
});
const sessions = ref<Session[]>([]);
const weekStart = ref(startOfWeek(new Date()));
const initialQuery =
    typeof window === 'undefined'
        ? new URLSearchParams()
        : new URLSearchParams(window.location.search);
const filters = reactive({
    cycle: initialQuery.get('cycle_id') || '',
    level: initialQuery.get('level_id') || '',
    group: initialQuery.get('group_id') || '',
    teacher: initialQuery.get('teacher_id') || '',
    room: initialQuery.get('room_id') || '',
});
const copyForm = reactive({ sourceGroup: '', targetGroup: '' });
const groupDefaultsForm = reactive({
    classroom_id: '',
    principal_teacher_id: '',
});
const settingsForm = reactive<Settings>({
    working_days: [],
    day_starts_at: '08:00',
    day_ends_at: '17:00',
    default_session_duration: 60,
    breaks: [],
    time_slots: [],
});
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
const dayOrder = [7, 1, 2, 3, 4, 5, 6];
const dayOptions = dayOrder.map((number) => ({
    number,
    name: dayNames[number],
}));
const days = computed(() =>
    dayOrder
        .filter((number) => settings.value.working_days.includes(number))
        .map((number) => {
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
const selectedGroup = computed(() =>
    catalogue.value.groups.find((group) => group.id === Number(filters.group)),
);
const slots = computed(() =>
    settings.value.time_slots?.length
        ? settings.value.time_slots
        : buildSlots(
              settings.value.day_starts_at,
              settings.value.day_ends_at,
              settings.value.default_session_duration,
              settings.value.breaks,
          ),
);
const printRowHeightMm = computed(() =>
    Math.min(16, 158 / Math.max(slots.value.length, 1)),
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
const unresolved = computed(() => conflictCount.value);
const todayIso = iso(new Date());
const todaySessions = computed(() =>
    sessions.value.filter(
        (item) => dayOfWeek(item.occurrence_date) === dayOfWeek(todayIso),
    ),
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
    if (!subject) return [];

    const subjectTeacherIds = new Set(
        (subject.teachers ?? []).map((teacher) => teacher.id),
    );
    return catalogue.value.teachers.filter(
        (teacher) =>
            subjectTeacherIds.has(teacher.id) &&
            (!group?.teachers?.length ||
                group.teachers.some((item) => item.id === teacher.id)),
    );
});
const searchedTeachers = computed(() => {
    const query = teacherSearch.value.trim().toLocaleLowerCase('fr');
    if (!query) return availableTeachers.value;

    return availableTeachers.value.filter((teacher) =>
        [teacher.name, teacher.email].some((value) =>
            value?.toLocaleLowerCase('fr').includes(query),
        ),
    );
});

watch(
    () => filters.level,
    () => {
        const cycle = catalogue.value.cycles.find((item) =>
            item.levels.some((level) => level.id === Number(filters.level)),
        );
        filters.cycle = cycle ? String(cycle.id) : '';
        if (
            !filteredGroups.value.some(
                (group) => group.id === Number(filters.group),
            )
        )
            filters.group = '';
    },
);
watch(
    () => filters.group,
    (value) => {
        if (value) {
            view.value = 'group';
            loadWeek();
        } else sessions.value = [];
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
    () => [
        filters.cycle,
        filters.level,
        filters.group,
        filters.teacher,
        filters.room,
    ],
    () => syncFiltersToUrl(),
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
        teacherSearch.value = form.teacher
            ? availableTeachers.value.find(
                  (teacher) => teacher.id === Number(form.teacher),
              )?.name || ''
            : '';
        teacherPickerOpen.value = false;
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
});
onBeforeUnmount(() => {
    if (document.fullscreenElement) document.exitFullscreen();
});

async function loadFoundation() {
    loading.value = true;
    try {
        [catalogue.value, settings.value] = await Promise.all([
            api('/admin/timetable-api/catalogue'),
            api('/admin/timetable-api/settings'),
        ]);
        if (catalogue.value.academic_year?.start_date)
            weekStart.value = startOfWeek(
                new Date(
                    `${catalogue.value.academic_year.start_date}T12:00:00`,
                ),
            );
        normalizeSettings(settings.value);
        Object.assign(settingsForm, JSON.parse(JSON.stringify(settings.value)));
        restoreUrlFilters();
        if (filters.group) await loadWeek();
    } catch (e: any) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}
function restoreUrlFilters() {
    if (
        filters.level &&
        !catalogue.value.cycles.some((cycle) =>
            cycle.levels.some((level) => level.id === Number(filters.level)),
        )
    ) {
        filters.level = '';
    }
    if (
        filters.group &&
        !catalogue.value.groups.some(
            (group) =>
                group.id === Number(filters.group) &&
                (!filters.level ||
                    group.school_level_id === Number(filters.level)),
        )
    ) {
        filters.group = '';
    }
    const selectedCycle = catalogue.value.cycles.find((cycle) =>
        cycle.levels.some((level) => level.id === Number(filters.level)),
    );
    filters.cycle = selectedCycle ? String(selectedCycle.id) : '';
    if (filters.group) view.value = 'group';
    if (
        filters.teacher &&
        !catalogue.value.teachers.some(
            (teacher) => teacher.id === Number(filters.teacher),
        )
    ) {
        filters.teacher = '';
    }
    if (
        filters.room &&
        !catalogue.value.rooms.some((room) => room.id === Number(filters.room))
    ) {
        filters.room = '';
    }
}
function syncFiltersToUrl() {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    const values: Record<string, string> = {
        cycle_id: filters.cycle,
        level_id: filters.level,
        group_id: filters.group,
        teacher_id: filters.teacher,
        room_id: filters.room,
    };
    Object.entries(values).forEach(([key, value]) => {
        if (value) url.searchParams.set(key, value);
        else url.searchParams.delete(key);
    });
    window.history.replaceState(window.history.state, '', url);
}
async function loadWeek() {
    loading.value = true;
    error.value = '';
    try {
        const response = await api(
            `/admin/timetable-api/calendar?from=${iso(weekStart.value)}&to=${iso(addDays(weekStart.value, 6))}&group_id=${filters.group}&template=1`,
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
            api('/admin/timetable-api/sessions/check-conflicts', {
                method: 'POST',
                body: JSON.stringify({
                    school_group_id: session.group.id,
                    course_id: session.subject.id,
                    teacher_id: session.teacher.id,
                    classroom_id: session.room.id,
                    day: dayOfWeek(session.occurrence_date),
                    start_time: shortTime(session.start_time, '08:00'),
                    end_time: shortTime(session.end_time, '09:00'),
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
function sessionsAt(
    day: string,
    slot: { start_time: string; end_time: string },
) {
    return visibleSessions.value.filter(
        (item) =>
            item.occurrence_date === day &&
            shortTime(item.start_time) >= slot.start_time &&
            shortTime(item.start_time) < slot.end_time,
    );
}
function sessionsOccupying(
    day: string,
    slot: { start_time: string; end_time: string },
) {
    return visibleSessions.value.filter(
        (item) =>
            item.occurrence_date === day &&
            shortTime(item.start_time) < slot.end_time &&
            shortTime(item.end_time) > slot.start_time,
    );
}
function sessionSpan(session: Session) {
    return Math.max(
        1,
        slots.value.filter(
            (slot) =>
                shortTime(session.start_time) < slot.end_time &&
                shortTime(session.end_time) > slot.start_time,
        ).length,
    );
}
function printSessionHeight(session: Session) {
    return `${Math.max(5, printRowHeightMm.value * sessionSpan(session) - 1)}mm`;
}
function setDurationUnits(units: number) {
    form.end = addMinutesToTime(
        form.start,
        settings.value.default_session_duration * units,
    );
}
function openCreate(
    day = days.value[0]?.number ?? 1,
    start = slots.value[0]?.start_time ?? '08:00',
) {
    if (!filters.group) return;
    selected.value = null;
    error.value = '';
    warnings.value = [];
    teacherSearch.value = '';
    teacherPickerOpen.value = false;
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
    });
    const group = catalogue.value.groups.find(
        (item) => item.id === Number(form.group),
    );
    if (group?.classroom_id) form.room = String(group.classroom_id);
    drawerOpen.value = true;
}
function openGroupSettings() {
    if (!selectedGroup.value) return;
    groupDefaultsForm.classroom_id = selectedGroup.value.classroom_id
        ? String(selectedGroup.value.classroom_id)
        : '';
    groupDefaultsForm.principal_teacher_id = selectedGroup.value
        .principal_teacher_id
        ? String(selectedGroup.value.principal_teacher_id)
        : '';
    groupSettingsOpen.value = true;
}
async function saveGroupDefaults() {
    if (!selectedGroup.value) return;
    saving.value = true;
    try {
        const updated = await api(
            `/admin/timetable-api/groups/${selectedGroup.value.id}/defaults`,
            {
                method: 'PUT',
                body: JSON.stringify({
                    classroom_id:
                        Number(groupDefaultsForm.classroom_id) || null,
                    principal_teacher_id:
                        Number(groupDefaultsForm.principal_teacher_id) || null,
                }),
            },
        );
        const index = catalogue.value.groups.findIndex(
            (group) => group.id === updated.id,
        );
        if (index >= 0) catalogue.value.groups[index] = updated;
        groupSettingsOpen.value = false;
    } catch (e: any) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
function addBreak(type: 'break' | 'meal' = 'break') {
    settingsForm.breaks.push({
        name: type === 'meal' ? 'Pause déjeuner' : 'Récréation',
        type,
        start_time: '10:00',
        end_time: '10:15',
    } as any);
}
async function saveSettings() {
    saving.value = true;
    try {
        settings.value = await api('/admin/timetable-api/settings', {
            method: 'PUT',
            body: JSON.stringify(settingsForm),
        });
        normalizeSettings(settings.value);
        Object.assign(settingsForm, JSON.parse(JSON.stringify(settings.value)));
        settingsOpen.value = false;
    } catch (e: any) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
function breakAt(slot: { start_time: string; end_time: string }) {
    return settings.value.breaks?.find(
        (item) =>
            item.start_time < slot.end_time && item.end_time > slot.start_time,
    );
}
function normalizeSettings(value: Settings) {
    value.day_starts_at = shortTime(value.day_starts_at, '08:00');
    value.day_ends_at = shortTime(value.day_ends_at, '17:00');
    value.breaks = (value.breaks ?? []).map((item) => ({
        ...item,
        type: item.type ?? 'break',
        start_time: shortTime(item.start_time ?? item.start, '12:00'),
        end_time: shortTime(item.end_time ?? item.end, '13:00'),
    }));
    value.time_slots = (value.time_slots ?? []).map((item) => ({
        start_time: shortTime(item.start_time ?? item.start, '08:00'),
        end_time: shortTime(item.end_time ?? item.end, '09:00'),
    }));
}

function shortTime(value: unknown, fallback = '—') {
    return typeof value === 'string' && value.length >= 5
        ? value.slice(0, 5)
        : fallback;
}
function openSession(session: Session) {
    selected.value = session;
    error.value = '';
    warnings.value = [];
    teacherSearch.value = session.teacher.name;
    teacherPickerOpen.value = false;
    Object.assign(form, {
        group: String(session.group.id),
        subject: String(session.subject.id),
        teacher: String(session.teacher.id),
        room: String(session.room.id),
        day: dayOfWeek(session.occurrence_date),
        start: shortTime(session.start_time, '08:00'),
        end: shortTime(session.end_time, '09:00'),
        status: session.status,
        notes: '',
    });
    drawerOpen.value = true;
}
function selectTeacher(teacher: Option) {
    form.teacher = String(teacher.id);
    teacherSearch.value = teacher.name;
    teacherPickerOpen.value = false;
}
function payload() {
    return {
        school_group_id: Number(form.group),
        course_id: Number(form.subject),
        teacher_id: Number(form.teacher),
        classroom_id: Number(form.room) || null,
        day: form.day,
        start_time: form.start,
        end_time: form.end,
        recurrence: selected.value ? undefined : 'weekly',
        status: form.status,
        notes: form.notes || null,
        except_session_id: selected.value?.id,
    };
}
async function validateForm() {
    error.value = '';
    warnings.value = [];
    const result = await api('/admin/timetable-api/sessions/check-conflicts', {
        method: 'POST',
        body: JSON.stringify(payload()),
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
        if (!(await validateForm())) return;
        if (selected.value)
            await api(`/admin/timetable-api/sessions/${selected.value.id}`, {
                method: 'PUT',
                body: JSON.stringify({ ...payload(), scope: 'all' }),
            });
        else
            await api('/admin/timetable-api/sessions', {
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
        await api(`/admin/timetable-api/sessions/${selected.value.id}/cancel`, {
            method: 'PATCH',
            body: JSON.stringify({
                scope: 'all',
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
        if (!(await validateForm())) return;
        await api(
            `/admin/timetable-api/sessions/${selected.value.id}/duplicate`,
            {
                method: 'POST',
                body: JSON.stringify({
                    ...payload(),
                    recurrence: 'weekly',
                }),
            },
        );
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
        school_group_id: session.group.id,
        course_id: session.subject.id,
        teacher_id: session.teacher.id,
        classroom_id: session.room.id,
        day: day.number,
        start_time: slot.start_time,
        end_time: addMinutesToTime(slot.start_time, duration),
        status: session.status,
        except_session_id: session.id,
    };
    try {
        const check = await api(
            '/admin/timetable-api/sessions/check-conflicts',
            {
                method: 'POST',
                body: JSON.stringify(move),
            },
        );
        if (!check.available)
            throw new Error(
                check.conflicts.map((item: any) => item.message).join('\n'),
            );
        await api(`/admin/timetable-api/sessions/${session.id}/move`, {
            method: 'PATCH',
            body: JSON.stringify({ ...move, scope: 'all' }),
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
            await api(`/admin/timetable-api/sessions/${item.id}/duplicate`, {
                method: 'POST',
                body: JSON.stringify({
                    school_group_id: target,
                    classroom_id: targetGroup?.classroom_id || item.room.id,
                    day: dayOfWeek(item.occurrence_date),
                    start_time: shortTime(item.start_time, '08:00'),
                    end_time: shortTime(item.end_time, '09:00'),
                    recurrence: 'weekly',
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
async function printTimetable() {
    if (!filters.group || printing.value) return;

    printing.value = true;
    await loadWeek();
    if (error.value) {
        printing.value = false;
        return;
    }

    await nextTick();
    if (document.fonts?.ready) await document.fonts.ready;
    await new Promise<void>((resolve) =>
        requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
    );
    window.print();
    printing.value = false;
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
function dayOfWeek(date: string) {
    return new Date(`${date}T12:00:00`).getDay() || 7;
}
function timeMinutes(value: unknown) {
    const [h, m] = shortTime(value, '00:00').split(':').map(Number);
    return h * 60 + m;
}
function addMinutesToTime(value: string, amount: number) {
    const total = timeMinutes(value) + amount;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}
function buildSlots(
    start: string,
    end: string,
    duration: number,
    breaks: Settings['breaks'] = [],
) {
    const result = [];
    let cursor = timeMinutes(start);
    const dayEnd = timeMinutes(end);
    const pauses = [...breaks].sort((a, b) =>
        a.start_time.localeCompare(b.start_time),
    );
    while (cursor < dayEnd) {
        const active = pauses.find(
            (item) => timeMinutes(item.start_time) === cursor,
        );
        let slotEnd;
        if (active) slotEnd = Math.min(dayEnd, timeMinutes(active.end_time));
        else {
            slotEnd = Math.min(cursor + duration, dayEnd);
            const next = pauses.find(
                (item) =>
                    timeMinutes(item.start_time) > cursor &&
                    timeMinutes(item.start_time) < slotEnd,
            );
            if (next) slotEnd = timeMinutes(next.start_time);
        }
        if (slotEnd <= cursor) break;
        result.push({
            start_time: addMinutesToTime('00:00', cursor),
            end_time: addMinutesToTime('00:00', slotEnd),
        });
        cursor = slotEnd;
    }
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
                    <Button variant="outline" @click="settingsOpen = true"
                        ><Settings2 class="mr-2 size-4" />Paramètres</Button
                    >
                    <Button
                        variant="outline"
                        :disabled="!filters.group"
                        @click="copyOpen = true"
                        ><Copy class="mr-2 size-4" />Copier un groupe</Button
                    ><Button
                        variant="outline"
                        :disabled="!filters.group || loading || printing"
                        @click="printTimetable"
                        ><Printer class="mr-2 size-4" />{{
                            printing ? 'Préparation…' : 'Imprimer'
                        }}</Button
                    ><Button
                        variant="outline"
                        :disabled="!filters.group || loading || printing"
                        @click="printTimetable"
                        ><FileDown class="mr-2 size-4" />{{
                            printing ? 'Préparation…' : 'PDF'
                        }}</Button
                    ><Button :disabled="!filters.group" @click="openCreate()"
                        ><Plus class="mr-2 size-4" />Séance</Button
                    >
                </div>
            </header>

            <section
                class="no-print rounded-2xl border border-blue-100 bg-white p-4 shadow-sm sm:p-5"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <label class="field level-picker flex-1"
                        ><span>1. Sélectionnez le niveau</span
                        ><select v-model="filters.level">
                            <option value="">Choisir un niveau…</option>
                            <optgroup
                                v-for="cycle in catalogue.cycles"
                                :key="cycle.id"
                                :label="cycle.name"
                            >
                                <option
                                    v-for="level in cycle.levels.filter(
                                        (item) => item.is_active !== false,
                                    )"
                                    :key="level.id"
                                    :value="level.id"
                                >
                                    {{ level.name
                                    }}{{
                                        level.specialization
                                            ? ' · ' + level.specialization
                                            : ''
                                    }}
                                </option>
                            </optgroup>
                        </select></label
                    ><label class="field flex-1"
                        ><span>2. Sélectionnez le groupe</span
                        ><select
                            v-model="filters.group"
                            :disabled="!filters.level"
                        >
                            <option value="">
                                {{
                                    filters.level
                                        ? 'Choisir un groupe…'
                                        : 'Sélectionnez d’abord un niveau'
                                }}
                            </option>
                            <option
                                v-for="group in filteredGroups"
                                :key="group.id"
                                :value="group.id"
                            >
                                {{ group.name }}
                            </option>
                        </select></label
                    ><Button
                        v-if="selectedGroup"
                        variant="outline"
                        @click="openGroupSettings"
                        ><Settings2 class="mr-2 size-4" />Configurer le
                        groupe</Button
                    >
                </div>
                <div
                    v-if="selectedGroup"
                    class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-3"
                >
                    <div>
                        <span class="text-slate-500">Groupe</span
                        ><b class="block">{{ selectedGroup.name }}</b>
                    </div>
                    <div>
                        <span class="text-slate-500">Salle par défaut</span
                        ><b class="block">{{
                            selectedGroup.classroom?.name || 'Non définie'
                        }}</b>
                    </div>
                    <div>
                        <span class="text-slate-500">Enseignant principal</span
                        ><b class="block">{{
                            selectedGroup.principal_teacher?.name ||
                            'Non défini'
                        }}</b>
                    </div>
                </div>
            </section>

            <section
                v-if="filters.group"
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
                v-if="filters.group"
                class="no-print rounded-2xl border bg-white p-3 shadow-sm sm:p-4"
            >
                <div
                    class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
                >
                    <div>
                        <b>Emploi du temps · {{ selectedGroup?.name }}</b>
                        <p class="text-xs text-slate-500">
                            {{ selectedGroup?.level?.cycle?.name }} ·
                            {{ selectedGroup?.level?.name }}
                            <template
                                v-if="selectedGroup?.level?.specialization"
                            >
                                · {{ selectedGroup.level.specialization }}
                            </template>
                            · Toute l'année
                            {{ catalogue.academic_year?.name }}
                        </p>
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
            </section>

            <section
                v-if="filters.group"
                class="overflow-hidden rounded-2xl border bg-white shadow-sm"
            >
                <div class="print-only print-heading">
                    <div class="print-school">
                        <b>{{ schoolName }}</b>
                        <span>{{ catalogue.academic_year?.name }}</span>
                    </div>
                    <div class="print-title">
                        <h1>Emploi du temps hebdomadaire</h1>
                        <h2 dir="rtl">جدول استعمال الزمن الأسبوعي</h2>
                    </div>
                    <div class="print-details">
                        <p><b>Groupe :</b> {{ selectedGroup?.name }}</p>
                        <p>
                            <b>Niveau :</b>
                            {{ selectedGroup?.level?.cycle?.name }} ·
                            {{ selectedGroup?.level?.name }}
                            <template
                                v-if="selectedGroup?.level?.specialization"
                            >
                                · {{ selectedGroup.level.specialization }}
                            </template>
                        </p>
                        <p>
                            <b>Salle :</b>
                            {{ selectedGroup?.classroom?.name || '—' }} ·
                            <b>Prof. principal :</b>
                            {{ selectedGroup?.principal_teacher?.name || '—' }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="loading"
                    class="no-print flex h-80 items-center justify-center text-sm text-slate-500"
                >
                    <RefreshCw class="mr-2 size-4 animate-spin" />Chargement du
                    planning…
                </div>
                <div v-else class="matrix-scroll overflow-auto">
                    <div
                        class="matrix"
                        :style="{
                            gridTemplateColumns: `92px repeat(${days.length}, minmax(170px, 1fr))`,
                            '--print-grid-columns': `12mm repeat(${days.length}, minmax(0, 1fr))`,
                            '--print-row-height': `${printRowHeightMm}mm`,
                        }"
                    >
                        <div class="matrix-corner sticky top-0 left-0 z-30">
                            <Clock3 class="size-4" />Heure
                        </div>
                        <div
                            v-for="day in days"
                            :key="day.iso"
                            class="day-head sticky top-0 z-20 text-slate-900"
                        >
                            <strong>{{ day.name }}</strong
                            ><span>Chaque semaine</span>
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
                                :class="{
                                    'slot-occupied': sessionsOccupying(
                                        day.iso,
                                        slot,
                                    ).length,
                                }"
                                @dblclick="
                                    !sessionsOccupying(day.iso, slot).length &&
                                    openCreate(day.number, slot.start_time)
                                "
                                @dragover.prevent
                                @drop="dropSession(day, slot)"
                            >
                                <div v-if="breakAt(slot)" class="break-cell">
                                    <Coffee class="size-4" /><b>{{
                                        breakAt(slot)?.name
                                    }}</b
                                    ><small
                                        >{{ breakAt(slot)?.start_time }}–{{
                                            breakAt(slot)?.end_time
                                        }}</small
                                    >
                                </div>
                                <button
                                    v-for="session in breakAt(slot)
                                        ? []
                                        : sessionsAt(day.iso, slot)"
                                    :key="`${session.id}-${session.occurrence_date}`"
                                    draggable="true"
                                    class="session-card"
                                    :style="{
                                        '--session-span': sessionSpan(session),
                                        '--print-session-height':
                                            printSessionHeight(session),
                                    }"
                                    :title="`${session.subject.title || session.subject.name} · ${session.teacher.name}`"
                                    @dragstart="dragged = session"
                                    @click="openSession(session)"
                                >
                                    <strong class="screen-subject">{{
                                        session.subject.title ||
                                        session.subject.name
                                    }}</strong>
                                    <strong
                                        class="print-only print-subject"
                                        dir="rtl"
                                        >{{
                                            session.subject.title_ar ||
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
                                        >{{ shortTime(session.start_time) }}–{{
                                            shortTime(session.end_time)
                                        }}</small
                                    >
                                </button>
                                <button
                                    v-if="
                                        !breakAt(slot) &&
                                        !sessionsOccupying(day.iso, slot).length
                                    "
                                    class="add-cell no-print"
                                    @click="
                                        openCreate(day.number, slot.start_time)
                                    "
                                >
                                    <Plus class="size-7" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>
            <section
                v-else
                class="rounded-2xl border-2 border-dashed border-slate-200 bg-white px-6 py-20 text-center shadow-sm"
            >
                <div
                    class="mx-auto grid size-16 place-items-center rounded-2xl bg-blue-50 text-blue-600"
                >
                    <CalendarDays class="size-8" />
                </div>
                <h2 class="mt-5 text-xl font-semibold text-slate-900">
                    Sélectionnez un niveau puis un groupe
                </h2>
                <p class="mx-auto mt-2 max-w-lg text-sm text-slate-500">
                    L’emploi du temps du groupe apparaîtra ici. Vous pourrez
                    ensuite ajouter, déplacer ou modifier ses séances.
                </p>
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
                    >
                    <div class="field relative">
                        <span>Enseignant</span>
                        <div class="relative">
                            <Search
                                class="absolute top-3 left-3 size-4 text-slate-400"
                            />
                            <Input
                                v-model="teacherSearch"
                                class="h-11 pl-9"
                                :disabled="!form.subject"
                                :placeholder="
                                    form.subject
                                        ? 'Rechercher un enseignant…'
                                        : 'Sélectionnez d’abord une matière'
                                "
                                @focus="teacherPickerOpen = true"
                                @input="
                                    form.teacher = '';
                                    teacherPickerOpen = true;
                                "
                            />
                        </div>
                        <div
                            v-if="teacherPickerOpen && form.subject"
                            class="absolute top-full z-30 mt-1 max-h-52 w-full overflow-y-auto rounded-xl border bg-white p-1.5 shadow-xl"
                        >
                            <button
                                v-for="teacher in searchedTeachers"
                                :key="teacher.id"
                                type="button"
                                class="flex w-full flex-col rounded-lg px-3 py-2 text-left text-sm hover:bg-blue-50"
                                @click="selectTeacher(teacher)"
                            >
                                <span class="font-medium text-slate-800">{{
                                    teacher.name
                                }}</span>
                                <span
                                    v-if="teacher.email"
                                    class="text-xs text-slate-400"
                                    >{{ teacher.email }}</span
                                >
                            </button>
                            <p
                                v-if="!searchedTeachers.length"
                                class="px-3 py-5 text-center text-xs text-slate-500"
                            >
                                {{
                                    availableTeachers.length
                                        ? 'Aucun enseignant ne correspond à la recherche.'
                                        : 'Aucun enseignant affecté à cette matière.'
                                }}
                            </p>
                        </div>
                        <p
                            v-if="form.subject && !availableTeachers.length"
                            class="text-xs text-amber-600"
                        >
                            Affectez d’abord un enseignant à cette matière dans
                            le module Matières.
                        </p>
                    </div>
                    <label class="field"
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
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-slate-600"
                            >Durée rapide</span
                        >
                        <Button
                            v-for="units in [1, 2, 3]"
                            :key="units"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="setDurationUnits(units)"
                        >
                            {{ units }} unité{{ units > 1 ? 's' : '' }} ·
                            {{ units * settings.default_session_duration }} min
                        </Button>
                    </div>
                    <label class="field"
                        ><span>Notes</span
                        ><textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Informations complémentaires…"
                        />
                    </label>
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
                            @click="validateForm()"
                            >Vérifier</Button
                        ><Button :disabled="saving || !form.teacher">{{
                            saving ? 'Enregistrement…' : 'Enregistrer'
                        }}</Button>
                    </div>
                </form>
            </aside>
        </div>

        <div
            v-if="groupSettingsOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/35 p-4"
            @click.self="groupSettingsOpen = false"
        >
            <form
                class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="saveGroupDefaults"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">
                            Paramètres du groupe
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ selectedGroup?.name }}
                        </p>
                    </div>
                    <button type="button" @click="groupSettingsOpen = false">
                        <X class="size-5" />
                    </button>
                </div>
                <label class="field"
                    ><span>Salle de classe par défaut</span
                    ><select v-model="groupDefaultsForm.classroom_id">
                        <option value="">Aucune salle par défaut</option>
                        <option
                            v-for="room in catalogue.rooms"
                            :key="room.id"
                            :value="room.id"
                            :disabled="
                                !room.is_active || room.is_available === false
                            "
                        >
                            {{ room.name }} · {{ room.capacity }} places
                        </option>
                    </select></label
                ><label class="field"
                    ><span>Enseignant principal</span
                    ><select v-model="groupDefaultsForm.principal_teacher_id">
                        <option value="">Aucun enseignant principal</option>
                        <option
                            v-for="teacher in catalogue.teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.name }}
                        </option>
                    </select></label
                >
                <div class="flex justify-end gap-2 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="groupSettingsOpen = false"
                        >Fermer</Button
                    ><Button :disabled="saving">Enregistrer</Button>
                </div>
            </form>
        </div>

        <div
            v-if="settingsOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/35 p-4"
            @click.self="settingsOpen = false"
        >
            <form
                class="mx-auto my-8 w-full max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="saveSettings"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            Paramètres des emplois du temps
                        </h2>
                        <p class="text-sm text-slate-500">
                            Ces horaires s’appliquent à tous les groupes de
                            l’école.
                        </p>
                    </div>
                    <button type="button" @click="settingsOpen = false">
                        <X class="size-5" />
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="field"
                        ><span>Début de journée</span
                        ><Input
                            v-model="settingsForm.day_starts_at"
                            type="time" /></label
                    ><label class="field"
                        ><span>Fin de journée</span
                        ><Input
                            v-model="settingsForm.day_ends_at"
                            type="time" /></label
                    ><label class="field"
                        ><span>Durée d’une séance</span
                        ><Input
                            v-model="settingsForm.default_session_duration"
                            type="number"
                            min="5"
                            max="480"
                    /></label>
                </div>
                <div>
                    <span class="text-sm font-semibold">Jours travaillés</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="day in dayOptions"
                            :key="day.number"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            ><input
                                v-model="settingsForm.working_days"
                                type="checkbox"
                                :value="day.number"
                            />{{ day.name }}</label
                        >
                    </div>
                </div>
                <div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <b>Récréations et pause repas</b>
                            <p class="text-xs text-slate-500">
                                Aucune séance ne pourra chevaucher ces périodes.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="addBreak('break')"
                                ><Plus class="mr-1 size-4" />Récréation</Button
                            ><Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="addBreak('meal')"
                                ><Coffee class="mr-1 size-4" />Repas</Button
                            >
                        </div>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="(item, index) in settingsForm.breaks"
                            :key="index"
                            class="grid items-center gap-2 rounded-xl border p-3 sm:grid-cols-[120px_1fr_110px_110px_auto]"
                        >
                            <select v-model="item.type">
                                <option value="break">Récréation</option>
                                <option value="meal">Repas</option></select
                            ><Input
                                v-model="item.name"
                                placeholder="Libellé"
                            /><Input
                                v-model="item.start_time"
                                type="time"
                            /><Input
                                v-model="item.end_time"
                                type="time"
                            /><Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="settingsForm.breaks.splice(index, 1)"
                                ><X class="size-4"
                            /></Button>
                        </div>
                        <p
                            v-if="!settingsForm.breaks.length"
                            class="rounded-xl border border-dashed p-5 text-center text-sm text-slate-500"
                        >
                            Aucune pause configurée.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="settingsOpen = false"
                        >Annuler</Button
                    ><Button :disabled="saving"
                        >Enregistrer pour tous les groupes</Button
                    >
                </div>
            </form>
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
.print-only {
    display: none;
}
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
.level-picker select {
    min-height: 2.75rem;
    border-color: rgb(191 219 254);
    background-color: rgb(248 250 252);
    font-weight: 600;
    color: rgb(30 64 175);
}
.level-picker select:hover {
    border-color: rgb(96 165 250);
    background-color: white;
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
.break-cell {
    position: absolute;
    inset: 0.35rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px dashed rgb(251 191 36);
    border-radius: 0.65rem;
    background: rgb(255 251 235);
    color: rgb(146 64 14);
    text-align: center;
}
.break-cell small {
    color: rgb(180 83 9);
}
.session-card {
    position: absolute;
    z-index: 5;
    top: 0.28rem;
    right: 0.28rem;
    left: 0.28rem;
    display: flex;
    width: auto;
    height: calc(var(--session-span, 1) * 112px - 0.56rem);
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
    inset: 0.45rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed transparent;
    border-radius: 0.65rem;
    color: rgb(37 99 235);
    opacity: 0;
    transform: scale(0.92);
    transition: 0.18s ease;
}
.slot-cell:hover .add-cell {
    border-color: rgb(147 197 253);
    background: rgb(219 234 254 / 0.65);
    opacity: 1;
    transform: scale(1);
}
.timetable-compact .slot-cell,
.timetable-compact .time-cell {
    min-height: 78px;
}
.timetable-compact .session-card span:nth-of-type(2),
.timetable-compact .session-card span:nth-of-type(3) {
    display: none;
}
.timetable-compact .session-card {
    height: calc(var(--session-span, 1) * 78px - 0.56rem);
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
    @page {
        size: A4 landscape;
        margin: 5mm;
    }
    :global(html),
    :global(body) {
        margin: 0 !important;
        overflow: hidden !important;
        background: white !important;
        color: black !important;
        print-color-adjust: economy;
    }
    :global([data-slot='sidebar']),
    :global([data-slot='sidebar-inset'] > header) {
        display: none !important;
    }
    :global([data-slot='sidebar-inset']) {
        width: 100% !important;
        min-height: 0 !important;
        overflow: hidden !important;
        margin: 0 !important;
    }
    .no-print {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    .timetable-page {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        min-height: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    .timetable-page > section:last-of-type {
        overflow: visible !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    .print-heading {
        display: grid !important;
        grid-template-columns: 1fr 1.35fr 1.35fr;
        align-items: center;
        gap: 4mm;
        margin-bottom: 2.5mm;
        border-bottom: 1px solid #444;
        padding-bottom: 2mm;
    }
    .print-heading h1 {
        margin: 0;
        font-size: 12pt;
        font-weight: 700;
    }
    .print-heading h2 {
        margin: 0.5mm 0 0;
        font-family: Arial, 'Noto Sans Arabic', sans-serif;
        font-size: 11pt;
    }
    .print-heading p,
    .print-heading span {
        margin: 0.4mm 0 0;
        font-size: 6.5pt;
        color: #333;
    }
    .print-school,
    .print-title,
    .print-details {
        display: flex;
        min-width: 0;
        flex-direction: column;
    }
    .print-school b {
        font-size: 10pt;
    }
    .print-title {
        text-align: center;
    }
    .print-details {
        align-items: flex-end;
        text-align: right;
    }
    .matrix-scroll {
        max-height: none !important;
        width: 100% !important;
        overflow: hidden !important;
    }
    .matrix {
        min-width: 100% !important;
        width: 100% !important;
        grid-template-columns: var(--print-grid-columns) !important;
        border-top: 1px solid #555;
        border-left: 1px solid #555;
        box-sizing: border-box !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .matrix > * {
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
    .slot-cell,
    .time-cell {
        min-height: var(--print-row-height) !important;
        height: var(--print-row-height) !important;
        border-color: #777 !important;
        background: white !important;
        overflow: hidden !important;
    }
    .slot-cell {
        overflow: visible !important;
    }
    .session-card {
        top: 0.5mm;
        right: 0.5mm;
        left: 0.5mm;
        height: var(--print-session-height) !important;
        justify-content: center;
        gap: 0.5mm;
        break-inside: avoid;
        border: 1px solid #555 !important;
        border-radius: 1mm;
        background: white !important;
        padding: 1.2mm;
        color: black !important;
        box-shadow: none !important;
    }
    .matrix-corner,
    .day-head {
        position: static !important;
        height: 9mm !important;
        border-color: #555 !important;
        background: #f2f2f2 !important;
        color: black !important;
    }
    .matrix-corner svg,
    .session-card svg {
        display: none !important;
    }
    .day-head span {
        display: none;
    }
    .day-head strong,
    .matrix-corner {
        font-size: 7pt;
    }
    .time-cell {
        position: static !important;
        justify-content: center;
        padding: 0 !important;
    }
    .time-cell strong,
    .time-cell span {
        font-size: 5.5pt;
        color: black !important;
    }
    .screen-subject,
    .session-card span:first-of-type {
        display: none !important;
    }
    .print-subject {
        overflow: visible !important;
        font-family: Arial, 'Noto Sans Arabic', sans-serif;
        font-size: 7pt !important;
        line-height: 1.35;
        text-align: center;
        white-space: normal !important;
    }
    .session-card span,
    .session-card small {
        justify-content: center;
        margin: 0;
        font-size: 5.5pt;
        color: #222 !important;
        text-align: center;
        white-space: normal;
    }
    .break-cell {
        inset: 0;
        border: 0;
        border-radius: 0;
        background: #f5f5f5 !important;
        color: #333 !important;
    }
    .break-cell svg {
        display: none;
    }
}
</style>
