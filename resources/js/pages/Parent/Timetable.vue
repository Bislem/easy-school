<script setup lang="ts">
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, MapPin, Printer, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    portal: any;
    context: any;
    academicYears: any[];
    days: any[];
    timeSlots: any[];
    breaks: any[];
    sessions: any[];
    hasEnrollment: boolean;
    hasGroup: boolean;
}>();

const todayDay = new Date().getDay() || 7;
const selectedDay = ref(
    props.days.some((day) => day.value === todayDay)
        ? todayDay
        : props.days[0]?.value,
);
const sessionsFor = (day: number) =>
    props.sessions.filter((item) => item.day === day);
const entriesFor = (day: number) =>
    [
        ...sessionsFor(day).map((item) => ({
            ...item,
            kind: 'lesson',
            label: item.subject || 'Cours',
        })),
        ...props.breaks.map((item, index) => ({
            ...item,
            id: `${day}-break-${index}`,
            kind: 'break',
            label: item.name || 'Pause',
        })),
    ].sort((a, b) => a.start_time.localeCompare(b.start_time));
const agenda = computed(() => entriesFor(selectedDay.value));
const arabicDays: Record<number, string> = {
    1: 'الإثنين',
    2: 'الثلاثاء',
    3: 'الأربعاء',
    4: 'الخميس',
    5: 'الجمعة',
    6: 'السبت',
    7: 'الأحد',
};
const shortTime = (value: string) => value?.slice(0, 5);
const scheduleRows = computed(() => {
    const rows = props.timeSlots.map((slot: any) => ({
        kind: 'lesson',
        start_time: shortTime(slot.start_time),
        end_time: shortTime(slot.end_time),
        name: '',
    }));
    props.breaks.forEach((item: any) => rows.push({
        kind: 'break',
        start_time: shortTime(item.start_time),
        end_time: shortTime(item.end_time),
        name: item.name || 'Pause',
    }));
    if (!rows.length) {
        props.sessions.forEach((item: any) => rows.push({
            kind: 'lesson',
            start_time: shortTime(item.start_time),
            end_time: shortTime(item.end_time),
            name: '',
        }));
    }
    return rows
        .filter((row: any, index: number, all: any[]) => all.findIndex((candidate) => candidate.start_time === row.start_time && candidate.end_time === row.end_time) === index)
        .sort((a: any, b: any) => a.start_time.localeCompare(b.start_time));
});
const sessionsAt = (day: number, row: any) =>
    props.sessions.filter((item: any) => item.day === day && shortTime(item.start_time) === row.start_time);
