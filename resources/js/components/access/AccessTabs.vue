<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { History, KeyRound, ShieldCheck, Users } from 'lucide-vue-next';

const page = usePage();
const tabs = [
    {
        label: 'Utilisateurs',
        href: '/admin/settings/access/users',
        icon: Users,
    },
    { label: 'Rôles', href: '/admin/settings/access/roles', icon: ShieldCheck },
    {
        label: 'Matrice des permissions',
        href: '/admin/settings/access/permissions',
        icon: KeyRound,
    },
    {
        label: "Historique d'accès",
        href: '/admin/settings/access/history',
        icon: History,
    },
];
</script>

<template>
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium text-primary">Paramètres</p>
            <h1 class="text-2xl font-semibold tracking-tight">
                Utilisateurs & accès
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Contrôlez les rôles, permissions et périmètres de votre
                établissement.
            </p>
        </div>
        <nav
            class="flex gap-2 overflow-x-auto border-b pb-px"
            aria-label="Utilisateurs et accès"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.href"
                :href="tab.href"
                class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium transition"
                :class="
                    page.url.startsWith(tab.href)
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
            >
                <component :is="tab.icon" class="size-4" />{{ tab.label }}
            </Link>
        </nav>
        <slot />
    </div>
</template>
