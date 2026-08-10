<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
</script>

<template>
    <SidebarGroup class="px-3 py-4">
        <SidebarGroupLabel
            class="px-2 text-[10px] font-bold tracking-[0.16em] text-primary/70 uppercase"
            >Navigation</SidebarGroupLabel
        >
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem
                v-for="item in items"
                :key="item.title"
                :class="
                    item.href === '/admin/settings'
                        ? 'mt-3 border-t border-sidebar-border/80 pt-3'
                        : ''
                "
            >
                <SidebarMenuButton
                    as-child
                    :is-active="urlIsActive(item.href, page.url)"
                    :tooltip="item.title"
                    class="h-10 rounded-xl px-3 font-medium text-sidebar-foreground/80 transition-all hover:translate-x-0.5 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-[active=true]:bg-primary data-[active=true]:text-primary-foreground data-[active=true]:shadow-md data-[active=true]:shadow-primary/20 [&>svg]:rounded-md [&>svg]:text-primary data-[active=true]:[&>svg]:text-primary-foreground"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" class="size-4.5" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
