<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { appConfirm, appPrompt } from '@/composables/useAppDialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { edit, index, print } from '@/routes/admin/reservations';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    reservation: any;
    paidAmount: number;
    isPaid: boolean;
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
    return d ? new Date(d).toLocaleDateString() : '—';
}
function fmtMoney(n?: number | string) {
    const v = Number(n ?? 0);
    return `${props.currency.symbol}${v.toFixed(2)}`;
}

function formatPaymentStatus(status: string) {
    const labels: Record<string, string> = {
        pending: 'En attente',
        completed: 'Payé',
        failed: 'Refusé',
        cancelled: 'Annulé',
        refunded: 'Remboursé',
        partially_refunded: 'Partiellement remboursé',
    };

    return labels[status] || status;
}

function formatPaymentMethod(method: string) {
    const labels: Record<string, string> = {
        credit_card: 'Carte de crédit',
        debit_card: 'Carte de débit',
        paypal: 'PayPal',
        stripe: 'Stripe',
        bank_transfer: 'Virement bancaire',
        cash: 'Espèces',
        algeria_post: 'Algérie Poste',
    };

    return labels[method] || method;
}

async function approveReservation() {
    if (
        Number(props.reservation.required_advance_amount) > 0 &&
        props.paidAmount < Number(props.reservation.required_advance_amount)
    ) {
        focusPaymentReview();
        return;
    }
    if (
        await appConfirm(
            `Approuver la réservation ${props.reservation.reservation_number} ?`,
            { title: 'Approuver la réservation', confirmText: 'Approuver' },
        )
    ) {
        router.patch(
            `/admin/reservations/${props.reservation.id}/approve`,
            {},
            { preserveScroll: true },
        );
    }
}

const paymentsSection = ref<HTMLElement | null>(null);
const highlightedPaymentId = ref<number | null>(null);
const paymentReviewMessage = ref('');
const pendingAdvancePayment = computed(() =>
    (props.reservation.payments || []).find(
        (payment: any) =>
            payment.payment_type === 'advance' && payment.status === 'pending',
    ),
);

function focusPaymentReview() {
    highlightedPaymentId.value = pendingAdvancePayment.value?.id ?? null;
    paymentReviewMessage.value = pendingAdvancePayment.value
        ? "Vérifiez et approuvez ou refusez cette preuve avant d'approuver la réservation."
        : "L'avance requise n'est pas encore approuvée. Aucune preuve en attente n'a été trouvée.";
    nextTick(() =>
        paymentsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }),
    );
    window.setTimeout(() => {
        highlightedPaymentId.value = null;
    }, 8000);
}

