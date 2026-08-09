<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
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
const school = page.props.school as { primary_color?: string } | undefined;

const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    ...(role === 'admin'
        ? [
              { title: 'Utilisateurs', href: '/admin/users', icon: Users },
              {
                  title: 'Étudiants',
                  href: '/admin/students',
                  icon: GraduationCap,
              },
              { title: 'Salles', href: '/admin/classrooms', icon: BookOpen },
              { title: 'Formations', href: '/admin/courses', icon: BookOpen },
              {
                  title: 'Inscriptions',
                  href: '/admin/enrollment-forms',
                  icon: ClipboardList,
              },
              { title: 'Dépenses', href: '/admin/expenses', icon: ReceiptText },
              { title: 'Salaires', href: '/admin/salaries', icon: WalletCards },
              {
                  title: 'Planifications',
                  href: '/admin/planifications',
                  icon: CalendarRange,
              },
              { title: 'Présences', href: '#', icon: ClipboardCheck },
          ]
        : []),
    {
        title: 'Paramètres du compte',
        href: '/settings/profile',
        icon: Settings,
    },
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
    <Sidebar
        collapsible="icon"
        variant="inset"
        :style="
            school?.primary_color
                ? { '--primary': school.primary_color }
                : undefined
        "
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
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

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
