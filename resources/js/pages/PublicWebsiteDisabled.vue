<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ agency: any }>();

const agencyName = computed(
    () => props.agency?.trading_name || 'EASE RENT CAR',
);
const phone = computed(() => props.agency?.phone || '+1 (213) 123-4567');
const email = computed(() => props.agency?.email || 'hello@easetech.dz');
const address = computed(() => {
    const values = [
        props.agency?.address_line_1,
        props.agency?.address_line_2,
        props.agency?.postal_code,
        props.agency?.city,
        props.agency?.country,
    ].filter(Boolean);

    return values.length ? values.join(', ') : '123 Iheddaden Bejaia City';
});
</script>

<template>
    <Head :title="agencyName" />
    <main
        class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12"
    >
        <section
            class="w-full max-w-xl rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-200 sm:p-12"
        >
            <img
                v-if="agency?.logo_url"
                :src="agency.logo_url"
                :alt="agencyName"
                class="mx-auto mb-6 h-14 max-w-32 object-contain"
            />
            <h1 class="text-2xl font-bold text-gray-900">{{ agencyName }}</h1>
            <p class="mt-4 text-gray-600">
                Our online booking website is currently unavailable. Please
                contact our team for assistance.
            </p>

            <div
                class="mt-8 space-y-4 rounded-xl bg-gray-50 p-6 text-left text-sm text-gray-700"
            >
                <a
                    :href="`tel:${phone.replace(/\s+/g, '')}`"
                    class="block hover:text-orange-600"
                    ><span class="font-semibold">Téléphone :</span> {{ phone }}</a
                >
                <a :href="`mailto:${email}`" class="block hover:text-orange-600"
                    ><span class="font-semibold">Email:</span> {{ email }}</a
                >
                <p><span class="font-semibold">Adresse :</span> {{ address }}</p>
            </div>
        </section>
    </main>
</template>
