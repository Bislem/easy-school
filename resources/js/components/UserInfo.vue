<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <Avatar class="h-8 w-8 shrink-0 overflow-hidden rounded-lg">
            <AvatarImage
                v-if="showAvatar"
                :src="user.avatar!"
                :alt="user.name"
            />
            <AvatarFallback
                class="rounded-lg bg-primary/10 text-primary dark:text-white"
            >
                {{ getInitials(user.name) }}
            </AvatarFallback>
        </Avatar>

        <div class="grid min-w-0 flex-1 text-left text-sm leading-tight">
            <span class="truncate font-medium">{{ user.name }}</span>
            <span
                v-if="showEmail"
                class="truncate text-xs text-muted-foreground"
                >{{ user.email }}</span
            >
        </div>
    </div>
</template>