async function approvePayment(payment: any) {
    if (await appConfirm(`Approuver le paiement ${payment.payment_number} ?`, { title: 'Approuver le paiement', confirmText: 'Approuver' })) {
        router.patch(`/admin/payments/${payment.id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => { highlightedPaymentId.value = null; paymentReviewMessage.value = ''; },
        });
    }
}

async function refusePayment(payment: any) {
    const notes = await appPrompt('Refuser cette preuve de paiement ? Ajoutez éventuellement un motif pour le client.', { title: 'Refuser le paiement', tone: 'warning', inputLabel: 'Motif (facultatif)', confirmText: 'Refuser' });
    if (notes !== null) {
        router.patch(`/admin/payments/${payment.id}/disapprove`, { notes }, {
            preserveScroll: true,
            onSuccess: () => { highlightedPaymentId.value = null; paymentReviewMessage.value = ''; },
        });
    }
}

async function rejectReservation() {
    const reason = await appPrompt(
        'Rejeter cette réservation ? Ajoutez un motif facultatif pour le client :',
        { title: 'Rejeter la réservation', tone: 'warning', inputLabel: 'Motif (facultatif)', confirmText: 'Rejeter' },
    );
    if (reason !== null) {
        router.patch(
            `/admin/reservations/${props.reservation.id}/reject`,
            { reason },
            { preserveScroll: true },
        );
    }
}

async function cancelReservation() {
    const reason = await appPrompt(
        'Annuler cette réservation ? Ajoutez un motif facultatif :',
        { title: 'Annuler la réservation', tone: 'danger', inputLabel: 'Motif (facultatif)', confirmText: 'Annuler la réservation' },
    );
    if (reason !== null) {
        router.patch(
            `/admin/reservations/${props.reservation.id}/cancel`,
            { reason },
            { preserveScroll: true },
        );
    }
}

async function markPaid() {
    if (
        await appConfirm(
            'Enregistrer le solde restant comme paiement en espèces terminé ?',
            { title: 'Marquer comme payé', confirmText: 'Enregistrer le paiement' },
        )
    ) {
        router.post(
            `/admin/reservations/${props.reservation.id}/mark-paid`,
            {},
            { preserveScroll: true },
        );
    }
}

async function changeStatus(
    action: 'start' | 'complete' | 'no-show',
    message: string,
) {
    if (await appConfirm(message, { title: 'Changer le statut', tone: 'warning', confirmText: 'Confirmer' })) {
        router.patch(
            `/admin/reservations/${props.reservation.id}/${action}`,
            {},
            { preserveScroll: true },
        );
    }
}

const fuelDialogOpen = ref(false);
const fuelAction = ref<'start' | 'complete'>('start');
const fuelLevel = ref<number | undefined>(undefined);
const fuelNotes = ref('');
const fuelError = ref('');

function openFuelDialog(action: 'start' | 'complete') {
    fuelAction.value = action;
    fuelLevel.value = undefined;
    fuelNotes.value = '';
    fuelError.value = '';
    fuelDialogOpen.value = true;
}

function submitFuelRecord() {
    if (
        fuelLevel.value === undefined ||
        fuelLevel.value < 0 ||
        fuelLevel.value > 100
    ) {
        fuelError.value = 'Saisissez un niveau de carburant entre 0 et 100 %.';
        return;
    }

    router.patch(
        `/admin/reservations/${props.reservation.id}/${fuelAction.value}`,
        { fuel_level: fuelLevel.value, fuel_notes: fuelNotes.value || null },
        {
            preserveScroll: true,
            onError: (errors) => {
                fuelError.value = String(
                    errors.fuel_level ||
                        errors.fuel_notes ||
                        "Impossible d'enregistrer le relevé de carburant.",
                );
            },
            onSuccess: () => {
                fuelDialogOpen.value = false;
            },
        },
    );
}

function fuelRecordLabel(type: string) {
    return type === 'rental_start' ? 'Début de location' : 'Fin de location';
}
</script>

<template>
    <Head :title="`Réservation ${reservation?.reservation_number || ''}`" />
    <AdminLayout>
        <main class="flex-1 space-y-5 p-4 sm:space-y-6 sm:p-6 lg:p-8">
            <div class="flex flex-col items-stretch gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="min-w-0 text-xl font-semibold sm:text-2xl">
                    <span class="block sm:inline">Réservation</span>
                    <span class="break-all sm:break-normal">{{ reservation?.reservation_number }}</span>
                </h1>
                <div class="grid grid-cols-3 gap-2 sm:flex sm:shrink-0">
                    <Link :href="index().url" class="min-w-0">
                        <Button variant="outline" class="w-full">Retour</Button>
                    </Link>
                    <Link :href="edit(reservation.id).url" class="min-w-0">
                        <Button variant="outline" class="w-full">Modifier</Button>
                    </Link>
                    <a
                        :href="print(reservation.id).url"
                        target="_blank"
                        rel="noopener"
                        class="min-w-0"
                    >
                        <Button variant="secondary" class="w-full">Imprimer</Button>
                    </a>
                </div>
            </div>

            <div
                class="flex flex-col items-stretch gap-2 rounded-md border bg-muted/20 p-3 sm:flex-row sm:flex-wrap sm:items-center"
            >
                <span class="mb-1 text-sm font-medium sm:mr-2 sm:mb-0">Actions</span>
                <template v-if="reservation.status === 'pending'">
                    <Button size="sm" class="w-full sm:w-auto" @click="approveReservation"
                        >Approuver la réservation</Button
                    >
                    <Button
                        v-if="!isPaid"
                        size="sm"
                        class="w-full sm:w-auto"
                        variant="destructive"
                        @click="rejectReservation"
                        >Rejeter la réservation</Button
                    >
                </template>
                <Button
                    v-if="reservation.status !== 'cancelled' && !isPaid"
                    size="sm"
                    class="w-full sm:w-auto"
                    variant="secondary"
                    @click="markPaid"
                >
                    Marquer payée (espèces)
                </Button>
                <template v-if="reservation.status === 'confirmed'">
                    <Button
                        size="sm"
                        class="w-full sm:w-auto"
                        variant="secondary"
                        @click="openFuelDialog('start')"
                    >
                        Démarrer la location
                    </Button>
                    <Button
                        size="sm"
                        class="w-full sm:w-auto"
                        variant="outline"
                        @click="
                            changeStatus(
                                'no-show',
                                'Marquer ce client comme absent ?',
                            )
                        "
                    >
                        Marquer absent
                    </Button>
                </template>
                <Button
                    v-if="reservation.status === 'active'"
                    size="sm"
                    class="w-full sm:w-auto"
                    variant="secondary"
                    @click="openFuelDialog('complete')"
                >
                    Terminer la location
                </Button>
                <Button
                    v-if="
                        !['confirmed', 'cancelled', 'completed'].includes(
                            reservation.status,
                        ) && !isPaid
                    "
                    size="sm"
                    class="w-full sm:w-auto"
                    variant="outline"
                    @click="cancelReservation"
                >
                    Annuler la réservation
                </Button>
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

            <Dialog v-model:open="fuelDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                fuelAction === 'start'
                                    ? 'Démarrer la location'
                                    : 'Terminer la location'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            Enregistrez le niveau du réservoir avant de terminer
                            cette action de location.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4">
                        <div>
                            <Label for="fuel-level"
                                >Niveau du réservoir (%)</Label
                            >
                            <Input
                                id="fuel-level"
                                v-model.number="fuelLevel"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                class="mt-1"
                                autofocus
                            />
                        </div>
                        <div>
                            <Label for="fuel-notes">Notes (facultatif)</Label>
                            <textarea
                                id="fuel-notes"
                                v-model="fuelNotes"
                                rows="3"
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Par exemple : relevé de jauge ou référence du reçu de carburant"
                            />
                        </div>
                        <p v-if="fuelError" class="text-sm text-destructive">
                            {{ fuelError }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="fuelDialogOpen = false"
                            >Annuler</Button
                        >
                        <Button @click="submitFuelRecord">
                            {{
                                fuelAction === 'start'
                                    ? 'Enregistrer et démarrer la location'
                                    : 'Enregistrer et terminer la location'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
                        <div class="mt-3 text-sm">Plaque</div>
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
                                {{ reservation.total_days }} jours
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
                                {{
                                    reservation.cancelled_at
                                        ? new Date(
                                              reservation.cancelled_at,
                                          ).toLocaleString()
                                        : '—'
                                }}
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

                <!-- Fuel Tank Records -->
                <div class="rounded-md border md:col-span-2">
                    <div class="border-b px-4 py-3 font-medium">
                        Relevés du réservoir
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table
                            v-if="reservation.fuel_tank_records?.length"
                            class="min-w-full divide-y divide-gray-200"
                        >
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Événement
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Niveau de carburant
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Enregistré le
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Enregistré par
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        Notes
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="record in reservation.fuel_tank_records"
                                    :key="record.id"
                                >
                                    <td class="px-4 py-2 font-medium">
                                        {{
                                            fuelRecordLabel(record.record_type)
                                        }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ record.fuel_level }}%
                                    </td>
                                    <td class="px-4 py-2">
                                        {{
                                            new Date(
                                                record.recorded_at,
                                            ).toLocaleString()
                                        }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ record.recorded_by?.name || '—' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ record.notes || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="text-sm text-muted-foreground">
                            Aucun relevé de carburant n'a été enregistré pour
                            cette réservation.
                        </p>
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
                        <div v-if="Number(reservation.tax_amount) > 0" class="flex items-center justify-between">
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
                        <div v-if="Number(reservation.security_deposit_amount) > 0" class="flex items-center justify-between rounded bg-amber-50 px-2 py-2 text-amber-800">
                            <div class="text-sm">Caution remboursable</div>
                            <div class="font-medium">{{ fmtMoney(reservation.security_deposit_amount) }}</div>
                        </div>
                        <div
                            class="flex items-center justify-between border-t pt-2"
                        >
                            <div class="text-sm">Total location</div>
                            <div class="text-lg font-semibold">
                                {{ fmtMoney(reservation.total_amount) }}
                            </div>
                        </div>
                        <div v-if="Number(reservation.security_deposit_amount) > 0" class="flex items-center justify-between text-sm">
                            <div>Montant avec caution</div>
                            <div class="font-semibold">{{ fmtMoney(Number(reservation.total_amount) + Number(reservation.security_deposit_amount)) }}</div>
                        </div>
                        <div v-if="Number(reservation.required_advance_amount) > 0" class="mt-2 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                            <div class="flex items-center justify-between"><span>Avance requise ({{ reservation.advance_percentage }}%)</span><strong>{{ fmtMoney(reservation.required_advance_amount) }}</strong></div>
                            <div class="mt-1 flex items-center justify-between"><span>Paiements approuvés</span><strong>{{ fmtMoney(paidAmount) }}</strong></div>
                            <p class="mt-1 font-medium" :class="paidAmount >= Number(reservation.required_advance_amount) ? 'text-green-700' : 'text-amber-700'">{{ paidAmount >= Number(reservation.required_advance_amount) ? 'Avance reçue — la réservation peut être approuvée.' : "L'avance doit être vérifiée avant l'approbation." }}</p>
                        </div>
                    </div>
                </div>

                <!-- Payments -->
                <div
                    ref="paymentsSection"
                    class="rounded-md border transition-all md:col-span-2"
                    :class="paymentReviewMessage ? 'border-amber-400 ring-4 ring-amber-200' : ''"
                >
                    <div class="border-b px-4 py-3 font-medium">Paiements</div>
                    <div v-if="paymentReviewMessage" class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{{ paymentReviewMessage }}</div>
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
                                        Méthode
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
                                    <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="p in reservation.payments || []"
                                    :key="p.id"
                                    class="transition-colors"
                                    :class="highlightedPaymentId === p.id ? 'bg-amber-100 ring-2 ring-inset ring-amber-400' : ''"
                                >
                                    <td class="px-4 py-2">
                                        {{ p.payment_number }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ fmtMoney(p.amount) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <div>{{
                                            formatPaymentMethod(
                                                p.payment_method,
                                            )
                                        }}</div>
                                        <a v-if="p.proof_url" :href="p.proof_url" target="_blank" rel="noopener" class="text-xs font-medium text-blue-600 hover:underline">Voir la preuve</a>
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ formatPaymentStatus(p.status) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{
                                            p.processed_at
                                                ? new Date(
                                                      p.processed_at,
                                                  ).toLocaleString()
                                                : '—'
                                        }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <div v-if="p.status === 'pending'" class="flex justify-end gap-2">
                                            <Button size="sm" @click="approvePayment(p)">Approuver</Button>
                                            <Button size="sm" variant="destructive" @click="refusePayment(p)">Refuser</Button>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !reservation.payments ||
                                        reservation.payments.length === 0
                                    "
                                >
                                    <td
                                        colspan="6"
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
    </AdminLayout>
</template>
