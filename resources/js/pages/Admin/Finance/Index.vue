<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    enrollments: { type: Object, required: true },
    payments: { type: Array, required: true },
    methods: { type: Array, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    currency: { type: Object, required: true },
});
const tab = ref('balances'),
    selected = ref<any>(null),
    mode = ref('');
const search = ref((props.filters as any).search ?? ''),
    status = ref((props.filters as any).status ?? '');
const payment = useForm({
    amount: '',
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    student_installment_id: '',
    reference: '',
    notes: '',
});
const config = useForm({
    formation_price: '',
    discount_amount: '0',
    installments: [] as any[],
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
const overdue = computed(() =>
    props.enrollments.data.filter((e: any) => e.payment_status === 'overdue'),
);
function filters() {
    router.get(
        '/admin/finance',
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}
function open(e: any, what: string) {
    selected.value = e;
    mode.value = what;
    if (what === 'pay') payment.amount = String(e.remaining_balance);
    if (what === 'config') {
        config.formation_price = String(
            e.formation_price ?? e.form?.course?.price ?? e.training_plan_group?.plan?.course?.price ?? 0,
        );
        config.discount_amount = String(e.discount_amount ?? 0);
    }
}
function savePayment() {
    payment.post(`/admin/finance/enrollments/${selected.value.id}/payments`, {
        onSuccess: () => {
            mode.value = '';
            payment.reset();
        },
    });
}
function saveConfig() {
    config.put(`/admin/finance/enrollments/${selected.value.id}`, {
        onSuccess: () => {
            mode.value = '';
            config.reset();
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
function addInstallment() {
    config.installments.push({ amount: '', due_date: '', notes: '' });
}
function reverse(p: any) {
    const reason = prompt(`Motif de contrepassation de ${p.reference}`);
    if (reason)
        router.post(`/admin/finance/payments/${p.id}/reverse`, { reason });
}
</script>

<template>
    <Head title="Finance étudiants" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header>
                    <h1 class="text-2xl font-semibold">Finance étudiants</h1>
                    <p class="text-sm text-muted-foreground">
                        Encaissements, échéances et soldes par inscription.
                    </p>
                </header>
                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="(value, key) in stats"
                        :key="key"
                        class="rounded-xl border bg-card p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{
                                {
                                    expected: 'Revenu attendu',
                                    collected: 'Encaissé',
                                    remaining: 'Restant',
                                    overdue: 'En retard',
                                }[key]
                            }}
                        </p>
                        <p class="mt-1 text-xl font-semibold">
                            {{ money(value) }}
                        </p>
                    </div>
                </section>
                <div class="flex flex-wrap gap-2">
                    <Button
                        :variant="tab === 'balances' ? 'default' : 'outline'"
                        @click="tab = 'balances'"
                        >Soldes étudiants</Button
                    ><Button
                        :variant="tab === 'overdue' ? 'default' : 'outline'"
                        @click="tab = 'overdue'"
                        >Échéances en retard</Button
                    ><Button
                        :variant="tab === 'history' ? 'default' : 'outline'"
                        @click="tab = 'history'"
                        >Historique des paiements</Button
                    >
                </div>
                <section
                    v-if="tab !== 'history'"
                    class="rounded-xl border bg-card"
                >
                    <div class="flex gap-2 border-b p-4">
                        <Input
                            v-model="search"
                            placeholder="Rechercher un étudiant"
                            @keyup.enter="filters"
                        /><select
                            v-model="status"
                            class="rounded-md border bg-background px-3"
                            @change="filters"
                        >
                            <option value="">Tous les statuts</option>
                            <option
                                v-for="(label, key) in statuses"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="p-3">Étudiant / formation</th>
                                    <th>Prix final</th>
                                    <th>Payé</th>
                                    <th>Reste</th>
                                    <th>Statut</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="e in tab === 'overdue'
                                        ? overdue
                                        : enrollments.data"
                                    :key="e.id"
                                    class="border-b"
                                >
                                    <td class="p-3">
                                        <strong
                                            >{{ e.student.first_name }}
                                            {{ e.student.last_name }}</strong
                                        >
                                        <div class="text-muted-foreground">
                                            {{ e.form?.course?.title || e.training_plan_group?.plan?.course?.title || 'Formation' }} · Groupe
                                            {{ e.group_number || '—' }}
                                        </div>
                                    </td>
                                    <td>{{ money(e.final_price) }}</td>
                                    <td>{{ money(e.total_paid) }}</td>
                                    <td>{{ money(e.remaining_balance) }}</td>
                                    <td>{{ statuses[e.payment_status] }}</td>
                                    <td class="space-x-1 whitespace-nowrap">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="open(e, 'config')"
                                            >Configurer</Button
                                        ><Button
                                            size="sm"
                                            variant="outline"
                                            @click="open(e, 'adjust')"
                                            >Ajuster</Button
                                        ><Button
                                            size="sm"
                                            :disabled="
                                                Number(e.remaining_balance) <= 0
                                            "
                                            @click="open(e, 'pay')"
                                            >Encaisser</Button
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section v-else class="rounded-xl border bg-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="p-3">Référence</th>
                                    <th>Étudiant</th>
                                    <th>Formation</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Caissier</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in payments"
                                    :key="p.id"
                                    class="border-b"
                                >
                                    <td class="p-3">
                                        {{ p.reference }}
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ p.status }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ p.student.first_name }}
                                        {{ p.student.last_name }}
                                    </td>
                                    <td>
                                        {{ p.enrollment.form?.course?.title || p.enrollment.training_plan_group?.plan?.course?.title || 'Formation' }}
                                    </td>
                                    <td>{{ p.payment_date }}</td>
                                    <td
                                        :class="
                                            Number(p.amount) < 0
                                                ? 'text-destructive'
                                                : ''
                                        "
                                    >
                                        {{ money(p.amount) }}
                                    </td>
                                    <td>{{ p.recorder?.name || 'Système' }}</td>
                                    <td class="space-x-1">
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="outline"
                                            ><a
                                                :href="`/admin/finance/payments/${p.id}/receipt`"
                                                >Reçu</a
                                            ></Button
                                        ><Button
                                            v-if="p.status === 'completed'"
                                            size="sm"
                                            variant="outline"
                                            @click="reverse(p)"
                                            >Contrepasser</Button
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
                                    : mode === 'config'
                                      ? 'Conditions financières'
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
                            v-else-if="mode === 'config'"
                            class="mt-4 grid gap-3"
                            @submit.prevent="saveConfig"
                        >
                            <Input
                                v-model="config.formation_price"
                                type="number"
                                step="0.01"
                                placeholder="Prix formation"
                                required
                            /><Input
                                v-model="config.discount_amount"
                                type="number"
                                step="0.01"
                                placeholder="Remise"
                            />
                            <div
                                v-for="(i, k) in config.installments"
                                :key="k"
                                class="grid grid-cols-2 gap-2"
                            >
                                <Input
                                    v-model="i.amount"
                                    type="number"
                                    step="0.01"
                                    placeholder="Montant échéance"
                                /><Input v-model="i.due_date" type="date" />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                @click="addInstallment"
                                >Ajouter une échéance</Button
                            ><Button>Enregistrer</Button>
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
