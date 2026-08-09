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
import { index as carsIndex } from '@/routes/admin/cars/index';
import { index as clientsIndex } from '@/routes/admin/clients/index';
import { index as paymentsIndex } from '@/routes/admin/payments/index';
import { index as reportsIndex } from '@/routes/admin/reports/index';
import { index as reservationsIndex } from '@/routes/admin/reservations/index';
import { index as supportIndex } from '@/routes/admin/support/index';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    BarChart,
    Calendar,
    Car,
    CreditCard,
    ReceiptText,
    LifeBuoy,
    Settings,
    User,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Véhicules',
        href: carsIndex(),
        icon: Car,
    },
    {
        title: 'Réservations',
        href: reservationsIndex(),
        icon: Calendar,
    },
    {
        title: 'Clients',
        href: clientsIndex(),
        icon: User,
    },
    {
        title: 'Paiements',
        href: paymentsIndex(),
        icon: CreditCard,
    },
    {
        title: 'Dépenses',
        href: '/admin/expenses',
        icon: ReceiptText,
    },
    {
        title: 'Rapports',
        href: reportsIndex(),
        icon: BarChart,
    },
    {
        title: 'Support',
        href: supportIndex(),
        icon: LifeBuoy,
    },
    {
        title: "Paramètres de l'agence",
        href: '/admin/settings',
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
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
