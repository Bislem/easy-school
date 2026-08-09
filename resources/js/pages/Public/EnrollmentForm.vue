<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { CalendarDays, Clock, Mail, MapPin, Users } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    enrollmentForm: any;
    confirmedCount: number;
    isAvailable: boolean;
}>();
const page = usePage();
const pending = computed(() =>
    Boolean((page.props.flash as any)?.enrollment_pending),
);
</script>

<template>
    <Head :title="enrollmentForm.title" />
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:py-12">
        <div class="mx-auto max-w-3xl">
            <img
                v-if="enrollmentForm.cover_url"
                :src="enrollmentForm.cover_url"
                :alt="enrollmentForm.title"
                class="mb-7 h-52 w-full rounded-2xl object-cover shadow-sm sm:h-72"
            />
            <div class="mb-7 text-center">
                <div
                    class="mx-auto grid size-12 place-items-center rounded-xl bg-slate-900 text-sm font-black text-white"
                >
                    ES
                </div>
                <p class="mt-3 text-sm font-semibold text-orange-600">
                    INSCRIPTION À UNE FORMATION
                </p>
                <h1 class="mt-2 text-3xl font-black text-slate-900">
                    {{ enrollmentForm.title }}
                </h1>
                <p class="mt-2 text-slate-600">
                    {{ enrollmentForm.course.title }}
                </p>
            </div>

            <div
                class="mb-6 grid gap-3 rounded-2xl border bg-white p-5 shadow-sm sm:grid-cols-2"
            >
                <p class="flex items-center gap-2 text-sm">
                    <CalendarDays class="size-4 text-orange-500" />Du
                    {{ enrollmentForm.start_date }} au
                    {{ enrollmentForm.end_date }}
                </p>
                <p class="flex items-center gap-2 text-sm">
                    <Users class="size-4 text-orange-500" />{{
                        enrollmentForm.teacher.name
                    }}
                </p>
                <p class="flex items-center gap-2 text-sm">
                    <Clock class="size-4 text-orange-500" />{{
                        enrollmentForm.course.duration_hours
                    }}
                    heures
                </p>
                <p
                    v-if="enrollmentForm.classroom"
                    class="flex items-center gap-2 text-sm"
                >
                    <MapPin class="size-4 text-orange-500" />{{
                        enrollmentForm.classroom.name
                    }}
                </p>
            </div>

            <div
                v-if="pending"
                class="rounded-2xl border border-green-200 bg-white p-7 text-center shadow-sm"
            >
                <Mail class="mx-auto size-12 text-green-600" />
                <h2 class="mt-4 text-2xl font-bold">
                    Vérifiez votre boîte e-mail
                </h2>
                <p class="mt-3 leading-7 text-slate-600">
                    Votre demande a bien été enregistrée. Cliquez sur le lien de
                    confirmation envoyé par e-mail pour terminer votre
                    inscription.
                </p>
                <div
                    class="mt-6 flex items-center justify-center gap-2 text-sm font-medium text-green-700"
                >
                    <span
                        class="grid size-6 place-items-center rounded-full bg-green-100"
                        >1</span
                    >
                    Formulaire rempli <span class="h-px w-8 bg-green-300"></span
                    ><span
                        class="grid size-6 place-items-center rounded-full bg-green-100"
                        >2</span
                    >
                    Confirmation e-mail
                </div>
            </div>

            <div
                v-else-if="!isAvailable"
                class="rounded-2xl border bg-white p-8 text-center shadow-sm"
            >
                <h2 class="text-2xl font-bold">Inscriptions fermées</h2>
                <p class="mt-3 text-slate-600">
                    Cette formation est complète ou les inscriptions ne sont
                    plus disponibles.
                </p>
            </div>

            <div
                v-else
                class="rounded-2xl border bg-white p-5 shadow-sm sm:p-8"
            >
                <div class="mb-6 flex items-center gap-3">
                    <span
                        class="grid size-9 place-items-center rounded-full bg-orange-100 font-bold text-orange-600"
                        >1</span
                    >
                    <div>
                        <h2 class="font-bold">Vos informations</h2>
                        <p class="text-sm text-slate-500">
                            Étape 1 sur 2 — la confirmation e-mail sera ensuite
                            obligatoire.
                        </p>
                    </div>
                </div>
                <Form
                    :action="`/inscription/${enrollmentForm.public_token}`"
                    method="post"
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="first_name">Prénom</Label
                            ><Input
                                id="first_name"
                                name="first_name"
                                class="mt-1"
                                required
                                autofocus
                            /><InputError
                                :message="errors.first_name"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="last_name">Nom</Label
                            ><Input
                                id="last_name"
                                name="last_name"
                                class="mt-1"
                                required
                            /><InputError
                                :message="errors.last_name"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label for="email">Adresse e-mail</Label
                        ><Input
                            id="email"
                            name="email"
                            type="email"
                            class="mt-1"
                            required
                            autocomplete="email"
                        /><InputError :message="errors.email" class="mt-1" />
                        <p class="mt-1 text-xs text-slate-500">
                            Un lien de confirmation sera envoyé à cette adresse.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="phone">Téléphone</Label
                            ><Input
                                id="phone"
                                name="phone"
                                type="tel"
                                class="mt-1"
                                required
                                autocomplete="tel"
                            /><InputError
                                :message="errors.phone"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="birth_date"
                                >Date de naissance (facultative)</Label
                            ><Input
                                id="birth_date"
                                name="birth_date"
                                type="date"
                                class="mt-1"
                            /><InputError
                                :message="errors.birth_date"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <Button
                        class="mt-2 w-full bg-orange-600 hover:bg-orange-700"
                        :disabled="processing"
                        >{{
                            processing
                                ? 'Envoi…'
                                : "Continuer et confirmer l'e-mail"
                        }}</Button
                    >
                </Form>
                <p class="mt-5 text-center text-xs text-slate-500">
                    {{ confirmedCount }} inscription(s) confirmée(s) sur
                    {{ enrollmentForm.max_students }} places.
                </p>
            </div>
        </div>
    </main>
</template>
