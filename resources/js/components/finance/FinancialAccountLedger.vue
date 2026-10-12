<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { Ban, Banknote, Download, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/** Installments, history and the payment / movement / cancellation dialogs of one billing account. */
const props = defineProps<{
    account: any;
    methods: any[];
    /** Route prefix of the billing module, e.g. /admin/school-payments */
    baseUrl: string;
}>();

const money = (v: any) =>
    `${Number(v || 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2 })} DZD`;
const installmentStatus: Record<string, string> = {
    pending: 'À venir',
    partial: 'Partiel',
    paid: 'Réglé',
    overdue: 'En retard',
};
const typeLabels: Record<string, string> = {
    payment: 'Paiement',
    discount: 'Remise',
    scholarship: 'Bourse / exonération',
    adjustment: 'Ajustement débiteur',
    waiver: 'Clôture (échéances annulées)',
    reversal: 'Annulation',
};
const cancellable = ['payment', 'discount', 'scholarship'];
const methodLabel = (value: string | null) =>
    props.methods.find((m) => m.value === value)?.label || '—';
const allocated = (i: any) =>
    i.allocations?.reduce((s: number, a: any) => s + Number(a.amount), 0) || 0;
const overdue = computed(() =>
    props.account.installments
        .filter((i: any) => i.status === 'overdue')
        .reduce((s: number, i: any) => s + Number(i.amount) - allocated(i), 0),
);
const cards = computed(() => [
    { label: 'Total attendu', value: props.account.expected_total },
    {
        label: 'Remises / bourses',
        value: -Number(props.account.adjustment_total),
    },
    { label: 'Payé', value: props.account.paid_total },
    { label: 'Restant', value: props.account.balance },
    { label: 'En retard', value: overdue.value },
]);

const modal = ref<'' | 'payment' | 'movement' | 'cancel'>('');
const target = ref<any>(null);
const payment = useForm({
    amount: '',
    transaction_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    reference: '',
    external_reference: '',
    notes: '',
});
const movement = useForm({
    type: 'discount',
    amount: '',
    reason: '',
    due_date: '',
});
const cancellation = useForm({ reason: '' });
const close = () => {
    modal.value = '';
    target.value = null;
};
const askCancel = (t: any) => {
    target.value = t;
    cancellation.reset();
    modal.value = 'cancel';
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: close };
    if (modal.value === 'payment') {
        payment.post(`${props.baseUrl}/accounts/${props.account.id}/payments`, {
            ...options,
            onSuccess: () => {
                close();
                payment.reset();
            },
        });
    } else if (modal.value === 'movement') {
        movement.post(
            `${props.baseUrl}/accounts/${props.account.id}/movements`,
            {
                ...options,
                onSuccess: () => {
                    close();
                    movement.reset();
                },
            },
        );
    } else if (modal.value === 'cancel' && target.value) {
        cancellation.post(
            `${props.baseUrl}/transactions/${target.value.id}/cancel`,
            options,
        );
    }
};
const activeForm = computed(
    () =>
        ({ payment, movement, cancel: cancellation })[modal.value || 'payment'],
);
</script>

