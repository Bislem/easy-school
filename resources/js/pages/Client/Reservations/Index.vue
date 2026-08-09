<script setup lang="ts">
import ClientLayout from '@/layouts/ClientLayout.vue';
import { show } from '@/routes/client/reservations';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    reservations: {
        data: Array<{
            id: number;
            reservation_number: string;
            car: {
                id: number;
                make: string;
                model: string;
                year: number;
                license_plate: string;
            } | null;
            start_date: string;
            end_date: string;
            total_days: number;
            total_amount: number | string;
            status: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    currency: { symbol: string; code: string };
}>();

const navigateToReservation = (id: number) => {
    router.visit(show(id).url);
};

const formatDate = (date: string) => {
    const [year, month, day] = date.slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
};

const formatStatus = (status: string) => {
    const labels: Record<string, string> = {
        pending: 'En attente',
        confirmed: 'Confirmée',
        active: 'En cours',
        completed: 'Terminée',
        cancelled: 'Annulée',
        no_show: 'Client absent',
    };

    return labels[status] ?? status;
};
</script>

<template>
    <Head title="Réservations" />
    <ClientLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Mes réservations</h1>
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="res in props.reservations.data"
                            :key="res.id"
                            @click="navigateToReservation(res.id)"
                            class="cursor-pointer transition-colors hover:bg-gray-50"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ res.reservation_number }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        res.car
                                            ? `${res.car.year} ${res.car.make} ${res.car.model}`
                                            : '—'
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ res.car?.license_plate }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ formatDate(res.start_date) }}
                                    →
                                    {{ formatDate(res.end_date) }}
                                </div>
                                <!-- duration in days-->
                                <div class="text-xs text-muted-foreground">
                                    {{ res.total_days }} jour{{
                                        res.total_days > 1 ? 's' : ''
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ props.currency.symbol }}
                                {{ Number(res.total_amount).toFixed(2) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ formatStatus(res.status) }}
                            </td>
                        </tr>
                        <tr v-if="props.reservations.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                Aucune réservation trouvée.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.reservations.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.reservations.links"
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
        </main>
    </ClientLayout>
</template>
