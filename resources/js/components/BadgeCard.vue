<script setup lang="ts">
defineProps<{ badge: any; school: any }>();
const labels: Record<string, string> = {
    active: 'Active',
    expired: 'Expirée',
    suspended: 'Suspendue',
    lost: 'Perdue',
    replaced: 'Remplacée',
    cancelled: 'Annulée',
};
</script>
<template>
    <div
        class="relative aspect-[1.586/1] w-full max-w-[540px] overflow-hidden rounded-2xl border bg-white text-slate-900 shadow-lg"
        :style="{ borderColor: badge.template?.primary_color }"
    >
        <div
            class="flex h-[26%] items-center justify-between px-5 text-white"
            :style="{ background: badge.template?.primary_color || '#f97316' }"
        >
            <div class="flex items-center gap-3">
                <img
                    v-if="school.logo_url"
                    :src="school.logo_url"
                    class="size-10 rounded bg-white object-contain p-1"
                />
                <div>
                    <p class="text-lg font-bold">
                        {{ school.trading_name || 'Easy School' }}
                    </p>
                    <p class="text-[10px] tracking-widest uppercase">
                        Carte officielle
                    </p>
                </div>
            </div>
            <span class="rounded-full bg-white/20 px-2 py-1 text-[10px]">{{
                labels[badge.display_status]
            }}</span>
        </div>
        <div class="flex h-[58%] gap-4 p-4">
            <img
                v-if="badge.photo_url_snapshot"
                :src="badge.photo_url_snapshot"
                class="h-full w-[27%] rounded-lg object-cover object-center"
            />
            <div
                v-else
                class="grid h-full w-[27%] place-items-center rounded-lg bg-slate-100 text-2xl font-bold"
            >
                {{ badge.first_name?.[0] }}{{ badge.last_name?.[0] }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-xl font-bold">
                    {{ badge.first_name }} {{ badge.last_name }}
                </p>
                <p
                    class="font-medium"
                    :style="{ color: badge.template?.primary_color }"
                >
                    {{ badge.role_label }}
                </p>
                <p v-if="badge.formation_label" class="mt-2 truncate text-xs">
                    {{ badge.formation_label }}
                </p>
                <p v-if="badge.group_label" class="text-xs text-slate-500">
                    {{ badge.group_label }}
                </p>
                <p class="mt-3 font-mono text-xs">{{ badge.card_number }}</p>
                <p class="text-[10px] text-slate-500">
                    Émise {{ badge.issue_date
                    }}<span v-if="badge.expiration_date">
                        · Exp. {{ badge.expiration_date }}</span
                    >
                </p>
            </div>
            <img
                :src="badge.qr_url"
                class="size-20 self-end"
                alt="QR de vérification"
            />
        </div>
        <div
            class="flex h-[16%] items-center justify-between px-5 text-[9px] text-white"
            :style="{
                background: badge.template?.secondary_color || '#111827',
            }"
        >
            <span>{{
                school.address_line_1 ||
                school.email ||
                'Carte vérifiable par QR code'
            }}</span
            ><img
                v-if="badge.barcode_url"
                :src="badge.barcode_url"
                class="h-7 max-w-40 bg-white"
                alt="Code-barres"
            />
        </div>
    </div>
</template>
