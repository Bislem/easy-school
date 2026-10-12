<script setup lang="ts">
import FinancialAccountLedger from '@/components/finance/FinancialAccountLedger.vue';
import {
    translatePublicRoot,
    watchPublicLocale,
} from '@/composables/usePublicLocale';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
const root = ref<HTMLElement | null>(null);
let stopLocaleWatch: (() => void) | undefined;
onMounted(() => {
    stopLocaleWatch = watchPublicLocale(() => root.value);
    nextTick(() => translatePublicRoot(root.value));
});
onBeforeUnmount(() => stopLocaleWatch?.());
defineProps<{ account: any; methods: any[] }>();
</script>
<template>
    <Head :title="`Compte financier — ${account.student.full_name}`" />
    <AdminLayout
        ><div ref="root">
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-6xl space-y-6">
                    <header>
                        <Link
                            href="/admin/school-payments"
                            class="mb-2 flex items-center gap-2 text-sm text-muted-foreground"
                            ><ArrowLeft class="size-4" />Paiements
                            scolaires</Link
                        >
                        <h1 class="text-2xl font-black">
                            {{ account.student.full_name }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ account.academic_year?.name }} ·
                            {{ account.accountable?.level?.name }} ·
                            {{
                                account.accountable?.group?.name ||
                                'Sans groupe'
                            }}
                            <template v-if="account.fee_structure">
                                · {{ account.fee_structure.name }}</template
                            >
                        </p>
                    </header>
                    <FinancialAccountLedger
                        :account="account"
                        :methods="methods"
                        base-url="/admin/school-payments"
                    />
                </div>
            </main></div
    ></AdminLayout>
</template>
