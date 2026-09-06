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
    GraduationCap,
    Mail,
    MapPin,
    Menu,
    Phone,
    X,
} from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const mobileOpen = ref(false);
const root = ref<HTMLElement | null>(null);
const languageOpen = ref(false);
const languages: Array<{ value: PublicLocale; label: string; short: string }> =
    [
        { value: 'fr', label: 'Français', short: 'FR' },
        { value: 'en', label: 'English', short: 'EN' },
        { value: 'ar', label: 'العربية', short: 'AR' },
    ];
watchPublicLocale(() => root.value);
onMounted(async () => {
    await nextTick();
    if (root.value) translatePublicRoot(root.value);
});
onBeforeUnmount(() => {
    document.documentElement.dir = 'ltr';
});
const nav = [
    { label: 'Accueil', href: '/' },
    { label: 'Modules', href: '/#modules' },
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
                            @click="languageOpen = !languageOpen"
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
                    <template v-else
                        ><Link :href="login()" class="text-sm font-semibold"
                            >Se connecter</Link
                        ><Link
                            href="/demo"
                            class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#10bea9] to-[#11d4b5] px-6 py-3 text-sm font-bold shadow-lg"
                            >Demander une démo
                            <ArrowRight class="size-4" /></Link
                    ></template>
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
                    class="mt-3 grid grid-cols-2 gap-2 border-t border-white/10 pt-3"
                >
                    <Link
                        :href="login()"
                        class="rounded-lg border border-white/20 px-3 py-3 text-center text-sm font-semibold"
                        >Connexion</Link
                    ><Link
                        href="/demo"
                        class="rounded-lg bg-[#12cbb2] px-3 py-3 text-center text-sm font-semibold"
                        >Démo</Link
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
                class="mx-auto grid max-w-[1380px] gap-10 px-8 py-14 md:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr_1.2fr]"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-xl font-extrabold text-white"
                    >
                        <GraduationCap class="size-8 text-[#12d9be]" /> Easy
                        School
                    </div>
                    <p class="mt-4 max-w-xs text-sm leading-6">
                        La plateforme SaaS complète pour la gestion des
                        établissements scolaires et centres de formation.
                    </p>
                    <div class="mt-5 flex gap-3">
                        <span
                            v-for="s in ['f', '▶', '◎']"
                            :key="s"
                            class="grid size-8 place-items-center rounded-md bg-[#0d3c65] text-xs text-white"
                            >{{ s }}</span
                        >
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Navigation</h3>
                    <div class="mt-4 grid gap-2 text-sm">
                        <a
                            v-for="item in nav"
                            :key="item.label"
                            :href="item.href"
                            >{{ item.label }}</a
                        >
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Ressources</h3>
                    <div class="mt-4 grid gap-2 text-sm">
                        <span>Blog</span><span>Centre d'aide</span
                        ><span>Documentation</span><span>Guides</span
                        ><span>FAQ</span>
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Entreprise</h3>
                    <div class="mt-4 grid gap-2 text-sm">
                        <span>À propos</span><span>Carrières</span
                        ><span>Partenaires</span><span>Presse</span>
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Contact</h3>
                    <div class="mt-4 grid gap-3 text-sm">
                        <span class="flex gap-2"
                            ><Mail class="size-4" /> contact@easyschool.dz</span
                        ><span class="flex gap-2"
                            ><Phone class="size-4" /> +213 555 12 34 56</span
                        ><span class="flex gap-2"
                            ><MapPin class="size-4" /> Alger, Algérie</span
                        >
                    </div>
                </div>
            </div>
            <div
                class="mx-auto flex max-w-[1380px] flex-col justify-between gap-3 border-t border-white/10 px-8 py-5 text-xs sm:flex-row"
            >
                <span>© 2026 Easy School. Tous droits réservés.</span
                ><span class="flex gap-6"
                    ><span>Confidentialité</span
                    ><span>Conditions d'utilisation</span></span
                >
            </div>
        </footer>
    </div>
</template>
