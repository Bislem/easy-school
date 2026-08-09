<script setup lang="ts">
import { about, contact, fleet, home, login } from '@/routes';
import { logout } from '@/routes';
import { index as adminCarsIndex } from '@/routes/admin/cars/index';
import { index as clientReservationsIndex } from '@/routes/client/reservations/index';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const $page = usePage();

const role = $page.props.auth.user?.role;

const dashboardLink =
    role === 'admin' ? adminCarsIndex() : clientReservationsIndex();
const mobileMenuOpen = ref(false);
const agency = computed(() => ($page.props as any).agency ?? {});
const agencyName = computed(() => agency.value.trading_name);
const agencyLogo = computed(() => agency.value.logo_url || '/logo/logo.png');
const agencyPhone = computed(() => agency.value.phone || '+1 (213) 123-4567');
const agencyEmail = computed(() => agency.value.email || 'hello@easetech.dz');
const agencyPrimaryColor = computed(() =>
    /^#[0-9A-Fa-f]{6}$/.test(agency.value.primary_color)
        ? agency.value.primary_color
        : '#f97316',
);
const clientLoginDisabled = computed(() =>
    Boolean(agency.value.client_login_disabled),
);
const agencyAddress = computed(() => {
    const values = [
        agency.value.address_line_1,
        agency.value.address_line_2,
        agency.value.postal_code,
        agency.value.city,
        agency.value.country,
    ].filter(Boolean);

    return values.length > 0 ? values.join(', ') : '123 Iheddaden Bejaia City';
});


const closeMobileMenu = () => {
    mobileMenuOpen.value = false
}

</script>

