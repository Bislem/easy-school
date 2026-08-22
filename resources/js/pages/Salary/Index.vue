<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
const props = defineProps({
    employee: { type: Object, required: true },
    statements: { type: Array, required: true },
    payments: { type: Array, required: true },
    currency: { type: Object, required: true },
});
const money = (v: any) =>
    `${Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} ${props.currency}`;
</script>
<template>
    <Head title="Ma paie" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <header>
                    <h1 class="text-2xl font-semibold">Ma paie</h1>
                    <p class="text-muted-foreground">
                        {{ employee.name }} · historique personnel
                    </p>
                </header>
                <section class="space-y-3">
                    <article
                        v-for="s in statements.data"
                        :key="s.id"
                        class="rounded-xl border bg-card p-5"
                    >
                        <div class="flex flex-wrap justify-between gap-3">
                            <div>
                                <b>{{ s.reference }}</b>
                                <p class="text-sm text-muted-foreground">
                                    {{ s.period_start }} → {{ s.period_end }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">
                                    Net {{ money(s.net_salary) }}
                                </p>
                                <p class="text-sm">
                                    Payé {{ money(s.amount_paid) }} · Reste
                                    {{ money(s.remaining_amount) }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 border-t pt-3 text-sm">
                            <p v-for="p in s.payments" :key="p.id">
                                {{ p.paid_at }} · {{ p.reference }} ·
                                {{ money(p.amount) }}
                            </p>
                        </div>
                    </article>
                    <p
                        v-if="!statements.data.length"
                        class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                    >
                        Aucun bulletin disponible.
                    </p>
                </section>
            </div>
        </main></AdminLayout
    >
</template>
