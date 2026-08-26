<script setup lang="ts">
import { appAlert } from '@/composables/useAppDialog';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { login } from '@/routes';
import { book } from '@/routes/fleet';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Car {
    id: number;
    make: string;
    model: string;
    price_per_day: string;
    security_deposit: string;
    image_url: string;
    images: { url: string; alt: string }[];
    fuel_type: string;
    transmission: string;
    year: string;
    description: string;
    status: string;
}

interface UnavailablePeriod {
    start_date: string;
    end_date: string;
}

interface Driver {
    id: number;
    full_name: string;
    phone: string;
}

const $page = usePage();
const car = computed<Car>(() => $page.props.car as Car);
const agency = computed(
    () =>
        $page.props.agency as {
            tax_enabled?: boolean;
            tax_rate?: number | string;
            online_advance_percentage?: number | string;
        },
);
const unavailablePeriods = computed<UnavailablePeriod[]>(
    () => ($page.props.unavailablePeriods as UnavailablePeriod[]) ?? [],
);
const approvedDrivers = computed<Driver[]>(
    () => ($page.props.approvedDrivers as Driver[]) ?? [],
);

const form = useForm({
    start_date: '',
    end_date: '',
    pickup_location: '',
    return_location: '',
    secondary_driver_mode: 'none',
    secondary_driver_id: '',
    new_driver_full_name: '',
    new_driver_phone: '',
    new_driver_email: '',
    new_driver_license: null as File | null,
});

function selectDriverLicense(event: Event) {
    form.new_driver_license =
        (event.target as HTMLInputElement).files?.[0] ?? null;
}

// Calculate rental details
const rentalDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    const diffTime = end.getTime() - start.getTime();
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
});

const subtotal = computed(() => {
    return rentalDays.value * parseFloat(car.value.price_per_day);
});

const tax = computed(() => {
    return agency.value.tax_enabled
        ? subtotal.value * (Number(agency.value.tax_rate || 0) / 100)
        : 0;
});

const total = computed(() => {
    return subtotal.value + tax.value;
});
const requiredAdvance = computed(
    () =>
        (total.value * Number(agency.value.online_advance_percentage || 0)) /
        100,
);

const availabilityError = computed(() => {
    if (!form.start_date) return '';

    if (!form.end_date) {
        const unavailableStart = unavailablePeriods.value.find(
            (period) =>
                form.start_date >= period.start_date &&
                form.start_date <= period.end_date,
        );

        return unavailableStart
            ? `Ce véhicule est indisponible du ${formatDate(unavailableStart.start_date)} au ${formatDate(unavailableStart.end_date)}. Veuillez choisir une autre date de prise en charge.`
            : '';
    }

    const conflict = unavailablePeriods.value.find(
        (period) =>
            form.start_date <= period.end_date &&
            form.end_date >= period.start_date,
    );

    return conflict
        ? `Ce véhicule est indisponible du ${formatDate(conflict.start_date)} au ${formatDate(conflict.end_date)}. Veuillez choisir d'autres dates.`
        : '';
});

const canSubmit = computed(() => {
    return (
        form.start_date &&
        form.end_date &&
        form.pickup_location &&
        form.return_location &&
        rentalDays.value > 0 &&
        !availabilityError.value
    );
});

const submitBooking = () => {
    const user = $page.props.auth.user;

    if (!user) {
        // Not authenticated → redirect to login
        router.get(login.url());
        return;
    }

    if (availabilityError.value) return;

    if (user.role === 'client') {
        // Authenticated and role is "user" → make booking
        form.post(book.url(car.value.id));
        return;
    }

    if (user.role === 'admin') {
        // Authenticated but role is "admin" → show alert
        void appAlert(
            'Les administrateurs ne peuvent pas effectuer de réservation.',
            { title: 'Réservation indisponible', tone: 'warning' },
        );
        return;
    }

    // fallback for any other role
    void appAlert(
        'Votre rôle ne vous permet pas d’effectuer une réservation.',
        { title: 'Accès non autorisé', tone: 'warning' },
    );
};

function formatDate(date: string) {
    const [year, month, day] = date.slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
}

// Auto-populate return location when pickup is selected
watch(
    () => form.pickup_location,
    (newLocation) => {
        if (newLocation && !form.return_location) {
            form.return_location = newLocation;
        }
    },
);

