<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, show } from '@/routes/admin/support';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    tickets: {
        data: Array<{
            id: number;
            subject: string;
            message: string;
            status: string;
            user?: { id: number; name: string; email: string };
            guest_name?: string;
            guest_email?: string;
            created_at: string;
            updated_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
        type?: 'customer' | 'guest';
    };
    statuses: Record<string, { label: string; color: string }>;
    statusCounts: {
        customer: Record<string, number>;
        guest: Record<string, number>;
    };
}>();

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const ticketType = ref<typeof props.filters.type>(
    props.filters?.type || 'customer',
);

// Generate status colors based on the colors from the backend
const statusColors = computed(() => {
    const colors: Record<string, { bg: string; text: string; dot: string }> =
        {};
    for (const [status, data] of Object.entries(props.statuses || {})) {
        const hex = (data as any).color?.replace('#', '') || '6B7280';
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        colors[status] = {
            bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
            text: (data as any).color,
            dot: (data as any).color,
        };
    }
    return colors;
});

const getStatusColor = (status: string) => {
    return (
        statusColors.value[status] || {
            bg: 'rgba(107, 114, 128, 0.1)',
            text: '#6B7280',
            dot: '#6B7280',
        }
    );
};

const getStatusCount = (
    type: 'customer' | 'guest' | undefined,
    status: string,
): number => {
    if (!type) return 0;
    return props.statusCounts?.[type]?.[status] || 0;
};

const getTotalCount = (type: 'customer' | 'guest' | undefined): number => {
    if (!type) return 0;
    return props.statusCounts?.[type]?.all || 0;
};

const doSearch = () => {
    router.get(
        index().url,
        {
            search: search.value,
            status: statusFilter.value === 'all' ? null : statusFilter.value,
            type: ticketType.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

// Watch for changes in search and ticket type to trigger search
watch(search, (newVal, oldVal) => {
    if (newVal === '' && oldVal !== '') doSearch();
});

watch(ticketType, () => {
    doSearch();
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

function goToTicket(id: number) {
    router.visit(show(id).url);
}
</script>

<template>
    <Head title="Support" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Tickets d'assistance</h1>
            </div>

            <!-- Ticket Type Toggle -->
            <div class="flex rounded-lg bg-muted p-1">
                <label class="flex-1">
                    <input
                        type="radio"
                        v-model="ticketType"
                        value="customer"
                        class="peer hidden"
                        @change="doSearch"
                    />
                    <div
                        class="flex cursor-pointer flex-col items-center justify-center rounded-md p-3 transition-colors"
                        :class="{
                            'border border-gray-200 bg-white shadow-sm':
                                ticketType === 'customer',
                            'hover:bg-gray-50': ticketType !== 'customer',
                        }"
                    >
                        <span class="font-medium">Tickets des clients</span>
                        <span class="text-sm text-muted-foreground">
                            {{ getTotalCount('customer') }} total
                        </span>
                    </div>
                </label>
                <label class="flex-1">
                    <input
                        type="radio"
                        v-model="ticketType"
                        value="guest"
                        class="peer hidden"
                        @change="doSearch"
                    />
                    <div
                        class="flex cursor-pointer flex-col items-center justify-center rounded-md p-3 transition-colors"
                        :class="{
                            'border border-gray-200 bg-white shadow-sm':
                                ticketType === 'guest',
                            'hover:bg-gray-50': ticketType !== 'guest',
                        }"
                    >
                        <span class="font-medium">Tickets anonymes</span>
                        <span class="text-sm text-muted-foreground">
                            {{ getTotalCount('guest') }} total
                        </span>
                    </div>
                </label>
            </div>

            <!-- Search and Filter -->
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Input
                        v-model="search"
                        :placeholder="`Rechercher dans les tickets ${ticketType}…`"
                        class="max-w-md"
                        @keyup.enter="doSearch"
                    />
                    <Button @click="doSearch">Chercher</Button>
                </div>

                <!-- Status Filter -->
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center">
                        <input
                            type="radio"
                            class="hidden"
                            v-model="statusFilter"
                            value="all"
                            @change="doSearch"
                        />
                        <span
                            class="cursor-pointer rounded-full px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'bg-primary text-primary-foreground':
                                    statusFilter === 'all',
                                'bg-muted text-muted-foreground hover:bg-muted/80':
                                    statusFilter !== 'all',
                            }"
                        >
                            Toute ({{ getTotalCount(ticketType) }})
                        </span>
                    </label>

                    <template v-for="(status, key) in statuses" :key="key">
                        <label class="inline-flex items-center">
                            <input
                                type="radio"
                                class="hidden"
                                v-model="statusFilter"
                                :value="key"
                                @change="doSearch"
                            />
                            <span
                                class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        statusFilter === key,
                                    'bg-muted text-muted-foreground hover:bg-muted/80':
                                        statusFilter !== key,
                                }"
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :style="{ backgroundColor: status.color }"
                                />
                                {{ status.label }} ({{
                                    getStatusCount(ticketType, key)
                                }})
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Ticket #
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    ticketType === 'customer'
                                        ? 'Customer'
                                        : 'Guest'
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Sujet
                            </th>
                            <th
                                v-if="ticketType === 'customer'"
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Statut
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Date
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="ticket in props.tickets.data"
                            :key="ticket.id"
                            class="cursor-pointer hover:bg-gray-50"
                            @click="goToTicket(ticket.id)"
                        >
                            <td class="px-4 py-3 text-sm text-gray-900">
                                #{{ ticket.id }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        ticketType === 'customer'
                                            ? ticket.user?.name
                                            : ticket.guest_name
                                    }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{
                                        ticketType === 'customer'
                                            ? ticket.user?.email
                                            : ticket.guest_email
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium">
                                    {{ ticket.subject }}
                                </div>
                                <div class="line-clamp-1 text-xs text-gray-500">
                                    {{ ticket.message }}
                                </div>
                            </td>
                            <td
                                v-if="ticketType === 'customer'"
                                class="px-4 py-3"
                            >
                                <span
                                    class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :style="{
                                        backgroundColor: getStatusColor(
                                            ticket.status,
                                        ).bg,
                                        color: getStatusColor(ticket.status)
                                            .text,
                                    }"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :style="{
                                            backgroundColor: getStatusColor(
                                                ticket.status,
                                            ).dot,
                                        }"
                                    />
                                    {{
                                        statuses[ticket.status]?.label ||
                                        ticket.status
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-4 py-3 text-sm whitespace-nowrap text-gray-500"
                            >
                                {{ formatDate(ticket.created_at) }}
                            </td>
                        </tr>
                        <tr v-if="props.tickets.data.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                Aucun ticket trouvé.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav
                v-if="props.tickets.links?.length > 3"
                class="flex justify-center"
            >
                <div class="flex gap-1">
                    <template v-for="(link, i) in props.tickets.links" :key="i">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-md px-3 py-1 text-sm"
                            :class="{
                                'bg-gray-900 text-white': link.active,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200':
                                    !link.active,
                            }"
                            v-html="link.label"
                        ></a>
                        <span
                            v-else
                            class="px-3 py-1 text-gray-400"
                            v-html="link.label"
                        ></span>
                    </template>
                </div>
            </nav>
        </main>
    </AdminLayout>
</template>
