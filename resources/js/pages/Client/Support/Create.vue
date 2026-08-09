<script setup lang="ts">
import { Button } from '@/components/ui/button';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { index, store } from '@/routes/client/support';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm<{
    subject: string;
    message: string;
}>({
    subject: '',
    message: '',
});

const canSubmit = computed(
    () =>
        form.subject.trim().length > 0 &&
        form.message.trim().length > 0 &&
        !form.processing,
);

const submitTicket = async () => {
    if (!form.subject || form.subject.trim().length === 0) return;
    if (!form.message || form.message.trim().length === 0) return;

    try {
        await form.post(store().url, {
            onSuccess: () => {
                // Redirect will be handled by Inertia after successful creation
            },
            onError: (errors) => {
                console.error('Failed to create ticket:', errors);
            },
        });
    } catch (error) {
        console.error('An error occurred while creating the ticket:', error);
    }
};
</script>

<template>

    <Head title="Créer un ticket" />
    <ClientLayout>
        <div class="p-4">
            <!-- En-tête de la page -->
            <div class="mb-6 w-full rounded-lg bg-white p-6 shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            Créer un nouveau ticket d'assistance
                        </h1>
                        <p class="mt-2 text-sm text-gray-600">
                            Besoin d'aide ? Soumettez un ticket d'assistance et notre
                            équipe vous répondra dans les plus brefs délais.
                        </p>
                    </div>
                    <div>
                        <Link :href="index().url">
                            <Button variant="outline">Retour aux tickets</Button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Formulaire du ticket -->
            <div class="w-full rounded-lg bg-white p-6 shadow">
                <form @submit.prevent="submitTicket" class="space-y-6">
                    <!-- Champ Sujet -->
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700">
                            Sujet
                            <span class="text-red-500">*</span>
                        </label>
                        <input id="subject" v-model="form.subject" type="text"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Brève description de votre problème" required maxlength="255"
                            aria-label="Sujet du ticket" />
                        <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">
                            {{ form.errors.subject }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ form.subject.length }}/255 caractères
                        </p>
                    </div>

                    <!-- Champ Message -->
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700">
                            Message
                            <span class="text-red-500">*</span>
                        </label>
                        <textarea id="message" v-model="form.message" rows="8"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Fournissez des informations détaillées concernant votre problème..." required
                            aria-label="Message du ticket"></textarea>
                        <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">
                            {{ form.errors.message }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            Veuillez fournir autant de détails que possible afin de
                            nous aider à mieux vous assister.
                        </p>
                    </div>

                    <!-- Bouton d'envoi -->
                    <div class="flex items-center justify-end space-x-3">
                        <Link :href="index().url">
                            <Button type="button" variant="outline" :disabled="form.processing">
                                Annuler
                            </Button>
                        </Link>
                        <button type="submit"
                            class="rounded-md cursor-pointer bg-slate-600 px-6 py-2 text-white hover:bg-slate-700 focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canSubmit">
                            <span v-if="form.processing">
                                Création du ticket...
                            </span>
                            <span v-else>Créer le ticket</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Texte d'aide -->
            <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                            fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Conseils pour soumettre un ticket
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc space-y-1 pl-5">
                                <li>
                                    Utilisez un objet clair et descriptif.
                                </li>
                                <li>
                                    Incluez les informations pertinentes, telles que
                                    les messages d'erreur ou des captures d'écran.
                                </li>
                                <li>
                                    Décrivez les étapes permettant de reproduire le
                                    problème.
                                </li>
                                <li>
                                    Notre équipe d'assistance répond généralement
                                    dans un délai de 24 heures.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
