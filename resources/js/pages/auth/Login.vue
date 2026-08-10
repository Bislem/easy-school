<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    LoaderCircle,
    Shield,
    User,
} from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const isDemoOpen = ref(false);
</script>

<template>
    <Head title="Se connecter" />

    <main
        class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8 sm:px-6 sm:py-12"
    >
        <div class="w-full max-w-md space-y-6 sm:space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div
                    class="mx-auto mb-5 grid size-12 place-items-center rounded-xl bg-primary text-sm font-black text-primary-foreground shadow-lg"
                >
                    ES
                </div>
                <h1 class="mb-2 text-3xl font-bold text-gray-900">
                    Bon retour parmi nous
                </h1>
                <p class="text-gray-600">
                    Connectez-vous à votre compte pour continuer
                </p>
            </div>

            <!-- Demo Credentials (Collapsible) -->
            <div
                class="overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 shadow-sm"
            >
                <!-- Toggle Button -->
                <button
                    @click="isDemoOpen = !isDemoOpen"
                    type="button"
                    class="flex w-full items-center justify-between px-6 py-4 transition-colors hover:bg-blue-100/50"
                >
                    <div class="flex items-center space-x-3">
                        <div class="rounded-full bg-blue-100 p-2">
                            <svg
                                class="h-4 w-4 text-blue-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-gray-900">
                            Comptes de démonstration
                        </span>
                    </div>
                    <ChevronDown
                        v-if="!isDemoOpen"
                        class="h-5 w-5 text-gray-600 transition-transform"
                    />
                    <ChevronUp
                        v-else
                        class="h-5 w-5 text-gray-600 transition-transform"
                    />
                </button>

                <!-- Collapsible Content -->
                <div v-show="isDemoOpen" class="space-y-3 px-6 pb-6">
                    <!-- Teacher Demo -->
                    <div class="mt-2 rounded-lg bg-white p-3 shadow-sm">
                        <div class="mb-2 flex items-center space-x-2">
                            <User class="h-4 w-4 text-gray-600" />
                            <span
                                class="text-xs font-semibold tracking-wide text-gray-700 uppercase"
                            >
                                Accès enseignant
                            </span>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div
                                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span class="text-gray-600">Email:</span>
                                <code
                                    class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs break-all text-gray-800"
                                >
                                    teacher@easyschool.test
                                </code>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-gray-600"
                                    >Mot de passe :</span
                                >
                                <code
                                    class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800"
                                >
                                    password
                                </code>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Demo -->
                    <div class="rounded-lg bg-white p-3 shadow-sm">
                        <div class="mb-2 flex items-center space-x-2">
                            <Shield class="h-4 w-4 text-primary" />
                            <span
                                class="text-xs font-semibold tracking-wide text-primary uppercase"
                            >
                                Accès administrateur
                            </span>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div
                                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span class="text-gray-600">Email:</span>
                                <code
                                    class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs break-all text-gray-800"
                                >
                                    admin@easyschool.test
                                </code>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-gray-600"
                                    >Mot de passe :</span
                                >
                                <code
                                    class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800"
                                >
                                    password
                                </code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Message -->
            <div
                v-if="status"
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-center"
            >
                <p class="text-sm font-medium text-green-800">
                    {{ status }}
                </p>
            </div>

            <!-- Login Form -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8"
            >
                <Form
                    v-bind="AuthenticatedSessionController.store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <!-- Email Field -->
                    <div>
                        <Label
                            for="email"
                            class="mb-2 block text-sm font-semibold text-gray-900"
                        >
                            Adresse e-mail
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="Saisissez votre adresse e-mail"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-primary focus:ring-2 focus:ring-primary"
                        />
                        <InputError :message="errors.email" class="mt-1" />
                    </div>

                    <!-- Password Field -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <Label
                                for="password"
                                class="block text-sm font-semibold text-gray-900"
                            >
                                Mot de passe
                            </Label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-sm font-medium text-primary hover:opacity-80"
                                :tabindex="5"
                            >
                                Mot de passe oublié ?
                            </TextLink>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Saisissez votre mot de passe"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-primary focus:ring-2 focus:ring-primary"
                        />
                        <InputError :message="errors.password" class="mt-1" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <Label
                            for="remember"
                            class="flex cursor-pointer items-center space-x-3"
                        >
                            <Checkbox
                                id="remember"
                                name="remember"
                                :tabindex="3"
                                class="rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <span class="text-sm text-gray-700"
                                >Se souvenir de moi pendant 30 jours</span
                            >
                        </Label>
                    </div>

                    <!-- Submit Button -->
                    <Button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-3 font-semibold text-primary-foreground transition-opacity duration-200 hover:opacity-90"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <LoaderCircle
                            v-if="processing"
                            class="mr-2 h-5 w-5 animate-spin"
                        />
                        {{ processing ? 'Connexion…' : 'Se connecter' }}
                    </Button>
                </Form>
            </div>

            <!-- Additional Info -->
            <div class="text-center">
                <p class="text-xs text-gray-500">
                    En vous connectant, vous acceptez nos conditions
                    d’utilisation et notre politique de confidentialité
                </p>
            </div>
        </div>
    </main>
</template>
