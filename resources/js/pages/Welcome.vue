<script setup lang="ts">
import CarCard from '@/components/CarCard.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { fleet } from '@/routes';
import { about } from '@/routes';

interface Car {
    id: number;
    make: string;
    model: string;
    year: number;
    price_per_day: string;
    description: string;
    fuel_type: string;
    image_url: string;
    color?: string;
    status?: string;
    license_plate?: string;
    image?: string;
}

const $page = usePage();
const homeCars = $page.props.homeCars as Car[];
</script>

<template>

    <Head>
        <title>Ease Rent Car - Service premium de location de voitures</title>
        <meta name="description"
            content="Ease Rent Car est une plateforme premium proposant des solutions fiables de location de voitures, de l’économique au luxe, pour de courtes ou longues durées." />
    </Head>

    <HomeLayout>
        <main>
            <!--  Hero Section with Light Background -->
            <section class="relative overflow-hidden bg-gradient-to-br from-gray-50 via-white to-gray-100 py-12">
                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-5">
                    <div class="absolute inset-0" style="
                            background-image: radial-gradient(
                                circle at 1px 1px,
                                rgba(0, 0, 0, 0.15) 1px,
                                transparent 0
                            );
                            background-size: 20px 20px;
                        "></div>
                </div>

                <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-16 lg:grid-cols-2">
                        <!--  Left Content -->
                        <div class="space-y-10">
                            <div class="space-y-6">
                                <div
                                    class="inline-flex items-center rounded-full bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700 ring-1 ring-orange-200">
                                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Expérience de location de voiture haut de gamme
                                </div>

                                <h1 class="text-3xl leading-tight font-bold text-gray-900 lg:text-6xl">
                                    Conduisez votre
                                    <span
                                        class="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent">
                                        Rêves
                                    </span>
                                </h1>

                                <p class="max-w-lg text-lg leading-relaxed text-gray-600">
                                    Découvrez le luxe et la fiabilité avec notre
                                    flotte haut de gamme. Des réunions d'affaires aux
                                    escapades du week-end, trouvez le véhicule idéal
                                    pour chaque trajet.
                                </p>
                            </div>

                            <div class="flex flex-col gap-4 sm:flex-row">
                                <a :href="fleet.url()"
                                    class="group cursor-pointer inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-600  text-md px-5 py-2 font-semibold text-white shadow-xl transition-all duration-200 hover:scale-105 hover:from-orange-600 hover:to-orange-700 hover:shadow-2xl">
                                    <svg class="mr-2 h-5 w-5 transition-transform group-hover:translate-x-1" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                    Nos véhicules
                                </a>
                                <a :href="about.url()"
                                    class="inline-flex cursor-pointer items-center justify-center rounded-xl border-2 border-gray-300 bg-white text-md px-5 py-2 font-semibold text-gray-700 transition-all duration-200 hover:border-orange-500 hover:text-orange-600 hover:shadow-lg">
                                    En savoir plus
                                </a>
                            </div>

                            <!--  Stats -->
                            <div class="grid grid-cols-3 gap-8 border-t border-gray-200 pt-10">
                                <div class="text-center">
                                    <div
                                        class="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-4xl font-bold text-transparent">
                                        1000+
                                    </div>
                                    <div class="mt-1 text-sm font-medium text-gray-600">
                                        Clients satisfaits
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-4xl font-bold text-transparent">
                                        150+
                                    </div>
                                    <div class="mt-1 text-sm font-medium text-gray-600">
                                        Voitures haut de gamme
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-4xl font-bold text-transparent">
                                        24/7
                                    </div>
                                    <div class="mt-1 text-sm font-medium text-gray-600">
                                        Assistance
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Image - Optimized for Dark Isometric Image -->
                        <div class="flex justify-center lg:justify-end">
                            <div class="relative">
                                <div
                                    class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-orange-500/20 to-orange-600/20 blur-2xl">
                                </div>
                                <img src="/images/hero_image.png" alt="Garage de voitures premium en vue isométrique"
                                    class="relative h-auto max-w-full rounded-2xl drop-shadow-2xl" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!--  Featured Cars Section -->
            <section id="fleet" class="bg-white py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-20 text-center">
                        <div
                            class="mb-6 inline-flex items-center rounded-full bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700 ring-1 ring-orange-200">
                            Notre collection Premium
                        </div>
                        <h2 class="mb-6 text-4xl font-bold text-gray-900 lg:text-5xl">
                            Découvrez notre
                            <span class="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent">
                                Flotte d'élite
                            </span>
                        </h2>
                        <p class="mx-auto max-w-3xl text-xl leading-relaxed text-gray-600">
                            Chaque véhicule de notre collection est méticuleusement

                            entretenu et équipé de fonctionnalités haut de gamme pour

                            garantir un voyage tout simplement exceptionnel.
                        </p>
                    </div>

                    <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                        <CarCard v-for="car in homeCars" :key="car.id" :car="car" />
                    </div>

                    <div class="mt-16 text-center">
                        <a :href="fleet.url()"
                            class="inline-flex cursor-pointer items-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 px-8 py-4 font-semibold text-white shadow-xl transition-all duration-200 hover:scale-105 hover:from-orange-600 hover:to-orange-700 hover:shadow-2xl">
                            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                </path>
                            </svg>
                            Voir la flotte complète
                        </a>
                    </div>
                </div>
            </section>

            <!--  Features Section -->
            <section id="services" class="bg-gray-50 py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-20 text-center">
                        <h2 class="mb-6 text-4xl font-bold text-gray-900 lg:text-5xl">
                            Pourquoi choisir
                            <span class="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent">
                                Ease Rent </span>?
                        </h2>
                        <p class="mx-auto max-w-2xl text-xl text-gray-600">
                            Nous nous engageons à vous offrir une expérience de location de voiture inégalée

                            avec un service haut de gamme à chaque
                            point de contact.
                        </p>
                    </div>

                    <div class="grid gap-12 md:grid-cols-3">
                        <div class="group text-center">
                            <div
                                class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 shadow-xl transition-transform duration-200 group-hover:scale-110">
                                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="mb-4 text-2xl font-bold text-gray-900">
                                Qualité supérieure
                            </h3>
                            <p class="leading-relaxed text-gray-600">
                                Chaque véhicule fait l'objet d'une inspection complète

                                et d'un entretien afin de garantir votre sécurité,

                                votre confort et votre tranquillité d'esprit.
                            </p>
                        </div>

                        <div class="group text-center">
                            <div
                                class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 shadow-xl transition-transform duration-200 group-hover:scale-110">
                                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="mb-4 text-2xl font-bold text-gray-900">
                                Assistance 24/7
                            </h3>
                            <p class="leading-relaxed text-gray-600">
                                Notre équipe d'assistance dédiée est disponible 24h/24 et 7j/7 pour répondre à toutes
                                vos questions et préoccupations pendant votre location.
                            </p>
                        </div>

                        <div class="group text-center">
                            <div
                                class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 shadow-xl transition-transform duration-200 group-hover:scale-110">
                                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="mb-4 text-2xl font-bold text-gray-900">
                                Meilleure valeur
                            </h3>
                            <p class="leading-relaxed text-gray-600">
                                Tarification transparente, sans frais cachés.

                                Bénéficiez de services de location de voitures haut de gamme à des tarifs compétitifs

                                et d'un excellent rapport qualité-prix.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>

<style scoped>
.font-sans {
    font-family:
        'Inter',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
