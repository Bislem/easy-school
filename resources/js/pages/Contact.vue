<script setup lang="ts">
import HomeLayout from '@/layouts/HomeLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { guestContact } from "@/routes/contact";
import { ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const showNotification = ref(false);
const notificationMessage = ref('');

const sendTicket = () => {
    form.post(guestContact().url, {
        onSuccess() {
            form.reset();
            showNotification.value = true;
            notificationMessage.value = 'Message envoyé avec succès !';
            setTimeout(() => {
                showNotification.value = false;
            }, 2000);
        },
        onError() {
            showNotification.value = true;
            notificationMessage.value = 'Échec de l’envoi du message. Veuillez réessayer.';
            setTimeout(() => {
                showNotification.value = false;
            }, 2000);
        }
    });
}
</script>
<template>
    <HomeLayout>
        <div class="min-h-screen bg-white py-16 ">
            <!-- notification -->
            <div>
                <p class="fixed top-24 right-4 bg-slate-700 text-white p-3 rounded-xl" v-if="showNotification">{{
                    notificationMessage }}</p>
            </div>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header Section -->
                <div class="mb-16 text-center">
                    <h1 class="mb-4 text-4xl font-bold text-gray-900">
                        Contactez-nous
                    </h1>
                    <p class="mx-auto max-w-2xl text-xl text-gray-600">
                        Vous avez des questions concernant nos services de location de voitures ? Nous sommes là
                        pour vous aider. Envoyez-nous un message et nous vous répondrons dans les plus brefs délais.
                    </p>
                </div>

                <div class="grid gap-12 lg:grid-cols-3">
                    <!-- Contact Form -->
                    <div class="lg:col-span-2">
                        <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                            <h2 class="mb-6 text-2xl font-bold text-gray-900">
                                Envoyez-nous un message
                            </h2>

                            <form class="space-y-6" @submit.prevent="sendTicket">
                                <!-- Name Field -->
                                <div>
                                    <label for="name" class="mb-2 block text-sm font-semibold text-gray-700">
                                        Nom et prénom
                                    </label>
                                    <input type="text" id="name" name="name"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        placeholder="Saisissez votre nom complet" v-model="form.name" />
                                    <span class="text-red-500" v-if="form.errors.name">{{ form.errors.name }}</span>
                                </div>

                                <!-- Email Field -->
                                <div>
                                    <label for="email" class="mb-2 block text-sm font-semibold text-gray-700">
                                        Adresse email
                                    </label>
                                    <input type="email" id="email" name="email"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        placeholder="Saisissez votre adresse e-mail" v-model="form.email" />
                                    <span class="text-red-500" v-if="form.errors.email">{{ form.errors.email }}</span>
                                </div>

                                <!-- Subject Field -->
                                <div>
                                    <label for="subject" class="mb-2 block text-sm font-semibold text-gray-700">
                                        Sujet
                                    </label>
                                    <input type="text" id="subject" name="subject"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        placeholder="Quel est l’objet de votre demande ?" v-model="form.subject" />
                                    <span class="text-red-500" v-if="form.errors.subject">{{ form.errors.subject
                                        }}</span>
                                </div>

                                <!-- Message Field -->
                                <div>
                                    <label for="message" class="mb-2 block text-sm font-semibold text-gray-700">
                                        Message
                                    </label>
                                    <textarea id="message" name="message" rows="6"
                                        class="resize-vertical w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        placeholder="Expliquez-nous comment nous pouvons vous aider..." v-model="form.message"></textarea>
                                    <span class="text-red-500" v-if="form.errors.message">{{ form.errors.message
                                        }}</span>
                                </div>

                                <!-- Submit Button -->
                                <div>
                                    <button type="submit"
                                        class="w-full cursor-pointer rounded-lg bg-orange-500 px-6 py-3 font-semibold text-white transition-colors duration-200 hover:bg-orange-600">
                                        Envoyer message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Contact Information Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-8">
                            <h3 class="mb-6 text-xl font-bold text-gray-900">
                                Entrer en contact
                            </h3>

                            <div class="space-y-6">
                                <!-- Phone -->
                                <div>
                                    <h4 class="mb-2 font-semibold text-gray-900">
                                        Téléphone
                                    </h4>
                                    <p class="text-gray-600">
                                        +1 (213) 123-4567
                                    </p>
                                </div>

                                <!-- Email -->
                                <div>
                                    <h4 class="mb-2 font-semibold text-gray-900">
                                        Email
                                    </h4>
                                    <p class="text-gray-600">
                                        info@easetech.dz
                                    </p>
                                </div>

                                <!-- Address -->
                                <div>
                                    <h4 class="mb-2 font-semibold text-gray-900">
                                        Adresse
                                    </h4>
                                    <p class="text-gray-600">
                                        123 Iheddaden Bejaia City
                                    </p>
                                </div>

                                <!-- Business Hours -->
                                <div>
                                    <h4 class="mb-2 font-semibold text-gray-900">
                                       Heures d'ouverture
                                    </h4>
                                    <div class="space-y-1 text-gray-600">
                                        <p>
                                            Du Dimanche au Jeudi : de 8h00 à 20h00
                                        </p>
                                        <p>Samedi : 9h00 - 18h00</p>
                                        <p>Vendredi : 8h00 - 12h00</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
