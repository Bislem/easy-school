<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import AnnouncementViewer from '@/components/announcements/AnnouncementViewer.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{ portal: any; showChildSelector?: boolean }>(),
    { showChildSelector: true },
);
const page = usePage<any>();
const latestAnnouncement = computed(() => page.props.latest_parent_announcement);
const announcementOpen = ref(Boolean(page.props.latest_parent_announcement));
watch(() => latestAnnouncement.value?.notification_id, (id) => { if (id) announcementOpen.value = true; });
function closeAnnouncement() {
    const id = latestAnnouncement.value?.notification_id;
    announcementOpen.value = false;
    if (id) router.patch(`/portal/notifications/${id}/read`, {}, { preserveScroll: true, preserveState: true });
}
const selected = computed(() =>
    props.portal.children.find(
        (child: any) => child.id === props.portal.selected_child_id,
    ),
);

function choose(event: Event) {
    router.post(
        '/parent/selected-child',
        { student_id: Number((event.target as HTMLSelectElement).value) },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppSidebarLayout>
        <div
            class="flex min-h-14 items-center gap-3 border-b bg-card/70 px-4 py-2 sm:px-6"
        >
            <div
                v-if="showChildSelector"
                class="relative min-w-0 flex-1 sm:max-w-sm"
            >
                <select
                    v-if="portal.children.length > 1"
                    :value="portal.selected_child_id"
                    aria-label="Enfant sélectionné"
                    class="h-10 w-full appearance-none rounded-lg border bg-background pr-9 pl-3 text-sm font-semibold shadow-sm outline-none focus:ring-2 focus:ring-ring"
                    @change="choose"
                >
                    <option
                        v-for="child in portal.children"
                        :key="child.id"
                        :value="child.id"
                    >
                        {{ child.name }} ·
                        {{ child.level || 'Niveau non défini' }}
                    </option>
                </select>
                <ChevronDown
                    v-if="portal.children.length > 1"
                    class="pointer-events-none absolute top-3 right-3 size-4 text-muted-foreground"
                />
                <div
                    v-else-if="selected"
                    class="truncate text-sm font-semibold"
                >
                    {{ selected.name }}
                    <span class="font-normal text-muted-foreground">
                        · {{ selected.level || 'Niveau non défini' }}
                    </span>
                </div>
                <div v-else class="text-sm text-muted-foreground">
                    Aucun enfant associé
                </div>
            </div>
            <div class="ml-auto hidden text-right sm:block">
                <p class="text-sm font-medium">{{ portal.parent.name }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ portal.academic_year?.name || 'Aucune année active' }}
                </p>
            </div>
        </div>

        <main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
            <slot />
        </main>
    </AppSidebarLayout>
    <AnnouncementViewer :open="announcementOpen" :announcement="latestAnnouncement" @close="closeAnnouncement" />
</template>
