<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import NavMain from '@/components/NavMain.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardCheck,
    CreditCard,
    ExternalLink,
    FlaskConical,
    LayoutDashboard,
    LogOut,
    MessagesSquare,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ title: string; description?: string }>();
const page = usePage();
const basePath = computed(
    () => (page.props.superAdmin as any).basePath as string,
);
const user = computed(() => page.props.auth.user as any);
const initials = computed(() =>
    String(user.value?.name || 'SA')
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase(),
);
const navItems = computed<NavItem[]>(() => [
    {
        title: 'Tableau de bord',
        href: `${basePath.value}/dashboard`,
        icon: LayoutDashboard,
    },
    {
        title: 'Gestion des clients',
        href: `${basePath.value}/schools`,
        icon: Building2,
    },
    {
        title: 'Plans et abonnements',
        href: `${basePath.value}/plans`,
        icon: CreditCard,
    },
    {
        title: 'Demandes de démo',
        href: `${basePath.value}/demo-requests`,
        icon: FlaskConical,
    },
    {
        title: 'Nouvelles inscriptions',
        href: `${basePath.value}/registrations`,
        icon: ClipboardCheck,
    },
    {
        title: 'Messages de contact',
        href: `${basePath.value}/contact-requests`,
        icon: MessagesSquare,
    },
]);
const logout = () => router.post(`${basePath.value}/logout`);
</script>

<template>
    <Head :title="title" />
    <AppShell variant="sidebar">
        <Sidebar collapsible="icon" variant="sidebar" class="platform-sidebar">
            <SidebarHeader class="border-b border-sidebar-border/70 p-3">
                <SidebarMenu
                    ><SidebarMenuItem
                        ><SidebarMenuButton
                            size="lg"
                            as-child
                            class="h-12 rounded-xl bg-background/80 shadow-sm ring-1 ring-sidebar-border/60"
                        >
                            <Link :href="`${basePath}/dashboard`"
                                ><span
                                    class="grid size-8 place-items-center rounded-lg bg-primary text-primary-foreground"
                                    ><ShieldCheck class="size-4"
                                /></span>
                                <div class="grid flex-1 text-left text-sm">
                                    <span class="truncate font-extrabold"
                                        >Easy School</span
                                    ><span
                                        class="truncate text-[10px] font-bold text-muted-foreground uppercase"
                                        >Administration SaaS</span
                                    >
                                </div></Link
                            >
                        </SidebarMenuButton></SidebarMenuItem
                    ></SidebarMenu
                >
            </SidebarHeader>
            <SidebarContent><NavMain :items="navItems" /></SidebarContent>
            <SidebarFooter class="border-t border-sidebar-border/70 p-3"
                ><SidebarMenu>
                    <SidebarMenuItem
                        ><SidebarMenuButton
                            as-child
                            tooltip="Voir le site public"
                            ><Link href="/" target="_blank"
                                ><ExternalLink /><span>Site public</span></Link
                            ></SidebarMenuButton
                        ></SidebarMenuItem
                    >
                    <SidebarMenuItem
                        ><SidebarMenuButton
                            class="text-destructive"
                            tooltip="Déconnexion"
                            @click="logout"
                            ><LogOut /><span
                                >Déconnexion</span
                            ></SidebarMenuButton
                        ></SidebarMenuItem
                    >
                </SidebarMenu></SidebarFooter
            >
        </Sidebar>
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <header
                class="sticky top-0 z-30 flex min-h-16 items-center justify-between border-b bg-background/95 px-5 backdrop-blur"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <SidebarTrigger />
                    <div>
                        <h1 class="font-extrabold">{{ props.title }}</h1>
                        <p class="text-xs text-muted-foreground">
                            {{
                                props.description ||
                                'Administration globale de la plateforme'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-xs font-bold">{{ user?.name }}</p>
                        <p class="text-[10px] text-muted-foreground">
                            Super administrateur
                        </p>
                    </div>
                    <Avatar class="size-9"
                        ><AvatarFallback>{{ initials }}</AvatarFallback></Avatar
                    >
                </div>
            </header>
            <main class="p-4 sm:p-6 lg:p-8"><slot /></main>
        </AppContent>
    </AppShell>
</template>
