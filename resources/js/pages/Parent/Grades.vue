<script setup lang="ts">
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    CalendarDays,
    ChevronDown,
    ChevronUp,
    UserRound,
} from 'lucide-vue-next';
import { reactive, ref } from 'vue';

const props = defineProps<{
    portal: any;
    context: any;
    academicYears: any[];
    periods: any[];
    subjects: any[];
    results: any[];
    filters: any;
    hasEnrollment: boolean;
}>();
const filters = reactive({ ...props.filters });
const expanded = ref<number[]>([]);
function apply() {
    router.get(
        '/parent/grades',
        Object.fromEntries(
            Object.entries(filters).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ),
        { preserveState: false, replace: true },
    );
}
function toggle(id: number) {
    expanded.value = expanded.value.includes(id)
        ? expanded.value.filter((item) => item !== id)
        : [...expanded.value, id];
}
const formatDate = (value: string) =>
    value
        ? new Date(`${value}T12:00:00`).toLocaleDateString('fr-DZ', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : 'Date non définie';
const gradeLabel = (item: any) =>
    item.grade_status === 'absent'
        ? 'Absent'
        : item.grade_status === 'exempted'
          ? 'Dispensé'
          : `${item.grade} / ${item.maximum_grade}`;
</script>

<template>
    <Head title="Notes et résultats" /><ParentLayout :portal="portal"
        ><div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">
                    Notes et résultats
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
            <section
                v-if="context"
                class="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-3"
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
                >
                <label class="text-xs font-medium text-slate-500"
                    >Période<select
                        v-model="filters.academic_period_id"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                        @change="apply"
                    >
                        <option
                            v-for="period in periods"
                            :key="period.id"
                            :value="period.id"
                        >
                            {{ period.name }}
                        </option>
                    </select></label
                >
                <label class="text-xs font-medium text-slate-500"
                    >Matière<select
                        v-model="filters.subject_id"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                        @change="apply"
                    >
                        <option :value="null">Toutes les matières</option>
                        <option
                            v-for="subject in subjects"
                            :key="subject.id"
                            :value="subject.id"
                        >
                            {{ subject.name }}
                        </option>
                    </select></label
                >
            </section>
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
                <b>Aucune note n’est disponible pour cette année scolaire.</b>
            </section>
            <div
                v-else-if="results.length"
                class="grid items-start gap-5 lg:grid-cols-2"
            >
                <article
                    v-for="result in results"
                    :key="result.subject.id"
                    class="overflow-hidden rounded-2xl border bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b bg-slate-50 p-5"
                    >
                        <div>
                            <h2 class="text-lg font-bold">
                                {{ result.subject.name }}
                            </h2>
                            <p class="text-xs text-slate-500">
                                Coefficient {{ result.subject.coefficient }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Moyenne</p>
                            <p class="text-xl font-bold">
                                {{
                                    result.average !== null
                                        ? `${result.average} / 20`
                                        : '—'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="divide-y">
                        <div
                            v-for="assessment in expanded.includes(
                                result.subject.id,
                            )
                                ? result.assessments
                                : result.assessments.slice(0, 3)"
                            :key="assessment.id"
                            class="p-4"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold">
                                        {{ assessment.name }}
                                    </p>
                                    <p class="text-sm text-slate-500">
                                        {{ assessment.type_label }}
                                    </p>
                                </div>
                                <p class="shrink-0 text-lg font-bold">
                                    {{ gradeLabel(assessment) }}
                                </p>
                            </div>
                            <div
                                class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500"
                            >
                                <span class="flex items-center gap-1"
                                    ><CalendarDays class="size-3.5" />{{
                                        formatDate(assessment.date)
                                    }}</span
                                ><span
                                    v-if="assessment.teacher"
                                    class="flex items-center gap-1"
                                    ><UserRound class="size-3.5" />{{
                                        assessment.teacher
                                    }}</span
                                ><span>Poids {{ assessment.weight }}</span>
                            </div>
                        </div>
                    </div>
                    <button
                        v-if="result.assessments.length > 3"
                        class="flex w-full items-center justify-center gap-2 border-t p-3 text-sm font-semibold text-primary"
                        @click="toggle(result.subject.id)"
                    >
                        <component
                            :is="
                                expanded.includes(result.subject.id)
                                    ? ChevronUp
                                    : ChevronDown
                            "
                            class="size-4"
                        />{{
                            expanded.includes(result.subject.id)
                                ? 'Réduire'
                                : 'Voir toutes les évaluations'
                        }}
                    </button>
                </article>
            </div>
            <section
                v-else
                class="rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <BookOpenCheck class="mx-auto size-10 text-slate-300" />
                <h2 class="mt-3 font-semibold">
                    Aucune note n’a encore été publiée.
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Les résultats apparaîtront ici après leur publication
                    officielle.
                </p>
            </section>
        </div></ParentLayout
    >
</template>
