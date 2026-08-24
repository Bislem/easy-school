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
    Building2,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    GraduationCap,
    IdCard,
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
              { title: 'Sites', href: '/admin/sites', icon: Building2 },
              {
                  title: 'Présences',
                  href: '/admin/attendance',
                  icon: ClipboardCheck,
              },
              { title: 'Salles', href: '/admin/classrooms', icon: Building2 },
              { title: 'Personnel', href: '/admin/users', icon: Users },
              { title: 'Badges', href: '/admin/badges', icon: IdCard },
              {
                  title: 'Certificats',
                  href: '/admin/certificates',
                  icon: IdCard,
              },
              { title: 'Rapports', href: '/admin/reports', icon: ReceiptText },
              {
                  title: 'Journal d’audit',
                  href: '/admin/audit',
                  icon: ClipboardList,
              },
              { title: 'Salaires', href: '/admin/salaries', icon: WalletCards },
              {
                  title: 'Finance étudiants',
                  href: '/admin/finance',
                  icon: ReceiptText,
              },
              { title: 'Dépenses', href: '/admin/expenses', icon: ReceiptText },
          ]
        : []),
    ...(role === 'teacher' || role === 'employee'
        ? [
              { title: 'Ma paie', href: '/my/salary', icon: WalletCards },
              { title: 'Ma carte', href: '/my/card', icon: IdCard },
          ]
        : []),
    ...(role === 'student'
        ? [
              { title: 'Mon espace', href: '/portal', icon: GraduationCap },
              {
                  title: 'Ma formation',
                  href: '/portal/formation',
                  icon: BookOpen,
              },
              {
                  title: 'Mon planning',
                  href: '/portal/planning',
                  icon: CalendarRange,
              },
              {
                  title: 'Présences',
                  href: '/portal/attendance',
                  icon: ClipboardCheck,
              },
              {
                  title: 'Mes paiements',
                  href: '/portal/payments',
                  icon: ReceiptText,
              },
              { title: 'Ma carte', href: '/my/card', icon: IdCard },
          ]
        : []),
    ...(role === 'parent'
        ? [{ title: 'Espace parent', href: '/portal', icon: Users }]
        : []),
    ...(role === 'teacher'
        ? [
              { title: 'Mes groupes', href: '/portal/groups', icon: Users },
              {
                  title: 'Mes étudiants',
                  href: '/portal/students',
                  icon: GraduationCap,
              },
              {
                  title: 'Mon planning',
                  href: '/portal/planning',
                  icon: CalendarRange,
              },
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
