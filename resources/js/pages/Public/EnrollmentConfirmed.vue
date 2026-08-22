<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
defineProps<{ enrollment: any }>();
</script>

<template>
    <Head title="Demande confirmée" />
    <main
        class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10"
    >
        <div
            class="w-full max-w-lg rounded-2xl border bg-white p-7 text-center shadow-sm sm:p-10"
        >
            <CheckCircle2 class="mx-auto size-16 text-green-600" />
            <h1 class="mt-5 text-3xl font-black text-slate-900">
                Demande confirmée
            </h1>
            <p class="mt-3 leading-7 text-slate-600">
                Votre adresse e-mail a été vérifiée. Votre demande pour
                <strong>{{ enrollment.form.course.title }}</strong> est est
                maintenant en attente d’étude par l’administration.
            </p>
            <div class="mt-6 rounded-xl bg-slate-50 p-4">
                <p class="text-sm text-slate-500">Votre groupe</p>
                <p class="mt-1 text-2xl font-bold">
                    Groupe {{ enrollment.group_number }}
                </p>
            </div>
            <p class="mt-6 text-sm text-slate-500">
                L'établissement vous contactera avec les prochaines
                informations.
            </p>
        </div>
    </main>
</template>
