<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    translatePublicRoot,
    watchPublicLocale,
} from '@/composables/usePublicLocale';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Chart, registerables } from 'chart.js';
import {
    Banknote,
    ChartNoAxesColumn,
    Download,
    GraduationCap,
    Landmark,
    Percent,
    WalletCards,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

Chart.register(...registerables);
const props = defineProps<{
    filters: any;
    totals: any;
    charts: any;
    recentTransactions: any[];
    outstanding: any;
    options: any;
}>();
const root = ref<HTMLElement | null>(null);
const monthlyCanvas = ref<HTMLCanvasElement | null>(null);
const methodsCanvas = ref<HTMLCanvasElement | null>(null);
let localeStop: (() => void) | undefined;
const chartInstances: Chart[] = [];
const money = (value: any) =>
    `${Number(value || 0).toLocaleString('fr-DZ', { maximumFractionDigits: 2 })} DZD`;
const statusLabels: Record<string, string> = {
    unpaid: 'Non payé',
    partial: 'Partiel',
    paid: 'Payé',
    overdue: 'En retard',
    pending: 'À venir',
};
const sourceLabel = (source: string) =>
    source === 'school' ? 'Scolaire' : 'Formation';
const methodLabel = (value: string | null) =>
    props.options.methods.find((method: any) => method.value === value)
        ?.label || '—';
const filters = useForm({
    source: props.filters.source || 'all',
    site_id: String(props.filters.site_id || ''),
    academic_year_id: String(props.filters.academic_year_id || ''),
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    level_id: String(props.filters.level_id || ''),
    group_id: String(props.filters.group_id || ''),
    formation_id: String(props.filters.formation_id || ''),
    session_id: String(props.filters.session_id || ''),
    student_id: String(props.filters.student_id || ''),
    status: props.filters.status || '',
    payment_method: props.filters.payment_method || '',
    outstanding_sort: props.filters.outstanding_sort || 'due_date',
});
const showSchool = computed(() => filters.source !== 'formation');
const showFormation = computed(() => filters.source !== 'school');
const groups = computed(() => [
    ...(showSchool.value
        ? props.options.schoolGroups.map((group: any) => ({
              ...group,
              source: 'school',
          }))
        : []),
    ...(showFormation.value
        ? props.options.formationGroups.map((group: any) => ({
              ...group,
              source: 'formation',
          }))
        : []),
]);
const sessions = computed(() =>
    props.options.sessions.filter(
        (session: any) =>
            !filters.formation_id ||
            String(session.course_id) === filters.formation_id,
    ),
);
const apply = () =>
    router.get('/admin/payments-overview', filters.data(), {
        preserveState: true,
        replace: true,
    });
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(filters.data()).forEach(([key, value]) => {
        if (value !== '' && value !== 'all') params.set(key, String(value));
    });
    return `/admin/payments-overview/export?${params}`;
});
function createCharts() {
    if (monthlyCanvas.value) {
        chartInstances.push(
            new Chart(monthlyCanvas.value, {
                type: 'line',
                data: {
                    labels: props.charts.monthly.map((row: any) => row.period),
                    datasets: [
                        {
                            label: 'Encaissements',
                            data: props.charts.monthly.map((row: any) =>
                                Number(row.total),
                            ),
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.12)',
                            fill: true,
                            tension: 0.3,
                        },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false },
            }),
        );
    }
    if (methodsCanvas.value) {
        chartInstances.push(
            new Chart(methodsCanvas.value, {
                type: 'doughnut',
                data: {
                    labels: props.charts.methods.map((row: any) =>
                        methodLabel(row.payment_method),
                    ),
                    datasets: [
                        {
                            data: props.charts.methods.map((row: any) =>
                                Number(row.total),
                            ),
                            backgroundColor: [
                                '#2563eb',
                                '#059669',
                                '#d97706',
                                '#7c3aed',
                                '#0891b2',
                                '#dc2626',
                            ],
                        },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false },
            }),
        );
    }
}
onMounted(() => {
    localeStop = watchPublicLocale(() => root.value);
    nextTick(() => {
        translatePublicRoot(root.value);
        createCharts();
    });
});
onBeforeUnmount(() => {
    localeStop?.();
    chartInstances.forEach((chart) => chart.destroy());
});
</script>

<template>
    <Head title="Vue d’ensemble des paiements" />
    <AdminLayout>
        <main ref="root" class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-[1600px] space-y-6">
                <header
                    class="flex flex-wrap items-center justify-between gap-3"
                >
                    <div>
                        <h1 class="text-2xl font-black">
                            Vue d’ensemble des paiements
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Centre de contrôle financier scolaire et formation.
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a :href="exportUrl"
                            ><Download class="mr-2 size-4" />Exporter</a
                        >
                    </Button>
                </header>

                <form
                    class="rounded-xl border bg-card p-4"
                    @submit.prevent="apply"
                >
                    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <select
                            v-model="filters.source"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="all">Toutes les sources</option>
                            <option value="school">Scolaire</option>
                            <option value="formation">Formation</option>
                        </select>
                        <select
                            v-model="filters.site_id"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Tous les sites</option>
                            <option
                                v-for="site in options.sites"
                                :key="site.id"
                                :value="String(site.id)"
                            >
                                {{ site.name }}
                            </option>
                        </select>
                        <select
                            v-if="showSchool"
                            v-model="filters.academic_year_id"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">
                                Toutes les années scolaires
                            </option>
                            <option
                                v-for="year in options.academicYears"
                                :key="year.id"
                                :value="String(year.id)"
                            >
                                {{ year.name }}
                            </option>
                        </select>
                        <select
                            v-if="showSchool"
                            v-model="filters.level_id"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Tous les niveaux</option>
                            <option
                                v-for="level in options.levels"
                                :key="level.id"
                                :value="String(level.id)"
                            >
                                {{ level.name }}
                            </option>
                        </select>
                        <select
                            v-if="showFormation"
                            v-model="filters.formation_id"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Toutes les formations</option>
                            <option
                                v-for="formation in options.formations"
                                :key="formation.id"
                                :value="String(formation.id)"
                            >
                                {{ formation.title }}
                            </option>
                        </select>
                        <select
                            v-if="showFormation"
                            v-model="filters.session_id"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Toutes les sessions</option>
                            <option
                                v-for="session in sessions"
                                :key="session.id"
                                :value="String(session.id)"
                            >
                                {{ session.title }}
                            </option>
                        </select>
                        <select
                            v-model="filters.group_id"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Tous les groupes</option>
                            <option
                                v-for="group in groups"
                                :key="`${group.source}-${group.id}`"
                                :value="`${group.source}:${group.id}`"
                            >
                                {{ group.name }} ·
                                {{ sourceLabel(group.source) }}
                            </option>
                        </select>
                        <select
                            v-model="filters.student_id"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Tous les étudiants</option>
                            <option
                                v-for="student in options.students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ student.first_name }} {{ student.last_name }}
                            </option>
                        </select>
                        <select
                            v-model="filters.status"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Tous les statuts</option>
                            <option
                                v-for="(label, status) in statusLabels"
                                :key="status"
                                :value="status"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <select
                            v-model="filters.payment_method"
                            class="h-9 rounded-md border px-3"
                        >
                            <option value="">Tous les modes de paiement</option>
                            <option
                                v-for="method in options.methods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </select>
                        <Input v-model="filters.date_from" type="date" />
                        <Input v-model="filters.date_to" type="date" />
                        <Button>Appliquer les filtres</Button>
                    </div>
                </form>

                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <article
                        v-for="card in [
                            {
                                key: 'expected',
                                label: 'Total attendu',
                                icon: Landmark,
                            },
                            {
                                key: 'collected',
                                label: 'Total encaissé',
                                icon: Banknote,
                            },
                            {
                                key: 'outstanding',
                                label: 'Restant',
                                icon: WalletCards,
                            },
                            {
                                key: 'overdue',
                                label: 'En retard',
                                icon: GraduationCap,
                            },
                            {
                                key: 'collection_rate',
                                label: 'Taux de collecte',
                                icon: Percent,
                            },
                        ]"
                        :key="card.key"
                        class="rounded-xl border bg-card p-4"
                    >
                        <component
                            :is="card.icon"
                            class="mb-2 size-5 text-primary"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <b class="text-xl">{{
                            card.key === 'collection_rate'
                                ? `${totals.combined[card.key]} %`
                                : money(totals.combined[card.key])
                        }}</b>
                    </article>
                </section>

                <section class="grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="domain in ['school', 'formation']"
                        :key="domain"
                        class="rounded-xl border bg-card p-5"
                    >
                        <div class="mb-4 flex items-center gap-2">
                            <ChartNoAxesColumn class="size-5 text-primary" />
                            <h2 class="font-black">
                                {{
                                    domain === 'school'
                                        ? 'SCOLAIRE'
                                        : 'FORMATION'
                                }}
                            </h2>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div
                                v-for="key in [
                                    'expected',
                                    'collected',
                                    'outstanding',
                                    'overdue',
                                ]"
                                :key="key"
                            >
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        (
                                            {
                                                expected: 'Attendu',
                                                collected: 'Encaissé',
                                                outstanding: 'Restant',
                                                overdue: 'En retard',
                                            } as any
                                        )[key]
                                    }}
                                </p>
                                <b>{{ money(totals[domain][key]) }}</b>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <article class="rounded-xl border bg-card p-5">
                        <h2 class="mb-4 font-black">Encaissements mensuels</h2>
                        <div class="h-72"><canvas ref="monthlyCanvas" /></div>
                        <div
                            class="mt-4 grid grid-cols-2 gap-2 border-t pt-4 sm:grid-cols-4"
                        >
                            <div
                                v-for="day in charts.daily.slice(-8)"
                                :key="day.period"
                                class="rounded-lg bg-muted/60 p-2"
                            >
                                <p class="text-xs text-muted-foreground">
                                    {{ day.period }}
                                </p>
                                <b class="text-sm">{{ money(day.total) }}</b>
                            </div>
                        </div>
                    </article>
                    <article class="rounded-xl border bg-card p-5">
                        <h2 class="mb-4 font-black">
                            Répartition par mode de paiement
                        </h2>
                        <div class="h-72"><canvas ref="methodsCanvas" /></div>
                    </article>
                </section>

                <section class="overflow-hidden rounded-xl border bg-card">
                    <h2 class="border-b p-4 font-black">
                        Transactions récentes
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left">
                                <tr>
                                    <th class="p-3">Date</th>
                                    <th>Étudiant</th>
                                    <th>Source</th>
                                    <th>Contexte</th>
                                    <th>Montant</th>
                                    <th>Méthode</th>
                                    <th>Enregistré par</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="transaction in recentTransactions"
                                    :key="transaction.id"
                                    class="border-t"
                                >
                                    <td class="p-3">{{ transaction.date }}</td>
                                    <td>
                                        <Link
                                            :href="transaction.url"
                                            class="font-bold text-primary"
                                            >{{ transaction.student }}</Link
                                        >
                                    </td>
                                    <td>
                                        {{ sourceLabel(transaction.source) }}
                                    </td>
                                    <td>{{ transaction.context }}</td>
                                    <td
                                        :class="
                                            transaction.type === 'refund'
                                                ? 'text-red-600'
                                                : 'text-emerald-700'
                                        "
                                    >
                                        {{ money(transaction.amount) }}
                                    </td>
                                    <td>
                                        {{
                                            methodLabel(
                                                transaction.payment_method,
                                            )
                                        }}
                                    </td>
                                    <td>
                                        {{ transaction.recorded_by || '—' }}
                                    </td>
                                </tr>
                                <tr v-if="!recentTransactions.length">
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        Aucune transaction pour cette période.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border bg-card">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b p-4"
                    >
                        <h2 class="font-black">Paiements en attente</h2>
                        <select
                            v-model="filters.outstanding_sort"
                            class="h-9 rounded-md border px-3"
                            @change="apply"
                        >
                            <option value="due_date">
                                Échéance la plus proche
                            </option>
                            <option value="remaining_desc">
                                Montant restant décroissant
                            </option>
                            <option value="student">Étudiant</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left">
                                <tr>
                                    <th class="p-3">Étudiant</th>
                                    <th>Source</th>
                                    <th>Contexte</th>
                                    <th>Échéance</th>
                                    <th>Date d’échéance</th>
                                    <th>Restant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in outstanding.data"
                                    :key="item.id"
                                    class="border-t"
                                >
                                    <td class="p-3">
                                        <Link
                                            :href="item.url"
                                            class="font-bold text-primary"
                                            >{{ item.student }}</Link
                                        >
                                    </td>
                                    <td>{{ sourceLabel(item.source) }}</td>
                                    <td>{{ item.context }}</td>
                                    <td>{{ item.label }}</td>
                                    <td>{{ item.due_date }}</td>
                                    <td class="font-bold">
                                        {{ money(item.remaining) }}
                                    </td>
                                    <td>{{ statusLabels[item.status] }}</td>
                                </tr>
                                <tr v-if="!outstanding.data.length">
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        Aucun paiement en attente.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex gap-2 border-t p-3">
                        <Link
                            v-for="link in outstanding.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="rounded border px-3 py-1 text-xs"
                            :class="{
                                'bg-primary text-primary-foreground':
                                    link.active,
                                'pointer-events-none opacity-40': !link.url,
                            }"
                            v-html="link.label"
                        />
                    </div>
                </section>
            </div>
        </main>
    </AdminLayout>
</template>
