<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { appPrompt } from '@/composables/useAppDialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { activate, index, suspend } from '@/routes/admin/clients';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    client: {
        id: number;
        name: string;
        email: string;
        phone?: string | null;
        is_active: boolean;
        approval_status: string;
        birth_date?: string | null;
        driving_license_number?: string | null;
        driving_license_delivered_at?: string | null;
        driving_license_authority?: string | null;
        driving_license_url?: string | null;
        rejection_reason?: string | null;
        created_at?: string;
    };
    stats: {
        total_reservations: number;
        total_payments: number;
        total_spent: number;
    };
    reservations: {
        data: Array<{
            id: number;
            reservation_number: string;
            start_date: string;
            end_date: string;
            total_days?: number;
            total_amount: number | string;
            status: string;
            car?: {
                year: number;
                make: string;
                model: string;
                license_plate: string;
            } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    payments: {
        data: Array<{
            id: number;
            payment_number: string;
            amount: number | string;
            currency?: string;
            payment_method: string;
            status: string;
            processed_at?: string | null;
            reservation?: { id: number; reservation_number: string } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    currency: { symbol: string; code: string };
    drivers: Array<{
        id: number;
        full_name: string;
        phone: string;
        email?: string | null;
        driving_license_url: string;
        approval_status: 'pending' | 'approved' | 'rejected';
        rejection_reason?: string | null;
    }>;
}>();

const showSuspendDialog = ref(false);
const processingSuspend = ref(false);

const showActivateDialog = ref(false);
const processingActivate = ref(false);

function fmtMoney(n?: number | string) {
    const v = Number(n ?? 0);
    return `${props.currency.symbol}${v.toFixed(2)}`;
}

function formatReservationStatus(status: string) {
    const labels: Record<string, string> = {
        pending: 'En attente',
        confirmed: 'Confirmée',
        active: 'En cours',
        completed: 'Terminée',
        cancelled: 'Annulée',
        no_show: 'Client absent',
    };

    return labels[status] || status;
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
    };

    return labels[method] || method;
}

function suspendClient() {
    processingSuspend.value = true;
    router.patch(
        suspend(props.client.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingSuspend.value = false;
            },
            onSuccess: () => {
                showSuspendDialog.value = false;
            },
        },
    );
}

function approveDriver(driverId: number) {
    router.patch(`/admin/clients/${props.client.id}/drivers/${driverId}/approve`, {}, { preserveScroll: true });
}

async function rejectDriver(driverId: number) {
    const reason = await appPrompt('Vous pouvez indiquer le motif du refus.', { title: 'Refuser le conducteur', tone: 'warning', inputLabel: 'Motif (facultatif)', confirmText: 'Refuser' }) ?? undefined;
    if (reason !== undefined) {
        router.patch(`/admin/clients/${props.client.id}/drivers/${driverId}/reject`, { reason }, { preserveScroll: true });
    }
}

function activateClient() {
    processingActivate.value = true;
    router.patch(
        activate(props.client.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingActivate.value = false;
            },
            onSuccess: () => {
                showActivateDialog.value = false;
            },
        },
    );
}

async function rejectClient() {
    const reason = await appPrompt('Refuser cette demande de compte ? Indiquez éventuellement le motif.', { title: 'Refuser le compte', tone: 'warning', inputLabel: 'Motif (facultatif)', confirmText: 'Refuser' });
    if (reason !== null) {
        router.patch(`/admin/clients/${props.client.id}/reject`, { reason }, { preserveScroll: true });
    }
}

function fmtDate(value?: string | null) {
    return value ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('fr-FR') : '—';
}

const statusStyle = computed(() => {
    const status = props.client.approval_status;
    const colors: Record<string, string> = { approved: '#10B981', pending: '#F59E0B', rejected: '#DC2626', suspended: '#6B7280' };
    const labels: Record<string, string> = { approved: 'Approuvé', pending: 'En attente', rejected: 'Refusé', suspended: 'Suspendu' };
    const hex = colors[status] || '#6B7280';
    const toRgb = (h: string) => [
        parseInt(h.slice(1, 3), 16),
        parseInt(h.slice(3, 5), 16),
        parseInt(h.slice(5, 7), 16),
    ];
    const [r, g, b] = toRgb(hex);
    return {
        bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
        dot: hex,
        text: hex,
        label: labels[status] || status,
    };
});
</script>

