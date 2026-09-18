<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { request } from '@/routes/password';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Eye,
    EyeOff,
    GraduationCap,
    LoaderCircle,
    LockKeyhole,
    Mail,
    ShieldCheck,
    UsersRound,
} from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{ status?: string; canResetPassword: boolean }>();

const showPassword = ref(false);
</script>

<template>
    <Head title="Connexion parent" />

    <main
        class="relative grid min-h-screen place-items-center overflow-hidden bg-[#051c3a] px-5 py-10 text-white"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,#0b5278_0,transparent_32%),radial-gradient(circle_at_85%_15%,#0b5e69_0,transparent_28%)] opacity-60"
        ></div>

        <div class="relative w-full max-w-md">
            <Link
                href="/"
                class="mx-auto mb-8 flex w-fit items-center gap-3 text-2xl font-black"
            >
                <GraduationCap class="size-10 text-[#16d1bc]" />
                Easy School
            </Link>

            <section
                class="rounded-3xl bg-white p-6 text-[#092044] shadow-2xl shadow-black/30 sm:p-9"
            >
                <div class="text-center">
                    <span
                        class="mx-auto grid size-20 place-items-center rounded-full bg-teal-50 text-[#0e9e99]"
                    >
                        <UsersRound class="size-9" />
                    </span>
                    <p
                        class="mt-5 text-xs font-bold tracking-[0.2em] text-[#079c94] uppercase"
                    >
                        Espace parents
                    </p>
                    <h1 class="mt-2 text-3xl font-black">
                        Connexion au portail parent
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Consultez la scolarité et les informations de vos
                        enfants en toute sécurité.
                    </p>
                </div>

                <div
                    v-if="status"
                    class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center text-sm font-medium text-emerald-700"
                >
                    {{ status }}
                </div>

                <Form
                    action="/parent/login"
                    method="post"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="mt-7 space-y-5"
                >
                    <div>
                        <label
                            for="parent-email"
                            class="mb-2 block text-xs font-bold"
                            >Adresse e-mail</label
                        >
                        <div class="relative">
                            <Mail
                                class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                id="parent-email"
                                name="email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="parent@exemple.com"
                                class="h-13 w-full rounded-xl border border-slate-200 bg-white pr-4 pl-12 text-sm transition outline-none focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10"
                            />
                        </div>
                        <InputError :message="errors.email" class="mt-1" />
                    </div>

                    <div>
                        <div class="mb-2 flex justify-between gap-3">
                            <label
                                for="parent-password"
                                class="text-xs font-bold"
                                >Mot de passe</label
                            >
                            <TextLink
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-xs font-bold text-[#079c94]"
                                >Mot de passe oublié ?</TextLink
                            >
                        </div>
                        <div class="relative">
                            <LockKeyhole
                                class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                id="parent-password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="Votre mot de passe"
                                class="h-13 w-full rounded-xl border border-slate-200 bg-white pr-12 pl-12 text-sm transition outline-none focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10"
                            />
                            <button
                                type="button"
                                class="absolute top-1/2 right-4 -translate-y-1/2 text-slate-400 hover:text-[#079c94]"
                                :aria-label="
                                    showPassword
                                        ? 'Masquer le mot de passe'
                                        : 'Afficher le mot de passe'
                                "
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="size-5" />
                                <Eye v-else class="size-5" />
                            </button>
                        </div>
                        <InputError :message="errors.password" class="mt-1" />
                    </div>

                    <label
                        for="parent-remember"
                        class="flex cursor-pointer items-center gap-3 text-sm text-slate-700"
                    >
                        <Checkbox id="parent-remember" name="remember" />
                        <span>Se souvenir de moi</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="flex h-13 w-full items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-[#099e9a] to-[#17d2b8] font-bold text-white shadow-lg shadow-teal-100 transition hover:-translate-y-0.5 disabled:opacity-60"
                    >
                        <LoaderCircle
                            v-if="processing"
                            class="size-5 animate-spin"
                        />
                        <span>{{
                            processing ? 'Connexion…' : 'Accéder à mon espace'
                        }}</span>
                        <ArrowRight v-if="!processing" class="size-5" />
                    </button>
                </Form>

                <div
                    class="mt-6 flex gap-3 rounded-xl border border-cyan-100 bg-cyan-50/60 p-4"
                >
                    <ShieldCheck class="size-6 shrink-0 text-[#0aa39c]" />
                    <p class="text-xs leading-5 text-slate-500">
                        Utilisez les identifiants parent fournis par votre
                        établissement.
                    </p>
                </div>

                <Link
                    href="/login"
                    class="mt-6 flex items-center justify-center gap-2 text-sm font-semibold text-slate-500 hover:text-[#079c94]"
                >
                    <ArrowLeft class="size-4" /> Connexion établissement et
                    personnel
                </Link>
            </section>
        </div>
    </main>
</template>
