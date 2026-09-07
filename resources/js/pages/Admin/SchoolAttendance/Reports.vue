<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileText } from 'lucide-vue-next';
import { reactive } from 'vue';

type Option = {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    level?: { name: string };
};
type Row = {
    id: number;
    name: string;
    scheduled_sessions: number;
    present_sessions: number;
    absences: number;
    justified_absences: number;
    unjustified_absences: number;
    late_arrivals: number;
    attendance_percentage: number;
    affected_teaching_sessions?: Array<{
        date: string;
        group: string;
        subject: string;
        start_time: string;
    }>;
};
const props = defineProps<{
    academicYears: Option[];
    academicYear: Option;
    periods: Array<Option & { starts_on: string; ends_on: string }>;
    students: Option[];
    groups: Option[];
    teachers: Option[];
    filters: Record<string, any>;
    rows: Row[];
}>();
const filters = reactive({ ...props.filters });
function apply() {
    router.get('/admin/school-attendance/reports', filters, {
        preserveState: true,
        replace: true,
    });
}
function changeScope() {
    filters.entity_id = '';
    apply();
}
function exportUrl(format: string) {
    return `/admin/school-attendance/reports/export/${format}?${new URLSearchParams(
        Object.entries(filters)
            .filter(([, value]) => value !== null && value !== '')
            .map(([key, value]) => [key, String(value)]),
    ).toString()}`;
}
</script>

<template>
    <Head title="Rapports de présence" />
    <AdminLayout
        ><main
            class="min-h-full flex-1 space-y-5 bg-slate-50/60 p-4 sm:p-6 lg:p-8"
        >
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <a
                        href="/admin/school-attendance"
                        class="mb-2 inline-flex items-center gap-1 text-sm text-blue-600"
                        ><ArrowLeft class="size-4" />Retour aux présences</a
                    >
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <span class="rounded-xl bg-blue-600 p-2 text-white"
                            ><FileText class="size-5" /></span
                        >Rapports de présence
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Les présences sont calculées depuis l’emploi du temps,
                        sans lignes de présence artificielles.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a :href="exportUrl('csv')"
                        ><Button variant="outline"
                            ><Download class="size-4" />CSV</Button
                        ></a
                    ><a :href="exportUrl('pdf')"
                        ><Button><Download class="size-4" />PDF</Button></a
                    >
                </div>
            </header>
            <section
                class="grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-3 xl:grid-cols-6"
            >
                <label class="text-xs font-medium"
                    >Année<select
                        v-model="filters.academic_year_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3"
                        @change="apply"
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
                <label class="text-xs font-medium"
                    >Rapport par<select
                        v-model="filters.scope"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3"
                        @change="changeScope"
                    >
                        <option value="student">Élève</option>
                        <option value="group">Groupe</option>
                        <option value="teacher">Enseignant</option>
                    </select></label
                >
                <label class="text-xs font-medium"
                    >Sélection<select
                        v-model="filters.entity_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3"
                        @change="apply"
                    >
                        <option value="">Tous</option>
                        <option
                            v-for="item in filters.scope === 'student'
                                ? students
                                : filters.scope === 'teacher'
                                  ? teachers
                                  : groups"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{
                                item.first_name
                                    ? `${item.last_name} ${item.first_name}`
                                    : item.name
                            }}{{ item.level ? ` · ${item.level.name}` : '' }}
                        </option>
                    </select></label
                >
                <label class="text-xs font-medium"
                    >Période<select
                        v-model="filters.period_type"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3"
                        @change="apply"
                    >
                        <option value="month">Mois</option>
                        <option value="trimester">Trimestre</option>
                        <option value="year">Année scolaire</option>
                    </select></label
                >
                <label
                    v-if="filters.period_type === 'month'"
                    class="text-xs font-medium"
                    >Mois<input
                        v-model="filters.month"
                        type="month"
                        class="mt-1 h-10 w-full rounded-md border px-3"
                        @change="apply"
                /></label>
                <label
                    v-if="filters.period_type === 'trimester'"
                    class="text-xs font-medium"
                    >Trimestre<select
                        v-model="filters.academic_period_id"
                        class="mt-1 h-10 w-full rounded-md border bg-white px-3"
                        @change="apply"
                    >
                        <option value="">Sélectionner</option>
                        <option
                            v-for="period in periods"
                            :key="period.id"
                            :value="period.id"
                        >
                            {{ period.name }}
                        </option>
                    </select></label
                >
                <div class="self-end text-xs text-muted-foreground">
                    {{ filters.from }} → {{ filters.to }}
                </div>
            </section>
            <section class="overflow-hidden rounded-2xl border bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] text-sm">
                        <thead
                            class="bg-slate-50 text-left text-xs text-slate-500 uppercase"
                        >
                            <tr>
                                <th class="p-3">Élève / enseignant</th>
                                <th class="p-3">Prévues</th>
                                <th class="p-3">Présentes</th>
                                <th class="p-3">Absences</th>
                                <th class="p-3">Justifiées</th>
                                <th class="p-3">Non justifiées</th>
                                <th class="p-3">Retards</th>
                                <th class="p-3">Présence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in rows" :key="row.id"
                                ><tr class="border-t">
                                    <td class="p-3 font-medium">
                                        {{ row.name }}
                                    </td>
                                    <td class="p-3">
                                        {{ row.scheduled_sessions }}
                                    </td>
                                    <td class="p-3 text-emerald-700">
                                        {{ row.present_sessions }}
                                    </td>
                                    <td class="p-3 text-red-700">
                                        {{ row.absences }}
                                    </td>
                                    <td class="p-3">
                                        {{ row.justified_absences }}
                                    </td>
                                    <td class="p-3">
                                        {{ row.unjustified_absences }}
                                    </td>
                                    <td class="p-3 text-amber-700">
                                        {{ row.late_arrivals }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700"
                                            >{{
                                                row.attendance_percentage
                                            }}%</span
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        row.affected_teaching_sessions?.length
                                    "
                                    class="border-t bg-red-50/40"
                                >
                                    <td colspan="8" class="p-3">
                                        <strong class="text-red-700"
                                            >Séances d’enseignement
                                            affectées</strong
                                        >
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span
                                                v-for="session in row.affected_teaching_sessions"
                                                :key="`${session.date}-${session.group}-${session.start_time}`"
                                                class="rounded-lg border border-red-100 bg-white px-3 py-2 text-xs"
                                                >{{ session.date }} →
                                                {{ session.group }} ·
                                                {{ session.subject }} ·
                                                {{ session.start_time }}</span
                                            >
                                        </div>
                                    </td>
                                </tr></template
                            >
                            <tr v-if="!rows.length">
                                <td
                                    colspan="8"
                                    class="p-10 text-center text-muted-foreground"
                                >
                                    Aucune donnée pour cette sélection.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main></AdminLayout
    >
</template>
