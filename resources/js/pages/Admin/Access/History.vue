<script setup lang="ts">
import AccessTabs from '@/components/access/AccessTabs.vue';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref } from 'vue';
type Entry = {
    id: number;
    event: string;
    related_type: string;
    related_id: number;
    related?: { name?: string } | null;
    old_values: unknown;
    new_values: unknown;
    occurred_at: string;
    user?: { name: string } | null;
};
const props = defineProps<{
    history: {
        data: Entry[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string };
}>();
const search = ref(props.filters.search ?? '');
function apply() {
    router.get(
        '/admin/settings/access/history',
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
const fmt = (v: unknown) => (v ? JSON.stringify(v, null, 2) : '—');
</script>
<template>
    <AdminLayout
        ><Head title="Historique des accès" />
        <main class="p-4 sm:p-6 lg:p-8">
            <AccessTabs
                ><form class="relative max-w-lg" @submit.prevent="apply">
                    <Search
                        class="absolute top-3 left-3 size-4 text-muted-foreground"
                    /><Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Rechercher une action…"
                    />
                </form>
                <div
                    v-if="!history.data.length"
                    class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                >
                    Aucune modification d’accès enregistrée.
                </div>
                <div v-else class="overflow-x-auto rounded-xl border bg-card">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Acteur</th>
                                <th class="p-3">Action</th>
                                <th class="p-3">Élément affecté</th>
                                <th class="p-3">Ancienne valeur</th>
                                <th class="p-3">Nouvelle valeur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="e in history.data" :key="e.id">
                                <td class="p-3 whitespace-nowrap">
                                    {{
                                        new Date(e.occurred_at).toLocaleString(
                                            'fr-DZ',
                                        )
                                    }}
                                </td>
                                <td class="p-3">
                                    {{ e.user?.name || 'Système' }}
                                </td>
                                <td class="p-3 font-medium">{{ e.event }}</td>
                                <td class="p-3">
                                    {{ e.related_type.split('\\').pop() }} #{{
                                        e.related_id
                                    }}
                                </td>
                                <td class="p-3">
                                    <pre
                                        class="max-w-xs text-xs whitespace-pre-wrap"
                                        >{{ fmt(e.old_values) }}</pre
                                    >
                                </td>
                                <td class="p-3">
                                    <pre
                                        class="max-w-xs text-xs whitespace-pre-wrap"
                                        >{{ fmt(e.new_values) }}</pre
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div></AccessTabs
            >
        </main></AdminLayout
    >
</template>
