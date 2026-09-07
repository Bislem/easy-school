<script setup lang="ts">
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const itemIsActive = (item: NavItem) =>
    item.href
        ? urlIsActive(item.href, page.url)
        : (item.children?.some(
              (child) => child.href && urlIsActive(child.href, page.url),
          ) ?? false);
</script>

<template>
    <SidebarGroup class="px-3 py-4">
        <SidebarGroupLabel
            class="px-2 text-[10px] font-bold tracking-[0.16em] text-primary/70 uppercase"
            >Navigation</SidebarGroupLabel
        >
        <SidebarMenu class="gap-2">
            <Collapsible
                v-for="item in items"
                :key="item.title"
                as-child
                :default-open="itemIsActive(item)"
                class="group/collapsible"
            >
                <SidebarMenuItem
                    :class="
                        item.href === '/admin/settings'
                            ? 'mt-3 border-t border-sidebar-border/80 pt-3'
                            : ''
                    "
                >
                    <template v-if="item.children?.length"
                        ><CollapsibleTrigger as-child
                            ><SidebarMenuButton
                                :is-active="itemIsActive(item)"
                                :tooltip="item.title"
                                class="h-11 rounded-xl border border-transparent px-3 font-semibold text-sidebar-foreground/80 transition-all duration-200 hover:translate-x-0.5 hover:border-primary/15 hover:bg-primary/8 hover:text-sidebar-foreground data-[active=true]:border-primary/25 data-[active=true]:bg-primary/12 data-[active=true]:text-primary data-[active=true]:shadow-sm [&>svg]:text-primary"
                                ><component
                                    :is="item.icon"
                                    class="size-4.5" /><span>{{
                                    item.title
                                }}</span
                                ><ChevronRight
                                    class="ml-auto size-4 transition-transform group-data-[state=open]/collapsible:rotate-90" /></SidebarMenuButton
                        ></CollapsibleTrigger>
                        <CollapsibleContent
                            ><SidebarMenuSub
                                class="my-2 ml-5 gap-1.5 border-l-2 border-primary/20 py-1.5 pl-3"
                                ><SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                    ><SidebarMenuSubButton
                                        as-child
                                        class="h-9 rounded-lg border border-transparent px-3 text-sidebar-foreground/70 transition-all duration-200 hover:translate-x-0.5 hover:border-primary/10 hover:bg-primary/8 hover:text-sidebar-foreground data-[active=true]:border-primary/25 data-[active=true]:bg-primary data-[active=true]:font-semibold data-[active=true]:text-primary-foreground data-[active=true]:shadow-sm data-[active=true]:shadow-primary/20 [&>svg]:text-primary/80 data-[active=true]:[&>svg]:text-primary-foreground"
                                        :is-active="
                                            child.href
                                                ? urlIsActive(
                                                      child.href,
                                                      page.url,
                                                  )
                                                : false
                                        "
                                        ><Link :href="child.href || '#'"
                                            ><component
                                                :is="child.icon"
                                                class="size-4"
                                            /><span>{{
                                                child.title
                                            }}</span></Link
                                        ></SidebarMenuSubButton
                                    ></SidebarMenuSubItem
                                ></SidebarMenuSub
                            ></CollapsibleContent
                        ></template
                    >
                    <SidebarMenuButton
                        v-else
                        as-child
                        :is-active="
                            item.href ? urlIsActive(item.href, page.url) : false
                        "
                        :tooltip="item.title"
                        class="h-11 rounded-xl border border-transparent px-3 font-medium text-sidebar-foreground/80 transition-all duration-200 hover:translate-x-0.5 hover:border-primary/15 hover:bg-primary/8 hover:text-sidebar-foreground data-[active=true]:border-primary data-[active=true]:bg-primary data-[active=true]:font-semibold data-[active=true]:text-primary-foreground data-[active=true]:shadow-md data-[active=true]:shadow-primary/25 [&>svg]:rounded-md [&>svg]:text-primary data-[active=true]:[&>svg]:text-primary-foreground"
                    >
                        <Link :href="item.href || '#'">
                            <component :is="item.icon" class="size-4.5" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
