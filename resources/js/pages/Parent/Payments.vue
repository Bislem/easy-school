<script setup lang="ts">
import StudentFinancePanel from '@/components/finance/StudentFinancePanel.vue';
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    portal: any;
    finance: { accounts: any[]; totals: Record<string, number> } | null;
}>();
const receiptUrl = (paymentId: number) =>
    `/parent/payments/${paymentId}/receipt`;
</script>
<template>
    <Head title="Paiements" /><ParentLayout :portal="portal"
        ><div>
            <h1 class="text-2xl font-bold sm:text-3xl">Paiements</h1>
            <p class="mt-1 text-sm text-slate-500">
                Échéancier, paiements enregistrés et reçus de l’enfant
                sélectionné.
            </p>
            <div class="mt-6">
                <StudentFinancePanel
                    :finance="finance"
                    :receipt-url="receiptUrl"
                />
            </div></div
    ></ParentLayout>
</template>
