<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { index, print } from '@/routes/client/reservations';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    reservation: any;
    statusMeta: Array<{ value: string; label: string; color: string }>;
    paymentStatusMeta: Array<{ value: string; label: string }>;
    currency: { symbol: string; code: string };
}>();

const statusMap = computed(() => {
    const map: Record<string, { label: string; color: string }> = {};
    for (const s of props.statusMeta || [])
        map[s.value] = { label: s.label, color: s.color };
    return map;
});

function getStatusStyle(status: string) {
    const meta = statusMap.value[status];
    if (!meta)
        return {
            bg: 'rgba(107,114,128,0.1)',
            text: '#6B7280',
            dot: '#6B7280',
            label: status,
        };
    const hex = meta.color.replace('#', '');
    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);
    return {
        bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
        text: meta.color,
        dot: meta.color,
        label: meta.label,
    };
}

function fmtDate(d?: string) {
    if (!d) return '—';
    const [year, month, day] = d.slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
}
function fmtDateTime(d?: string) {
    if (!d) return '—';
    const [date, time] = d.split('T');
    return `${fmtDate(date)}${time ? ` ${time.slice(0, 5)}` : ''}`;
}
function fmtMoney(n?: number | string) {
    const v = Number(n ?? 0);
    return `${props.currency.symbol}${v.toFixed(2)}`;
}

const proofFolders = ref<string[]>([]);
const proofForm = useForm({
    proof: [] as string[],
    transaction_id: '',
    notes: '',
});
const advancePayment = computed(() =>
    (props.reservation.payments || []).find(
        (payment: any) =>
            payment.payment_type === 'advance' &&
            ['pending', 'completed'].includes(payment.status),
    ),
);
const canSubmitProof = computed(
    () =>
        Number(props.reservation.required_advance_amount) > 0 &&
        !advancePayment.value,
);

function submitProof() {
    proofForm.proof = [...proofFolders.value];
    proofForm.post(
        `/client/reservations/${props.reservation.id}/payment-proof`,
        {
            preserveScroll: true,
            onSuccess: () => {
                proofFolders.value = [];
                proofForm.reset();
            },
        },
    );
}
</script>

