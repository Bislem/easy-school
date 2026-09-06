<script setup lang="ts">
import { publicLocale } from '@/composables/usePublicLocale';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Crown,
    HelpCircle,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-vue-next';

const props = defineProps<{ plans: any[] }>();
const fallback = [
    {
        id: 'starter',
        name: 'Essentiel',
        description: 'Pour lancer la gestion numérique de votre établissement.',
        price: null,
        currency: 'DZD',
        billing_period: 'monthly',
        max_students: 150,
        max_sites: 1,
        features: [
            'Dossiers étudiants',
            'Formations et planning',
            'Paiements et reçus',
            'Support par email',
        ],
    },
    {
        id: 'growth',
        name: 'Croissance',
        description:
            'Pour les écoles qui veulent centraliser toute leur activité.',
        price: null,
        currency: 'DZD',
        billing_period: 'monthly',
        max_students: 600,
        max_sites: 3,
        features: [
            'Tous les modules Essentiel',
            'Ressources humaines',
            'Application mobile',
            'Rapports avancés',
            'Support prioritaire',
        ],
    },
    {
        id: 'network',
        name: 'Réseau',
        description:
            'Une solution sur mesure pour les groupes et multi-campus.',
        price: null,
        currency: 'DZD',
        billing_period: 'monthly',
        max_students: null,
        max_sites: null,
        features: [
            'Utilisateurs et sites illimités',
            'Accompagnement au déploiement',
            'Configuration personnalisée',
            'Support dédié',
        ],
    },
];
const plans = props.plans.length ? props.plans : fallback;
const featureText = (feature: any) =>
    typeof feature === 'string'
        ? feature
        : feature?.label || feature?.name || String(feature);
const limit = (value: number | null, noun: 'students' | 'sites') => {
    const labels = {
        fr: {
            students: 'étudiants',
            sites: 'sites',
            upTo: 'Jusqu’à',
            unlimited: 'illimités',
        },
        en: {
            students: 'students',
            sites: 'sites',
            upTo: 'Up to',
            unlimited: 'unlimited',
        },
        ar: {
            students: 'طالب',
            sites: 'فرع',
            upTo: 'حتى',
            unlimited: 'غير محدود',
        },
    }[publicLocale.value];
    return value
        ? `${labels.upTo} ${value.toLocaleString(publicLocale.value)} ${labels[noun]}`
        : `${labels[noun]} ${labels.unlimited}`;
};
</script>

