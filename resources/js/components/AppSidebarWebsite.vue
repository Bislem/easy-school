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
import {
    about as aboutIndex,
    contact as contactIndex,
    home,
    login as loginIndex,
    register as registerIndex,
    fleet as vehiculeIndex,
} from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Calendar, Car, CreditCard, User } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Accueil',
        href: home(),
        icon: Car,
    },
    {
        title: 'Véhicules',
        href: vehiculeIndex(),
        icon: Calendar,
    },
    {
        title: 'Contact',
        href: contactIndex(),
        icon: User,
    },
    {
        title: 'À propos',
        href: aboutIndex(),
        icon: CreditCard,
    },
    {
        title: 'Se connecter',
        href: loginIndex(),
        icon: CreditCard,
    },
    {
        title: 'Creer un compte',
        href: registerIndex(),
        icon: CreditCard,
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