const images = computed(() => {
    if (car.value.images && car.value.images.length > 0) {
        return car.value.images;
    }
    return [
        {
            url: car.value.image_url,
            alt: `${car.value.make} ${car.value.model}`,
        },
    ];
});

const commonLocations = [
    'Agence du centre-ville',
    'Aéroport – Terminal 1',
    'Aéroport – Terminal 2',
    'Gare centrale',
    'Centre commercial',
    'Quartier des hôtels',
    'Quartier des affaires',
];
</script>
<template>
    <HomeLayout>
        <div
            class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!--  Header -->
                <div class="mb-8">
                    <nav
                        class="mb-6 flex items-center space-x-2 text-sm text-gray-500"
                    >
                        <a
                            href="/fleet"
                            class="font-medium transition-colors duration-200 hover:text-orange-500"
                            >Flotte</a
                        >
                        <svg
                            class="h-4 w-4 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            ></path>
                        </svg>
                        <span class="font-medium text-gray-900"
                            >{{ car.make }} {{ car.model }}</span
                        >
                    </nav>
                    <div class="flex items-center space-x-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-600"
                        >
                            <svg
                                class="h-6 w-6 text-white"
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
                        </div>
                        <div>
                            <h1
                                class="text-4xl leading-tight font-bold text-gray-900"
                            >
                                Réserver {{ car.make }} {{ car.model }}
                            </h1>
                            <p class="mt-1 text-gray-600">
                                Finalisez votre réservation en quelques étapes
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!--  Car Details Section -->
                    <div class="space-y-8 lg:col-span-2">
                        <!--  Car Images -->
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-lg"
                        >
                            <div
                                class="relative h-72 bg-gradient-to-br from-gray-100 to-gray-200 sm:h-96"
                            >
                                <img
                                    :src="images[0]?.url"
                                    alt="car image"
                                    class="h-full w-full object-cover transition-all duration-500"
                                />
                            </div>

                            <!--  Car Info -->
                            <div class="p-8">
                                <div
                                    class="mb-6 flex items-start justify-between"
                                >
                                    <div>
                                        <h2
                                            class="mb-2 text-3xl font-bold text-gray-900"
                                        >
                                            {{ car.make }} {{ car.model }}
                                        </h2>
                                        <div
                                            class="flex items-center space-x-6 text-sm text-gray-500"
                                        >
                                            <span
                                                class="flex items-center rounded-full bg-gray-100 px-3 py-1"
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
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
                                                {{ car.year }}
                                            </span>
                                            <span
                                                class="flex items-center rounded-full bg-gray-100 px-3 py-1 capitalize"
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
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
                                                {{ car.fuel_type }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div
                                            class="rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-2 text-white"
                                        >
                                            <span class="text-3xl font-bold"
                                                >{{
                                                    car.price_per_day
                                                }}
                                                DZD</span
                                            >
                                            <span
                                                class="block text-sm text-orange-100"
                                                >par jour</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <p class="leading-relaxed text-gray-600">
                                    {{ car.description }}
                                </p>
                            </div>
                        </div>

                        <!--  Booking Form -->
                        <div
                            class="rounded-2xl border border-gray-100 bg-white p-8 shadow-lg"
                        >
                            <div class="mb-8 flex items-center space-x-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-orange-500 to-orange-600"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                        ></path>
                                    </svg>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    Détails de la réservation
                                </h3>
                            </div>

                            <form class="space-y-8">
                                <!--  Rental Dates -->
                                <div class="space-y-4">
                                    <h4
                                        class="flex items-center text-lg font-semibold text-gray-900"
                                    >
                                        <svg
                                            class="mr-2 h-5 w-5 text-orange-500"
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
                                        Période de location
                                    </h4>
                                    <div class="grid gap-6 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                Date de prise en charge *
                                            </label>
                                            <input
                                                v-model="form.start_date"
                                                type="date"
                                                :min="$page.props.minDate"
                                                :max="$page.props.maxDate"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors.start_date,
                                                }"
                                            />
                                            <span
                                                v-if="form.errors.start_date"
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{ form.errors.start_date }}
                                            </span>
                                        </div>

                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                Date de retour *
                                            </label>
                                            <input
                                                v-model="form.end_date"
                                                type="date"
                                                :min="
                                                    form.start_date ||
                                                    $page.props.minDate
                                                "
                                                :max="$page.props.maxDate"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors.end_date,
                                                }"
                                            />
                                            <span
                                                v-if="form.errors.end_date"
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{ form.errors.end_date }}
                                            </span>
                                        </div>
                                    </div>
                                    <p
                                        v-if="availabilityError"
                                        class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
                                    >
                                        {{ availabilityError }}
                                    </p>
                                    <div
                                        v-else-if="unavailablePeriods.length"
                                        class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800"
                                    >
                                        <p class="font-medium">
                                            Dates indisponibles
                                        </p>
                                        <ul class="mt-1 list-inside list-disc">
                                            <li
                                                v-for="period in unavailablePeriods"
                                                :key="`${period.start_date}-${period.end_date}`"
                                            >
                                                {{
                                                    formatDate(
                                                        period.start_date,
                                                    )
                                                }}
                                                –
                                                {{
                                                    formatDate(period.end_date)
                                                }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!--  Locations -->
                                <div class="space-y-4">
                                    <h4
                                        class="flex items-center text-lg font-semibold text-gray-900"
                                    >
                                        <svg
                                            class="mr-2 h-5 w-5 text-orange-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                            ></path>
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                            ></path>
                                        </svg>
                                        Lieux de prise en charge et de retour
                                    </h4>
                                    <div class="grid gap-6 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                Lieu de prise en charge *
                                            </label>
                                            <select
                                                v-model="form.pickup_location"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors
                                                            .pickup_location,
                                                }"
                                            >
                                                <option value="">
                                                    Sélectionnez le lieu de
                                                    prise en charge
                                                </option>
                                                <option
                                                    v-for="location in commonLocations"
                                                    :key="location"
                                                    :value="location"
                                                >
                                                    {{ location }}
                                                </option>
                                            </select>
                                            <span
                                                v-if="
                                                    form.errors.pickup_location
                                                "
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{
                                                    form.errors.pickup_location
                                                }}
                                            </span>
                                        </div>

                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                Lieu de retour *
                                            </label>
                                            <select
                                                v-model="form.return_location"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors
                                                            .return_location,
                                                }"
                                            >
                                                <option value="">
                                                    Sélectionnez le lieu de
                                                    retour
                                                </option>
                                                <option
                                                    v-for="location in commonLocations"
                                                    :key="location"
                                                    :value="location"
                                                >
                                                    {{ location }}
                                                </option>
                                            </select>
                                            <span
                                                v-if="
                                                    form.errors.return_location
                                                "
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{
                                                    form.errors.return_location
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4 border-t pt-6">
                                    <div>
                                        <h4
                                            class="text-lg font-semibold text-gray-900"
                                        >
                                            Deuxième conducteur
                                        </h4>
                                        <p class="mt-1 text-sm text-gray-600">
                                            Facultatif. Un conducteur doit être
                                            approuvé par l’agence avant d’être
                                            affecté à la réservation.
                                        </p>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <label
                                            class="flex cursor-pointer items-center gap-2 rounded-xl border p-3"
                                        >
                                            <input
                                                v-model="
                                                    form.secondary_driver_mode
                                                "
                                                type="radio"
                                                value="none"
                                            />
                                            Aucun
                                        </label>
                                        <label
                                            class="flex cursor-pointer items-center gap-2 rounded-xl border p-3"
                                        >
                                            <input
                                                v-model="
                                                    form.secondary_driver_mode
                                                "
                                                type="radio"
                                                value="existing"
                                            />
                                            Conducteur approuvé
                                        </label>
                                        <label
                                            class="flex cursor-pointer items-center gap-2 rounded-xl border p-3"
                                        >
                                            <input
                                                v-model="
                                                    form.secondary_driver_mode
                                                "
                                                type="radio"
                                                value="new"
                                            />
                                            Nouveau conducteur
                                        </label>
                                    </div>

                                    <div
                                        v-if="
                                            form.secondary_driver_mode ===
                                            'existing'
                                        "
                                    >
                                        <label
                                            class="mb-2 block text-sm font-semibold text-gray-700"
                                            >Choisir un conducteur *</label
                                        >
                                        <select
                                            v-model="form.secondary_driver_id"
                                            required
                                            class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-3"
                                        >
                                            <option value="">
                                                Sélectionnez un conducteur
                                                approuvé
                                            </option>
                                            <option
                                                v-for="driver in approvedDrivers"
                                                :key="driver.id"
                                                :value="driver.id"
                                            >
                                                {{ driver.full_name }} —
                                                {{ driver.phone }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="!approvedDrivers.length"
                                            class="mt-2 text-sm text-amber-700"
                                        >
                                            Vous n’avez aucun conducteur
                                            approuvé. Ajoutez-en un ci-dessous
                                            ou depuis vos paramètres.
                                        </p>
                                        <span
                                            v-if="
                                                form.errors.secondary_driver_id
                                            "
                                            class="text-sm text-red-500"
                                            >{{
                                                form.errors.secondary_driver_id
                                            }}</span
                                        >
                                    </div>

                                    <div
                                        v-if="
                                            form.secondary_driver_mode === 'new'
                                        "
                                        class="space-y-4 rounded-xl bg-amber-50 p-4"
                                    >
                                        <p class="text-sm text-amber-800">
                                            Ce conducteur sera enregistré dans
                                            votre liste et soumis à
                                            l’administrateur. Il sera affecté
                                            automatiquement à cette réservation
                                            après approbation.
                                        </p>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label
                                                    class="mb-1 block text-sm font-medium"
                                                    >Nom complet *</label
                                                >
                                                <input
                                                    v-model="
                                                        form.new_driver_full_name
                                                    "
                                                    required
                                                    class="w-full rounded-lg border px-3 py-2"
                                                />
                                                <span
                                                    v-if="
                                                        form.errors
                                                            .new_driver_full_name
                                                    "
                                                    class="text-sm text-red-500"
                                                    >{{
                                                        form.errors
                                                            .new_driver_full_name
                                                    }}</span
                                                >
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-sm font-medium"
                                                    >Téléphone *</label
                                                >
                                                <input
                                                    v-model="
                                                        form.new_driver_phone
                                                    "
                                                    required
                                                    type="tel"
                                                    class="w-full rounded-lg border px-3 py-2"
                                                />
                                                <span
                                                    v-if="
                                                        form.errors
                                                            .new_driver_phone
                                                    "
                                                    class="text-sm text-red-500"
                                                    >{{
                                                        form.errors
                                                            .new_driver_phone
                                                    }}</span
                                                >
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-sm font-medium"
                                                    >E-mail (optionnel)</label
                                                >
                                                <input
                                                    v-model="
                                                        form.new_driver_email
                                                    "
                                                    type="email"
                                                    class="w-full rounded-lg border px-3 py-2"
                                                />
                                                <span
                                                    v-if="
                                                        form.errors
                                                            .new_driver_email
                                                    "
                                                    class="text-sm text-red-500"
                                                    >{{
                                                        form.errors
                                                            .new_driver_email
                                                    }}</span
                                                >
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-sm font-medium"
                                                    >Photo du permis *</label
                                                >
                                                <input
                                                    required
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    class="w-full text-sm"
                                                    @change="
                                                        selectDriverLicense
                                                    "
                                                />
                                                <span
                                                    v-if="
                                                        form.errors
                                                            .new_driver_license
                                                    "
                                                    class="text-sm text-red-500"
                                                    >{{
                                                        form.errors
                                                            .new_driver_license
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!--  Price Summary Sidebar -->
                    <div class="lg:col-span-1">
                        <div
                            class="sticky top-4 rounded-2xl border border-gray-100 bg-white p-8 shadow-lg"
                        >
                            <div class="mb-6 flex items-center space-x-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-orange-500 to-orange-600"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                        ></path>
                                    </svg>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    Récapitulatif de la réservation
                                </h3>
                            </div>

                            <div class="mb-8 space-y-6">
                                <div
                                    class="space-y-4 rounded-xl bg-gray-50 p-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >Période de location</span
                                        >
                                        <span class="font-bold text-gray-900">
                                            {{
                                                rentalDays > 0
                                                    ? `${rentalDays} jour${rentalDays > 1 ? 's' : ''}`
                                                    : '-'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >Tarif journalier</span
                                        >
                                        <span class="font-bold text-gray-900"
                                            >{{ car.price_per_day }} DZD</span
                                        >
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div
                                        class="flex items-center justify-between py-2"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >Sous-total</span
                                        >
                                        <span
                                            class="text-lg font-bold text-gray-900"
                                        >
                                            {{
                                                rentalDays > 0
                                                    ? subtotal.toFixed(2)
                                                    : '0.00'
                                            }}
                                            DZD
                                        </span>
                                    </div>

                                    <div
                                        v-if="agency.tax_enabled"
                                        class="flex items-center justify-between py-2"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >Taxe ({{
                                                Number(
                                                    agency.tax_rate,
                                                ).toLocaleString('fr-FR')
                                            }}
                                            %)</span
                                        >
                                        <span
                                            class="text-lg font-bold text-gray-900"
                                        >
                                            {{
                                                rentalDays > 0
                                                    ? tax.toFixed(2)
                                                    : '0.00'
                                            }}
                                            DZD
                                        </span>
                                    </div>

                                    <div
                                        class="border-t-2 border-gray-200 pt-4"
                                    >
                                        <div
                                            v-if="
                                                Number(car.security_deposit) > 0
                                            "
                                            class="mb-3 flex items-center justify-between rounded-lg bg-amber-50 px-3 py-2 text-amber-800"
                                        >
                                            <span class="font-medium"
                                                >Caution remboursable</span
                                            >
                                            <span class="font-bold"
                                                >{{
                                                    Number(
                                                        car.security_deposit,
                                                    ).toFixed(2)
                                                }}
                                                DZD</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-xl font-bold text-gray-900"
                                                >Total location</span
                                            >
                                            <span
                                                class="text-2xl font-bold text-orange-500"
                                            >
                                                {{
                                                    rentalDays > 0
                                                        ? total.toFixed(2)
                                                        : '0.00'
                                                }}
                                                DZD
                                            </span>
                                        </div>
                                        <div
                                            v-if="
                                                Number(car.security_deposit) > 0
                                            "
                                            class="mt-2 flex items-center justify-between text-sm"
                                        >
                                            <span
                                                class="font-medium text-gray-600"
                                                >Montant à prévoir avec
                                                caution</span
                                            >
                                            <span
                                                class="font-bold text-gray-900"
                                                >{{
                                                    (
                                                        total +
                                                        Number(
                                                            car.security_deposit,
                                                        )
                                                    ).toFixed(2)
                                                }}
                                                DZD</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                Number(
                                                    agency.online_advance_percentage,
                                                ) > 0
                                            "
                                            class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <span
                                                    >Avance requise ({{
                                                        Number(
                                                            agency.online_advance_percentage,
                                                        ).toLocaleString(
                                                            'fr-FR',
                                                        )
                                                    }}%)</span
                                                ><strong
                                                    >{{
                                                        requiredAdvance.toFixed(
                                                            2,
                                                        )
                                                    }}
                                                    DZD</strong
                                                >
                                            </div>
                                            <p class="mt-1 text-xs">
                                                Après la réservation, envoyez
                                                une preuve de paiement Algérie
                                                Poste. L'agence vérifiera le
                                                paiement avant de confirmer.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!--  Book Now Button -->
                            <button
                                @click="submitBooking"
                                :disabled="!canSubmit || form.processing"
                                :class="{
                                    'transform cursor-pointer bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg hover:scale-[1.01] hover:from-orange-600 hover:to-orange-700 hover:shadow-xl':
                                        canSubmit && !form.processing,
                                    'cursor-not-allowed bg-gray-300 text-gray-500':
                                        !canSubmit || form.processing,
                                }"
                                class="w-full rounded-xl px-6 py-5 text-lg font-bold transition-all duration-300"
                            >
                                <span
                                    v-if="form.processing"
                                    class="flex items-center justify-center"
                                >
                                    <svg
                                        class="mr-3 -ml-1 h-6 w-6 animate-spin text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        ></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                    Traitement en cours...
                                </span>
                                <span
                                    v-else-if="!$page.props.auth.user"
                                    class="flex items-center justify-center"
                                >
                                    <svg
                                        class="mr-2 h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"
                                        ></path>
                                    </svg>
                                    Login to Book
                                </span>
                                <span
                                    v-else
                                    class="flex items-center justify-center"
                                >
                                    <svg
                                        class="mr-2 h-5 w-5 fill-white"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path
                                            d="M416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64zM438 225.7C427.3 217.9 412.3 220.3 404.5 231L285.1 395.2L233 343.1C223.6 333.7 208.4 333.7 199.1 343.1C189.8 352.5 189.7 367.7 199.1 377L271.1 449C276.1 454 283 456.5 289.9 456C296.8 455.5 303.3 451.9 307.4 446.2L443.3 259.2C451.1 248.5 448.7 233.5 438 225.7z"
                                        />
                                    </svg>
                                    Réserver maintenant
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
