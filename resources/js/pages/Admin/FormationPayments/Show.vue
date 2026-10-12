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
let stop: (() => void) | undefined;
onMounted(() => {
    stop = watchPublicLocale(() => root.value);
    nextTick(() => translatePublicRoot(root.value));
});
onBeforeUnmount(() => stop?.());
defineProps<{ account: any; methods: any[] }>();
</script>
<template>
    <Head title="Compte formation" />
    <AdminLayout
        ><div ref="root">
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-6xl space-y-6">
                    <header>
                        <Link
                            href="/admin/formation-payments"
                            class="mb-2 flex items-center gap-2 text-sm text-muted-foreground"
                            ><ArrowLeft class="size-4" />Paiements
                            formations</Link
                        >
                        <h1 class="text-2xl font-black">
                            {{ account.student.full_name }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{
                                account.accountable?.form?.course?.title ||
                                account.accountable?.training_plan_group?.plan
                                    ?.level?.course?.title
                            }}
                            · {{ account.accountable?.form?.title || '—' }} ·
                            {{
                                account.accountable?.training_plan_group
                                    ?.name || 'Sans groupe'
                            }}
                        </p>
                    </header>
                    <FinancialAccountLedger
                        :account="account"
                        :methods="methods"
                        base-url="/admin/formation-payments"
                    />
                </div>
            </main></div
    ></AdminLayout>
</template>
