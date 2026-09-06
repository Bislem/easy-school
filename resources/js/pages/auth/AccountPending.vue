<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Clock3,
    CreditCard,
    GraduationCap,
    LogOut,
    Mail,
    Phone,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-vue-next';

const props = defineProps<{
    school: {
        name: string;
        status: 'pending' | 'rejected';
        registration_submitted_at?: string;
        registration_rejection_reason?: string | null;
    };
    plan?: {
        name: string;
        price: string;
        currency: string;
        billing_period: string;
    } | null;
    contact: { email: string; phone: string; hours: string };
}>();

const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Validation de votre compte" />
    <main
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#051c3a] px-5 py-12 text-white"
    >
        <div
            class="absolute inset-0 [background-image:radial-gradient(circle_at_18%_25%,#0a4770_0,transparent_30%),radial-gradient(circle_at_82%_15%,#113e69_0,transparent_27%)] opacity-40"
        ></div>
        <section
            class="relative w-full max-w-2xl rounded-[24px] bg-white p-7 text-[#092044] shadow-2xl sm:p-11"
        >
            <div class="text-center">
                <span
                    class="mx-auto grid size-20 place-items-center rounded-full"
                    :class="
                        school.status === 'rejected'
                            ? 'bg-red-50 text-red-500'
                            : 'bg-[#e9faf8] text-[#0e9e99]'
                    "
                >
                    <TriangleAlert
                        v-if="school.status === 'rejected'"
                        class="size-9"
                    />
                    <Clock3 v-else class="size-9" />
                </span>
                <p
                    class="mt-5 text-xs font-black tracking-wider text-[#0aa39c] uppercase"
                >
                    {{ school.name }}
                </p>
                <h1 class="mt-2 text-3xl font-black">
                    {{
                        school.status === 'rejected'
                            ? 'Votre demande nécessite une correction'
                            : 'Votre demande est en cours de validation'
                    }}
                </h1>
                <p
                    class="mx-auto mt-3 max-w-lg text-sm leading-6 text-slate-500"
                >
                    {{
                        school.status === 'rejected'
                            ? 'Le paiement n’a pas pu être validé. Contactez notre équipe pour transmettre les informations nécessaires.'
                            : 'Notre équipe vérifie votre justificatif de paiement. Votre espace sera activé dès que le paiement aura été approuvé.'
                    }}
                </p>
            </div>

            <div
                v-if="school.registration_rejection_reason"
                class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            >
                <b>Motif :</b> {{ school.registration_rejection_reason }}
            </div>

            <div class="mt-7 grid gap-3 sm:grid-cols-2">
                <div class="flex gap-3 rounded-xl border border-slate-200 p-4">
                    <CreditCard class="size-6 shrink-0 text-[#0aa39c]" />
                    <div>
                        <p class="text-xs text-slate-500">Plan sélectionné</p>
                        <p class="mt-1 font-bold">
                            {{ plan?.name || 'Plan Easy School' }}
                        </p>
                        <p v-if="plan" class="text-xs text-slate-500">
                            {{ plan.price }} {{ plan.currency }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 rounded-xl border border-slate-200 p-4">
                    <ShieldCheck class="size-6 shrink-0 text-[#0aa39c]" />
                    <div>
                        <p class="text-xs text-slate-500">Statut du paiement</p>
                        <p class="mt-1 font-bold">
                            {{
                                school.status === 'rejected'
                                    ? 'À régulariser'
                                    : 'Vérification en cours'
                            }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Traitement par un administrateur
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-xl bg-[#effbfa] p-5">
                <h2 class="font-black">Besoin d’aide ?</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Notre équipe peut vous renseigner sur votre paiement et
                    votre activation.
                </p>
                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <a
                        :href="`mailto:${contact.email}`"
                        class="flex items-center gap-2 font-semibold text-[#078f88]"
                        ><Mail class="size-4" />{{ contact.email }}</a
                    ><a
                        :href="`tel:${contact.phone.replace(/\s/g, '')}`"
                        class="flex items-center gap-2 font-semibold text-[#078f88]"
                        ><Phone class="size-4" />{{ contact.phone }}</a
                    >
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ contact.hours }}</p>
            </div>

            <button
                class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50"
                @click="logout"
            >
                <LogOut class="size-4" /> Se déconnecter
            </button>
            <div
                class="mt-6 flex items-center justify-center gap-2 text-sm font-black"
            >
                <GraduationCap class="size-6 text-[#16cdb5]" /> Easy School
            </div>
        </section>
    </main>
</template>
