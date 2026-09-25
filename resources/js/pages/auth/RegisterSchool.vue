<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import {
    publicLocale,
    setPublicLocale,
    translatePublicRoot,
    watchPublicLocale,
    type PublicLocale,
} from '@/composables/usePublicLocale';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    CheckCircle2,
    Eye,
    EyeOff,
    GraduationCap,
    LoaderCircle,
    LockKeyhole,
    Mail,
    Phone,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const root = ref<HTMLElement | null>(null);
const showPassword = ref(false);
const showConfirmation = ref(false);
const fieldClass =
    'h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10';
const languages: Array<{ value: PublicLocale; label: string }> = [
    { value: 'fr', label: 'FR' },
    { value: 'en', label: 'EN' },
    { value: 'ar', label: 'AR' },
];
let stopLocaleWatch: (() => void) | undefined;
onMounted(() => {
    stopLocaleWatch = watchPublicLocale(() => root.value);
    nextTick(() => translatePublicRoot(root.value));
});
onBeforeUnmount(() => stopLocaleWatch?.());
</script>

<template>
    <div ref="root">
        <Head title="Créer votre école" />
        <main class="min-h-screen bg-[#051c3a] px-5 py-6 text-white sm:px-8">
            <div
                class="pointer-events-none fixed inset-0 [background-image:radial-gradient(circle_at_18%_25%,#0a4770_0,transparent_30%),radial-gradient(circle_at_82%_15%,#113e69_0,transparent_27%)] opacity-40"
            ></div>
            <div class="relative mx-auto max-w-6xl">
                <header class="flex items-center justify-between gap-4">
                    <Link
                        href="/"
                        class="flex items-center gap-3 text-xl font-black"
                        ><GraduationCap class="size-9 text-[#16d1bc]" />Easy
                        School</Link
                    >
                    <div class="flex items-center gap-2">
                        <button
                            v-for="language in languages"
                            :key="language.value"
                            type="button"
                            class="rounded-lg px-2 py-1 text-xs font-bold"
                            :class="
                                publicLocale === language.value
                                    ? 'bg-[#16d1bc] text-[#05203e]'
                                    : 'bg-white/10'
                            "
                            @click="setPublicLocale(language.value)"
                        >
                            {{ language.label }}
                        </button>
                        <Link
                            href="/"
                            class="ml-2 flex items-center gap-2 text-sm font-semibold"
                            ><ArrowLeft class="size-4" /> Retour à
                            l'accueil</Link
                        >
                    </div>
                </header>
                <div
                    class="mt-10 grid items-start gap-10 lg:grid-cols-[.8fr_1.2fr]"
                >
                    <section class="pt-6">
                        <p class="font-bold text-[#15cfb8]">
                            30 jours gratuits, sans paiement
                        </p>
                        <h1
                            class="mt-4 text-4xl leading-tight font-black sm:text-5xl"
                        >
                            Créez votre école.<br /><span class="text-[#16cdb5]"
                                >Commencez immédiatement.</span
                            >
                        </h1>
                        <p class="mt-5 max-w-lg leading-7 text-slate-300">
                            Ouvrez votre espace Easy School et accédez à toutes
                            les fonctions essentielles pendant 30 jours. Aucun
                            plan ni justificatif de paiement demandé.
                        </p>
                        <div class="mt-8 space-y-4 text-sm">
                            <p class="flex items-center gap-3">
                                <CheckCircle2 class="size-5 text-[#16d1bc]" />
                                Activation immédiate
                            </p>
                            <p class="flex items-center gap-3">
                                <ShieldCheck class="size-5 text-[#16d1bc]" />
                                Données isolées et sécurisées
                            </p>
                            <p class="flex items-center gap-3">
                                <CheckCircle2 class="size-5 text-[#16d1bc]" />
                                Vos données restent conservées après l'essai
                            </p>
                        </div>
                    </section>
                    <section
                        class="rounded-[22px] bg-white p-6 text-[#092044] shadow-2xl sm:p-9"
                    >
                        <div class="text-center">
                            <span
                                class="mx-auto grid size-16 place-items-center rounded-full bg-[#e9faf8] text-[#0e9e99]"
                                ><Building2 class="size-8"
                            /></span>
                            <h2 class="mt-4 text-2xl font-black">
                                Créer votre école
                            </h2>
                            <p class="mt-2 text-sm text-slate-500">
                                Seulement les informations nécessaires pour
                                démarrer.
                            </p>
                        </div>
                        <Form
                            action="/register-school"
                            method="post"
                            v-slot="{ errors, processing }"
                            class="mt-8 space-y-5"
                        >
                            <div>
                                <label
                                    for="name"
                                    class="mb-2 block text-xs font-bold"
                                    >Nom de l'école *</label
                                >
                                <div class="relative">
                                    <Building2
                                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                    /><input
                                        id="name"
                                        name="name"
                                        required
                                        autofocus
                                        placeholder="Ex. École Les Horizons"
                                        :class="[fieldClass, 'pl-12']"
                                    />
                                </div>
                                <InputError
                                    :message="errors.name"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <label
                                    for="organization_type"
                                    class="mb-2 block text-xs font-bold"
                                    >Type d'établissement *</label
                                ><select
                                    id="organization_type"
                                    name="organization_type"
                                    required
                                    :class="fieldClass"
                                >
                                    <option value="private_school">
                                        École privée
                                    </option>
                                    <option value="training_center">
                                        Centre de formation
                                    </option>
                                    <option value="language_school">
                                        École de langues
                                    </option></select
                                ><InputError
                                    :message="errors.organization_type"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <label
                                    for="admin_name"
                                    class="mb-2 block text-xs font-bold"
                                    >Nom de l'administrateur *</label
                                >
                                <div class="relative">
                                    <UserRound
                                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                    /><input
                                        id="admin_name"
                                        name="admin_name"
                                        required
                                        autocomplete="name"
                                        placeholder="Votre nom complet"
                                        :class="[fieldClass, 'pl-12']"
                                    />
                                </div>
                                <InputError
                                    :message="errors.admin_name"
                                    class="mt-1"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="email"
                                        class="mb-2 block text-xs font-bold"
                                        >Adresse e-mail *</label
                                    >
                                    <div class="relative">
                                        <Mail
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            autocomplete="email"
                                            placeholder="admin@ecole.dz"
                                            :class="[fieldClass, 'pl-12']"
                                        />
                                    </div>
                                    <InputError
                                        :message="errors.email"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <label
                                        for="phone"
                                        class="mb-2 block text-xs font-bold"
                                        >Téléphone</label
                                    >
                                    <div class="relative">
                                        <Phone
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            placeholder="+213 5 00 00 00 00"
                                            :class="[fieldClass, 'pl-12']"
                                        />
                                    </div>
                                    <InputError
                                        :message="errors.phone"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="password"
                                        class="mb-2 block text-xs font-bold"
                                        >Mot de passe *</label
                                    >
                                    <div class="relative">
                                        <LockKeyhole
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="password"
                                            name="password"
                                            :type="
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            autocomplete="new-password"
                                            placeholder="8 caractères minimum"
                                            :class="[fieldClass, 'pr-12 pl-12']"
                                        /><button
                                            type="button"
                                            class="absolute top-1/2 right-4 -translate-y-1/2 text-slate-400"
                                            @click="
                                                showPassword = !showPassword
                                            "
                                        >
                                            <EyeOff
                                                v-if="showPassword"
                                                class="size-5"
                                            /><Eye v-else class="size-5" />
                                        </button>
                                    </div>
                                    <InputError
                                        :message="errors.password"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <label
                                        for="password_confirmation"
                                        class="mb-2 block text-xs font-bold"
                                        >Confirmation *</label
                                    >
                                    <div class="relative">
                                        <LockKeyhole
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            :type="
                                                showConfirmation
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            autocomplete="new-password"
                                            placeholder="Confirmez le mot de passe"
                                            :class="[fieldClass, 'pr-12 pl-12']"
                                        /><button
                                            type="button"
                                            class="absolute top-1/2 right-4 -translate-y-1/2 text-slate-400"
                                            @click="
                                                showConfirmation =
                                                    !showConfirmation
                                            "
                                        >
                                            <EyeOff
                                                v-if="showConfirmation"
                                                class="size-5"
                                            /><Eye v-else class="size-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="flex h-13 w-full items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-[#099e9a] to-[#17d2b8] font-bold text-white shadow-lg transition hover:-translate-y-0.5 disabled:opacity-60"
                            >
                                <LoaderCircle
                                    v-if="processing"
                                    class="size-5 animate-spin"
                                /><span>{{
                                    processing
                                        ? 'Création en cours…'
                                        : 'Créer mon école'
                                }}</span
                                ><ArrowRight
                                    v-if="!processing"
                                    class="size-5"
                                />
                            </button>
                            <p class="text-center text-sm text-slate-500">
                                Vous avez déjà un compte ?
                                <Link
                                    href="/login"
                                    class="font-bold text-[#078f88]"
                                    >Se connecter</Link
                                >
                            </p>
                        </Form>
                    </section>
                </div>
            </div>
        </main>
    </div>
</template>
