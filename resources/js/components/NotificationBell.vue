<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    enableFirebaseNotifications,
    firebaseNotificationState,
} from '@/lib/firebase-messaging';
import { router, usePage } from '@inertiajs/vue3';
import { Bell, BellRing, CheckCheck, ExternalLink } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type PortalNotification = {
    id: number;
    title: string;
    message: string;
    type: string;
    data?: { url?: string } | null;
    read_at?: string | null;
    occurred_at: string;
};

const page = usePage();
const notifications = computed(
    () => (page.props.auth_notifications ?? []) as PortalNotification[],
);
const unread = computed(() =>
    Number(page.props.unread_notifications_count ?? 0),
);
const pushState = ref<'unsupported' | 'default' | 'denied' | 'subscribed'>(
    'default',
);
const pushBusy = ref(false);

async function refreshPushState() {
    pushState.value = await firebaseNotificationState();
}

async function enablePush() {
    pushBusy.value = true;
    try {
        await enableFirebaseNotifications();
        await refreshPushState();
    } finally {
        pushBusy.value = false;
    }
}

function realtimeRefresh() {
    router.reload({
        only: ['auth_notifications', 'unread_notifications_count'],
    });
}
onMounted(() => {
    refreshPushState().catch(() => undefined);
    window.addEventListener('portal-notification-created', realtimeRefresh);
});
onBeforeUnmount(() =>
    window.removeEventListener('portal-notification-created', realtimeRefresh),
);

function destination(notification: PortalNotification): string | null {
    const url = notification.data?.url;
    return typeof url === 'string' &&
        url.startsWith('/') &&
        !url.startsWith('//')
        ? url
        : null;
}

function openNotification(notification: PortalNotification) {
    const url = destination(notification);
    const visit = () => {
        if (url) router.visit(url);
    };

    if (notification.read_at) visit();
    else {
        router.patch(
            `/portal/notifications/${notification.id}/read`,
            {},
            { preserveScroll: true, onSuccess: visit },
        );
    }
}

function readAll() {
    if (!unread.value) return;
    router.patch(
        '/portal/notifications/read-all',
        {},
        { preserveScroll: true },
    );
}

function displayedDate(value: string): string {
    return new Date(value).toLocaleString('fr-FR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative grid size-10 shrink-0 place-items-center rounded-xl border bg-card text-foreground shadow-sm transition hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Ouvrir les notifications"
            >
                <Bell class="size-5" />
                <span
                    v-if="unread"
                    class="absolute -top-1.5 -right-1.5 grid min-h-5 min-w-5 place-items-center rounded-full border-2 border-background bg-red-600 px-1 text-[10px] font-bold text-white"
                    >{{ unread > 99 ? '99+' : unread }}</span
                >
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="w-[min(92vw,24rem)] overflow-hidden rounded-2xl p-0"
        >
            <div class="flex items-center justify-between gap-3 border-b p-4">
                <div>
                    <p class="font-semibold">Notifications</p>
                    <p class="text-xs text-muted-foreground">
                        {{ unread }} non lue(s)
                    </p>
                </div>
                <Button
                    v-if="unread"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="readAll"
                >
                    <CheckCheck class="mr-2 size-4" />Tout lire
                </Button>
            </div>
            <button
                v-if="pushState === 'default'"
                type="button"
                class="flex w-full items-center gap-3 border-b bg-primary/5 px-4 py-3 text-left text-xs text-primary hover:bg-primary/10"
                :disabled="pushBusy"
                @click="enablePush"
            >
                <BellRing class="size-4" /><span
                    ><b class="block">Activer les notifications du navigateur</b
                    >Recevez-les même lorsque cet onglet est fermé.</span
                >
            </button>
            <p
                v-else-if="pushState === 'denied'"
                class="border-b bg-amber-50 px-4 py-3 text-xs text-amber-800"
            >
                Les notifications sont bloquées dans les paramètres de ce
                navigateur.
            </p>
            <div class="max-h-[min(65vh,32rem)] overflow-y-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full gap-3 border-b p-4 text-left transition last:border-0 hover:bg-muted/50"
                    :class="
                        notification.read_at
                            ? 'opacity-70'
                            : 'bg-primary/[0.04]'
                    "
                    @click="openNotification(notification)"
                >
                    <span
                        class="relative mt-1 grid size-9 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"
                    >
                        <Bell class="size-4" />
                        <span
                            v-if="!notification.read_at"
                            class="absolute top-0 right-0 size-2.5 rounded-full border-2 border-background bg-red-500"
                        />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2">
                            <b class="text-sm leading-5">{{
                                notification.title
                            }}</b>
                            <ExternalLink
                                v-if="destination(notification)"
                                class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                            />
                        </span>
                        <span
                            class="mt-1 line-clamp-2 block text-xs leading-5 text-muted-foreground"
                            >{{ notification.message }}</span
                        >
                        <span
                            class="mt-1 block text-[11px] text-muted-foreground"
                            >{{ displayedDate(notification.occurred_at) }}</span
                        >
                    </span>
                </button>
                <div
                    v-if="!notifications.length"
                    class="px-5 py-12 text-center text-sm text-muted-foreground"
                >
                    <Bell class="mx-auto mb-3 size-8 opacity-40" />Aucune
                    notification.
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
