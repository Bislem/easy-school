<script setup lang="ts">
import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import HomeLayout from '@/layouts/HomeLayout.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
</script>

<template>
    <HomeLayout>
        <Head title="Créer un compte" />

        <div
            class="flex min-h-screen items-center justify-center bg-white px-4 py-12 sm:px-6 lg:px-8"
        >
            <div class="w-full max-w-3xl space-y-8">
                <!-- Header -->
                <div class="text-center">
                    <h2 class="mb-2 text-3xl font-bold text-gray-900">
                        Créer un compte
                    </h2>
                    <p class="text-gray-600">
                        Join us and start your car rental journey
                    </p>
                </div>

                <!-- Form -->
                <div
                    class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
                >
                    <Form
                        v-bind="RegisteredUserController.store.form()"
                        :reset-on-success="[
                            'password',
                            'password_confirmation',
                        ]"
                        v-slot="{ errors, processing }"
                        class="space-y-6"
                    >
                        <!-- Name Field -->
                        <div>
                            <Label
                                for="name"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                Nom complet
                            </Label>
                            <Input
                                id="name"
                                type="text"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="name"
                                name="name"
                                placeholder="Saisissez votre nom complet"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                            />
                            <InputError
                                :message="errors.name"
                                class="mt-1 text-sm text-red-600"
                            />
                        </div>

                        <!-- Email Field -->
                        <div>
                            <Label
                                for="email"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                Adresse e-mail
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                required
                                :tabindex="2"
                                autocomplete="email"
                                name="email"
                                placeholder="Saisissez votre adresse e-mail"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                            />
                            <InputError
                                :message="errors.email"
                                class="mt-1 text-sm text-red-600"
                            />
                        </div>

                        <!-- Phone Field -->
                        <div>
                            <Label for="phone" class="mb-2 block text-sm font-medium text-gray-700">Numéro de téléphone</Label>
                            <Input id="phone" type="tel" required name="phone" maxlength="50" autocomplete="tel" placeholder="Ex. 0550 00 00 00" class="w-full" />
                            <InputError :message="errors.phone" class="mt-1 text-sm text-red-600" />
                        </div>

                        <!-- Driving License Fields -->
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label for="birth_date" class="mb-2 block text-sm font-medium text-gray-700">Date de naissance</Label>
                                <Input id="birth_date" type="date" required name="birth_date" class="w-full" />
                                <InputError :message="errors.birth_date" class="mt-1 text-sm text-red-600" />
                            </div>
                            <div>
                                <Label for="driving_license_number" class="mb-2 block text-sm font-medium text-gray-700">Numéro du permis de conduire</Label>
                                <Input id="driving_license_number" type="text" required name="driving_license_number" maxlength="100" class="w-full" />
                                <InputError :message="errors.driving_license_number" class="mt-1 text-sm text-red-600" />
                            </div>
                            <div>
                                <Label for="driving_license_delivered_at" class="mb-2 block text-sm font-medium text-gray-700">Date de délivrance</Label>
                                <Input id="driving_license_delivered_at" type="date" required name="driving_license_delivered_at" class="w-full" />
                                <InputError :message="errors.driving_license_delivered_at" class="mt-1 text-sm text-red-600" />
                            </div>
                            <div>
                                <Label for="driving_license_authority" class="mb-2 block text-sm font-medium text-gray-700">Autorité de délivrance</Label>
                                <Input id="driving_license_authority" type="text" required name="driving_license_authority" maxlength="255" placeholder="Ex. Daïra d'Alger" class="w-full" />
                                <InputError :message="errors.driving_license_authority" class="mt-1 text-sm text-red-600" />
                            </div>
                            <div class="sm:col-span-2">
                                <Label for="driving_license_copy" class="mb-2 block text-sm font-medium text-gray-700">Copie du permis de conduire</Label>
                                <input id="driving_license_copy" type="file" required name="driving_license_copy" accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" />
                                <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP ou PDF, maximum 5 Mo.</p>
                                <InputError :message="errors.driving_license_copy" class="mt-1 text-sm text-red-600" />
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div>
                            <Label
                                for="password"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                Mot de passe
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                required
                                :tabindex="3"
                                autocomplete="new-password"
                                name="password"
                                placeholder="Créez un mot de passe"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                            />
                            <InputError
                                :message="errors.password"
                                class="mt-1 text-sm text-red-600"
                            />
                        </div>

                        <!-- Confirm Password Field -->
                        <div>
                            <Label
                                for="password_confirmation"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                Confirmer le mot de passe
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                :tabindex="4"
                                autocomplete="new-password"
                                name="password_confirmation"
                                placeholder="Confirmez votre mot de passe"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                                class="mt-1 text-sm text-red-600"
                            />
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-orange-500 px-4 py-3 font-semibold text-white transition-colors duration-200 hover:bg-orange-600"
                            tabindex="5"
                            :disabled="processing"
                            data-test="register-user-button"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="mr-2 h-5 w-5 animate-spin"
                            />
                            Créer un compte
                        </Button>

                        <!-- Login Link -->
                        <div class="border-t border-gray-200 pt-4 text-center">
                            <p class="text-sm text-gray-600">
                                Vous avez déjà un compte ?
                                <TextLink
                                    :href="login()"
                                    class="font-medium text-orange-500 transition-colors hover:text-orange-600"
                                    :tabindex="6"
                                >
                                    Connectez-vous ici
                                </TextLink>
                            </p>
                        </div>
                    </Form>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
