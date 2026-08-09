<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const school = computed(() => (usePage().props as any).school);
</script>

<template>
    <img
        v-if="school?.logo_url"
        :src="school.logo_url"
        :alt="school.trading_name"
        class="size-8 rounded object-contain"
    />
    <AppLogoIcon v-else />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            school?.trading_name || 'Gestion Easy École'
        }}</span>
    </div>
</template>
