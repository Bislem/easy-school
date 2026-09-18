<script setup lang="ts">
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, UserRound } from 'lucide-vue-next';
import { reactive } from 'vue';
const props = defineProps<{
    portal: any;
    context: any;
    academicYears: any[];
    periods: any[];
    subjects: any[];
    upcoming: any[];
    past: any[];
    filters: any;
    hasEnrollment: boolean;
}>();
const filters = reactive({ ...props.filters });
function apply() {
    router.get(
        '/parent/exams',
        Object.fromEntries(
            Object.entries(filters).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ),
        { preserveState: false, replace: true },
    );
}
const day = (value: string) =>
    new Date(`${value}T12:00:00`).toLocaleDateString('fr-DZ', {
        day: '2-digit',
    });
const month = (value: string) =>
    new Date(`${value}T12:00:00`)
        .toLocaleDateString('fr-DZ', { month: 'short' })
        .replace('.', '')
        .toUpperCase();
const fullDate = (value: string) =>
    new Date(`${value}T12:00:00`).toLocaleDateString('fr-DZ', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
</script>
<template>
    <Head title="Examens" /><ParentLayout :portal="portal"
        ><div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">Examens</h1>
                <p v-if="context" class="mt-1 text-slate-500">
                    {{ context.name }} ·
                    {{
                        [context.level, context.group]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </p>
            </div>
            <section
                v-if="context"
                class="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <label class="text-xs font-medium text-slate-500"
                    >Année scolaire<select
                        v-model="filters.academic_year_id"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
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
                ><label class="text-xs font-medium text-slate-500"
                    >Période<select
                        v-model="filters.academic_period_id"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                        @change="apply"
                    >
                        <option :value="null">Toutes</option>
                        <option
                            v-for="period in periods"
                            :key="period.id"
                            :value="period.id"
                        >
                            {{ period.name }}
                        </option>
                    </select></label
                ><label class="text-xs font-medium text-slate-500"
                    >Matière<select
                        v-model="filters.subject_id"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                        @change="apply"
                    >
                        <option :value="null">Toutes</option>
                        <option
                            v-for="subject in subjects"
                            :key="subject.id"
                            :value="subject.id"
                        >
                            {{ subject.title }}
                        </option>
                    </select></label
                ><label class="text-xs font-medium text-slate-500"
                    >Afficher<select
                        v-model="filters.scope"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                        @change="apply"
                    >
                        <option value="all">Tous</option>
                        <option value="upcoming">À venir</option>
                        <option value="past">Passés</option>
                    </select></label
                >
            </section>
            <section
                v-if="!context || !hasEnrollment"
                class="rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <b>{{
                    !context
                        ? 'Aucun enfant associé à ce compte.'
                        : 'Aucune information n’est disponible pour cette année scolaire.'
                }}</b>
            </section>
            <template v-else
                ><section v-if="filters.scope !== 'past'">
                    <h2 class="mb-4 text-lg font-bold">Examens à venir</h2>
                    <div v-if="upcoming.length" class="space-y-3">
                        <article
                            v-for="exam in upcoming"
                            :key="exam.id"
                            class="flex gap-4 rounded-2xl border bg-white p-5"
                        >
                            <div class="w-14 shrink-0 text-center">
                                <p class="text-2xl font-bold text-primary">
                                    {{ day(exam.date) }}
                                </p>
                                <p class="text-xs font-semibold text-slate-500">
                                    {{ month(exam.date) }}
                                </p>
                            </div>
                            <div>
                                <h3 class="font-bold">{{ exam.subject }}</h3>
                                <p class="text-sm text-slate-600">
                                    {{ exam.name }}
                                </p>
                                <div
                                    class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500"
                                >
                                    <span class="flex items-center gap-1"
                                        ><CalendarDays class="size-3.5" />{{
                                            fullDate(exam.date)
                                        }}</span
                                    ><span
                                        v-if="exam.teacher"
                                        class="flex items-center gap-1"
                                        ><UserRound class="size-3.5" />{{
                                            exam.teacher
                                        }}</span
                                    ><span>{{ exam.period }}</span>
                                </div>
                            </div>
                        </article>
                    </div>
                    <p
                        v-else
                        class="rounded-2xl border border-dashed bg-white p-10 text-center text-slate-500"
                    >
                        Aucun examen à venir.
                    </p>
                </section>
                <section v-if="filters.scope !== 'upcoming'">
                    <h2 class="mb-4 text-lg font-bold">Examens passés</h2>
                    <div v-if="past.length" class="space-y-3">
                        <article
                            v-for="exam in past"
                            :key="exam.id"
                            class="flex gap-4 rounded-2xl border bg-white p-5"
                        >
                            <div class="w-14 shrink-0 text-center">
                                <p class="text-2xl font-bold text-slate-600">
                                    {{ day(exam.date) }}
                                </p>
                                <p class="text-xs font-semibold text-slate-500">
                                    {{ month(exam.date) }}
                                </p>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold">{{ exam.subject }}</h3>
                                <p class="text-sm text-slate-600">
                                    {{ exam.name }}
                                </p>
                                <p
                                    v-if="
                                        exam.result_published &&
                                        exam.grade !== null
                                    "
                                    class="mt-3 font-bold"
                                >
                                    {{ exam.grade }} / {{ exam.maximum_grade }}
                                </p>
                                <p v-else class="mt-3 text-sm text-slate-500">
                                    Résultat non publié pour le moment.
                                </p>
                            </div>
                        </article>
                    </div>
                    <p
                        v-else
                        class="rounded-2xl border border-dashed bg-white p-10 text-center text-slate-500"
                    >
                        Aucun examen passé pour cette période.
                    </p>
                </section></template
            >
        </div></ParentLayout
    >
</template>
