<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    translatePublicRoot,
    watchPublicLocale,
} from '@/composables/usePublicLocale';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, Download, Plus, X } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps<{ account: any; methods: any[] }>();
const root = ref<HTMLElement | null>(null);
let stop: (() => void) | undefined;
onMounted(() => {
    stop = watchPublicLocale(() => root.value);
    nextTick(() => translatePublicRoot(root.value));
});
onBeforeUnmount(() => stop?.());
const money = (v: any) =>
    `${Number(v || 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2 })} DZD`;
const modal = ref('');
const payment = useForm({
    amount: '',
    transaction_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    reference: '',
    external_reference: '',
    notes: '',
});
const movement = useForm({ type: 'discount', amount: '', reason: '' });
const allocated = (i: any) =>
    i.allocations?.reduce((s: number, a: any) => s + Number(a.amount), 0) || 0;
const status: any = {
    pending: 'À venir',
    partial: 'Partiel',
    paid: 'Payé',
    overdue: 'En retard',
};
const savePayment = () =>
    payment.post(
        `/admin/formation-payments/accounts/${props.account.id}/payments`,
        {
            onSuccess: () => {
                modal.value = '';
                payment.reset();
            },
        },
    );
const saveMovement = () =>
    movement.post(
        `/admin/formation-payments/accounts/${props.account.id}/movements`,
        {
            onSuccess: () => {
                modal.value = '';
                movement.reset();
            },
        },
    );
</script>
<template>
    <Head title="Compte formation" /><AdminLayout
        ><div ref="root">
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-6xl space-y-6">
                    <header class="flex flex-wrap justify-between gap-3">
                        <div>
                            <Link
                                href="/admin/formation-payments"
                                class="mb-2 flex items-center gap-2 text-sm text-muted-foreground"
                                ><ArrowLeft class="size-4" />Paiements
                                formations</Link
                            >
                            <h1 class="text-2xl font-black">
                                {{ account.student.full_name }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                {{ account.accountable?.form?.course?.title }} ·
                                {{ account.accountable?.form?.title }} ·
                                {{
                                    account.accountable?.training_plan_group
                                        ?.name || 'Sans groupe'
                                }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                @click="modal = 'movement'"
                                ><Plus class="mr-2 size-4" />Remise /
                                ajustement</Button
                            ><Button @click="modal = 'payment'"
                                ><Banknote
                                    class="mr-2 size-4"
                                />Encaisser</Button
                            >
                        </div>
                    </header>
                    <section class="grid gap-3 sm:grid-cols-4">
                        <article
                            v-for="x in [
                                {
                                    l: 'Total attendu',
                                    v: account.expected_total,
                                },
                                { l: 'Payé', v: account.paid_total },
                                { l: 'Restant', v: account.balance },
                                {
                                    l: 'En retard',
                                    v: account.installments
                                        .filter(
                                            (i: any) => i.status === 'overdue',
                                        )
                                        .reduce(
                                            (s: number, i: any) =>
                                                s +
                                                Number(i.amount) -
                                                allocated(i),
                                            0,
                                        ),
                                },
                            ]"
                            :key="x.l"
                            class="rounded-xl border bg-card p-4"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ x.l }}
                            </p>
                            <b class="text-xl">{{ money(x.v) }}</b>
                        </article>
                    </section>
                    <section class="rounded-xl border bg-card">
                        <h2 class="border-b p-4 font-black">
                            Échéancier attendu
                        </h2>
                        <div class="divide-y">
                            <div
                                v-for="i in account.installments"
                                :key="i.id"
                                class="grid gap-3 p-4 sm:grid-cols-[1fr_180px_160px_100px]"
                            >
                                <div>
                                    <b>{{ i.label }}</b>
                                    <p class="text-xs text-muted-foreground">
                                        {{ i.due_date }}
                                    </p>
                                </div>
                                <div>
                                    {{ money(allocated(i)) }} /
                                    {{ money(i.amount) }}
                                </div>
                                <div>
                                    Reste
                                    {{ money(Number(i.amount) - allocated(i)) }}
                                </div>
                                <span>{{ status[i.status] }}</span>
                            </div>
                        </div>
                    </section>
                    <section class="overflow-hidden rounded-xl border bg-card">
                        <h2 class="border-b p-4 font-black">
                            Historique des transactions
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left">
                                        <th class="p-3">Date</th>
                                        <th>Référence</th>
                                        <th>Type</th>
                                        <th>Montant</th>
                                        <th>Méthode</th>
                                        <th>Utilisateur</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="t in account.transactions"
                                        :key="t.id"
                                        class="border-t"
                                    >
                                        <td class="p-3">
                                            {{ t.transaction_date }}
                                        </td>
                                        <td>{{ t.reference }}</td>
                                        <td>{{ t.type }}</td>
                                        <td>{{ money(t.amount) }}</td>
                                        <td>{{ t.payment_method || '—' }}</td>
                                        <td>{{ t.recorder?.name || '—' }}</td>
                                        <td>
                                            <Button
                                                v-if="t.type === 'payment'"
                                                as-child
                                                size="sm"
                                                variant="outline"
                                                ><a
                                                    :href="`/admin/formation-payments/transactions/${t.id}/receipt`"
                                                    ><Download
                                                        class="size-4" /></a
                                            ></Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>
            <div
                v-if="modal"
                class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
            >
                <form
                    class="w-full max-w-md space-y-4 rounded-2xl bg-background p-6"
                    @submit.prevent="
                        modal === 'payment' ? savePayment() : saveMovement()
                    "
                >
                    <div class="flex justify-between">
                        <h2 class="text-xl font-black">
                            {{
                                modal === 'payment'
                                    ? 'Paiement formation'
                                    : 'Mouvement financier'
                            }}
                        </h2>
                        <button
                            type="button"
                            @click="modal = ''"
                            aria-label="Fermer"
                        >
                            <X />
                        </button>
                    </div>
                    <template v-if="modal === 'payment'"
                        ><Input
                            v-model="payment.amount"
                            required
                            type="number"
                            min="0.01"
                            :max="account.balance"
                            placeholder="Montant" /><Input
                            v-model="payment.transaction_date"
                            required
                            type="date" /><select
                            v-model="payment.payment_method"
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option
                                v-for="m in methods"
                                :key="m.value"
                                :value="m.value"
                            >
                                {{ m.label }}
                            </option></select
                        ><Input
                            v-model="payment.external_reference"
                            placeholder="Référence bancaire / CCP" /><Input
                            v-model="payment.notes"
                            placeholder="Notes du paiement" /></template
                    ><template v-else
                        ><select
                            v-model="movement.type"
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="discount">Remise formation</option>
                            <option value="scholarship">Exonération</option>
                            <option value="adjustment">Ajustement</option>
                            <option value="refund">
                                Remboursement
                            </option></select
                        ><Input
                            v-model="movement.amount"
                            required
                            type="number"
                            min="0.01"
                            placeholder="Montant" /><Input
                            v-model="movement.reason"
                            required
                            placeholder="Motif" /></template
                    ><Button class="w-full">Enregistrer</Button>
                </form>
            </div>
        </div></AdminLayout
    >
</template>
