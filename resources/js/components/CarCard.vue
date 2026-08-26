<script setup lang="ts">
import { show } from '@/routes/fleet';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Car {
    id: number;
    make: string;
    model: string;
    year: number;
    price_per_day: string;
    description: string;
    fuel_type: string;
    image_url: string;
}

interface Props {
    car: Car;
}

const bookCar = (carId: number) => {
    router.get(show(carId).url);
};

const $page = usePage();
const bookingDisabled = computed(() =>
    Boolean(($page.props as any).agency?.booking_disabled),
);

defineProps<Props>();
</script>

<template>
    <div
        class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-lg transition-all duration-300 hover:shadow-2xl"
    >
        <!-- Car Image -->
        <div
            class="relative h-56 overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100"
        >
            <img
                :src="car.image_url"
                :alt="`${car.make} ${car.model}`"
                class="h-full w-full object-cover transition-all duration-500 group-hover:scale-[1.03]"
            />

            <!-- Price Badge -->
            <div
                class="absolute top-4 right-4 rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-2 shadow-lg"
            >
                <span class="text-sm font-bold text-white"
                    >{{ car.price_per_day }} DZD</span
                >
                <span class="text-xs text-orange-100">/Jour</span>
            </div>

            <!-- Gradient Overlay -->
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
            ></div>
        </div>

        <!--  Car Details -->
        <div class="space-y-4 p-4">
            <!-- Header -->
            <div class="space-y-2">
                <h3
                    class="text-xl font-bold text-gray-900 transition-colors group-hover:text-orange-600"
                >
                    {{ car.make }} {{ car.model }} - {{ car.year }} -
                    {{ car.id }}
                </h3>

                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1 capitalize">
                        <svg
                            class="h-4 w-4 text-orange-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"
                            ></path>
                        </svg>
                        <span class="font-medium">{{ car.fuel_type }}</span>
                    </div>
                    <div
                        class="rounded-md bg-slate-400 px-2 py-1 text-xs text-white"
                    >
                        <p>+ GPS</p>
                    </div>
                    <div
                        class="rounded-md bg-slate-400 px-2 py-1 text-xs text-white"
                    >
                        <p>+ Assurance</p>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <p class="line-clamp-2 text-sm leading-relaxed text-gray-600">
                {{ car.description }}
            </p>
        </div>
        <!--  Book Button -->
        <div v-if="!bookingDisabled" class="p-4">
            <button
                @click="bookCar(car.id)"
                class="group/btn w-full cursor-pointer rounded-xl bg-gradient-to-r from-slate-700 to-slate-900 px-6 py-3.5 font-semibold text-white shadow-lg transition-all duration-200 hover:from-orange-600 hover:to-orange-700 focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:outline-none"
            >
                <span
                    class="flex items-center justify-center gap-2 text-orange-500 group-hover:text-white"
                >
                    <svg
                        class="h-5 w-5 transition-transform group-hover/btn:scale-110"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        ></path>
                    </svg>
                    Réservez maintenant
                </span>
            </button>
        </div>
    </div>
</template>
