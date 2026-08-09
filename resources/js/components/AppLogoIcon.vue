<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const agency = computed(() => (usePage().props as any).agency);
</script>

<template>
    <img
        v-if="agency?.logo_url"
        :src="agency.logo_url"
        :alt="agency.trading_name"
        class="h-7 max-w-9 object-contain"
    />
    <AppLogoIcon v-else />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            agency?.trading_name || 'Real Rent Car'
        }}</span>
    </div>
</template>
