<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { appPrompt } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Check,
    ChevronsUpDown,
    Download,
    ReceiptText,
    Search,
    Settings,
    TriangleAlert,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    enrollments: { type: Object, required: true },
    students: { type: Array, required: true },
    methods: { type: Array, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    currency: { type: Object, required: true },
});
const selected = ref<any>(null),
    mode = ref('');
const studentSearch = ref(''),
    studentOpen = ref(false);
const listFilters = useForm({
    search: props.filters.search ?? '',
    student_id: props.filters.student_id
        ? String(props.filters.student_id)
        : '',
    status: props.filters.status ?? '',
    month: props.filters.month ?? '',
    payment_method: props.filters.payment_method ?? '',
});
const payment = useForm({
    amount: '',
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    student_installment_id: '',
    reference: '',
    notes: '',
});
const adjustment = useForm({ type: 'credit', amount: '', reason: '' });
const statuses: Record<string, string> = {
    unpaid: 'Non payé',
    partially_paid: 'Partiellement payé',
    paid: 'Payé',
    overdue: 'En retard',
};
const money = (v: any) =>
    `${Number(v ?? 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2 })} ${(props.currency as any).symbol}`;
const selectedStudent = computed(() =>
    props.students.find((s: any) => String(s.id) === listFilters.student_id),
);
const filteredStudents = computed(() => {
    const query = studentSearch.value.toLowerCase().trim();
    return props.students.filter(
        (s: any) =>
            !query ||
            `${s.first_name} ${s.last_name}`.toLowerCase().includes(query),
    );
});
function filters() {
    router.get('/admin/finance', listFilters.data(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function selectStudent(student: any | null) {
    listFilters.student_id = student ? String(student.id) : '';
    studentSearch.value = '';
    studentOpen.value = false;
}
function clearFilters() {
    listFilters.defaults({
        search: '',
        student_id: '',
        status: '',
        month: '',
        payment_method: '',
    });
    listFilters.reset();
    filters();
}
function open(e: any, what: string) {
    selected.value = e;
    mode.value = what;
    if (what === 'pay') payment.amount = String(e.remaining_balance);
}
function savePayment() {
    payment.post(`/admin/finance/enrollments/${selected.value.id}/payments`, {
        onSuccess: () => {
            mode.value = '';
            payment.reset();
        },
    });
}
function saveAdjustment() {
    adjustment.post(
        `/admin/finance/enrollments/${selected.value.id}/adjustments`,
        {
            onSuccess: () => {
                mode.value = '';
                adjustment.reset();
            },
        },
    );
}
async function reverse(p: any) {
    const reason = await appPrompt(
        `Indiquez le motif de contrepassation de ${p.reference}.`,
        {
            title: 'Contrepasser le paiement',
            tone: 'danger',
            inputLabel: 'Motif',
            inputRequired: true,
            confirmText: 'Contrepasser',
        },
    );
    if (reason)
        router.post(`/admin/finance/payments/${p.id}/reverse`, { reason });
}
function receiptPayment(enrollment: any) {
    return [...(enrollment.payments ?? [])]
        .filter((item: any) => item.status === 'completed')
        .sort((a: any, b: any) =>
            String(b.payment_date).localeCompare(String(a.payment_date)),
        )[0];
}
</script>

<template>
    <Head title="Finance étudiants" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <h1 class="text-2xl font-semibold">Finance étudiants</h1>
                    <p class="text-sm text-muted-foreground">
                        Encaissements, échéances et soldes par inscription.
                    </p>
                    <Button as-child variant="outline"
                        ><Link href="/admin/finance/settings"
                            ><Settings class="mr-2 size-4" />Paramètres</Link
                        ></Button
                    >
                </header>
                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="card in [
                            {
                                key: 'expected',
                                label: 'Revenu attendu',
                                icon: ReceiptText,
                                tone: 'text-primary bg-primary/10',
                            },
                            {
                                key: 'collected',
                                label: 'Encaissé',
                                icon: Banknote,
                                tone: 'text-emerald-600 bg-emerald-100',
                            },
                            {
                                key: 'remaining',
                                label: 'Restant',
                                icon: WalletCards,
                                tone: 'text-amber-600 bg-amber-100',
                            },
                            {
                                key: 'overdue',
                                label: 'En retard',
                                icon: TriangleAlert,
                                tone: 'text-red-600 bg-red-100',
                            },
                        ]"
                        :key="card.key"
                        class="rounded-xl border bg-card p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    {{ card.label }}
                                </p>
                                <b class="mt-1 block text-2xl">{{
                                    money(stats[card.key])
                                }}</b>
                            </div>
                            <span class="rounded-lg p-2" :class="card.tone"
                                ><component :is="card.icon" class="size-5"
                            /></span>
                        </div>
                    </article>
                </section>
                <form
                    class="rounded-xl border bg-card p-4 shadow-sm"
                    @submit.prevent="filters"
                >
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            /><Input
                                v-model="listFilters.search"
                                class="pl-9"
                                placeholder="Étudiant ou formation…"
                            />
                        </div>
                        <div class="relative">
                            <button
                                type="button"
                                class="flex h-9 w-full items-center justify-between rounded-md border bg-background px-3 text-left text-sm"
                                @click="studentOpen = !studentOpen"
                            >
                                <span>{{
                                    selectedStudent
                                        ? `${selectedStudent.first_name} ${selectedStudent.last_name}`
                                        : 'Tous les étudiants'
                                }}</span
                                ><ChevronsUpDown class="size-4" />
                            </button>
                            <div
                                v-if="studentOpen"
                                class="absolute z-30 mt-1 w-full min-w-72 rounded-md border bg-popover p-2 shadow-lg"
                            >
                                <div class="relative mb-2">
                                    <Search
                                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                                    /><Input
                                        v-model="studentSearch"
                                        autofocus
                                        class="pl-9"
                                        placeholder="Rechercher…"
                                    />
                                </div>
                                <div class="max-h-56 overflow-y-auto">
                                    <button
                                        type="button"
                                        class="flex w-full gap-2 rounded p-2 text-sm hover:bg-muted"
                                        @click="selectStudent(null)"
                                    >
                                        <Check
                                            class="size-4"
                                            :class="
                                                !listFilters.student_id
                                                    ? 'opacity-100'
                                                    : 'opacity-0'
                                            "
                                        />Tous les étudiants</button
                                    ><button
                                        v-for="student in filteredStudents"
                                        :key="student.id"
                                        type="button"
                                        class="flex w-full gap-2 rounded p-2 text-sm hover:bg-muted"
                                        @click="selectStudent(student)"
                                    >
                                        <Check
                                            class="size-4"
                                            :class="
                                                listFilters.student_id ===
                                                String(student.id)
                                                    ? 'opacity-100'
                                                    : 'opacity-0'
                                            "
                                        />{{ student.first_name }}
                                        {{ student.last_name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <Input
                            v-model="listFilters.month"
                            type="month"
                        /><select
                            v-model="listFilters.status"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Tous les statuts</option>
                            <option
                                v-for="(label, key) in statuses"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option></select
                        ><select
                            v-model="listFilters.payment_method"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Tous les moyens</option>
                            <option
                                v-for="method in methods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </select>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="clearFilters"
                            >Réinitialiser</Button
                        ><Button
                            ><Search class="mr-2 size-4" />Appliquer</Button
                        >
                    </div>
                </form>
                <section class="space-y-3">
                    <article
                        v-for="e in enrollments.data"
                        :key="e.id"
                        class="overflow-hidden rounded-xl border bg-card shadow-sm"
                    >
                        <div
                            class="grid gap-4 p-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] lg:items-center"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <b
                                        >{{ e.student.first_name }}
                                        {{ e.student.last_name }}</b
                                    ><span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="
                                            e.payment_status === 'paid'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : e.payment_status === 'overdue'
                                                  ? 'bg-red-100 text-red-700'
                                                  : 'bg-amber-100 text-amber-700'
                                        "
                                        >{{ statuses[e.payment_status] }}</span
                                    >
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        e.form?.course?.title ||
                                        e.training_plan_group?.plan?.course
                                            ?.title ||
                                        'Formation'
                                    }}
                                    · Groupe {{ e.group_number || '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Prix final
                                </p>
                                <b>{{ money(e.final_price) }}</b>
                                <p class="text-xs text-muted-foreground">
                                    Remise {{ money(e.discount_amount) }}
                                </p>
                            </div>
                            <div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full bg-emerald-500"
                                        :style="{
                                            width: `${Number(e.final_price) ? Math.min(100, (Number(e.total_paid) / Number(e.final_price)) * 100) : 100}%`,
                                        }"
                                    ></div>
                                </div>
                                <p class="mt-2 text-xs">
                                    <span class="text-emerald-600"
                                        >{{
                                            money(e.total_paid)
                                        }}
                                        encaissé</span
                                    >
                                    ·
                                    <span class="text-amber-600"
                                        >{{
                                            money(e.remaining_balance)
                                        }}
                                        restant</span
                                    >
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="open(e, 'adjust')"
                                    >Ajuster</Button
                                ><Button
                                    v-if="Number(e.remaining_balance) > 0"
                                    size="sm"
                                    @click="open(e, 'pay')"
                                    >Encaisser</Button
                                >
                            </div>
                        </div>
                        <div
                            v-if="e.payments?.length"
                            class="border-t bg-muted/20 px-4 py-3"
                        >
                            <p
                                class="mb-2 text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Paiements et reçus
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="p in e.payments"
                                    :key="p.id"
                                    class="flex items-center gap-2 rounded-lg border bg-background p-2 text-sm"
                                >
                                    <a
                                        :href="`/admin/finance/payments/${p.id}/receipt`"
                                        class="flex items-center gap-2"
                                        ><Download class="size-4" /><span
                                            ><b
                                                :class="
                                                    Number(p.amount) < 0
                                                        ? 'text-destructive'
                                                        : ''
                                                "
                                                >{{ money(p.amount) }}</b
                                            ><small
                                                class="block text-muted-foreground"
                                                >{{ p.payment_date }} ·
                                                {{
                                                    p.recorder?.name ||
                                                    'Système'
                                                }}</small
                                            ></span
                                        ></a
                                    ><Button
                                        v-if="p.status === 'completed'"
                                        size="sm"
                                        variant="ghost"
                                        @click="reverse(p)"
                                        >Contrepasser</Button
                                    >
                                </div>
                            </div>
                        </div>
                    </article>
                    <div
                        v-if="!enrollments.data.length"
                        class="rounded-xl border border-dashed p-12 text-center text-muted-foreground"
                    >
                        Aucun dossier financier trouvé.
                    </div>
                    <div
                        v-if="enrollments.links?.length > 3"
                        class="flex justify-center gap-1"
                    >
                        <Link
                            v-for="link in enrollments.links"
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
                <div
                    v-if="mode"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-background p-6"
                    >
                        <h2 class="text-lg font-semibold">
                            {{
                                mode === 'pay'
                                    ? 'Nouveau paiement'
                                    : 'Ajustement manuel'
                            }}
                        </h2>
                        <form
                            v-if="mode === 'pay'"
                            class="mt-4 grid gap-3"
                            @submit.prevent="savePayment"
                        >
                            <Input
                                v-model="payment.amount"
                                type="number"
                                step="0.01"
                                placeholder="Montant"
                                required
                            /><Input
                                v-model="payment.payment_date"
                                type="date"
                                required
                            /><select
                                v-model="payment.payment_method"
                                class="h-9 rounded-md border bg-background px-3"
                            >
                                <option
                                    v-for="m in methods"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label }}
                                </option></select
                            ><select
                                v-model="payment.student_installment_id"
                                class="h-9 rounded-md border bg-background px-3"
                            >
                                <option value="">Sans échéance précise</option>
                                <option
                                    v-for="i in selected.installments"
                                    :key="i.id"
                                    :value="i.id"
                                >
                                    {{ i.due_date }} — {{ money(i.amount) }} ({{
                                        i.status
                                    }})
                                </option></select
                            ><Input
                                v-model="payment.reference"
                                placeholder="Référence automatique si vide"
                            /><textarea
                                v-model="payment.notes"
                                class="rounded-md border p-2"
                                placeholder="Note"
                            ></textarea
                            ><Button>Enregistrer et générer le reçu</Button>
                        </form>
                        <form
                            v-else
                            class="mt-4 grid gap-3"
                            @submit.prevent="saveAdjustment"
                        >
                            <select
                                v-model="adjustment.type"
                                class="h-9 rounded-md border bg-background px-3"
                            >
                                <option value="credit">
                                    Crédit / réduction
                                </option>
                                <option value="charge">
                                    Frais supplémentaire
                                </option></select
                            ><Input
                                v-model="adjustment.amount"
                                type="number"
                                step="0.01"
                                placeholder="Montant"
                                required
                            /><textarea
                                v-model="adjustment.reason"
                                class="rounded-md border p-2"
                                placeholder="Motif obligatoire"
                                required
                            ></textarea
                            ><Button>Enregistrer l’ajustement</Button>
                        </form>
                        <Button
                            class="mt-3 w-full"
                            variant="ghost"
                            @click="mode = ''"
                            >Fermer</Button
                        >
                    </div>
                </div>
            </div>
        </main></AdminLayout
    >
</template>
