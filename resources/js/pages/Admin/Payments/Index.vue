<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { appConfirm, appPrompt } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Payment = {
    id: number;
    payment_number: string;
    amount: number | string;
    currency?: string;
    payment_method: string;
    status: string;
    processed_at?: string | null;
    proof_url?: string | null;
    payment_type?: string;
    user?: { id: number; name: string; email: string } | null;
    reservation?: { id: number; reservation_number: string } | null;
};

const props = defineProps<{
    payments: {
        data: Payment[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statuses: Record<string, { label: string; count: number; color: string }>;
    paymentMethods: Array<{ value: string; label: string }>;
    reservations: Array<{
        id: number;
        reservation_number: string;
        total_amount: number | string;
        user?: { name: string; email: string } | null;
    }>;
    filters: { search?: string; status?: string };
    currency: { symbol: string; code: string };
}>();

const showManualForm = ref(false);
const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const form = useForm({
    reservation_id: '',
    amount: '',
    payment_method: 'cash',
    transaction_id: '',
    notes: '',
});

const statusStyles = computed(() =>
    Object.fromEntries(
        Object.entries(props.statuses).map(([status, meta]) => [
            status,
            {
                backgroundColor: `${meta.color}1A`,
                color: meta.color,
                dot: meta.color,
                label: meta.label,
            },
        ]),
    ),
);

function statusStyle(status: string) {
    return (
        statusStyles.value[status] ?? {
            backgroundColor: '#6B72801A',
            color: '#6B7280',
            dot: '#6B7280',
            label: status,
        }
    );
}

function fmtMoney(amount?: number | string) {
    return `${props.currency.symbol}${Number(amount ?? 0).toFixed(2)}`;
}

function filterPayments() {
    router.get(
        '/admin/payments',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function recordPayment() {
    form.post('/admin/payments', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.payment_method = 'cash';
            showManualForm.value = false;
        },
    });
}

async function approve(payment: Payment) {
    if (
        await appConfirm(`Approuver le paiement ${payment.payment_number} ?`, {
            title: 'Approuver le paiement',
            confirmText: 'Approuver',
        })
    ) {
        router.patch(
            `/admin/payments/${payment.id}/approve`,
            {},
            { preserveScroll: true },
        );
    }
}

async function disapprove(payment: Payment) {
    const notes = await appPrompt(
        `Refuser le paiement ${payment.payment_number} ? Ajoutez éventuellement un motif pour le client :`,
        {
            title: 'Refuser le paiement',
            tone: 'warning',
            inputLabel: 'Motif (facultatif)',
            confirmText: 'Refuser',
        },
    );
    if (notes !== null) {
        router.patch(
            `/admin/payments/${payment.id}/disapprove`,
            { notes },
            { preserveScroll: true },
        );
    }
}

watch(
    () => form.reservation_id,
    (reservationId) => {
        const reservation = props.reservations.find(
            (item) => item.id === Number(reservationId),
        );
        if (reservation && !form.amount)
            form.amount = String(reservation.total_amount);
    },
);
</script>

<template>
    <Head title="Paiements" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Paiements</h1>
                    <p class="text-sm text-muted-foreground">
                        Enregistrez et examinez les paiements soumis par les
                        clients.
                    </p>
                </div>
                <Button @click="showManualForm = !showManualForm">
                    {{
                        showManualForm
                            ? 'Fermer le formulaire'
                            : 'Enregistrer un paiement manuel'
                    }}
                </Button>
            </div>

            <form
                v-if="showManualForm"
                class="grid gap-4 rounded-md border p-5 md:grid-cols-2"
                @submit.prevent="recordPayment"
            >
                <div class="md:col-span-2">
                    <h2 class="font-medium">Enregistrer un paiement</h2>
                    <p class="text-sm text-muted-foreground">
                        Les paiements manuels sont enregistrés comme approuvés
                        immédiatement.
                    </p>
                </div>
                <label class="grid gap-1 text-sm font-medium">
                    Réservation
                    <select
                        v-model="form.reservation_id"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">Sélectionnez une réservation</option>
                        <option
                            v-for="reservation in reservations"
                            :key="reservation.id"
                            :value="String(reservation.id)"
                        >
                            {{ reservation.reservation_number }} —
                            {{ reservation.user?.name }} ({{
                                fmtMoney(reservation.total_amount)
                            }})
                        </option>
                    </select>
                    <span
                        v-if="form.errors.reservation_id"
                        class="text-xs text-destructive"
                        >{{ form.errors.reservation_id }}</span
                    >
                </label>
                <label class="grid gap-1 text-sm font-medium">
                    Montant
                    <Input
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                    <span
                        v-if="form.errors.amount"
                        class="text-xs text-destructive"
                        >{{ form.errors.amount }}</span
                    >
                </label>
                <label class="grid gap-1 text-sm font-medium">
                    Méthode
                    <select
                        v-model="form.payment_method"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="method in paymentMethods"
                            :key="method.value"
                            :value="method.value"
                        >
                            {{ method.label }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.payment_method"
                        class="text-xs text-destructive"
                        >{{ form.errors.payment_method }}</span
                    >
                </label>
                <label class="grid gap-1 text-sm font-medium">
                    Identifiant de transaction/référence
                    <span class="font-normal text-muted-foreground"
                        >(facultatif)</span
                    >
                    <Input v-model="form.transaction_id" maxlength="255" />
                </label>
                <label class="grid gap-1 text-sm font-medium md:col-span-2">
                    Remarques
                    <span class="font-normal text-muted-foreground"
                        >(optional)</span
                    >
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        maxlength="2000"
                        class="rounded-md border bg-background px-3 py-2"
                    />
                </label>
                <div class="flex justify-end md:col-span-2">
                    <Button type="submit" :disabled="form.processing"
                        >Enregistrer le paiement approuvé</Button
                    >
                </div>
            </form>

            <div class="flex flex-col gap-3 sm:flex-row">
                <Input
                    v-model="search"
                    class="max-w-md"
                    placeholder="Rechercher un paiement, un client ou une réservation..."
                    @keyup.enter="filterPayments"
                />
                <select
                    v-model="statusFilter"
                    class="h-9 rounded-md border bg-background px-3"
                    @change="filterPayments"
                >
                    <option value="">Tous les statuts</option>
                    <option
                        v-for="(status, value) in statuses"
                        :key="value"
                        :value="value"
                    >
                        {{ status.label }} ({{ status.count }})
                    </option>
                </select>
                <Button variant="outline" @click="filterPayments"
                    >Rechercher</Button
                >
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                #
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Client
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Réservation
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Montant
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Méthode
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Statut
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Traité le
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="payment in payments.data" :key="payment.id">
                            <td class="px-4 py-3">
                                {{ payment.payment_number }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ payment.user?.name || '—' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ payment.user?.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="payment.reservation"
                                    :href="`/admin/reservations/${payment.reservation.id}`"
                                    class="text-blue-600 hover:underline"
                                    >{{
                                        payment.reservation.reservation_number
                                    }}</Link
                                ><span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-green-800">
                                {{ fmtMoney(payment.amount) }}
                            </td>
                            <td class="px-4 py-3 capitalize">
                                <div>
                                    {{
                                        payment.payment_method.replace('_', ' ')
                                    }}
                                </div>
                                <a
                                    v-if="payment.proof_url"
                                    :href="payment.proof_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs font-medium text-blue-600 hover:underline"
                                    >Voir la preuve</a
                                >
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :style="{
                                        backgroundColor: statusStyle(
                                            payment.status,
                                        ).backgroundColor,
                                        color: statusStyle(payment.status)
                                            .color,
                                    }"
                                    ><span
                                        class="size-2 rounded-full"
                                        :style="{
                                            backgroundColor: statusStyle(
                                                payment.status,
                                            ).dot,
                                        }"
                                    />{{
                                        statusStyle(payment.status).label
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{
                                    payment.processed_at
                                        ? new Date(
                                              payment.processed_at,
                                          ).toLocaleString()
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    v-if="payment.status === 'pending'"
                                    class="flex justify-end gap-2"
                                >
                                    <Button size="sm" @click="approve(payment)"
                                        >Approuver</Button
                                    ><Button
                                        size="sm"
                                        variant="destructive"
                                        @click="disapprove(payment)"
                                        >Refuser</Button
                                    >
                                </div>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                            </td>
                        </tr>
                        <tr v-if="payments.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                Aucun paiement trouvé.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="payments.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, index) in payments.links"
                    :key="index"
                    :href="link.url || ''"
                    :class="[
                        'rounded px-3 py-1 text-sm',
                        link.active
                            ? 'bg-gray-900 text-white'
                            : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                    ><span v-html="link.label"
                /></Link>
            </nav>
        </main>
    </AdminLayout>
</template>
