<script setup lang="ts">
/** Read-only view of a student's school and formation billing accounts. */
const props = defineProps<{
    finance: { accounts: any[]; totals: Record<string, number> } | null;
    /** Builds the receipt link of a payment; omit to hide receipts. */
    receiptUrl?: (paymentId: number, account: any) => string;
    /** Builds the admin link of an account; omit to hide it. */
    accountUrl?: (account: any) => string;
}>();
const money = (v: any) =>
    `${Number(v || 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2 })} DZD`;
const statusLabels: Record<string, string> = {
    unpaid: 'Non payé',
    partial: 'Partiellement payé',
    paid: 'Soldé',
    overdue: 'En retard',
    pending: 'À venir',
};
const statusClass = (status: string) =>
    status === 'overdue'
        ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
        : status === 'paid'
          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
          : 'bg-muted';
const totals = () => [
    { label: 'Total payé', value: props.finance?.totals.paid },
    { label: 'Reste à payer', value: props.finance?.totals.balance },
    { label: 'Dont en retard', value: props.finance?.totals.overdue },
];
</script>

<template>
    <div class="space-y-5">
        <p
            v-if="!finance || !finance.accounts.length"
            class="rounded-lg border p-4 text-sm text-muted-foreground"
        >
            Aucun échéancier de paiement pour le moment.
        </p>
        <template v-else>
            <section class="grid gap-3 sm:grid-cols-3">
                <article
                    v-for="card in totals()"
                    :key="card.label"
                    class="rounded-xl border bg-card p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <b class="text-lg">{{ money(card.value) }}</b>
                </article>
            </section>
            <article
                v-for="account in finance.accounts"
                :key="account.id"
                class="rounded-xl border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-bold">{{ account.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ account.context || '—' }}
                        </p>
                        <span
                            class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs"
                            :class="statusClass(account.status)"
                            >{{
                                account.closed
                                    ? 'Clôturé'
                                    : statusLabels[account.status] ||
                                      account.status
                            }}</span
                        >
                    </div>
                    <div class="text-right text-sm">
                        <p>Attendu : {{ money(account.expected) }}</p>
                        <p v-if="account.credits > 0">
                            Remises : −{{ money(account.credits) }}
                        </p>
                        <p>Payé : {{ money(account.paid) }}</p>
                        <strong>Reste : {{ money(account.balance) }}</strong>
                        <p v-if="accountUrl">
                            <a
                                :href="accountUrl(account)"
                                class="text-primary underline"
                                >Ouvrir le compte</a
                            >
                        </p>
                    </div>
                </div>
                <div class="mt-4 divide-y border-t text-sm">
                    <div
                        v-for="item in account.installments"
                        :key="item.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2"
                    >
                        <div>
                            <p>{{ item.label }}</p>
                            <p class="text-xs text-muted-foreground">
                                Échéance {{ item.due_date }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span>{{ money(item.amount) }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="statusClass(item.status)"
                                >{{
                                    item.status === 'paid'
                                        ? 'Réglé'
                                        : `${statusLabels[item.status] || item.status} · reste ${money(item.remaining)}`
                                }}</span
                            >
                        </div>
                    </div>
                </div>
                <div v-if="account.payments.length" class="mt-4">
                    <p
                        class="mb-1 text-xs font-bold text-muted-foreground uppercase"
                    >
                        Paiements
                    </p>
                    <div class="divide-y text-sm">
                        <div
                            v-for="payment in account.payments"
                            :key="payment.id"
                            class="flex items-center justify-between gap-3 py-2"
                            :class="
                                payment.cancelled
                                    ? 'text-muted-foreground line-through'
                                    : ''
                            "
                        >
                            <div>
                                <p>{{ payment.reference }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ payment.date }} ·
                                    {{ payment.method || '—' }}
                                    <template v-if="payment.cancelled">
                                        · annulé</template
                                    >
                                </p>
                            </div>
                            <div class="text-right">
                                <strong>{{ money(payment.amount) }}</strong>
                                <p v-if="receiptUrl && !payment.cancelled">
                                    <a
                                        class="text-primary underline"
                                        :href="receiptUrl(payment.id, account)"
                                        >Reçu</a
                                    >
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </template>
    </div>
</template>