<template>
    <div class="space-y-6">
        <div
            v-if="account.closed_at"
            class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            Compte clôturé le {{ account.closed_at.slice(0, 10) }}
            <span v-if="account.closed_reason"
                >— {{ account.closed_reason }}</span
            >. Les échéances non échues ont été annulées ; aucun nouvel
            enregistrement n’est possible.
        </div>
        <div v-else class="flex flex-wrap justify-end gap-2">
            <Button variant="outline" @click="modal = 'movement'"
                ><Plus class="mr-2 size-4" />Remise / ajustement</Button
            ><Button
                :disabled="Number(account.balance) <= 0"
                @click="modal = 'payment'"
                ><Banknote class="mr-2 size-4" />Encaisser</Button
            >
        </div>
        <section class="grid gap-3 sm:grid-cols-5">
            <article
                v-for="card in cards"
                :key="card.label"
                class="rounded-xl border bg-card p-4"
            >
                <p class="text-xs text-muted-foreground">{{ card.label }}</p>
                <b class="text-xl">{{ money(card.value) }}</b>
            </article>
        </section>
        <section class="rounded-xl border bg-card">
            <h2 class="border-b p-4 font-black">Échéancier</h2>
            <div class="divide-y">
                <div
                    v-for="i in account.installments"
                    :key="i.id"
                    class="grid items-center gap-3 p-4 sm:grid-cols-[1fr_200px_160px_110px]"
                >
                    <div>
                        <b>{{ i.label }}</b>
                        <p class="text-xs text-muted-foreground">
                            Échéance {{ i.due_date }}
                        </p>
                    </div>
                    <div>{{ money(allocated(i)) }} / {{ money(i.amount) }}</div>
                    <div>
                        Reste {{ money(Number(i.amount) - allocated(i)) }}
                    </div>
                    <span
                        class="w-fit rounded-full px-2 py-1 text-xs"
                        :class="
                            i.status === 'overdue'
                                ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
                                : 'bg-muted'
                        "
                        >{{ installmentStatus[i.status] || i.status }}</span
                    >
                </div>
                <p
                    v-if="!account.installments.length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    Aucune échéance.
                </p>
            </div>
        </section>
        <section class="overflow-hidden rounded-xl border bg-card">
            <h2 class="border-b p-4 font-black">Historique des opérations</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="p-3">Date</th>
                            <th>Référence</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Motif / notes</th>
                            <th>Utilisateur</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="t in account.transactions"
                            :key="t.id"
                            class="border-t"
                            :class="
                                t.reversal
                                    ? 'text-muted-foreground line-through'
                                    : ''
                            "
                        >
                            <td class="p-3">{{ t.transaction_date }}</td>
                            <td>{{ t.reference }}</td>
                            <td>{{ typeLabels[t.type] || t.type }}</td>
                            <td
                                :class="
                                    Number(t.amount) < 0
                                        ? 'text-red-600'
                                        : 'text-emerald-700'
                                "
                            >
                                {{ money(t.amount) }}
                            </td>
                            <td>{{ methodLabel(t.payment_method) }}</td>
                            <td
                                class="max-w-56 truncate"
                                :title="t.reason || t.notes"
                            >
                                {{ t.reason || t.notes || '—' }}
                            </td>
                            <td>{{ t.recorder?.name || '—' }}</td>
                            <td class="flex gap-1 py-2 pr-3">
                                <Button
                                    v-if="t.type === 'payment'"
                                    as-child
                                    size="sm"
                                    variant="outline"
                                    title="Télécharger le reçu"
                                    ><a
                                        :href="`${baseUrl}/transactions/${t.id}/receipt`"
                                        ><Download class="size-4" /></a
                                ></Button>
                                <Button
                                    v-if="
                                        !account.closed_at &&
                                        cancellable.includes(t.type) &&
                                        !t.reversal
                                    "
                                    size="sm"
                                    variant="outline"
                                    title="Annuler l’opération"
                                    @click="askCancel(t)"
                                    ><Ban class="size-4"
                                /></Button>
                            </td>
                        </tr>
                        <tr v-if="!account.transactions.length">
                            <td colspan="8" class="p-4 text-muted-foreground">
                                Aucune opération enregistrée.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <div
            v-if="modal"
            class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        >
            <form
                class="w-full max-w-md space-y-4 rounded-2xl bg-background p-6"
                @submit.prevent="submit"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-black">
                        {{
                            modal === 'payment'
                                ? 'Enregistrer un paiement'
                                : modal === 'movement'
                                  ? 'Remise / ajustement'
                                  : 'Annuler l’opération'
                        }}
                    </h2>
                    <button type="button" aria-label="Fermer" @click="close">
                        <X />
                    </button>
                </div>
                <template v-if="modal === 'payment'">
                    <Input
                        v-model="payment.amount"
                        required
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="account.balance"
                        :placeholder="`Montant (max ${money(account.balance)})`"
                    />
                    <Input
                        v-model="payment.transaction_date"
                        required
                        type="date"
                    />
                    <select
                        v-model="payment.payment_method"
                        required
                        class="h-10 w-full rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="m in methods"
                            :key="m.value"
                            :value="m.value"
                        >
                            {{ m.label }}
                        </option>
                    </select>
                    <Input
                        v-model="payment.reference"
                        placeholder="N° de reçu (optionnel, généré sinon)"
                    />
                    <Input
                        v-model="payment.external_reference"
                        placeholder="Référence chèque / virement / CCP"
                    />
                    <Input v-model="payment.notes" placeholder="Notes" />
                </template>
                <template v-else-if="modal === 'movement'">
                    <select
                        v-model="movement.type"
                        class="h-10 w-full rounded-md border bg-background px-3"
                    >
                        <option value="discount">Remise</option>
                        <option value="scholarship">
                            Bourse / exonération
                        </option>
                        <option value="adjustment">
                            Ajustement débiteur (frais supplémentaire)
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        {{
                            movement.type === 'adjustment'
                                ? 'Ajoute une nouvelle échéance au compte.'
                                : 'Réduit les dernières échéances non réglées.'
                        }}
                    </p>
                    <Input
                        v-model="movement.amount"
                        required
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="Montant"
                    />
                    <Input
                        v-if="movement.type === 'adjustment'"
                        v-model="movement.due_date"
                        type="date"
                        title="Date d’échéance (aujourd’hui par défaut)"
                    />
                    <Input
                        v-model="movement.reason"
                        required
                        placeholder="Motif obligatoire"
                    />
                </template>
                <template v-else>
                    <p class="text-sm">
                        {{ typeLabels[target?.type] }}
                        {{ target?.reference }} de
                        <b>{{ money(target?.amount) }}</b
                        >. Une écriture d’annulation sera ajoutée ; l’opération
                        d’origine reste visible dans l’historique.
                    </p>
                    <Input
                        v-model="cancellation.reason"
                        required
                        placeholder="Motif de l’annulation"
                    />
                </template>
                <p
                    v-for="(error, key) in activeForm.errors"
                    :key="key"
                    class="text-sm text-red-600"
                >
                    {{ error }}
                </p>
                <Button class="w-full" :disabled="activeForm.processing">
                    {{
                        modal === 'cancel'
                            ? 'Confirmer l’annulation'
                            : 'Enregistrer'
                    }}
                </Button>
            </form>
        </div>
    </div>
</template>