const dateForDay = (day: number) => {
    const value = new Date();
    const current = value.getDay() || 7;
    value.setDate(value.getDate() + day - current);
    return value.toLocaleDateString('fr-DZ', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
};

function move(offset: number) {
    const index = props.days.findIndex(
        (day) => day.value === selectedDay.value,
    );
    if (index >= 0)
        selectedDay.value =
            props.days[
                (index + offset + props.days.length) % props.days.length
            ].value;
}
function goToday() {
    if (props.days.some((day) => day.value === todayDay))
        selectedDay.value = todayDay;
}
function selectYear(event: Event) {
    router.get(
        '/parent/timetable',
        {
            academic_year_id: Number((event.target as HTMLSelectElement).value),
        },
        { preserveState: false },
    );
}
function printTimetable() {
    window.print();
}
</script>

<template>
    <Head title="Emploi du temps" />
    <ParentLayout :portal="portal">
        <div class="space-y-6">
            <div class="no-print flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold sm:text-3xl">
                        Emploi du temps
                    </h1>
                    <p v-if="context" class="mt-1 text-slate-500">
                        {{ context.name }} ·
                        {{
                            [context.level, context.group]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                </div>
                <div class="flex items-end gap-2">
                    <label v-if="academicYears.length > 1" class="text-sm text-slate-500">Année scolaire
                        <select :value="portal.academic_year?.id" class="mt-1 block rounded-xl border bg-white px-3 py-2 text-slate-900" @change="selectYear">
                            <option v-for="year in academicYears" :key="year.id" :value="year.id">{{ year.name }}</option>
                        </select>
                    </label>
                    <button v-if="sessions.length" type="button" class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm" @click="printTimetable">
                        <Printer class="size-4" /> Imprimer
                    </button>
                </div>
            </div>

            <section
                v-if="!context"
                class="rounded-2xl border border-dashed bg-white p-12 text-center text-slate-500"
            >
                Aucun enfant associé à ce compte.
            </section>
            <section
                v-else-if="!hasEnrollment"
                class="rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <b>Aucune information disponible pour cette année scolaire.</b>
            </section>
            <section
                v-else-if="!hasGroup"
                class="rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <b>L’élève n’a pas encore été affecté à un groupe.</b>
            </section>
            <section
                v-else-if="!sessions.length"
                class="rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <b>Aucun emploi du temps n’est disponible pour le moment.</b>
            </section>

            <template v-else>
                <section class="print-area hidden overflow-hidden rounded-2xl border bg-white shadow-sm lg:block">
                    <header class="print-heading border-b p-5 text-center">
                        <div class="grid grid-cols-3 items-center gap-4">
                            <div class="text-left text-sm"><b>{{ context.school || 'Établissement scolaire' }}</b><p class="text-slate-500">{{ portal.academic_year?.name }}</p></div>
                            <div><h2 class="text-xl font-bold">Emploi du temps hebdomadaire</h2><p dir="rtl" class="mt-1 text-lg font-bold">جدول استعمال الزمن الأسبوعي</p></div>
                            <div class="text-right text-sm"><b>{{ context.name }}</b><p class="text-slate-500">{{ [context.level, context.group].filter(Boolean).join(' · ') }}</p></div>
                        </div>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="timetable-grid min-w-[900px] w-full table-fixed border-collapse">
                            <thead><tr><th class="time-column">Heure<br><span dir="rtl">الوقت</span></th><th v-for="day in days" :key="day.value">{{ day.label }}<span dir="rtl">{{ arabicDays[day.value] }}</span></th></tr></thead>
                            <tbody>
                                <tr v-for="row in scheduleRows" :key="`${row.start_time}-${row.end_time}`" :class="{ 'break-row': row.kind === 'break' }">
                                    <th class="time-column">{{ row.start_time }}<span>{{ row.end_time }}</span></th>
                                    <template v-if="row.kind === 'break'">
                                        <td :colspan="days.length" class="break-label">{{ row.name }} · استراحة</td>
                                    </template>
                                    <td v-for="day in row.kind === 'lesson' ? days : []" :key="day.value">
                                        <article v-for="session in sessionsAt(day.value, row)" :key="session.id" class="lesson-card">
                                            <strong dir="rtl">{{ session.subject_ar || session.subject || 'حصة' }}</strong>
                                            <span v-if="session.subject_ar && session.subject">{{ session.subject }}</span>
                                            <small>{{ session.teacher }}</small>
                                            <small v-if="session.room">Salle {{ session.room }}</small>
                                        </article>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="no-print lg:hidden">
                    <div class="mb-4 rounded-2xl border bg-white p-2">
                        <div class="flex items-center justify-between">
                            <button class="rounded-xl p-2" @click="move(-1)">
                                <ChevronLeft />
                            </button>
                            <select
                                v-model="selectedDay"
                                class="border-0 bg-transparent font-bold"
                            >
                                <option
                                    v-for="day in days"
                                    :key="day.value"
                                    :value="day.value"
                                >
                                    {{ day.label }}
                                </option>
                            </select>
                            <button class="rounded-xl p-2" @click="move(1)">
                                <ChevronRight />
                            </button>
                        </div>
                        <div
                            class="flex items-center justify-between px-2 pb-1"
                        >
                            <p class="text-xs text-slate-500 capitalize">
                                {{ dateForDay(selectedDay) }}
                            </p>
                            <button
                                v-if="
                                    days.some((day) => day.value === todayDay)
                                "
                                class="text-xs font-semibold text-primary"
                                @click="goToday"
                            >
                                Aujourd’hui
                            </button>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <article
                            v-for="item in agenda"
                            :key="item.id"
                            class="flex gap-4 rounded-2xl border bg-white p-4"
                            :class="
                                item.kind === 'break'
                                    ? 'border-dashed bg-amber-50'
                                    : ''
                            "
                        >
                            <div
                                class="w-14 shrink-0 text-sm font-bold text-primary"
                            >
                                {{ item.start_time }}
                            </div>
                            <div>
                                <b :dir="item.subject_ar ? 'rtl' : undefined">{{ item.subject_ar || item.label }}</b>
                                <p v-if="item.subject_ar && item.subject" class="text-sm text-slate-600">{{ item.subject }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    jusqu’à {{ item.end_time }}
                                </p>
                                <p
                                    v-if="item.teacher"
                                    class="mt-2 flex items-center gap-1 text-sm text-slate-600"
                                >
                                    <UserRound class="size-4" />{{
                                        item.teacher
                                    }}
                                </p>
                                <p
                                    v-if="item.room"
                                    class="mt-1 flex items-center gap-1 text-sm text-slate-600"
                                >
                                    <MapPin class="size-4" />Salle
                                    {{ item.room }}
                                </p>
                            </div>
                        </article>
                        <p
                            v-if="!agenda.length"
                            class="rounded-2xl border border-dashed bg-white p-10 text-center text-slate-500"
                        >
                            Aucun cours prévu ce jour.
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </ParentLayout>
</template>

<style scoped>
.timetable-grid th,
.timetable-grid td {
    border: 1px solid rgb(203 213 225);
}
.timetable-grid thead th {
    background: rgb(241 245 249);
    padding: 0.75rem 0.5rem;
    font-size: 0.875rem;
}
.timetable-grid thead th span {
    display: block;
    margin-top: 0.2rem;
    color: rgb(71 85 105);
    font-size: 0.8rem;
}
.time-column {
    width: 5.5rem;
    text-align: center;
}
.timetable-grid tbody .time-column {
    background: rgb(248 250 252);
    padding: 0.6rem 0.25rem;
    font-size: 0.75rem;
}
.timetable-grid tbody .time-column span {
    display: block;
    color: rgb(100 116 139);
    font-weight: 400;
}
.timetable-grid tbody td {
    height: 6.5rem;
    padding: 0.35rem;
    vertical-align: top;
}
.lesson-card {
    display: flex;
    height: 100%;
    min-height: 5.7rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px solid rgb(153 246 228);
    border-radius: 0.6rem;
    background: rgb(240 253 250);
    padding: 0.45rem;
    text-align: center;
}
.lesson-card strong {
    color: rgb(15 118 110);
    font-family: Arial, 'Noto Sans Arabic', sans-serif;
    font-size: 1rem;
}
.lesson-card span {
    margin-top: 0.15rem;
    font-size: 0.72rem;
    font-weight: 600;
}
.lesson-card small {
    margin-top: 0.15rem;
    color: rgb(71 85 105);
    font-size: 0.65rem;
}
.break-row td,
.break-row th {
    height: auto !important;
}
.break-label {
    background: rgb(255 251 235);
    padding: 0.45rem !important;
    color: rgb(146 64 14);
    font-weight: 700;
    text-align: center;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 7mm;
    }
    :global(html),
    :global(body) {
        margin: 0 !important;
        overflow: visible !important;
        background: white !important;
        color: black !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    :global([data-slot='sidebar']),
    :global([data-slot='sidebar-inset'] > header),
    .no-print {
        display: none !important;
    }
    :global([data-slot='sidebar-inset']) {
        width: 100% !important;
        min-height: 0 !important;
        margin: 0 !important;
    }
    :global(main) {
        max-width: none !important;
        padding: 0 !important;
    }
    .print-area {
        display: block !important;
        overflow: visible !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    .print-heading {
        padding: 0 0 3mm !important;
    }
    .print-heading h2 {
        font-size: 12pt;
    }
    .print-heading p,
    .print-heading div {
        font-size: 7pt;
    }
    .timetable-grid {
        min-width: 100% !important;
    }
    .timetable-grid thead th {
        padding: 1.5mm;
        font-size: 7pt;
    }
    .timetable-grid tbody td {
        height: 18mm;
        padding: 0.8mm;
    }
    .time-column {
        width: 13mm;
    }
    .lesson-card {
        min-height: 15.5mm;
        border-color: #777;
        border-radius: 1mm;
        background: white;
        padding: 0.8mm;
    }
    .lesson-card strong {
        color: black;
        font-size: 7pt;
    }
    .lesson-card span,
    .lesson-card small,
    .timetable-grid tbody .time-column {
        color: #222;
        font-size: 5.5pt;
    }
    .break-label {
        padding: 1mm !important;
        font-size: 6pt;
    }
}
</style>
