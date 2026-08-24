<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { onErrorCaptured, ref, watch } from 'vue';

const page = usePage();
const message = ref(page.props.flash?.restricted_action ?? null);
const successMessage = ref(page.props.flash?.success ?? null);
const renderError = ref(false);

onErrorCaptured((error) => {
    console.error('Admin page rendering failed:', error);
    renderError.value = true;
    return false;
});

watch(
    () => page.props.flash?.restricted_action,
    (val) => {
        message.value = val;
        if (val) {
            setTimeout(() => (message.value = null), 10000);
        }
    },
);

watch(
    () => page.props.flash?.success,
    (val) => {
        successMessage.value = val;
        if (val) {
            setTimeout(() => (successMessage.value = null), 5000);
        }
    },
);
</script>

<template>
    <AppSidebarLayout>
        <div
            v-if="message"
            class="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg bg-yellow-500/90 px-4 py-2 text-sm font-medium text-white shadow-lg"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L4.34 16c-.77 1.333.192 3 1.732 3z"
                />
            </svg>
            <span>{{ message }}</span>
        </div>
        <div
            v-if="successMessage"
            class="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg bg-green-600/90 px-4 py-2 text-sm font-medium text-white shadow-lg"
        >
            <span>{{ successMessage }}</span>
        </div>
        <div
            v-if="renderError"
            class="m-6 rounded-xl border border-red-200 bg-red-50 p-6 text-red-900"
        >
            <h1 class="font-semibold">Impossible d’afficher cette page</h1>
            <p class="mt-1 text-sm">
                Actualisez la page. Si le problème persiste, consultez la
                console du navigateur pour identifier la donnée concernée.
            </p>
        </div>
        <slot v-else />
    </AppSidebarLayout>
</template>
