<script setup lang="ts">
import {
    publicLocale,
    setPublicLocale,
    translatePublicRoot,
    watchPublicLocale,
    type PublicLocale,
} from '@/composables/usePublicLocale';
import { home, login } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronDown,
    GraduationCap,
    Mail,
    MapPin,
    Menu,
    School,
    ShieldCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const mobileOpen = ref(false);
const root = ref<HTMLElement | null>(null);
const languageOpen = ref(false);
const connectionOpen = ref(false);
const languages: Array<{ value: PublicLocale; label: string; short: string }> =
    [
        { value: 'fr', label: 'Français', short: 'FR' },
        { value: 'en', label: 'English', short: 'EN' },
        { value: 'ar', label: 'العربية', short: 'AR' },
    ];
watchPublicLocale(() => root.value);

const toggleConnection = async () => {
    connectionOpen.value = !connectionOpen.value;
    languageOpen.value = false;
    await nextTick();
    if (root.value) translatePublicRoot(root.value);
};

const closeConnectionOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null;
    if (!target?.closest('[data-connection-menu]')) {
        connectionOpen.value = false;
    }
};

onMounted(async () => {
    document.addEventListener('click', closeConnectionOutside);
    await nextTick();
    if (root.value) translatePublicRoot(root.value);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeConnectionOutside);
    document.documentElement.dir = 'ltr';
});
const nav = [
    { label: 'Accueil', href: '/' },
    { label: 'Modules', href: '/#modules' },
    { label: 'Portail parents', href: '/#parents' },
    { label: 'Tarifs', href: '/pricing' },
    { label: 'Contact', href: '/contact' },
];
</script>

