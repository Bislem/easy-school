<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    GraduationCap,
    LayoutDashboard,
    ReceiptText,
    Settings,
    Users,
    WalletCards,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();
const role = page.props.auth.user.role;

const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    ...(role === 'admin'
        ? [
              {
                  title: 'Inscriptions',
                  href: '/admin/enrollment-forms',
                  icon: ClipboardList,
              },
              {
                  title: 'Planifications',
                  href: '/admin/planifications',
                  icon: CalendarRange,
              },
              {
                  title: 'Étudiants',
                  href: '/admin/students',
                  icon: GraduationCap,
              },
              { title: 'Formations', href: '/admin/courses', icon: BookOpen },
              { title: 'Présences', href: '#', icon: ClipboardCheck },
              { title: 'Salles', href: '/admin/classrooms', icon: BookOpen },
              { title: 'Utilisateurs', href: '/admin/users', icon: Users },
              { title: 'Salaires', href: '/admin/salaries', icon: WalletCards },
              { title: 'Dépenses', href: '/admin/expenses', icon: ReceiptText },
          ]
        : []),
    ...(role === 'admin'
        ? [
              {
                  title: "Paramètres de l'école",
                  href: '/admin/settings',
                  icon: Settings,
              },
          ]
        : []),
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="school-sidebar">
        <SidebarHeader class="border-b border-sidebar-border/70 p-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-12 rounded-xl bg-background/80 shadow-sm ring-1 ring-sidebar-border/60 transition hover:bg-background hover:shadow-md data-[state=open]:bg-background"
                    >
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
