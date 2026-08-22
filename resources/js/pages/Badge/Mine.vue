<script setup lang="ts">
import BadgeCard from '@/components/BadgeCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ badge: Object, school: { type: Object, required: true } });
</script>
<template>
    <Head title="Ma carte" /><AppLayout
        ><main class="p-6">
            <div class="mx-auto max-w-2xl space-y-5">
                <div>
                    <h1 class="text-2xl font-semibold">Ma carte</h1>
                    <p class="text-muted-foreground">
                        Votre badge numérique officiel.
                    </p>
                </div>
                <BadgeCard v-if="badge" :badge="badge" :school="school" />
                <div
                    v-else
                    class="rounded-xl border p-8 text-center text-muted-foreground"
                >
                    Aucune carte active.
                </div>
            </div>
        </main></AppLayout
    >
</template>