<template>
    <div ref="root" class="min-h-screen bg-white font-sans text-[#0a2246]">
        <header class="absolute inset-x-0 top-0 z-50 text-white">
            <nav
                class="mx-auto flex h-20 max-w-[1380px] items-center justify-between px-6 lg:px-10"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2.5 text-xl font-extrabold"
                    ><GraduationCap class="size-9 text-[#12d9be]" /><span
                        >Easy School</span
                    ></Link
                >
                <div class="hidden items-center gap-8 xl:flex">
                    <a
                        v-for="item in nav"
                        :key="item.label"
                        :href="item.href"
                        class="relative py-7 text-sm font-semibold text-white/85 transition hover:text-[#14d6bd]"
                        :class="
                            page.url === item.href ||
                            (item.href === '/' && page.url === '/')
                                ? 'text-[#14d6bd] after:absolute after:inset-x-0 after:bottom-4 after:h-0.5 after:bg-[#14d6bd]'
                                : ''
                        "
                        >{{ item.label }}</a
                    >
                </div>
                <div class="hidden items-center gap-5 lg:flex">
                    <div class="relative" data-no-translate>
                        <button
                            class="flex items-center gap-2 rounded-lg border border-white/20 px-3 py-2 text-xs font-black"
                            aria-label="Choisir la langue"
                            @click="
                                languageOpen = !languageOpen;
                                connectionOpen = false;
                            "
                        >
                            <span class="text-[#20d8bf]">◎</span>
                            {{ publicLocale.toUpperCase() }}
                        </button>
                        <div
                            v-if="languageOpen"
                            class="absolute top-full right-0 mt-2 w-36 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 text-[#0a2246] shadow-xl"
                        >
                            <button
                                v-for="language in languages"
                                :key="language.value"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-bold hover:bg-slate-100"
                                :class="{
                                    'bg-teal-50 text-teal-700':
                                        publicLocale === language.value,
                                }"
                                @click="
                                    setPublicLocale(language.value);
                                    languageOpen = false;
                                "
                            >
                                {{ language.label }}
                                <span>{{ language.short }}</span>
                            </button>
                        </div>
                    </div>
                    <Link
                        v-if="page.props.auth.user"
                        href="/dashboard"
                        class="text-sm font-semibold"
                        >Tableau de bord</Link
                    >
                    <template v-else>
                        <div class="relative" data-connection-menu>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-bold text-white transition hover:border-white/35 hover:bg-white/10"
                                :aria-expanded="connectionOpen"
                                aria-haspopup="menu"
                                @click="toggleConnection"
                            >
                                Connexion
                                <ChevronDown
                                    class="size-4 text-[#4de2ce] transition"
                                    :class="connectionOpen ? 'rotate-180' : ''"
                                />
                            </button>
                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="-translate-y-2 scale-95 opacity-0"
                                enter-to-class="translate-y-0 scale-100 opacity-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="translate-y-0 scale-100 opacity-100"
                                leave-to-class="-translate-y-2 scale-95 opacity-0"
                            >
                                <div
                                    v-if="connectionOpen"
                                    class="absolute top-full right-0 mt-3 w-[360px] origin-top-right overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 text-[#092447] shadow-[0_24px_70px_-18px_rgba(2,20,43,.55)]"
                                    role="menu"
                                >
                                    <div class="px-3 pt-2 pb-3">
                                        <p class="text-sm font-black">
                                            Choisissez votre espace
                                        </p>
                                        <p
                                            class="mt-1 text-[11px] text-slate-500"
                                        >
                                            Nous vous dirigerons vers la bonne
                                            page de connexion.
                                        </p>
                                    </div>
                                    <Link
                                        href="/parent/login"
                                        class="group flex items-center gap-3 rounded-xl border border-transparent p-3 transition hover:border-teal-100 hover:bg-teal-50"
                                        role="menuitem"
                                        @click="connectionOpen = false"
                                    >
                                        <span
                                            class="grid size-11 shrink-0 place-items-center rounded-xl bg-teal-100 text-teal-700"
                                            ><Users class="size-5"
                                        /></span>
                                        <span class="min-w-0 flex-1"
                                            ><b class="block text-sm"
                                                >Je suis parent</b
                                            ><span
                                                class="mt-0.5 block text-[10px] text-slate-500"
                                                >Suivre la scolarité de mes
                                                enfants</span
                                            ></span
                                        >
                                        <ArrowRight
                                            class="size-4 text-slate-300 transition group-hover:translate-x-1 group-hover:text-teal-600"
                                        />
                                    </Link>
                                    <Link
                                        :href="login()"
                                        class="group mt-1 flex items-center gap-3 rounded-xl border border-transparent p-3 transition hover:border-blue-100 hover:bg-blue-50"
                                        role="menuitem"
                                        @click="connectionOpen = false"
                                    >
                                        <span
                                            class="grid size-11 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-700"
                                            ><School class="size-5"
                                        /></span>
                                        <span class="min-w-0 flex-1"
                                            ><b class="block text-sm"
                                                >Je fais partie d’une école</b
                                            ><span
                                                class="mt-0.5 block text-[10px] text-slate-500"
                                                >Administration, équipe et
                                                élèves</span
                                            ></span
                                        >
                                        <ArrowRight
                                            class="size-4 text-slate-300 transition group-hover:translate-x-1 group-hover:text-blue-600"
                                        />
                                    </Link>
                                </div>
                            </Transition>
                        </div>
                        <Link
                            href="/demo"
                            class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#10bea9] to-[#11d4b5] px-6 py-3 text-sm font-bold shadow-lg"
                            >Demander une démo <ArrowRight class="size-4"
                        /></Link>
                    </template>
                </div>
                <button
                    class="lg:hidden"
                    aria-label="Ouvrir le menu"
                    @click="mobileOpen = !mobileOpen"
                >
                    <X v-if="mobileOpen" /><Menu v-else />
                </button>
            </nav>
            <div
                v-if="mobileOpen"
                class="mx-4 rounded-2xl border border-white/10 bg-[#061d3b]/95 p-4 shadow-2xl backdrop-blur lg:hidden"
            >
                <a
                    v-for="item in nav"
                    :key="item.label"
                    :href="item.href"
                    class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-white/10"
                    @click="mobileOpen = false"
                    >{{ item.label }}</a
                >
                <div
                    class="mt-3 grid gap-2 border-t border-white/10 pt-3"
                    data-connection-menu
                >
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/5 px-3 py-3 text-sm font-bold"
                        :aria-expanded="connectionOpen"
                        @click="toggleConnection"
                    >
                        Connexion
                        <ChevronDown
                            class="size-4 text-[#4de2ce] transition"
                            :class="connectionOpen ? 'rotate-180' : ''"
                        />
                    </button>
                    <div
                        v-if="connectionOpen"
                        class="grid gap-2 rounded-xl border border-white/10 bg-black/10 p-2"
                    >
                        <Link
                            href="/parent/login"
                            class="flex items-center gap-3 rounded-xl bg-white p-3 text-left text-[#092447]"
                            @click="mobileOpen = false"
                        >
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-teal-100 text-teal-700"
                                ><Users class="size-5"
                            /></span>
                            <span
                                ><b class="block text-sm">Je suis parent</b
                                ><span class="text-[10px] text-slate-500"
                                    >Accéder au portail parents</span
                                ></span
                            >
                        </Link>
                        <Link
                            :href="login()"
                            class="flex items-center gap-3 rounded-xl bg-white p-3 text-left text-[#092447]"
                            @click="mobileOpen = false"
                        >
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-700"
                                ><School class="size-5"
                            /></span>
                            <span
                                ><b class="block text-sm"
                                    >Je fais partie d’une école</b
                                ><span class="text-[10px] text-slate-500"
                                    >Équipe, administration ou élève</span
                                ></span
                            >
                        </Link>
                    </div>
                    <Link
                        href="/demo"
                        class="rounded-lg bg-[#12cbb2] px-3 py-3 text-center text-sm font-semibold"
                        >Demander une démo</Link
                    >
                </div>
                <div class="mt-2 grid grid-cols-3 gap-2" data-no-translate>
                    <button
                        v-for="language in languages"
                        :key="language.value"
                        class="rounded-lg border border-white/15 px-2 py-2 text-xs font-black"
                        :class="{
                            'bg-[#12cbb2]': publicLocale === language.value,
                        }"
                        @click="setPublicLocale(language.value)"
                    >
                        {{ language.short }}
                    </button>
                </div>
            </div>
        </header>
        <slot />
        <footer
            id="contact"
            class="rounded-t-[28px] bg-[#061d3b] text-slate-300"
        >
            <div
                class="mx-auto grid max-w-[1280px] gap-10 px-8 py-14 md:grid-cols-2 lg:grid-cols-[1.45fr_1fr_1fr_1.2fr] lg:py-16"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-xl font-extrabold text-white"
                    >
                        <GraduationCap class="size-8 text-[#12d9be]" /> Easy
                        School
                    </div>
                    <p class="mt-4 max-w-sm text-sm leading-6">
                        La plateforme qui relie la scolarité, l’administration,
                        les équipes et les parents dans un espace sécurisé.
                    </p>
                    <div
                        class="mt-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-[10px] font-bold text-cyan-200"
                    >
                        <ShieldCheck class="size-4" /> Conçu et accompagné en
                        Algérie
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Découvrir</h3>
                    <div class="mt-4 grid gap-2 text-sm">
                        <a href="/#modules">Fonctionnalités</a>
                        <a href="/#parents">Portail parents</a>
                        <Link href="/pricing">Tarifs</Link>
                        <Link href="/demo">Demander une démo</Link>
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Accès</h3>
                    <div class="mt-4 grid gap-2 text-sm">
                        <Link href="/parent/login">Espace parents</Link>
                        <Link :href="login()">Équipe & administration</Link>
                        <Link href="/register-school"
                            >Inscrire un établissement</Link
                        >
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Contact</h3>
                    <div class="mt-4 grid gap-3 text-sm">
                        <span class="flex gap-2"
                            ><Mail class="size-4" /> contact@easyschool.dz</span
                        ><span class="flex gap-2"
                            ><MapPin class="size-4" /> Alger, Algérie</span
                        >
                        <Link
                            href="/contact"
                            class="mt-1 font-semibold text-[#3ce0c8]"
                            >Nous écrire →</Link
                        >
                    </div>
                </div>
            </div>
            <div
                class="mx-auto flex max-w-[1280px] flex-col justify-between gap-3 border-t border-white/10 px-8 py-5 text-xs sm:flex-row"
            >
                <span>© 2026 Easy School. Tous droits réservés.</span
                ><span class="flex gap-6"
                    ><span>Plateforme SaaS sécurisée</span
                    ><span>Made in Algeria</span></span
                >
            </div>
        </footer>
    </div>
</template>