<template>
    <HomeLayout
        ><Head title="Tarifs — Easy School" />
        <main class="bg-[#f7fafc]">
            <section
                class="relative overflow-hidden bg-[#061d3b] px-6 pt-32 pb-28 text-center text-white"
            >
                <div
                    class="absolute inset-0 [background-image:radial-gradient(circle_at_20%_30%,#0b7890_0,transparent_25%),radial-gradient(circle_at_80%_15%,#11b8a3_0,transparent_25%)] opacity-30"
                />
                <div class="relative mx-auto max-w-3xl">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-teal-300/30 bg-teal-400/10 px-4 py-2 text-xs font-black text-[#2ce0c8]"
                        ><Sparkles class="size-4" /> DES PLANS QUI GRANDISSENT
                        AVEC VOUS</span
                    >
                    <h1 class="mt-6 text-4xl font-black sm:text-6xl">
                        Simple à choisir.<br /><span class="text-[#13cbb1]"
                            >Puissant au quotidien.</span
                        >
                    </h1>
                    <p class="mx-auto mt-5 max-w-2xl leading-7 text-slate-300">
                        Centralisez votre école sans multiplier les outils.
                        Chaque formule inclut la sécurité multi-tenant, les
                        mises à jour et notre accompagnement.
                    </p>
                </div>
            </section>

            <section
                class="relative z-10 mx-auto -mt-16 max-w-[1280px] px-5 pb-20"
            >
                <div class="grid items-stretch gap-5 lg:grid-cols-3">
                    <article
                        v-for="(plan, index) in plans"
                        :key="plan.id"
                        class="relative flex flex-col overflow-hidden rounded-[26px] border bg-white p-7 shadow-xl shadow-slate-200/60"
                        :class="
                            index === 1
                                ? 'border-[#12bca9] ring-4 ring-[#12bca9]/10 lg:-translate-y-4'
                                : 'border-slate-200'
                        "
                    >
                        <div
                            v-if="index === 1"
                            class="absolute top-0 right-6 rounded-b-xl bg-[#0ebda8] px-4 py-2 text-[10px] font-black tracking-wider text-white"
                        >
                            LE PLUS CHOISI
                        </div>
                        <span
                            class="grid size-12 place-items-center rounded-2xl"
                            :class="
                                index === 1
                                    ? 'bg-teal-50 text-teal-600'
                                    : 'bg-slate-100 text-[#0a2246]'
                            "
                        >
                            <Crown v-if="index === 1" class="size-6" /><Users
                                v-else
                                class="size-6"
                            />
                        </span>
                        <h2 class="mt-5 text-2xl font-black">
                            {{ plan.name }}
                        </h2>
                        <p
                            class="mt-2 min-h-12 text-sm leading-6 text-slate-500"
                        >
                            {{ plan.description }}
                        </p>
                        <div class="mt-6 border-y border-slate-100 py-5">
                            <template v-if="Number(plan.price) > 0"
                                ><span class="text-4xl font-black">{{
                                    Number(plan.price).toLocaleString('fr-DZ')
                                }}</span
                                ><span
                                    class="ml-2 text-sm font-bold text-slate-500"
                                    >{{ plan.currency }} /
                                    {{
                                        plan.billing_period === 'yearly'
                                            ? 'an'
                                            : 'mois'
                                    }}</span
                                ></template
                            >
                            <span v-else class="text-3xl font-black"
                                >Sur devis</span
                            >
                        </div>
                        <div
                            class="mt-5 grid grid-cols-2 gap-2 text-xs font-bold text-slate-600"
                        >
                            <span class="rounded-xl bg-slate-50 p-3">{{
                                limit(plan.max_students, 'students')
                            }}</span>
                            <span class="rounded-xl bg-slate-50 p-3">{{
                                limit(plan.max_sites, 'sites')
                            }}</span>
                        </div>
                        <ul class="my-6 flex-1 space-y-3 text-sm">
                            <li
                                v-for="feature in plan.features || []"
                                :key="featureText(feature)"
                                class="flex gap-3"
                            >
                                <span
                                    class="grid size-5 shrink-0 place-items-center rounded-full bg-teal-50"
                                    ><Check
                                        class="size-3 text-teal-600" /></span
                                >{{ featureText(feature) }}
                            </li>
                        </ul>
                        <Link
                            href="/demo"
                            class="flex items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-sm font-black transition"
                            :class="
                                index === 1
                                    ? 'bg-[#0ebda8] text-white shadow-lg shadow-teal-200 hover:bg-[#0aa895]'
                                    : 'bg-[#071f3e] text-white hover:bg-[#0d315d]'
                            "
                            >Essayer gratuitement <ArrowRight class="size-4"
                        /></Link>
                    </article>
                </div>
                <div
                    class="mt-8 flex flex-col items-center justify-between gap-5 rounded-3xl bg-[#e9f9f6] p-7 sm:flex-row"
                >
                    <div class="flex items-start gap-4">
                        <span
                            class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white text-teal-600"
                            ><ShieldCheck
                        /></span>
                        <div>
                            <h3 class="font-black">
                                Besoin d’une configuration particulière ?
                            </h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Multi-campus, migration de données ou
                                accompagnement personnalisé : parlons-en.
                            </p>
                        </div>
                    </div>
                    <Link
                        href="/contact"
                        class="flex shrink-0 items-center gap-2 rounded-xl border border-teal-600 px-5 py-3 text-sm font-black text-teal-700"
                        >Nous contacter <HelpCircle class="size-4"
                    /></Link>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>
