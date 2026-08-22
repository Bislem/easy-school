<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps({
    logs: { type: Object, required: true },
    events: { type: Array, required: true },
    filters: { type: Object, required: true },
});
const event = ref((props.filters as any).event ?? ''),
    from = ref((props.filters as any).date_from ?? ''),
    to = ref((props.filters as any).date_to ?? '');
function apply() {
    router.get(
        '/admin/audit',
        {
            event: event.value || undefined,
            date_from: from.value || undefined,
            date_to: to.value || undefined,
        },
        { replace: true },
    );
}
</script>
<template>
    <Head title="Audit" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-5">
                <h1 class="text-2xl font-semibold">Journal d’audit</h1>
                <section class="flex flex-wrap gap-2 rounded-xl border p-4">
                    <select
                        v-model="event"
                        class="rounded-md border bg-background px-3"
                    >
                        <option value="">Toutes les actions</option>
                        <option v-for="e in events" :key="e" :value="e">
                            {{ e }}
                        </option></select
                    ><Input v-model="from" type="date" /><Input
                        v-model="to"
                        type="date"
                    /><Button @click="apply">Filtrer</Button>
                </section>
                <section class="space-y-2">
                    <article
                        v-for="log in logs.data"
                        :key="log.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex justify-between gap-3">
                            <b>{{ log.event }}</b
                            ><span class="text-xs text-muted-foreground">{{
                                log.occurred_at
                            }}</span>
                        </div>
                        <p class="text-sm">
                            {{ log.user?.name || 'Système' }} ·
                            {{ log.related_type }} #{{ log.related_id }}
                        </p>
                        <details class="mt-2 text-xs">
                            <summary>Modifications</summary>
                            <pre
                                class="mt-2 overflow-auto rounded bg-muted p-3"
                                >{{
                                    JSON.stringify(
                                        {
                                            avant: log.old_values,
                                            après: log.new_values,
                                        },
                                        null,
                                        2,
                                    )
                                }}</pre
                            >
                        </details>
                    </article>
                </section>
            </div>
        </main></AdminLayout
    >
</template>
