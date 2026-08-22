<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Calculator, Download, Plus, WalletCards, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps({
    statements: { type: Object, required: true },
    configurations: { type: Array, required: true },
    payments: { type: Array, required: true },
    employees: { type: Array, required: true },
    salaryTypes: { type: Array, required: true },
    filters: { type: Object, required: true },
    currency: { type: Object, required: true },
});
const tab = ref('pending'),
    configOpen = ref(false),
    generateOpen = ref(false),
    paying = ref<any>(null);
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
const config = useForm({
    staff_id: '',
    salary_type: 'monthly',
    base_rate: '',
    effective_from: new Date().toISOString().slice(0, 10),
    effective_to: '',
    notes: '',
});
const statement = useForm({
    staff_id: '',
    period_start: new Date().toISOString().slice(0, 7) + '-01',
    period_end: new Date().toISOString().slice(0, 10),
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
        (c: any) => String(c.staff_id) === statement.staff_id,
    ),
);
const money = (v: any) =>
    `${Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} ${props.currency.symbol}`;
function addAdjustment() {
    statement.adjustments.push({
        type: 'bonus',
        label: '',
        amount: '',
        notes: '',
    });
}
function saveConfig() {
    config.post('/admin/salaries/configurations', {
        onSuccess: () => (configOpen.value = false),
    });
}
function generate() {
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
                        <Button variant="outline" @click="configOpen = true"
                            ><Plus class="mr-2 size-4" />Configuration</Button
                        ><Button @click="generateOpen = true"
                            ><Calculator class="mr-2 size-4" />Calculer</Button
                        >
                    </div>
                </header>
                <nav
                    class="flex gap-1 overflow-x-auto rounded-xl border bg-card p-2"
                >
                    <button
                        v-for="i in [
                            { id: 'pending', n: 'À payer' },
                            { id: 'history', n: 'Bulletins' },
                            { id: 'config', n: 'Configurations' },
                            { id: 'payments', n: 'Paiements' },
                        ]"
                        :key="i.id"
                        class="shrink-0 rounded-lg px-3 py-2 text-sm"
                        :class="
                            tab === i.id
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        @click="tab = i.id"
                    >
                        {{ i.n }}
                    </button>
                </nav>
                <section
                    v-if="tab === 'pending' || tab === 'history'"
                    class="overflow-x-auto rounded-xl border bg-card"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="p-4">Employé / période</th>
                                <th class="p-4">Calcul</th>
                                <th class="p-4 text-right">Brut</th>
                                <th class="p-4 text-right">Net</th>
                                <th class="p-4 text-right">Payé / restant</th>
                                <th class="p-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="s in statements.data.filter(
                                    (x: any) =>
                                        tab === 'history' ||
                                        Number(x.remaining_amount) > 0,
                                )"
                                :key="s.id"
                            >
                                <td class="p-4">
                                    <b>{{ s.staff.name }}</b>
                                    <p class="text-xs text-muted-foreground">
                                        {{ s.reference }} ·
                                        {{ s.period_start }} →
                                        {{ s.period_end }}
                                    </p>
                                </td>
                                <td class="p-4">
                                    {{ labels[s.salary_type] }} ·
                                    {{ s.units }} unité(s)
                                </td>
                                <td class="p-4 text-right">
                                    {{ money(s.gross_salary) }}
                                </td>
                                <td class="p-4 text-right font-semibold">
                                    {{ money(s.net_salary) }}
                                </td>
                                <td class="p-4 text-right">
                                    {{ money(s.amount_paid) }} /
                                    {{ money(s.remaining_amount) }}
                                </td>
                                <td class="p-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="
                                                Number(s.remaining_amount) > 0
                                            "
                                            size="sm"
                                            @click="openPay(s)"
                                            ><WalletCards
                                                class="mr-1 size-4"
                                            />Payer</Button
                                        ><Button
                                            as-child
                                            size="sm"
                                            variant="outline"
                                            ><a
                                                :href="`/admin/salaries/${s.id}/print`"
                                                ><Download class="size-4" /></a
                                        ></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section
                    v-else-if="tab === 'config'"
                    class="grid gap-3 md:grid-cols-2"
                >
                    <article
                        v-for="c in configurations"
                        :key="c.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <b>{{ c.staff.name }}</b>
                        <p class="text-sm text-muted-foreground">
                            {{ c.staff.employee_type.name }}
                        </p>
                        <p class="mt-3">
                            {{ labels[c.salary_type] }} ·
                            {{ money(c.base_rate) }}
                        </p>
                        <p class="text-xs">
                            {{ c.effective_from }} →
                            {{ c.effective_to || 'sans fin' }}
                        </p>
                    </article>
                </section>
                <section
                    v-else
                    class="overflow-x-auto rounded-xl border bg-card"
                >
                    <table class="w-full text-sm">
                        <tbody class="divide-y">
                            <tr v-for="p in payments" :key="p.id">
                                <td class="p-4">{{ p.paid_at }}</td>
                                <td class="p-4">{{ p.staff.name }}</td>
                                <td class="p-4">
                                    {{ p.statement.reference }} ·
                                    {{ p.reference }}
                                </td>
                                <td class="p-4">{{ p.payment_method }}</td>
                                <td class="p-4 text-right font-semibold">
                                    {{ money(p.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>
        <div v-if="configOpen" class="fixed inset-0 z-50 bg-black/50 p-4">
            <form
                class="mx-auto mt-16 max-w-xl space-y-4 rounded-xl bg-background p-6"
                @submit.prevent="saveConfig"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">
                        Configuration salariale
                    </h2>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="configOpen = false"
                        ><X
                    /></Button>
                </div>
                <label
                    ><Label>Employé</Label
                    ><select
                        v-model="config.staff_id"
                        required
                        class="h-9 w-full rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>Sélectionner</option>
                        <option
                            v-for="e in employees"
                            :key="e.id"
                            :value="String(e.id)"
                        >
                            {{ e.name }} — {{ e.employee_type.name }}
                        </option>
                    </select></label
                ><label
                    ><Label>Type</Label
                    ><select
                        v-model="config.salary_type"
                        class="h-9 w-full rounded-md border bg-background px-3"
                    >
                        <option v-for="t in salaryTypes" :key="t" :value="t">
                            {{ labels[t] }}
                        </option>
                    </select></label
                ><label
                    ><Label>Taux de base</Label
                    ><Input
                        v-model="config.base_rate"
                        type="number"
                        min="0"
                        step="0.01"
                        required /><InputError
                        :message="config.errors.base_rate"
                /></label>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        ><Label>Début</Label
                        ><Input
                            v-model="config.effective_from"
                            type="date" /></label
                    ><label
                        ><Label>Fin</Label
                        ><Input v-model="config.effective_to" type="date"
                    /></label>
                </div>
                <Button class="w-full">Enregistrer</Button>
            </form>
        </div>
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
                <label
                    ><Label>Employé</Label
                    ><select
                        v-model="statement.staff_id"
                        required
                        class="h-9 w-full rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>Sélectionner</option>
                        <option
                            v-for="e in employees"
                            :key="e.id"
                            :value="String(e.id)"
                        >
                            {{ e.name }}
                        </option></select
                    ><small v-if="selected"
                        >{{ labels[selected.salary_type] }} ·
                        {{ money(selected.base_rate) }}</small
                    ><InputError :message="statement.errors.staff_id"
                /></label>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        ><Label>Début</Label
                        ><Input
                            v-model="statement.period_start"
                            type="date" /></label
                    ><label
                        ><Label>Fin</Label
                        ><Input v-model="statement.period_end" type="date"
                    /></label>
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
                <div class="flex justify-between">
                    <b>Primes et retenues</b
                    ><Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="addAdjustment"
                        >Ajouter</Button
                    >
                </div>
                <div
                    v-for="(a, i) in statement.adjustments"
                    :key="i"
                    class="grid grid-cols-3 gap-2"
                >
                    <select v-model="a.type" class="h-9 rounded-md border">
                        <option v-for="(n, k) in adjustmentLabels" :value="k">
                            {{ n }}
                        </option></select
                    ><Input v-model="a.label" placeholder="Libellé" /><Input
                        v-model="a.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="Montant"
                    />
                </div>
                <Button class="w-full">Générer</Button>
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