<template>
    <Head :title="`Client ${client.name}`" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <div>
                        <h1 class="text-2xl font-semibold">
                            {{ client.name }}
                        </h1>
                        <div class="text-sm text-muted-foreground">
                            {{ client.email }}
                        </div>
                        <div class="text-sm text-muted-foreground">{{ client.phone || '—' }}</div>
                    </div>
                    <span
                        class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                        :style="{
                            backgroundColor: statusStyle.bg,
                            color: statusStyle.text,
                        }"
                    >
                        <span
                            class="size-2 rounded-full"
                            :style="{ backgroundColor: statusStyle.dot }"
                        />
                        {{ statusStyle.label }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="'/admin/clients/' + client.id + '/edit'"><Button variant="outline">Modifier</Button></Link>
                    <Button
                        v-if="client.approval_status === 'approved'"
                        variant="destructive"
                        @click="showSuspendDialog = true"
                        >Suspendre l'utilisateur</Button
                    >
                    <Button v-else @click="showActivateDialog = true"
                        >Approuver le client</Button
                    >
                    <Button v-if="client.approval_status === 'pending'" variant="destructive" @click="rejectClient">Refuser</Button>
                    <Link :href="index()">
                        <Button variant="outline">Retour</Button>
                    </Link>
                </div>
            </div>

            <section class="rounded-md border" :class="client.approval_status === 'pending' ? 'border-amber-300 ring-2 ring-amber-100' : ''">
                <div class="border-b px-4 py-3 font-medium">Dossier du permis de conduire</div>
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><p class="text-sm text-muted-foreground">Date de naissance</p><p class="font-medium">{{ fmtDate(client.birth_date) }}</p></div>
                    <div><p class="text-sm text-muted-foreground">Numéro du permis</p><p class="font-medium">{{ client.driving_license_number || '—' }}</p></div>
                    <div><p class="text-sm text-muted-foreground">Délivré le</p><p class="font-medium">{{ fmtDate(client.driving_license_delivered_at) }}</p></div>
                    <div><p class="text-sm text-muted-foreground">Autorité de délivrance</p><p class="font-medium">{{ client.driving_license_authority || '—' }}</p></div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <a v-if="client.driving_license_url" :href="client.driving_license_url" target="_blank" rel="noopener" class="inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">Ouvrir la copie du permis</a>
                        <p v-else class="text-sm text-destructive">Aucune copie du permis disponible.</p>
                    </div>
                    <div v-if="client.rejection_reason" class="rounded bg-red-50 p-3 text-sm text-red-700 sm:col-span-2 lg:col-span-4"><strong>Motif du refus :</strong> {{ client.rejection_reason }}</div>
                </div>
            </section>

            <section class="rounded-md border">
                <div class="border-b px-4 py-3 font-medium">Conducteurs supplémentaires ({{ drivers.length }}/3)</div>
                <div v-if="drivers.length" class="divide-y">
                    <div v-for="driver in drivers" :key="driver.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <strong>{{ driver.full_name }}</strong>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="driver.approval_status === 'approved' ? 'bg-green-100 text-green-800' : driver.approval_status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'">{{ driver.approval_status }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ driver.phone }}<span v-if="driver.email"> · {{ driver.email }}</span></p>
                            <a :href="driver.driving_license_url" target="_blank" class="text-sm text-primary underline">Voir le permis</a>
                            <p v-if="driver.rejection_reason" class="mt-1 text-sm text-red-600">{{ driver.rejection_reason }}</p>
                        </div>
                        <div v-if="driver.approval_status !== 'approved'" class="flex gap-2">
                            <Button size="sm" @click="approveDriver(driver.id)">Approuver</Button>
                            <Button size="sm" variant="destructive" @click="rejectDriver(driver.id)">Refuser</Button>
                        </div>
                    </div>
                </div>
                <p v-else class="p-4 text-sm text-muted-foreground">Aucun conducteur supplémentaire.</p>
            </section>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">
                        Total dépensé
                    </div>
                    <div class="text-xl font-semibold">
                        {{ fmtMoney(stats.total_spent) }}
                    </div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">
                        Réservations
                    </div>
                    <div class="text-xl font-semibold">
                        {{ stats.total_reservations }}
                    </div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">Paiements</div>
                    <div class="text-xl font-semibold">
                        {{ stats.total_payments }}
                    </div>
                </div>
            </div>

            <!-- Reservations -->
            <div class="rounded-md border">
                <div class="border-b px-4 py-3 font-medium">
                    Réservations passées
                </div>
                <div class="overflow-x-auto">
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
                                    Véhicule
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Dates
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Statut
                                </th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="r in reservations.data" :key="r.id">
                                <td class="px-4 py-3">
                                    {{ r.reservation_number }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{
                                            r.car
                                                ? `${r.car.year} ${r.car.make} ${r.car.model}`
                                                : '—'
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ r.car?.license_plate }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{
                                            new Date(
                                                r.start_date,
                                            ).toLocaleDateString()
                                        }}
                                        →
                                        {{
                                            new Date(
                                                r.end_date,
                                            ).toLocaleDateString()
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ fmtMoney(r.total_amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatReservationStatus(r.status) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`/admin/reservations/${r.id}`">
                                        <Button variant="outline" size="sm"
                                            >Voir</Button
                                        >
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="reservations.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-6 text-center text-gray-500"
                                >
                                    Aucune réservation.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav
                    v-if="reservations.links?.length"
                    class="flex gap-2 px-4 py-3"
                >
                    <Link
                        v-for="(link, i) in reservations.links"
                        :key="i"
                        :href="link.url || ''"
                        :class="[
                            'rounded px-3 py-1 text-sm',
                            link.active
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-700',
                            !link.url && 'pointer-events-none opacity-50',
                        ]"
                    >
                        <span v-html="link.label" />
                    </Link>
                </nav>
            </div>

            <!-- Payments -->
            <div class="rounded-md border">
                <div class="border-b px-4 py-3 font-medium">Paiements</div>
                <div class="overflow-x-auto">
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="p in payments.data" :key="p.id">
                                <td class="px-4 py-3">
                                    {{ p.payment_number }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{
                                            p.reservation?.reservation_number ||
                                            '—'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ fmtMoney(p.amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatPaymentMethod(p.payment_method) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatPaymentStatus(p.status) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        p.processed_at
                                            ? new Date(
                                                  p.processed_at,
                                              ).toLocaleString()
                                            : '—'
                                    }}
                                </td>
                            </tr>
                            <tr v-if="payments.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-6 text-center text-gray-500"
                                >
                                    Aucun paiement.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav v-if="payments.links?.length" class="flex gap-2 px-4 py-3">
                    <Link
                        v-for="(link, i) in payments.links"
                        :key="i"
                        :href="link.url || ''"
                        :class="[
                            'rounded px-3 py-1 text-sm',
                            link.active
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-700',
                            !link.url && 'pointer-events-none opacity-50',
                        ]"
                    >
                        <span v-html="link.label" />
                    </Link>
                </nav>
            </div>
        </main>

        <!-- Suspend Confirmation Dialog -->
        <Dialog v-model:open="showSuspendDialog">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertCircle class="h-5 w-5 text-destructive" />
                        Suspendre l'utilisateur
                    </DialogTitle>
                    <DialogDescription>
                        Voulez-vous vraiment suspendre cet utilisateur ? Il ne
                        pourra plus se connecter tant qu'il ne sera pas
                        réactivé.
                    </DialogDescription>
                </DialogHeader>
                <Alert variant="destructive" class="mt-4">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        Cette action peut être annulée plus tard par un
                        administrateur, mais l'utilisateur sera bloqué
                        immédiatement.
                    </AlertDescription>
                </Alert>
                <DialogFooter class="mt-4">
                    <DialogClose as-child>
                        <Button variant="outline">Annuler</Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="processingSuspend"
                        @click="suspendClient"
                    >
                        {{
                            processingSuspend
                                ? 'Suspension...'
                                : "Suspendre l'utilisateur"
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Activate Confirmation Dialog -->
        <Dialog v-model:open="showActivateDialog">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertCircle class="h-5 w-5 text-destructive" />
                        Activer l'utilisateur
                    </DialogTitle>
                    <DialogDescription>
                        Voulez-vous vraiment activer cet utilisateur ? Il pourra
                        se connecter à nouveau.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <DialogClose as-child>
                        <Button variant="outline">Annuler</Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="processingActivate"
                        @click="activateClient"
                    >
                        {{
                            processingActivate
                                ? 'Activation...'
                                : "Activer l'utilisateur"
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
