<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
defineProps({
    badge: { type: Object, required: true },
    school: { type: Object, required: true },
});
const labels: any = {
    active: 'Carte valide',
    expired: 'Carte expirée',
    suspended: 'Carte suspendue',
    lost: 'Carte déclarée perdue',
    replaced: 'Carte remplacée',
    cancelled: 'Carte annulée',
};
</script>
<template>
    <Head title="Vérification de carte" />
    <main class="grid min-h-screen place-items-center bg-muted p-5">
        <section
            class="w-full max-w-md rounded-2xl border bg-background p-7 text-center shadow"
        >
            <img
                v-if="school.logo_url"
                :src="school.logo_url"
                class="mx-auto mb-3 size-16 object-contain"
            />
            <h1 class="text-xl font-semibold">{{ school.trading_name }}</h1>
            <div class="my-6 rounded-xl border p-5">
                <p class="text-lg font-bold">
                    {{ badge.first_name }} {{ badge.last_name }}
                </p>
                <p class="text-muted-foreground">{{ badge.role_label }}</p>
                <p v-if="badge.formation_label" class="mt-2 text-sm">
                    {{ badge.formation_label }} · {{ badge.group_label }}
                </p>
                <p class="mt-3 font-mono text-sm">{{ badge.card_number }}</p>
            </div>
            <p
                class="font-semibold"
                :class="
                    badge.display_status === 'active'
                        ? 'text-green-600'
                        : 'text-destructive'
                "
            >
                {{ labels[badge.display_status] }}
            </p>
            <p class="mt-2 text-xs text-muted-foreground">
                Cette page confirme uniquement l’authenticité et le statut de la
                carte. Aucune donnée privée n’est contenue dans le QR code.
            </p>
        </section>
    </main>
</template>
