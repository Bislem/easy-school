<script setup lang="ts">
import { home, login } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { Mail, MapPin, Menu, Phone, X } from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage();
const mobileOpen = ref(false);
const nav = [
    { label: 'Accueil', href: '/' },
    { label: 'Formations', href: '/#formations' },
    { label: 'À propos', href: '/#about' },
    { label: 'Formateurs', href: '/#formateurs' },
    { label: 'Contact', href: '/#contact' },
];
</script>

<template>
    <div class="min-h-screen bg-white text-[#101b36]">
        <div class="bg-[#f4511e] text-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 text-xs sm:px-6"
            >
                <div class="flex gap-5">
                    <span class="flex items-center gap-1.5"
                        ><Phone class="size-3.5" /> +213 560 12 34 56</span
                    ><span class="hidden items-center gap-1.5 sm:flex"
                        ><Mail class="size-3.5" /> contact@easyschool.dz</span
                    >
                </div>
                <span class="flex items-center gap-1.5"
                    ><MapPin class="size-3.5" /> Béjaïa, Algérie</span
                >
            </div>
        </div>
        <header
            class="sticky top-0 z-50 border-b border-slate-100 bg-white/95 backdrop-blur"
        >
            <nav
                class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2 text-xl font-extrabold"
                >
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-[#101b36] text-white"
                        >ES</span
                    >
                    <span>Easy<span class="text-[#f4511e]">School</span></span>
                </Link>
                <div class="hidden items-center gap-8 lg:flex">
                    <a
                        v-for="item in nav"
                        :key="item.label"
                        :href="item.href"
                        class="text-sm font-semibold text-slate-600 transition hover:text-[#f4511e]"
                        >{{ item.label }}</a
                    >
                </div>
                <div class="hidden items-center gap-3 lg:flex">
                    <Link
                        v-if="page.props.auth.user"
                        href="/dashboard"
                        class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold hover:border-[#f4511e]"
                        >Espace personnel</Link
                    >
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold hover:border-[#f4511e]"
                            >Connexion</Link
                        >
                        <Link
                            :href="login()"
                            class="rounded-xl bg-[#f4511e] px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-200"
                            >Connexion</Link
                        >
                    </template>
                </div>
                <button
                    class="lg:hidden"
                    aria-label="Menu"
                    @click="mobileOpen = !mobileOpen"
                >
                    <X v-if="mobileOpen" /><Menu v-else />
                </button>
            </nav>
            <div v-if="mobileOpen" class="border-t bg-white p-4 lg:hidden">
                <div class="flex flex-col gap-1">
                    <a
                        v-for="item in nav"
                        :key="item.label"
                        :href="item.href"
                        class="rounded-lg px-4 py-3 font-medium hover:bg-orange-50"
                        @click="mobileOpen = false"
                        >{{ item.label }}</a
                    >
                </div>
                <div class="mt-3 grid gap-2 border-t pt-3">
                    <Link
                        v-if="page.props.auth.user"
                        href="/dashboard"
                        class="rounded-lg bg-[#101b36] px-4 py-3 text-center font-semibold text-white"
                        >Tableau de bord</Link
                    >
                    <template v-else
                        ><Link
                            :href="login()"
                            class="rounded-lg border px-4 py-3 text-center font-semibold"
                            >Connexion</Link
                        ><Link
                            :href="login()"
                            class="rounded-lg bg-[#f4511e] px-4 py-3 text-center font-semibold text-white"
                            >Connexion</Link
                        ></template
                    >
                </div>
            </div>
        </header>
        <slot />
        <footer id="contact" class="bg-[#0d1830] text-slate-300">
            <div
                class="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-4"
            >
                <div>
                    <div class="text-xl font-extrabold text-white">
                        Easy<span class="text-[#f4511e]">School</span>
                    </div>
                    <p class="mt-4 text-sm leading-6">
                        Centre de formation moderne à Béjaïa. Des compétences
                        pratiques pour construire votre avenir.
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-white">Navigation</h3>
                    <div class="mt-4 grid gap-2 text-sm">
                        <a
                            v-for="item in nav"
                            :key="item.label"
                            :href="item.href"
                            class="hover:text-white"
                            >{{ item.label }}</a
                        >
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Formations</h3>
                    <div class="mt-4 grid gap-2 text-sm">
                        <span>Développement web</span
                        ><span>Design graphique</span
                        ><span>Marketing digital</span><span>Langues</span>
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-white">Contact</h3>
                    <div class="mt-4 grid gap-3 text-sm">
                        <span class="flex gap-2"
                            ><Phone class="size-4" /> +213 560 12 34 56</span
                        ><span class="flex gap-2"
                            ><Mail class="size-4" /> contact@easyschool.dz</span
                        ><span class="flex gap-2"
                            ><MapPin class="size-4" /> Béjaïa, Algérie</span
                        >
                    </div>
                </div>
            </div>
            <div class="border-t border-white/10 py-5 text-center text-xs">
                © 2026 EasySchool. Tous droits réservés.
            </div>
        </footer>
    </div>
</template>
