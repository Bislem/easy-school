<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
defineProps({
    certificate: { type: Object, required: true },
    school: { type: Object, required: true },
});
</script>
<template>
    <Head title="Vérification du certificat" />
    <main class="grid min-h-screen place-items-center bg-muted p-5">
        <section
            class="w-full max-w-lg rounded-2xl border bg-background p-8 text-center shadow"
        >
            <img
                v-if="school.logo_url"
                :src="school.logo_url"
                class="mx-auto size-16 object-contain"
            />
            <h1 class="mt-2 text-xl font-semibold">
                {{ school.trading_name }}
            </h1>
            <div class="my-6 rounded-xl border p-5">
                <p class="font-semibold text-green-600">Document authentique</p>
                <p class="mt-3 text-lg font-bold">{{ certificate.type }}</p>
                <p>{{ certificate.student }}</p>
                <p class="text-muted-foreground">{{ certificate.formation }}</p>
                <p class="mt-3 font-mono text-sm">{{ certificate.number }}</p>
                <p class="text-sm">Délivré le {{ certificate.issue_date }}</p>
            </div>
            <p class="text-xs text-muted-foreground">
                Le QR contient uniquement un jeton de vérification opaque.
            </p>
        </section>
    </main>
</template>