<template>
    <div class="public-site" :style="{ '--website-primary': agencyPrimaryColor }">
        <header class="sticky top-0 z-50 border-b border-gray-100 bg-white/95 shadow-sm backdrop-blur-md">
            <div class="bg-[var(--website-primary)] text-white shadow-sm"
                :style="{ backgroundColor: agencyPrimaryColor }">
                <div
                    class="mx-auto flex h-10 max-w-7xl items-center justify-between gap-4 px-4 text-xs sm:px-6 lg:px-8">
                    <a :href="`tel:${agencyPhone.replace(/\s+/g, '')}`"
                        class="group inline-flex min-w-0 items-center gap-2 font-medium transition-opacity hover:opacity-80">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.95.68l1.5 4.5a1 1 0 01-.5 1.21l-2.27 1.14a11.04 11.04 0 005.5 5.5l1.14-2.27a1 1 0 011.21-.5l4.5 1.5a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C9.16 21 3 14.84 3 7V5z" />
                            </svg>
                        </span>
                        <span class="truncate">{{ agencyPhone }}</span>
                    </a>

                    <span class="inline-flex min-w-0 items-center justify-end gap-2 text-right font-medium"
                        :title="agencyAddress">
                        <span class="hidden truncate sm:inline">{{
                            agencyAddress
                        }}</span>
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 21s7-5.25 7-12a7 7 0 10-14 0c0 6.75 7 12 7 12z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 11.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                            </svg>
                        </span>
                    </span>
                </div>
            </div>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav class="flex h-16 items-center justify-between">
                    <!--  Logo -->
                    <Link :href="home()" :class="{
                        'text-orange-500': $page.url === home().url,
                        'text-gray-700': $page.url !== home().url,
                    }" class="font-medium transition-colors hover:text-orange-500">
                        <div class="flex items-center gap-2">
                            <img :src="agencyLogo" :alt="agencyName" class="object-contain"
                                :class="{ 'h-15': agencyLogo, 'h-8': !agencyLogo }" />
                            <p v-if="agencyName && agencyName !== ''"
                                class="hidden max-w-36 truncate font-bold md:block">
                                {{ agencyName }}
                            </p>
                        </div>
                    </Link>

                    <!--  Navigation -->
                    <div class="hidden items-center space-x-8 md:flex">
                        <Link :href="home()" :class="{
                            'text-orange-500': $page.url === home().url,
                            'text-gray-700': $page.url !== home().url,
                        }" class="font-medium transition-colors hover:text-orange-500">
                            Accueil
                        </Link>
                        <Link :href="fleet()" :class="{
                            'text-orange-500':
                                $page.url.startsWith('/fleet'),
                            'text-gray-700':
                                !$page.url.startsWith('/fleet'),
                        }" class="font-medium transition-colors hover:text-orange-500">
                            Gamme de véhicules
                        </Link>
                        <Link :href="about()" :class="{
                            'text-orange-500': $page.url === '/about',
                            'text-gray-700': $page.url !== '/about',
                        }" class="font-medium transition-colors hover:text-orange-500">
                            À propos
                        </Link>
                        <Link :href="contact()" :class="{
                            'text-orange-500': $page.url === '/contact',
                            'text-gray-700': $page.url !== '/contact',
                        }" class="font-medium transition-colors hover:text-orange-500">
                            Contact
                        </Link>
                    </div>

                    <button type="button" @click="mobileMenuOpen = true" class="md:hidden flex items-center justify-center
                       w-11 h-11 rounded-lg
                       text-gray-700 hover:text-red-500
                       hover:bg-gray-100 transition" aria-label="Ouvrir le menu">
                        <!-- Hamburger -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-7 h-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>




                    <!-- Auth Buttons -->
                    <div class="hidden md:flex items-center space-x-3">
                        <Link v-if="$page.props.auth.user" :href="dashboardLink"
                            class="inline-flex items-center rounded bg-gray-50 px-2.5 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-100 hover:shadow-md md:rounded-xl md:px-6">
                            <svg class="h-4 w-4 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                                </path>
                            </svg>
                            <span class="hidden md:block">Tableau de bord</span>
                        </Link>

                        <template v-else-if="!clientLoginDisabled">
                            <Link :href="login()"
                                class="hidden md:inline-flex items-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:scale-105 hover:from-orange-600 hover:to-orange-700 hover:shadow-xl    ">
                                Se connecter
                            </Link>
                            <!-- <Link
                                :href="register()"
                                class="inline-flex items-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:scale-105 hover:from-orange-600 hover:to-orange-700 hover:shadow-xl"
                            >
                                Commencer
                            </Link> -->
                        </template>
                    </div>
                </nav>
            </div>
        </header>


        <!-- Mobile Menu Overlay -->
        <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0"
            enter-to-class="opacity-100" leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 bg-black/50 md:hidden" @click="closeMobileMenu"></div>
        </Transition>


        <!-- Mobile Sidebar -->
        <Transition enter-active-class="transition-transform duration-300 ease-out" enter-from-class="translate-x-full"
            enter-to-class="translate-x-0" leave-active-class="transition-transform duration-300 ease-in"
            leave-from-class="translate-x-0" leave-to-class="translate-x-full">
            <aside v-if="mobileMenuOpen" class="fixed right-0 top-0 z-50 h-full w-[300px]
               max-w-[85vw] bg-white shadow-2xl md:hidden">

                <!-- Sidebar Header -->
                <div class="flex h-[66px] items-center justify-between
                   border-b border-gray-200 px-5">
                    <span class="text-xl font-bold text-orange-500">
                        EasyRent
                    </span>

                    <button type="button" @click="closeMobileMenu" class="flex h-10 w-10 items-center justify-center
                       rounded-lg text-gray-600
                       transition hover:bg-gray-100 hover:text-orange-500" aria-label="Fermer le menu">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="h-6 w-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>


                <!-- Sidebar Navigation -->
                <nav class="px-5 py-6">

                    <div class="space-y-2">

                        <!-- Accueil -->
                        <Link :href="home()" @click="closeMobileMenu" :class="{
                            'bg-orange-50 text-orange-500':
                                $page.url === home().url,
                            'text-gray-700 hover:bg-gray-50':
                                $page.url !== home().url,
                        }" class="flex w-full items-center rounded-lg
                           px-4 py-3 font-medium transition-colors">
                            Accueil
                        </Link>


                        <!-- Fleet -->
                        <Link :href="fleet()" @click="closeMobileMenu" :class="{
                            'bg-orange-50 text-orange-500':
                                $page.url.startsWith('/fleet'),
                            'text-gray-700 hover:bg-gray-50':
                                !$page.url.startsWith('/fleet'),
                        }" class="flex w-full items-center rounded-lg
                           px-4 py-3 font-medium transition-colors">
                            Gamme de véhicules
                        </Link>


                        <!-- About -->
                        <Link :href="about()" @click="closeMobileMenu" :class="{
                            'bg-orange-50 text-orange-500':
                                $page.url === '/about',
                            'text-gray-700 hover:bg-gray-50':
                                $page.url !== '/about',
                        }" class="flex w-full items-center rounded-lg
                           px-4 py-3 font-medium transition-colors">
                            À propos
                        </Link>


                        <!-- Contact -->
                        <Link :href="contact()" @click="closeMobileMenu" :class="{
                            'bg-orange-50 text-orange-500':
                                $page.url === '/contact',
                            'text-gray-700 hover:bg-gray-50':
                                $page.url !== '/contact',
                        }" class="flex w-full items-center rounded-lg
                           px-4 py-3 font-medium transition-colors">
                            Contact
                        </Link>

                    </div>


                    <!-- Divider -->
                    <div class="my-6 border-t border-gray-200"></div>


                    <!-- Login -->
                    <template v-if="!$page.props.auth.user">

                        <Link :href="login()" @click="closeMobileMenu" class="flex w-full items-center justify-center
                   rounded-lg border border-gray-300
                   px-4 py-3 font-semibold text-gray-700
                   transition
                   hover:border-orange-500
                   hover:text-orange-500">
                            Se connecter
                        </Link>

                    </template>


                    <!-- ========================= -->
                    <!-- AUTHENTICATED USER -->
                    <!-- ========================= -->
                    <template v-else>

                        <!-- Dashboard -->
                        <Link :href="dashboardLink" @click="closeMobileMenu" class="flex w-full mb-5 items-center justify-center
                   rounded-lg bg-gray-50
                   px-4 py-3
                   font-semibold text-gray-700
                   transition
                   hover:bg-gray-100
                   hover:shadow-md">
                            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>

                            Tableau de bord
                        </Link>


                        <Link :href="logout()" @click="closeMobileMenu" class="flex w-full items-center justify-center
           rounded-lg border border-red-200
           px-4 py-3
           font-semibold text-red-500
           transition
           hover:border-red-300
           hover:bg-red-50">
                            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
                            </svg>

                            Se déconnecter
                        </Link>

                    </template>


                </nav>
            </aside>
        </Transition>

        <slot />

        <!--  Footer -->
        <footer class="bg-gray-900 py-16 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-12 md:grid-cols-4">
                    <div class="space-y-6">
                        <div class="flex items-center space-x-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-orange-600">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="max-w-44 truncate text-xl font-bold">
                                    {{ agencyName }}
                                </h3>
                                <p class="text-xs font-medium text-gray-400">
                                    Voiture de Lux
                                </p>
                            </div>
                        </div>
                        <p class="leading-relaxed text-gray-400">
                            Service de location de voitures haut de gamme
                            proposant des véhicules de luxe et fiables pour tous
                            vos besoins de transport avec un service client
                            exceptionnel.
                        </p>
                    </div>

                    <div class="space-y-6">
                        <h4 class="text-lg font-semibold">Services</h4>
                        <ul class="space-y-3 text-gray-400">
                            <li>
                                <a href="#" class="transition-colors hover:text-orange-500">Location de voitures de
                                    luxe</a>
                            </li>
                            <li>
                                <a href="#" class="transition-colors hover:text-orange-500">Location longue durée</a>
                            </li>
                            <li>
                                <a href="#" class="transition-colors hover:text-orange-500">Solutions pour
                                    entreprises</a>
                            </li>
                            <li>
                                <a href="#" class="transition-colors hover:text-orange-500">Transferts aéroport</a>
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-6">
                        <h4 class="text-lg font-semibold">Assistance</h4>
                        <ul class="space-y-3 text-gray-400">
                            <li>
                                <a :href="contact.url()" class="transition-colors hover:text-orange-500">Nous
                                    contacter</a>
                            </li>
                            <li>
                                <a href="#" class="transition-colors hover:text-orange-500">Centre d'aide</a>
                            </li>
                            <li>
                                <a href="#" class="transition-colors hover:text-orange-500">Conditions générales</a>
                            </li>
                            <li>
                                <a href="#" class="transition-colors hover:text-orange-500">Politique de
                                    confidentialité</a>
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-6">
                        <h4 class="text-lg font-semibold">
                            Informations de contact
                        </h4>
                        <div class="space-y-3 text-gray-400">
                            <div class="flex items-center space-x-3">
                                <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                    </path>
                                </svg>
                                <a :href="`tel:${agencyPhone.replace(/\s+/g, '')}`" class="hover:text-orange-500">{{
                                    agencyPhone
                                }}</a>
                            </div>
                            <div class="flex items-center space-x-3">
                                <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                    </path>
                                </svg>
                                <a :href="`mailto:${agencyEmail}`" class="hover:text-orange-500">{{ agencyEmail }}</a>
                            </div>
                            <div class="flex items-center space-x-3">
                                <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>{{ agencyAddress }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-2 border-t border-gray-800 pt-8">
                    <p class="text-center text-gray-400">
                        &copy; 2026 {{ agencyName }}. Tous droits réservés.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
