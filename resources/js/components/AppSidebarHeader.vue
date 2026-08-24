<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const pageTitle = computed(() => {
    const path = page.url.split('?')[0];
    const titles: Array<[string, string]> = [
        ['/admin/enrollment-forms', 'Inscriptions'],
        ['/admin/planifications', 'Planifications'],
        ['/admin/students', 'Étudiants'],
        ['/admin/courses', 'Formations'],
        ['/admin/sites', 'Sites'],
        ['/admin/classrooms', 'Salles'],
        ['/admin/users', 'Personnel'],
        ['/admin/staff', 'Personnel'],
        ['/admin/salaries', 'Salaires'],
        ['/admin/expenses', 'Dépenses'],
        ['/admin/settings', 'Paramètres de l’école'],
        ['/settings', 'Paramètres du compte'],
        ['/dashboard', 'Tableau de bord'],
    ];

    return (
        titles.find(([prefix]) => path.startsWith(prefix))?.[1] ??
        'Gestion scolaire'
    );
});
const roleLabel = computed(() =>
    user.value.role === 'admin'
        ? 'Administrateur'
        : user.value.role === 'teacher'
          ? 'Enseignant'
          : 'Employé',
);
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-sidebar-border/70 bg-background/95 px-3 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 sm:px-5"
    >
        <div class="flex min-w-0 items-center gap-2 sm:gap-3">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            <div v-else class="min-w-0">
                <p class="truncate text-sm font-semibold sm:text-base">
                    {{ pageTitle }}
                </p>
                <p class="hidden text-xs text-muted-foreground sm:block">
                    {{ roleLabel }}
                </p>
            </div>
        </div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="flex min-w-0 shrink-0 items-center gap-2 rounded-xl border bg-card px-2 py-1.5 text-left shadow-sm transition hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:px-3"
                    aria-label="Ouvrir le menu du profil"
                >
                    <UserInfo
                        :user="user"
                        class="[&>div:last-child]:hidden sm:[&>div:last-child]:grid"
                    />
                    <ChevronDown
                        class="hidden size-4 text-muted-foreground sm:block"
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64 rounded-xl p-2">
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </header>
</template>
