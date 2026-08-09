<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';
import { computed } from 'vue';
import ClientLayout from '@/layouts/ClientLayout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres d’apparence',
        href: edit().url,
    },
];

const page = usePage();

const layout = computed(() => {
    return page.props.auth.user.role === 'admin'
        ? AppLayout
        : ClientLayout
})
</script>

<template>
    <component :is="layout" :breadcrumbs="breadcrumbItems">
        <Head title="Paramètres d’apparence" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Paramètres d’apparence"
                    description="Personnalisez l’apparence de votre compte"
                />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </component>
</template>
