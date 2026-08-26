<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appConfirm } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Calculator,
    Check,
    ChevronsUpDown,
    Clock3,
    Download,
    FileText,
    Search,
    Settings,
    SlidersHorizontal,
    Trash2,
    WalletCards,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
const props = defineProps({
    statements: { type: Object, required: true },
    summary: { type: Object, required: true },
    configurations: { type: Array, required: true },
    payments: { type: Array, required: true },
    employees: { type: Array, required: true },
    salaryTypes: { type: Array, required: true },
    filters: { type: Object, required: true },
    currency: { type: Object, required: true },
});
const generateOpen = ref(false),
    employeeSearch = ref(''),
    employeeDropdownOpen = ref(false),
    filterEmployeeSearch = ref(''),
    filterEmployeeDropdownOpen = ref(false),
    includeAdjustments = ref(false),
    previewLoading = ref(false),
    monthlyHours = ref<number | null>(null),
    availableHours = ref<number | null>(null),
    accountedHours = ref<number | null>(null),
    previewError = ref(''),
    paying = ref<any>(null);
const listFilters = useForm({
    search: props.filters.search ?? '',
    staff_id: props.filters.staff_id ? String(props.filters.staff_id) : '',
    period: props.filters.period ?? '',
    status: props.filters.status ?? '',
    salary_type: props.filters.salary_type ?? '',
});
const labels: any = {
    monthly: 'Mensuel fixe',
    hourly: 'Horaire',
    per_session: 'Par séance',
    daily: 'Journalier',
    custom: 'Manuel',
};
const adjustmentLabels: any = {
    bonus: 'Prime',
    deduction: 'Déduction',
    advance: 'Avance',
    exceptional: 'Exceptionnel',
    reimbursement: 'Remboursement',
};
const statement = useForm({
    staff_id: '',
    salary_configuration_id: '',
    period: new Date().toISOString().slice(0, 7),
    worked_units: '',
    manual_amount: '',
    notes: '',
    adjustments: [] as any[],
});
const payment = useForm({
    amount: '',
    paid_at: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
});
const selected = computed(() =>
    props.configurations.find(
        (c: any) => String(c.id) === statement.salary_configuration_id,
    ),
);
const filteredEmployees = computed(() => {
    const query = employeeSearch.value.trim().toLowerCase();
    return props.employees.filter(
        (employee: any) =>
            !query ||
            `${employee.name} ${employee.employee_code ?? ''} ${employee.employee_type?.name ?? ''}`
                .toLowerCase()
                .includes(query),
    );
});
const selectedEmployee = computed(() =>
    props.employees.find(
        (employee: any) => String(employee.id) === statement.staff_id,
    ),
);
const attendanceBased = computed(() =>
    ['hourly', 'daily', 'per_session'].includes(selected.value?.salary_type),
);
const canGenerate = computed(
    () =>
        !previewLoading.value &&
        (!attendanceBased.value || (availableHours.value ?? 0) > 0),
);
const selectedFilterEmployee = computed(() =>
    props.employees.find(
        (employee: any) => String(employee.id) === listFilters.staff_id,
    ),
);
const filteredFilterEmployees = computed(() => {
    const query = filterEmployeeSearch.value.trim().toLowerCase();
    return props.employees.filter(
        (employee: any) =>
            !query ||
            `${employee.name} ${employee.employee_code ?? ''} ${employee.employee_type?.name ?? ''}`
                .toLowerCase()
                .includes(query),
    );
});
function selectEmployee(employee: any) {
    statement.staff_id = String(employee.id);
    employeeSearch.value = '';
    employeeDropdownOpen.value = false;
}
function selectFilterEmployee(employee: any | null) {
    listFilters.staff_id = employee ? String(employee.id) : '';
    filterEmployeeSearch.value = '';
    filterEmployeeDropdownOpen.value = false;
}
let previewRequest = 0;
async function loadAttendancePreview() {
    if (
        !statement.staff_id ||
        !statement.salary_configuration_id ||
        !statement.period
    ) {
        monthlyHours.value = null;
        availableHours.value = null;
        accountedHours.value = null;
        previewError.value = '';
        return;
    }
    const request = ++previewRequest;
    previewLoading.value = true;
    previewError.value = '';
    try {
        const query = new URLSearchParams({
            staff_id: statement.staff_id,
            salary_configuration_id: statement.salary_configuration_id,
            period: statement.period,
        });
        const response = await fetch(
            `/admin/salaries/attendance-preview?${query}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        const payload = await response.json();
        if (!response.ok)
            throw new Error(
                payload.message ??
                    Object.values(payload.errors ?? {})[0] ??
                    'Calcul impossible.',
            );
        if (request !== previewRequest) return;
        monthlyHours.value = Number(payload.monthly_worked_hours);
        availableHours.value = Number(payload.available_worked_hours);
        accountedHours.value = Number(payload.already_accounted_hours);
        statement.worked_units =
            payload.salary_type === 'hourly'
                ? String(availableHours.value)
                : '';
    } catch (error) {
        if (request !== previewRequest) return;
        monthlyHours.value = null;
        availableHours.value = null;
        accountedHours.value = null;
        statement.worked_units = '';
        previewError.value =
            error instanceof Error
                ? error.message
                : 'Calcul des heures impossible.';
    } finally {
        if (request === previewRequest) previewLoading.value = false;
    }
}
watch(
    () => [
        statement.staff_id,
        statement.salary_configuration_id,
        statement.period,
        selected.value?.salary_type,
    ],
    loadAttendancePreview,
);
const money = (v: any) =>
    `${Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} ${props.currency.symbol}`;
const hours = (statement: any) =>
    Number(
        statement.calculation_details?.attendance_worked_hours ?? 0,
    ).toLocaleString('fr-FR', { maximumFractionDigits: 2 });
function applyFilters() {
    router.get('/admin/salaries', listFilters.data(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function clearFilters() {
    listFilters.defaults({
        search: '',
        staff_id: '',
        period: '',
        status: '',
        salary_type: '',
    });
    listFilters.reset();
    applyFilters();
}
function addAdjustment() {
    statement.adjustments.push({
        type: 'bonus',
        label: '',
        amount: '',
        notes: '',
    });
}
function generate() {
    if (!includeAdjustments.value) statement.adjustments = [];
    statement.post('/admin/salaries/generate', {
        onSuccess: () => (generateOpen.value = false),
    });
}
function openPay(s: any) {
    paying.value = s;
    payment.reset();
    payment.amount = String(s.remaining_amount);
    payment.paid_at = new Date().toISOString().slice(0, 10);
}
function pay() {
    payment.post(`/admin/salaries/${paying.value.id}/payments`, {
        onSuccess: () => (paying.value = null),
    });
}
async function dismissStatement(statementToDelete: any) {
    if (
        await appConfirm(
            `Supprimer le calcul ${statementToDelete.reference} ? Les pointages associés redeviendront disponibles.`,
            {
                title: 'Supprimer le calcul',
                tone: 'danger',
                confirmText: 'Supprimer',
            },
        )
    ) {
        router.delete(`/admin/salaries/${statementToDelete.id}`, {
            preserveScroll: true,
        });
    }
}
</script>
<template>
    <Head title="Paie" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Paie du personnel
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Calculs, paiements partiels et historique définitif.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child variant="outline"
                            ><Link href="/admin/salaries/configurations"
                                ><Settings
                                    class="mr-2 size-4"
                                />Paramètres</Link
                            ></Button
                        ><Button @click="generateOpen = true"
                            ><Calculator class="mr-2 size-4" />Calculer</Button
                        >
                    </div>
                </header>
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <article
                        v-for="item in [
                            {
                                label: 'Bulletins',
                                value: summary.statements,
                                icon: FileText,
                                tone: 'text-slate-600 bg-slate-100',
                            },
                            {
                                label: 'Heures tracées',
                                value: `${Number(summary.hours).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} h`,
                                icon: Clock3,
                                tone: 'text-blue-600 bg-blue-100',
                            },
                            {
                                label: 'Net calculé',
                                value: money(summary.net),
                                icon: Calculator,
                                tone: 'text-primary bg-primary/10',
                            },
                            {
                                label: 'Déjà payé',
                                value: money(summary.paid),
                                icon: Banknote,
                                tone: 'text-emerald-600 bg-emerald-100',
                            },
                            {
                                label: 'Reste à payer',
                                value: money(summary.remaining),
                                icon: WalletCards,
                                tone: 'text-amber-600 bg-amber-100',
                            },
                        ]"
                        :key="item.label"
                        class="rounded-xl border bg-card p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.label }}
                                </p>
                                <b class="mt-1 block text-2xl">{{
                                    item.value
                                }}</b>
                            </div>
                            <span class="rounded-lg p-2" :class="item.tone"
                                ><component :is="item.icon" class="size-5"
                            /></span>
                        </div>
                    </article>
                </section>

                <form
                    class="rounded-xl border bg-card p-4 shadow-sm"
                    @submit.prevent="applyFilters"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <SlidersHorizontal class="size-4" /><b
                            >Rechercher et filtrer</b
                        ><span class="text-xs text-muted-foreground"
                            >Les totaux suivent les filtres</span
                        >
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            /><Input
                                v-model="listFilters.search"
                                type="search"
                                class="pl-9"
                                placeholder="Nom, matricule, référence…"
                            />
                        </div>
                        <div class="relative">
                            <button
                                type="button"
                                class="flex h-9 w-full items-center justify-between rounded-md border bg-background px-3 text-left text-sm"
                                @click="
                                    filterEmployeeDropdownOpen =
                                        !filterEmployeeDropdownOpen
                                "
                            >
                                <span class="truncate">{{
                                    selectedFilterEmployee
                                        ? `${selectedFilterEmployee.name} · ${selectedFilterEmployee.employee_code}`
                                        : 'Tous les employés'
                                }}</span
                                ><ChevronsUpDown
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </button>
                            <div
                                v-if="filterEmployeeDropdownOpen"
                                class="absolute z-30 mt-1 w-full min-w-72 rounded-md border bg-popover p-2 shadow-lg"
                            >
                                <div class="relative mb-2">
                                    <Search
                                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                                    /><Input
                                        v-model="filterEmployeeSearch"
                                        type="search"
                                        autofocus
                                        class="pl-9"
                                        placeholder="Rechercher un employé…"
                                    />
                                </div>
                                <div class="max-h-56 overflow-y-auto">
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                        @click="selectFilterEmployee(null)"
                                    >
                                        <Check
                                            class="size-4"
                                            :class="
                                                !listFilters.staff_id
                                                    ? 'opacity-100'
                                                    : 'opacity-0'
                                            "
                                        />Tous les employés</button
                                    ><button
                                        v-for="employee in filteredFilterEmployees"
                                        :key="employee.id"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                        @click="selectFilterEmployee(employee)"
                                    >
                                        <Check
                                            class="size-4"
                                            :class="
                                                listFilters.staff_id ===
                                                String(employee.id)
                                                    ? 'opacity-100'
                                                    : 'opacity-0'
                                            "
                                        /><span
                                            ><b>{{ employee.name }}</b
                                            ><small
                                                class="block text-muted-foreground"
                                                >{{ employee.employee_code }} ·
                                                {{
                                                    employee.employee_type?.name
                                                }}</small
                                            ></span
                                        >
                                    </button>
                                    <p
                                        v-if="!filteredFilterEmployees.length"
                                        class="p-3 text-center text-sm text-muted-foreground"
                                    >
                                        Aucun employé trouvé.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <Input
                            v-model="listFilters.period"
                            type="month"
                            title="Mois de paie"
                        />
                        <select
                            v-model="listFilters.status"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Tous les états</option>
                            <option value="pending">À payer</option>
                            <option value="partially_paid">
                                Partiellement payé
                            </option>
                            <option value="paid">Payé</option>
                        </select>
                        <select
                            v-model="listFilters.salary_type"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Tous les calculs</option>
                            <option
                                v-for="type in salaryTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ labels[type] }}
                            </option>
                        </select>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="clearFilters"
                            >Réinitialiser</Button
                        ><Button type="submit"
                            ><Search class="mr-2 size-4" />Appliquer</Button
                        >
                    </div>
                </form>

                <section class="space-y-3">
                    <article
                        v-for="s in statements.data"
                        :key="s.id"
                        class="overflow-hidden rounded-xl border bg-card shadow-sm"
                    >
                        <div
                            class="grid gap-4 p-4 lg:grid-cols-[1.35fr_1fr_1fr_auto] lg:items-center"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <b class="text-base">{{ s.staff.name }}</b
                                    ><span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            s.status === 'paid'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : s.status === 'partially_paid'
                                                  ? 'bg-blue-100 text-blue-700'
                                                  : 'bg-amber-100 text-amber-700'
                                        "
                                        >{{
                                            s.status === 'paid'
                                                ? 'Payé'
                                                : s.status === 'partially_paid'
                                                  ? 'Paiement partiel'
                                                  : 'À payer'
                                        }}</span
                                    >
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ s.staff.employee_type?.name }} ·
                                    {{ s.period_start }} → {{ s.period_end }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ s.reference }}
                                </p>
                            </div>
                            <div>
                                <p class="font-medium">
                                    {{ labels[s.salary_type] }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Base {{ money(s.base_rate) }} ·
                                    {{ s.units }} unité(s)
                                </p>
                                <p class="mt-1 text-sm">
                                    <Clock3 class="mr-1 inline size-4" />{{
                                        hours(s)
                                    }}
                                    h ·
                                    {{
                                        s.calculation_details?.session_count ??
                                        0
                                    }}
                                    séance(s)
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Net
                                    <b class="text-foreground">{{
                                        money(s.net_salary)
                                    }}</b>
                                </p>
                                <div
                                    class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full bg-emerald-500"
                                        :style="{
                                            width: `${Number(s.net_salary) ? Math.min(100, (Number(s.amount_paid) / Number(s.net_salary)) * 100) : 100}%`,
                                        }"
                                    ></div>
                                </div>
                                <p class="mt-1 text-xs">
                                    <span class="text-emerald-600"
                                        >{{ money(s.amount_paid) }} payé</span
                                    >
                                    ·
                                    <span class="text-amber-600"
                                        >{{
                                            money(s.remaining_amount)
                                        }}
                                        restant</span
                                    >
                                </p>
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="Number(s.remaining_amount) > 0"
                                    size="sm"
                                    @click="openPay(s)"
                                    ><WalletCards
                                        class="mr-1 size-4"
                                    />Payer</Button
                                ><Button
                                    as-child
                                    size="sm"
                                    variant="outline"
                                    title="Télécharger le bulletin"
                                    ><a :href="`/admin/salaries/${s.id}/print`"
                                        ><Download class="size-4" /></a></Button
                                ><Button
                                    v-if="Number(s.amount_paid) === 0"
                                    size="sm"
                                    variant="ghost"
                                    class="text-destructive"
                                    title="Supprimer ce calcul"
                                    @click="dismissStatement(s)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                        </div>
                        <div
                            v-if="s.payments?.length"
                            class="border-t bg-muted/20 px-4 py-3"
                        >
                            <p
                                class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Paiements et reçus
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    v-for="p in s.payments"
                                    :key="p.id"
                                    :href="`/admin/salaries/payments/${p.id}/receipt`"
                                    class="flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm hover:border-primary"
                                    ><Download class="size-4" /><span
                                        ><b>{{ money(p.amount) }}</b
                                        ><small
                                            class="block text-muted-foreground"
                                            >{{
                                                new Date(
                                                    p.paid_at,
                                                ).toLocaleDateString('fr-FR')
                                            }}
                                            ·
                                            {{
                                                String(
                                                    p.payment_method,
                                                ).replace('_', ' ')
                                            }}</small
                                        ></span
                                    ></a
                                >
                            </div>
                        </div>
                    </article>
                    <div
                        v-if="!statements.data.length"
                        class="rounded-xl border border-dashed bg-card p-12 text-center"
                    >
                        <Search
                            class="mx-auto mb-3 size-8 text-muted-foreground"
                        /><b>Aucun paiement ou bulletin trouvé</b>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Modifiez les filtres ou calculez un nouveau
                            bulletin.
                        </p>
                    </div>
                    <div
                        v-if="statements.links?.length > 3"
                        class="flex flex-wrap justify-center gap-1"
                    >
                        <Link
                            v-for="link in statements.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            preserve-scroll
                            class="rounded-md border px-3 py-2 text-sm"
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
        <div
            v-if="generateOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4"
        >
            <form
                class="mx-auto my-8 max-w-2xl space-y-4 rounded-xl bg-background p-6"
                @submit.prevent="generate"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">Calculer un bulletin</h2>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="generateOpen = false"
                        ><X
                    /></Button>
                </div>
                <label class="relative block"
                    ><Label>Employé</Label>
                    <button
                        type="button"
                        class="mt-1 flex h-10 w-full items-center justify-between rounded-md border bg-background px-3 text-left text-sm"
                        @click="employeeDropdownOpen = !employeeDropdownOpen"
                    >
                        <span v-if="selectedEmployee"
                            ><b>{{ selectedEmployee.name }}</b> ·
                            {{ selectedEmployee.employee_type.name }}</span
                        >
                        <span v-else class="text-muted-foreground"
                            >Rechercher et sélectionner</span
                        >
                        <ChevronsUpDown class="size-4 text-muted-foreground" />
                    </button>
                    <div
                        v-if="employeeDropdownOpen"
                        class="absolute z-20 mt-1 w-full rounded-md border bg-popover p-2 shadow-lg"
                    >
                        <div class="relative mb-2">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            /><Input
                                v-model="employeeSearch"
                                type="search"
                                autofocus
                                placeholder="Nom, matricule ou fonction"
                                class="pl-9"
                            />
                        </div>
                        <div class="max-h-56 overflow-y-auto">
                            <button
                                v-for="e in filteredEmployees"
                                :key="e.id"
                                type="button"
                                class="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                @click="selectEmployee(e)"
                            >
                                <Check
                                    class="size-4"
                                    :class="
                                        statement.staff_id === String(e.id)
                                            ? 'opacity-100'
                                            : 'opacity-0'
                                    "
                                />
                                <span
                                    ><b>{{ e.name }}</b
                                    ><small class="block text-muted-foreground"
                                        >{{ e.employee_code }} ·
                                        {{ e.employee_type.name }}</small
                                    ></span
                                >
                            </button>
                            <p
                                v-if="!filteredEmployees.length"
                                class="p-3 text-center text-sm text-muted-foreground"
                            >
                                Aucun employé trouvé.
                            </p>
                        </div>
                    </div>
                    <InputError :message="statement.errors.staff_id" />
                </label>
                <label
                    ><Label>Configuration salariale</Label
                    ><select
                        v-model="statement.salary_configuration_id"
                        required
                        class="h-9 w-full rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>Sélectionner</option>
                        <option
                            v-for="c in configurations"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }} — {{ labels[c.salary_type] }} ·
                            {{ money(c.base_rate) }}
                        </option></select
                    ><small v-if="selected"
                        >{{ labels[selected.salary_type] }} ·
                        {{ money(selected.base_rate) }}</small
                    ><InputError
                        :message="statement.errors.salary_configuration_id"
                /></label>
                <div
                    v-if="Object.keys(statement.errors).length"
                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"
                >
                    {{ Object.values(statement.errors)[0] }}
                </div>
                <label
                    ><Label>Mois de paie</Label
                    ><Input
                        v-model="statement.period"
                        type="month"
                        required /><InputError
                        :message="statement.errors.period"
                /></label>
                <div
                    v-if="selected"
                    class="rounded-xl border border-primary/20 bg-primary/5 p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <Label>Heures travaillées du mois</Label>
                            <p class="text-xs text-muted-foreground">
                                Calculées automatiquement depuis les pointages
                                et non modifiables.
                            </p>
                        </div>
                        <Clock3 class="size-5 text-primary" />
                    </div>
                    <p
                        v-if="previewLoading"
                        class="mt-3 text-sm text-muted-foreground"
                    >
                        Calcul des heures depuis les présences…
                    </p>
                    <div
                        v-else-if="monthlyHours !== null"
                        class="mt-3 grid grid-cols-3 gap-2 text-center"
                    >
                        <div class="rounded-lg bg-background p-2">
                            <b class="block text-lg">{{ monthlyHours }} h</b
                            ><small class="text-muted-foreground"
                                >Total du mois</small
                            >
                        </div>
                        <div class="rounded-lg bg-background p-2">
                            <b class="block text-lg text-emerald-700"
                                >{{ availableHours }} h</b
                            ><small class="text-muted-foreground"
                                >Disponibles</small
                            >
                        </div>
                        <div class="rounded-lg bg-background p-2">
                            <b class="block text-lg text-slate-600"
                                >{{ accountedHours }} h</b
                            ><small class="text-muted-foreground"
                                >Déjà calculées/payées</small
                            >
                        </div>
                    </div>
                    <p
                        v-if="
                            selected.salary_type === 'hourly' &&
                            availableHours !== null
                        "
                        class="mt-3 text-xs font-medium text-primary"
                    >
                        Ce bulletin utilisera strictement
                        {{ availableHours }} heure(s) ×
                        {{ money(selected.base_rate) }}.
                    </p>
                    <p v-if="previewError" class="mt-2 text-xs text-red-600">
                        {{ previewError }}
                    </p>
                    <p
                        v-else-if="attendanceBased && availableHours === 0"
                        class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
                    >
                        Aucune heure disponible pour cet employé. Enregistrez
                        d’abord ses présences dans le module Présences, ou
                        choisissez une configuration mensuelle fixe.
                    </p>
                </div>
                <label v-if="selected?.salary_type === 'daily'"
                    ><Label>Jours travaillés</Label
                    ><Input
                        v-model="statement.worked_units"
                        type="number"
                        min="0"
                        step="0.5" /></label
                ><label v-if="selected?.salary_type === 'custom'"
                    ><Label>Montant manuel</Label
                    ><Input
                        v-model="statement.manual_amount"
                        type="number"
                        min="0"
                        step="0.01"
                /></label>
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border bg-muted/20 p-4"
                >
                    <input
                        v-model="includeAdjustments"
                        type="checkbox"
                        class="mt-1 size-4"
                    />
                    <span
                        ><b>Inclure des primes et retenues</b
                        ><small class="mt-0.5 block text-muted-foreground"
                            >Ajoutez uniquement les éléments exceptionnels
                            propres à ce bulletin.</small
                        ></span
                    >
                </label>
                <section
                    v-if="includeAdjustments"
                    class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <b>Primes et retenues</b>
                            <p class="text-xs text-muted-foreground">
                                Bonus, déductions, avances et remboursements.
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="addAdjustment"
                            ><Plus class="mr-1 size-4" />Ajouter une
                            ligne</Button
                        >
                    </div>
                    <div
                        v-for="(a, i) in statement.adjustments"
                        :key="i"
                        class="grid gap-2 rounded-lg border bg-background p-3 sm:grid-cols-[150px_1fr_140px_auto]"
                    >
                        <select
                            v-model="a.type"
                            class="h-9 rounded-md border bg-background px-2"
                        >
                            <option
                                v-for="(n, k) in adjustmentLabels"
                                :key="k"
                                :value="k"
                            >
                                {{ n }}
                            </option>
                        </select>
                        <Input
                            v-model="a.label"
                            placeholder="Libellé de la prime ou retenue"
                        />
                        <Input
                            v-model="a.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="Montant"
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="text-destructive"
                            @click="statement.adjustments.splice(i, 1)"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <button
                        v-if="!statement.adjustments.length"
                        type="button"
                        class="w-full rounded-lg border border-dashed p-5 text-sm text-muted-foreground hover:bg-background"
                        @click="addAdjustment"
                    >
                        + Ajouter la première prime ou retenue
                    </button>
                </section>
                <Button class="w-full" :disabled="!canGenerate">Générer</Button>
            </form>
        </div>
        <div v-if="paying" class="fixed inset-0 z-50 bg-black/50 p-4">
            <form
                class="mx-auto mt-20 max-w-md space-y-4 rounded-xl bg-background p-6"
                @submit.prevent="pay"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            Enregistrer un paiement
                        </h2>
                        <p class="text-sm">
                            Reste {{ money(paying.remaining_amount) }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="paying = null"
                        ><X
                    /></Button>
                </div>
                <label
                    ><Label>Montant</Label
                    ><Input
                        v-model="payment.amount"
                        type="number"
                        min="0.01"
                        :max="paying.remaining_amount"
                        step="0.01" /><InputError
                        :message="payment.errors.amount" /></label
                ><label
                    ><Label>Date</Label
                    ><Input v-model="payment.paid_at" type="date" /></label
                ><label
                    ><Label>Méthode</Label
                    ><select
                        v-model="payment.payment_method"
                        class="h-9 w-full rounded-md border"
                    >
                        <option value="cash">Espèces</option>
                        <option value="bank_transfer">Virement</option>
                        <option value="cheque">Chèque</option>
                        <option value="card">Carte</option>
                        <option value="other">Autre</option>
                    </select></label
                ><label
                    ><Label>Référence</Label
                    ><Input v-model="payment.reference" /></label
                ><Button class="w-full">Valider définitivement</Button>
            </form>
        </div></AdminLayout
    >
</template>
