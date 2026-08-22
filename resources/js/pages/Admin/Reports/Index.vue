<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({
    reportType: { type: String, required: true },
    reportTypes: { type: Object, required: true },
    rows: { type: Array, required: true },
    filters: { type: Object, required: true },
});
const type = ref(props.reportType),
    dateFrom = ref((props.filters as any).date_from ?? ''),
    dateTo = ref((props.filters as any).date_to ?? ''),
    search = ref((props.filters as any).search ?? '');
const columns = computed(() =>
    props.rows.length ? Object.keys(props.rows[0] as object) : [],
);
function params() {
    return {
        type: type.value,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        search: search.value || undefined,
    };
}
function apply() {
    router.get('/admin/reports', params(), {
        preserveState: true,
        replace: true,
    });
}
function exportTo(format: string) {
    const q = new URLSearchParams(
        Object.entries(params()).filter(([, v]) => v !== undefined) as [
            string,
            string,
        ][],
    );
    window.location.href = `/admin/reports/export/${format}?${q}`;
}
</script>
<template>
    <Head title="Rapports" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header>
                    <h1 class="text-2xl font-semibold">Rapports de gestion</h1>
                    <p class="text-muted-foreground">
                        Données opérationnelles filtrables et exportables.
                    </p>
                </header>
                <section
                    class="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-5"
                >
                    <select
                        v-model="type"
                        class="h-9 rounded-md border bg-background px-3"
                        @change="apply"
                    >
                        <option
                            v-for="(label, key) in reportTypes"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option></select
                    ><Input v-model="dateFrom" type="date" /><Input
                        v-model="dateTo"
                        type="date"
                    /><Input
                        v-model="search"
                        placeholder="Rechercher"
                        @keyup.enter="apply"
                    /><Button @click="apply">Filtrer</Button>
                    <div class="flex gap-2 md:col-span-5">
                        <Button variant="outline" @click="exportTo('csv')"
                            >Exporter CSV</Button
                        ><Button variant="outline" @click="exportTo('pdf')"
                            >Exporter PDF</Button
                        ><span class="self-center text-sm text-muted-foreground"
                            >{{ rows.length }} résultat(s)</span
                        >
                    </div>
                </section>
                <section class="overflow-x-auto rounded-xl border bg-card">
                    <table v-if="rows.length" class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th v-for="c in columns" :key="c" class="p-3">
                                    {{ c }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, i) in rows"
                                :key="i"
                                class="border-b"
                            >
                                <td v-for="c in columns" :key="c" class="p-3">
                                    {{ row[c] ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="p-10 text-center text-muted-foreground">
                        Aucune donnée pour ces filtres.
                    </p>
                </section>
            </div>
        </main></AdminLayout
    >
</template>
