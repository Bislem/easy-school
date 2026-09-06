<script setup lang="ts">
import { Button } from '@/components/ui/button';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>
<template>
    <HomeLayout
        ><Head title="Demande envoyée" />
        <main class="mx-auto max-w-xl px-5 py-24 text-center">
            <h1 class="text-3xl font-black">Votre demande a été envoyée</h1>
            <p class="mt-4 text-slate-600">
                Notre équipe vérifiera les informations de votre école. Si elle
                est approuvée, vos identifiants et la date d'expiration seront
                envoyés par e-mail.
            </p>
            <Button class="mt-7" as-child
                ><Link href="/">Retour à l'accueil</Link></Button
            >
        </main></HomeLayout
    >
</template>