<template>
    <Head :title="`Réservation ${reservation?.reservation_number || ''}`" />
    <ClientLayout>
        <main class="flex-1 space-y-5 p-4 sm:space-y-6 sm:p-6 lg:p-8">
            <div
                class="flex flex-col items-stretch gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h1 class="min-w-0 text-xl font-semibold sm:text-2xl">
                    <span class="block sm:inline">Réservation</span>
                    <span class="break-all sm:break-normal">{{
                        reservation?.reservation_number
                    }}</span>
                </h1>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                    <Link :href="index().url" class="min-w-0">
                        <Button variant="outline" class="w-full">Retour</Button>
                    </Link>
                    <a
                        :href="print(reservation.id).url"
                        target="_blank"
                        rel="noopener"
                        class="min-w-0"
                    >
                        <Button variant="secondary" class="w-full"
                            >Imprimer</Button
                        >
                    </a>
                </div>
            </div>

            <!-- Header ribbon -->
            <div
                class="flex flex-col items-start gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <div class="text-sm text-muted-foreground">Statut</div>
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                            :style="{
                                backgroundColor: getStatusStyle(
                                    reservation.status,
                                ).bg,
                                color: getStatusStyle(reservation.status).text,
                            }"
                        >
                            <span
                                class="size-2 rounded-full"
                                :style="{
                                    backgroundColor: getStatusStyle(
                                        reservation.status,
                                    ).dot,
                                }"
                            />
                            {{ getStatusStyle(reservation.status).label }}
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-muted-foreground">Total</div>
                    <div class="text-xl font-semibold">
                        {{ fmtMoney(reservation.total_amount) }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Client -->
                <div class="rounded-md border">
                    <div class="border-b px-4 py-3 font-medium">Client</div>
                    <div class="space-y-1 p-4">
                        <div class="text-sm">Nom</div>
                        <div class="font-medium">
                            {{ reservation.user?.name || '—' }}
                        </div>
                        <div class="mt-3 text-sm">Adresse e-mail</div>
                        <div class="font-medium">
                            {{ reservation.user?.email || '—' }}
                        </div>
                    </div>
                </div>

                <!-- Car -->
                <div class="rounded-md border">
                    <div class="border-b px-4 py-3 font-medium">Véhicule</div>
                    <div class="space-y-1 p-4">
                        <div class="text-sm">Véhicule</div>
                        <div class="font-medium">
                            {{
                                reservation.car
                                    ? `${reservation.car.year} ${reservation.car.make} ${reservation.car.model}`
                                    : '—'
                            }}
                        </div>
                        <div class="mt-3 text-sm">Immatriculation</div>
                        <div class="font-medium">
                            {{ reservation.car?.license_plate || '—' }}
                        </div>
                    </div>
                </div>

                <!-- Reservation Details -->
                <div class="rounded-md border md:col-span-2">
                    <div class="border-b px-4 py-3 font-medium">
                        Détails de la réservation
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3">
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Date de début
                            </div>
                            <div class="font-medium">
                                {{ fmtDate(reservation.start_date) }}
                                {{ reservation.pickup_time }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Date de fin
                            </div>
                            <div class="font-medium">
                                {{ fmtDate(reservation.end_date) }}
                                {{ reservation.return_time }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Durée
                            </div>
                            <div class="font-medium">
                                {{ reservation.total_days }} jour(s)
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Lieu de prise en charge
                            </div>
                            <div class="font-medium">
                                {{ reservation.pickup_location || '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                Lieu de retour
                            </div>
                            <div class="font-medium">
                                {{ reservation.return_location || '—' }}
                            </div>
                        </div>
                        <div v-if="reservation.status === 'cancelled'">
                            <div class="text-sm text-muted-foreground">
                                Annulée le
                            </div>
                            <div class="font-medium">
                                {{ fmtDateTime(reservation.cancelled_at) }}
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                Motif
                            </div>
                            <div class="font-medium">
                                {{ reservation.cancellation_reason || '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Amounts -->
                <div class="rounded-md border">
                    <div class="border-b px-4 py-3 font-medium">Montants</div>
                    <div class="space-y-2 p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm">Tarif journalier</div>
                            <div class="font-medium">
                                {{ fmtMoney(reservation.daily_rate) }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-sm">Sous-total</div>
                            <div class="font-medium">
                                {{ fmtMoney(reservation.subtotal) }}
                            </div>
                        </div>
                        <div
                            v-if="Number(reservation.tax_amount) > 0"
                            class="flex items-center justify-between"
                        >
                            <div class="text-sm">Taxe</div>
                            <div class="font-medium">
                                {{ fmtMoney(reservation.tax_amount) }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-sm">Remise</div>
                            <div class="font-medium">
                                -{{ fmtMoney(reservation.discount_amount) }}
                            </div>
                        </div>
                        <div
                            v-if="
                                Number(reservation.security_deposit_amount) > 0
                            "
                            class="flex items-center justify-between rounded bg-amber-50 px-2 py-2 text-amber-800"
                        >
                            <div class="text-sm">Caution remboursable</div>
                            <div class="font-medium">
                                {{
                                    fmtMoney(
                                        reservation.security_deposit_amount,
                                    )
                                }}
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between border-t pt-2"
                        >
                            <div class="text-sm">Total location</div>
                            <div class="text-lg font-semibold">
                                {{ fmtMoney(reservation.total_amount) }}
                            </div>
                        </div>
                        <div
                            v-if="
                                Number(reservation.security_deposit_amount) > 0
                            "
                            class="flex items-center justify-between text-sm"
                        >
                            <div>Montant avec caution</div>
                            <div class="font-semibold">
                                {{
                                    fmtMoney(
                                        Number(reservation.total_amount) +
                                            Number(
                                                reservation.security_deposit_amount,
                                            ),
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payments -->
                <div
                    v-if="Number(reservation.required_advance_amount) > 0"
                    class="rounded-md border border-amber-200 md:col-span-2"
                >
                    <div
                        class="border-b border-amber-200 bg-amber-50 px-4 py-3 font-medium text-amber-900"
                    >
                        Avance requise pour confirmer la réservation
                    </div>
                    <div class="space-y-4 p-4">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    {{ reservation.advance_percentage }}% du
                                    total de location
                                </p>
                                <p class="text-xl font-bold">
                                    {{
                                        fmtMoney(
                                            reservation.required_advance_amount,
                                        )
                                    }}
                                </p>
                            </div>
                            <span
                                v-if="advancePayment"
                                class="rounded-full px-3 py-1 text-sm"
                                :class="
                                    advancePayment.status === 'completed'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-yellow-100 text-yellow-700'
                                "
                                >{{
                                    advancePayment.status === 'completed'
                                        ? 'Paiement approuvé'
                                        : 'Preuve en vérification'
                                }}</span
                            >
                        </div>
                        <form
                            v-if="canSubmitProof"
                            class="grid gap-4 md:grid-cols-2"
                            @submit.prevent="submitProof"
                        >
                            <div class="md:col-span-2">
                                <p class="text-sm">
                                    Téléversez une preuve lisible du paiement
                                    Algérie Poste. L'agence vérifiera le montant
                                    et les détails avant d'approuver la
                                    réservation.
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <FileUpload
                                    v-model="proofFolders"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    :required="true"
                                    :allowed-file-types="[
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                        'application/pdf',
                                    ]"
                                    collection="proof"
                                /><span
                                    v-if="proofForm.errors.proof"
                                    class="text-xs text-destructive"
                                    >{{ proofForm.errors.proof }}</span
                                >
                            </div>
                            <label class="grid gap-1 text-sm font-medium"
                                >Référence de transaction (facultatif)<input
                                    v-model="proofForm.transaction_id"
                                    class="h-9 rounded-md border bg-background px-3"
                                    maxlength="255"
                            /></label>
                            <label class="grid gap-1 text-sm font-medium"
                                >Notes (facultatif)<input
                                    v-model="proofForm.notes"
                                    class="h-9 rounded-md border bg-background px-3"
                                    maxlength="2000"
                            /></label>
                            <div class="md:col-span-2">
                                <Button
                                    type="submit"
                                    :disabled="
                                        proofForm.processing ||
                                        proofFolders.length !== 1
                                    "
                                    >Envoyer la preuve de paiement</Button
                                >
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Payments -->
                <div class="rounded-md border md:col-span-2">
                    <div class="border-b px-4 py-3 font-medium">Paiements</div>
                    <div class="overflow-x-auto p-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        #
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Montant
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Mode
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Statut
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Traité le
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="p in reservation.payments || []"
                                    :key="p.id"
                                >
                                    <td class="px-4 py-2">
                                        {{ p.payment_number }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ fmtMoney(p.amount) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ p.payment_method }}
                                    </td>
                                    <td class="px-4 py-2">{{ p.status }}</td>
                                    <td class="px-4 py-2">
                                        {{ fmtDateTime(p.processed_at) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !reservation.payments ||
                                        reservation.payments.length === 0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="px-4 py-4 text-center text-gray-500"
                                    >
                                        Aucun paiement enregistré.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </ClientLayout>
</template>
