<script setup lang="ts">
import { publicLocale } from '@/composables/usePublicLocale';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BarChart3,
    Bell,
    BookOpen,
    Building2,
    CalendarDays,
    Check,
    CheckCircle2,
    CirclePlay,
    Clock3,
    FileBadge,
    FileText,
    GraduationCap,
    Grid2X2,
    HeartHandshake,
    IdCard,
    Landmark,
    Layers3,
    MessageCircle,
    Phone,
    ReceiptText,
    ShieldCheck,
    Smartphone,
    Sparkles,
    UserCog,
    Users,
    UsersRound,
} from 'lucide-vue-next';

const features = [
    [
        'Gestion des étudiants',
        'Admission, profils, suivi et historique complet.',
        Users,
        'blue',
    ],
    [
        'Dossiers étudiants',
        'Documents, pièces jointes et suivis personnalisés.',
        FileText,
        'green',
    ],
    [
        'Gestion RH',
        'Organigramme, rôles, postes et permissions.',
        UsersRound,
        'violet',
    ],
    [
        'Dossiers employés',
        'Profils, contrats, salaires et évaluations.',
        UserCog,
        'orange',
    ],
    [
        'Sessions & groupes',
        'Planification, groupes, créneaux et salles.',
        ShieldCheck,
        'cyan',
    ],
    [
        'Formations & cours',
        'Programmes, modules et contenus.',
        BookOpen,
        'indigo',
    ],
    [
        'Inscriptions',
        'Gestion des inscriptions et réinscriptions.',
        IdCard,
        'rose',
    ],
    ['Présences', 'Suivi des présences et absences.', Clock3, 'green'],
    [
        'Paiements & factures',
        'Facturation, paiements, relances et reçus.',
        ReceiptText,
        'amber',
    ],
    [
        'Sites / campus multiples',
        'Gérez plusieurs sites avec des stats par site.',
        Layers3,
        'sky',
    ],
    ['Certificats', 'Génération automatique de certificats.', Award, 'violet'],
    [
        'Badges',
        'Badges numériques pour étudiants et employés.',
        FileBadge,
        'red',
    ],
    [
        'Rapports & statistiques',
        'Tableaux de bord, KPI et rapports avancés.',
        BarChart3,
        'blue',
    ],
    [
        'Emploi du temps',
        'Planning des cours et ressources.',
        CalendarDays,
        'green',
    ],
    [
        'Communication parents',
        'Messages, annonces et notifications.',
        HeartHandshake,
        'orange',
    ],
    [
        'Portail enseignant',
        'Suivi des classes, notes et ressources.',
        UsersRound,
        'violet',
    ],
    [
        'Mobile parents',
        'Suivi des enfants et infos en temps réel.',
        Smartphone,
        'cyan',
    ],
];
const colors: Record<string, string> = {
    blue: 'bg-blue-500',
    green: 'bg-emerald-500',
    violet: 'bg-violet-500',
    orange: 'bg-orange-500',
    cyan: 'bg-cyan-600',
    indigo: 'bg-indigo-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    red: 'bg-red-400',
};
const assurances = [
    [
        Sparkles,
        'Interface moderne',
        'Design intuitif et expérience utilisateur exceptionnelle.',
    ],
    [
        Landmark,
        'SaaS multi-tenant',
        'Une plateforme, plusieurs établissements gérés.',
    ],
    [
        ShieldCheck,
        'Sécurité avancée',
        'Données chiffrées et sauvegardes régulières.',
    ],
    [
        Building2,
        'Multi-sites',
        'Gérez plusieurs campus avec statistiques par site.',
    ],
    [
        Grid2X2,
        'Modules complets',
        'Tous les besoins couverts dans une seule solution.',
    ],
    [
        HeartHandshake,
        'Accompagnement',
        'Support réactif et suivi personnalisé.',
    ],
];
</script>

<template>
    <HomeLayout
        ><Head title="Easy School — Gestion scolaire simplifiée" />
        <main>
            <section
                class="relative overflow-hidden bg-[#061d3b] pt-28 pb-20 text-white lg:min-h-[650px] lg:pb-10"
            >
                <div
                    class="absolute inset-0 [background-image:radial-gradient(circle_at_15%_40%,#0d5174_0,transparent_31%),radial-gradient(circle_at_85%_20%,#10446d_0,transparent_35%)] opacity-30"
                ></div>
                <div
                    class="absolute top-16 -right-20 size-80 rounded-full border border-dashed border-cyan-400/30"
                ></div>
                <div
                    class="relative mx-auto grid max-w-[1440px] items-center gap-10 px-6 lg:grid-cols-[.78fr_1.22fr] lg:px-10"
                >
                    <div class="py-10 lg:py-16">
                        <span
                            class="inline-flex rounded-full bg-[#0c6b6c] px-4 py-2 text-[11px] font-extrabold tracking-wide text-[#33e3cc]"
                            >PLATEFORME SaaS MULTI-ÉTABLISSEMENTS</span
                        >
                        <h1
                            class="mt-7 text-4xl leading-[1.08] font-black tracking-tight sm:text-5xl xl:text-[58px]"
                        >
                            Pilotez votre établissement<br /><span
                                class="text-[#13cbb1]"
                                >en toute simplicité</span
                            >
                        </h1>
                        <p
                            class="mt-6 max-w-xl text-base leading-7 text-slate-200"
                        >
                            Easy School est la plateforme complète pour gérer
                            étudiants, employés, sessions, groupes, formations,
                            finances, RH et plusieurs sites à partir d’un seul
                            espace sécurisé.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-4">
                            <Link
                                href="/demo"
                                class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#12bda8] to-[#16d4b5] px-6 py-3.5 text-sm font-bold shadow-xl"
                                >Demander une démo
                                <ArrowRight class="size-4" /></Link
                            ><a
                                href="#fonctionnalites"
                                class="flex items-center gap-3 rounded-xl border border-white/60 px-6 py-3.5 text-sm font-bold"
                                >Voir une démo <CirclePlay class="size-5"
                            /></a>
                        </div>
                        <div
                            class="mt-10 flex flex-wrap gap-x-8 gap-y-4 text-xs text-slate-200"
                        >
                            <span
                                v-for="item in [
                                    [CheckCircle2, 'Démo jusqu’à 15 jours'],
                                    [Check, 'Mise en place rapide'],
                                    [ShieldCheck, 'Données sécurisées'],
                                    [Grid2X2, 'Multi-sites'],
                                ]"
                                :key="item[1] as string"
                                class="flex items-center gap-2"
                                ><component
                                    :is="item[0]"
                                    class="size-5 text-[#13cbb1]"
                                />{{ item[1] }}</span
                            >
                        </div>
                    </div>
                    <div
                        class="relative pb-8 sm:pb-10 lg:-mt-2 lg:self-start lg:pt-5 lg:pb-12 xl:-mt-4 xl:pt-4"
                        :class="
                            publicLocale === 'ar'
                                ? 'sm:pl-4 lg:mr-0 lg:-ml-16 lg:pr-16 lg:pl-0 xl:-ml-24 xl:pr-20'
                                : 'sm:pr-4 lg:-mr-16 lg:pr-0 lg:pl-16 xl:-mr-24 xl:pl-20'
                        "
                    >
                        <div
                            class="absolute top-8 right-0 bottom-5 left-10 rounded-[36px] bg-cyan-400/20 blur-3xl lg:top-5 lg:left-16 xl:left-20"
                        ></div>
                        <div
                            class="hero-phone-float group absolute z-30 w-28 sm:w-36 lg:w-40 xl:w-44"
                            :class="
                                publicLocale === 'ar'
                                    ? 'top-[16%] right-0 sm:right-1 lg:right-0 xl:right-1'
                                    : '-bottom-1 -left-4 sm:-bottom-2 sm:-left-5 lg:-bottom-1 lg:-left-8 xl:-left-10'
                            "
                        >
                            <span
                                class="hero-phone-orbit pointer-events-none absolute inset-[8%] -z-10 rounded-[32%] border border-cyan-300/50"
                            ></span>
                            <span
                                class="hero-phone-glow pointer-events-none absolute inset-[12%] -z-20 rounded-full bg-cyan-300/35 blur-2xl"
                            ></span>
                            <img
                                src="/images/mobile/welcome.png"
                                alt="Application mobile Easy School pour les parents"
                                class="relative w-full -rotate-2 drop-shadow-[0_24px_32px_rgba(0,0,0,0.55)] transition-all duration-700 ease-out group-hover:scale-[1.09] group-hover:rotate-1 group-hover:drop-shadow-[0_32px_42px_rgba(16,211,190,0.38)]"
                            />
                            <div
                                class="absolute top-[19%] z-40 flex items-center gap-1.5 rounded-full border border-white/50 bg-white/95 px-2.5 py-1.5 text-[8px] font-black whitespace-nowrap text-[#075b63] opacity-0 shadow-xl transition-all duration-500 group-hover:-translate-y-2 group-hover:opacity-100 sm:text-[9px]"
                                :class="
                                    publicLocale === 'ar'
                                        ? '-left-7'
                                        : '-right-7'
                                "
                            >
                                <span class="relative flex size-2"
                                    ><span
                                        class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                    ></span
                                    ><span
                                        class="relative inline-flex size-2 rounded-full bg-emerald-500"
                                    ></span
                                ></span>
                                Suivi en temps réel
                            </div>
                            <Sparkles
                                class="hero-phone-sparkle absolute -top-1 -right-2 size-5 text-cyan-200 drop-shadow-[0_0_8px_rgba(103,232,249,.9)]"
                            />
                        </div>
                        <div
                            class="relative z-20 overflow-hidden rounded-2xl border border-white/20 bg-white shadow-[0_32px_80px_-18px_rgba(0,0,0,0.72)] ring-1 ring-cyan-300/25"
                            :class="
                                publicLocale === 'ar'
                                    ? 'mr-8 sm:mr-12 lg:mr-10 xl:mr-12'
                                    : 'ml-8 sm:ml-12 lg:ml-10 xl:ml-12'
                            "
                        >
                            <div
                                class="flex h-8 items-center gap-1.5 border-b border-slate-200 bg-slate-50 px-3"
                            >
                                <span
                                    class="size-2 rounded-full bg-red-400"
                                ></span>
                                <span
                                    class="size-2 rounded-full bg-amber-400"
                                ></span>
                                <span
                                    class="size-2 rounded-full bg-emerald-400"
                                ></span>
                                <span
                                    class="mx-auto h-3.5 w-2/5 rounded-full bg-slate-200/80"
                                ></span>
                            </div>
                            <img
                                src="/images/dashborad-screenshot.png"
                                alt="Tableau de bord Easy School"
                                class="block h-[310px] w-full bg-white object-cover object-left object-top sm:h-[360px] lg:h-[390px] xl:h-[430px]"
                            />
                        </div>
                        <div
                            v-if="false"
                            aria-hidden="true"
                            class="overflow-hidden rounded-2xl border border-cyan-300/40 bg-[#f6f9fd] text-[#102a4f] shadow-2xl lg:rounded-b-none"
                        >
                            <div class="flex min-h-[420px]">
                                <aside
                                    class="hidden w-36 shrink-0 bg-[#082749] p-4 text-[10px] text-slate-300 sm:block"
                                >
                                    <div
                                        class="mb-6 flex items-center gap-2 text-sm font-bold text-white"
                                    >
                                        <GraduationCap
                                            class="size-5 text-[#15d5bc]"
                                        />
                                        Easy School
                                    </div>
                                    <div
                                        v-for="(n, i) in [
                                            'Tableau de bord',
                                            'Étudiants',
                                            'Employés',
                                            'Formations',
                                            'Groupes',
                                            'Sessions',
                                            'Présences',
                                            'Paiements',
                                            'RH',
                                            'Certificats',
                                            'Budgets',
                                            'Rapports',
                                            'Parents',
                                        ]"
                                        :key="n"
                                        class="mb-1.5 rounded px-2 py-1.5"
                                        :class="
                                            i === 0
                                                ? 'bg-blue-600 text-white'
                                                : ''
                                        "
                                    >
                                        {{ n }}
                                    </div>
                                </aside>
                                <div class="min-w-0 flex-1 p-4 sm:p-5">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <strong>Tableau de bord</strong>
                                        <div
                                            class="flex items-center gap-3 text-[9px]"
                                        >
                                            <span
                                                class="rounded-md border bg-white px-4 py-2"
                                                >Campus Central</span
                                            ><Bell class="size-4" /><span
                                                class="size-7 rounded-full bg-orange-100"
                                            ></span>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4"
                                    >
                                        <div
                                            v-for="s in [
                                                ['Étudiants', '1 842', '+8,2%'],
                                                ['Employés', '156', '+5,1%'],
                                                [
                                                    'Inscriptions',
                                                    '2 316',
                                                    '+9,4%',
                                                ],
                                                [
                                                    'Revenus',
                                                    '125 680 DA',
                                                    '+12,7%',
                                                ],
                                            ]"
                                            :key="s[0]"
                                            class="rounded-lg border bg-white p-3 shadow-sm"
                                        >
                                            <p
                                                class="text-[9px] text-slate-400"
                                            >
                                                {{ s[0] }}
                                            </p>
                                            <strong
                                                class="mt-1 block text-sm"
                                                >{{ s[1] }}</strong
                                            ><span
                                                class="text-[8px] text-emerald-500"
                                                >{{ s[2] }} ce mois</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="mt-3 grid gap-2 sm:grid-cols-[1.55fr_.8fr]"
                                    >
                                        <div
                                            class="rounded-lg border bg-white p-4"
                                        >
                                            <div
                                                class="flex justify-between text-[10px] font-bold"
                                            >
                                                <span
                                                    >Évolution des
                                                    inscriptions</span
                                                ><span class="text-slate-400"
                                                    >6 derniers mois</span
                                                >
                                            </div>
                                            <svg
                                                viewBox="0 0 330 130"
                                                class="mt-3 w-full"
                                            >
                                                <path
                                                    d="M8 112 L55 91 L102 72 L149 87 L196 48 L243 57 L290 20 L324 9 L324 130 L8 130Z"
                                                    fill="#eff6ff"
                                                />
                                                <path
                                                    d="M8 112 L55 91 L102 72 L149 87 L196 48 L243 57 L290 20 L324 9"
                                                    fill="none"
                                                    stroke="#3b82f6"
                                                    stroke-width="3"
                                                />
                                            </svg>
                                        </div>
                                        <div
                                            class="rounded-lg border bg-white p-3"
                                        >
                                            <strong class="text-[10px]"
                                                >Activité récente</strong
                                            >
                                            <div
                                                v-for="a in [
                                                    'Nouvelle inscription',
                                                    'Paiement reçu',
                                                    'Absence signalée',
                                                    'Nouveau certificat',
                                                ]"
                                                :key="a"
                                                class="mt-3 flex gap-2 text-[8px]"
                                            >
                                                <span
                                                    class="grid size-5 place-items-center rounded bg-emerald-100"
                                                    >•</span
                                                ><b>{{ a }}</b>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 grid grid-cols-4 gap-2">
                                        <div
                                            v-for="(s, i) in [
                                                'Campus Central',
                                                'Campus Est',
                                                'Campus Ouest',
                                                'Campus Sud',
                                            ]"
                                            :key="s"
                                            class="rounded-lg border bg-white p-2 text-[8px]"
                                        >
                                            <b>{{ s }}</b>
                                            <p class="mt-2 text-sm font-bold">
                                                {{ [1842, 782, 645, 512][i] }}
                                            </p>
                                            <div
                                                class="mt-1 h-1 rounded bg-blue-100"
                                            >
                                                <div
                                                    class="h-full rounded bg-blue-500"
                                                    :style="{
                                                        width: `${82 - i * 12}%`,
                                                    }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-b">
                <div class="mx-auto max-w-[1380px] px-6 py-5">
                    <p class="text-center text-xs font-semibold">
                        Ils nous font confiance
                    </p>
                    <div
                        class="mt-5 grid grid-cols-2 gap-4 text-center text-xs text-slate-500 md:grid-cols-3 lg:grid-cols-6"
                    >
                        <span
                            v-for="n in [
                                'Institut Excellence',
                                'Académie Horizon',
                                'Centre de Formation Atlas',
                                'École Nouvelle Vision',
                                'Campus Leadership',
                                'Institut Al Falah',
                            ]"
                            :key="n"
                            class="flex items-center justify-center gap-2 border-r py-2"
                            ><Award class="size-6 text-slate-400" />{{
                                n
                            }}</span
                        >
                    </div>
                </div>
            </section>
            <section
                id="fonctionnalites"
                class="mx-auto max-w-[1380px] px-6 py-8 lg:px-10"
            >
                <h2 class="text-center text-xl font-extrabold">
                    Une plateforme complète pour chaque acteur de votre
                    établissement
                </h2>
                <div
                    id="modules"
                    class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6"
                >
                    <article
                        v-for="f in features"
                        :key="f[0] as string"
                        class="flex min-h-24 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-lg text-white"
                            :class="colors[f[3] as string]"
                            ><component :is="f[2]" class="size-5"
                        /></span>
                        <div>
                            <h3 class="text-xs font-extrabold">{{ f[0] }}</h3>
                            <p
                                class="mt-1 text-[10px] leading-4 text-slate-500"
                            >
                                {{ f[1] }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>

            <section
                class="mx-auto grid max-w-[1380px] gap-4 px-6 pb-5 lg:px-10"
            >
                <article
                    class="grid items-center overflow-hidden rounded-2xl bg-gradient-to-r from-[#eef8ff] to-[#f9fcff] p-8 md:grid-cols-2"
                >
                    <div class="rounded-xl border bg-white p-5 shadow-lg">
                        <div
                            class="mb-3 flex justify-between text-[10px] font-bold"
                        >
                            <span>Mes établissements</span><span>Détails</span>
                        </div>
                        <div
                            v-for="(r, i) in [
                                'Campus Central',
                                'Campus Est',
                                'Campus Ouest',
                                'Campus Sud',
                            ]"
                            :key="r"
                            class="grid grid-cols-5 border-t py-3 text-[9px]"
                        >
                            <b>{{ r }}</b
                            ><span>{{ [1842, 782, 645, 512][i] }}</span
                            ><span>{{ [156, 68, 54, 44][i] }}</span
                            ><span>{{ [2316, 944, 721, 593][i] }}</span
                            ><span class="font-bold text-emerald-500"
                                >Actif</span
                            >
                        </div>
                    </div>
                    <div class="p-3 md:pl-16">
                        <span
                            class="rounded-full bg-cyan-100 px-3 py-1 text-[9px] font-bold text-cyan-700"
                            >MULTI-SITES</span
                        >
                        <h2 class="mt-4 text-2xl font-black">
                            Gérez plusieurs sites depuis<br />une plateforme
                            centrale
                        </h2>
                        <p class="mt-3 text-xs leading-5 text-slate-500">
                            Easy School vous permet de piloter tous vos
                            établissements avec une vue globale et des
                            paramètres spécifiques à chaque site.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span
                                v-for="x in [
                                    'Statistiques par site',
                                    'Paramètres indépendants',
                                    'Consolidation globale',
                                    'Contrôle centralisé',
                                ]"
                                :key="x"
                                class="rounded-lg border bg-white px-3 py-2 text-[9px] font-semibold"
                                >✓ {{ x }}</span
                            >
                        </div>
                    </div>
                </article>
                <div class="grid gap-4 lg:grid-cols-2">
                    <article
                        class="grid rounded-2xl bg-gradient-to-r from-[#effaff] to-white p-8 md:grid-cols-[.95fr_1.05fr]"
                    >
                        <div>
                            <span class="text-[9px] font-bold text-blue-500"
                                >RESSOURCES HUMAINES</span
                            >
                            <h2 class="mt-3 text-2xl font-black">
                                Gérez vos ressources humaines<br />et
                                l'administration facilement
                            </h2>
                            <p class="mt-3 text-xs leading-5 text-slate-500">
                                Suivez vos employés, gérez les contrats,
                                salaires, absences et évaluations.
                            </p>
                            <div class="mt-5 grid gap-3 text-[10px]">
                                <span
                                    v-for="x in [
                                        'Contrats et documents RH',
                                        'Gestion des salaires et primes',
                                        'Congés, absences et pointage',
                                        'Évaluations et suivi de performance',
                                    ]"
                                    :key="x"
                                    class="flex gap-2"
                                    ><CheckCircle2
                                        class="size-4 text-[#10bfa9]"
                                    />{{ x }}</span
                                >
                            </div>
                        </div>
                        <div
                            class="mt-5 rounded-xl border bg-white p-4 shadow-lg md:mt-0"
                        >
                            <div class="flex gap-3">
                                <span
                                    class="size-12 rounded-full bg-slate-200"
                                ></span>
                                <div>
                                    <b class="text-xs">Nadia Belkheir</b>
                                    <p class="text-[9px] text-slate-400">
                                        Enseignante
                                    </p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-2 gap-3 text-[9px]">
                                <span>Poste<br /><b>Enseignante</b></span
                                ><span>Département<br /><b>Pédagogie</b></span
                                ><span
                                    >Date d'embauche<br /><b
                                        >12/09/2021</b
                                    ></span
                                ><span
                                    >Salaire mensuel<br /><b>85 000 DA</b></span
                                >
                            </div>
                            <div
                                class="mt-5 flex h-20 items-end justify-around rounded-lg bg-slate-50 p-3"
                            >
                                <span
                                    v-for="h in [42, 62, 50, 72, 58]"
                                    :key="h"
                                    class="w-3 bg-blue-500"
                                    :style="{ height: `${h}%` }"
                                ></span>
                            </div>
                        </div>
                    </article>
                    <article
                        class="grid rounded-2xl bg-gradient-to-r from-[#fff9ee] to-white p-8 md:grid-cols-[.95fr_1.05fr]"
                    >
                        <div>
                            <span class="text-[9px] font-bold text-violet-500"
                                >CERTIFICATS & BADGES</span
                            >
                            <h2 class="mt-3 text-2xl font-black">
                                Certificats et badges<br />générés
                                automatiquement
                            </h2>
                            <p class="mt-3 text-xs leading-5 text-slate-500">
                                Créez et délivrez des certificats professionnels
                                en quelques clics.
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <span
                                    v-for="x in [
                                        'Modèles personnalisables',
                                        'QR code & vérification',
                                        'Export PDF / Impression',
                                        'Émission en masse',
                                    ]"
                                    :key="x"
                                    class="rounded-lg bg-amber-50 px-3 py-2 text-[9px]"
                                    >▣ {{ x }}</span
                                >
                            </div>
                        </div>
                        <div class="relative mt-5 md:mt-0">
                            <div
                                class="border-[7px] border-double border-amber-300 bg-white p-7 text-center shadow-xl"
                            >
                                <Award class="mx-auto size-8 text-amber-500" />
                                <p class="mt-2 font-serif text-xl font-bold">
                                    CERTIFICAT<br /><span class="text-xs"
                                        >DE RÉUSSITE</span
                                    >
                                </p>
                                <p class="mt-4 text-[9px]">Décerné à</p>
                                <p class="font-serif text-lg font-bold">
                                    Ahmed Belkacem
                                </p>
                                <p class="mt-3 text-[8px]">
                                    pour avoir complété avec succès la formation
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
                <article
                    class="grid overflow-hidden rounded-2xl bg-gradient-to-r from-[#f2faff] to-white p-8 lg:grid-cols-[.7fr_.45fr_1fr]"
                >
                    <div>
                        <span class="text-[9px] font-bold text-blue-500"
                            >APPLICATION MOBILE</span
                        >
                        <h2 class="mt-3 text-2xl font-black">
                            Parents connectés,<br />enfants suivis
                        </h2>
                        <p class="mt-3 text-xs leading-5 text-slate-500">
                            L'application mobile Easy School permet aux parents
                            de suivre la scolarité de leurs enfants en temps
                            réel.
                        </p>
                        <div class="mt-5 grid gap-3 text-[10px]">
                            <span
                                v-for="x in [
                                    'Notes, moyennes et bulletins',
                                    'Présences et absences',
                                    'Observations et messages',
                                    'Actualités et événements',
                                ]"
                                :key="x"
                                class="flex gap-2"
                                ><CheckCircle2
                                    class="size-4 text-[#10bfa9]"
                                />{{ x }}</span
                            >
                        </div>
                    </div>
                    <div
                        class="mx-auto mt-5 w-56 rounded-[32px] border-[7px] border-[#071726] bg-white p-3 shadow-2xl lg:mt-0 lg:-mb-16"
                    >
                        <div class="rounded-xl bg-blue-600 p-3 text-white">
                            <p class="text-[8px]">Bonjour,</p>
                            <b>Samira</b>
                            <div
                                class="mt-4 rounded-lg bg-white p-3 text-[#102a4f]"
                            >
                                <p class="text-[8px]">Élève</p>
                                <b class="text-xs">Yacine Belkacem</b>
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-4 gap-2 py-4 text-center text-[7px]"
                        >
                            <span
                                v-for="x in [
                                    'Aperçu',
                                    'Notes',
                                    'Présences',
                                    'Messages',
                                ]"
                                :key="x"
                                >▣<br />{{ x }}</span
                            >
                        </div>
                        <p class="text-[9px] font-bold">Moyenne générale</p>
                        <strong class="text-xl">16,45</strong
                        ><span class="text-[8px]"> /20</span>
                    </div>
                    <div class="p-3 lg:pl-16">
                        <h3 class="mt-3 text-lg font-extrabold">
                            Restez informé au quotidien
                        </h3>
                        <p class="mt-3 text-xs leading-5 text-slate-500">
                            Recevez toutes les informations importantes
                            concernant vos enfants.
                        </p>
                        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <span
                                v-for="(x, i) in [
                                    'Notifications en temps réel',
                                    'Messagerie sécurisée',
                                    'Agenda et événements',
                                    'Suivi complet des enfants',
                                ]"
                                :key="x"
                                class="rounded-xl border bg-white p-4 text-center text-[9px] font-semibold shadow-sm"
                                ><component
                                    :is="
                                        [
                                            Bell,
                                            MessageCircle,
                                            CalendarDays,
                                            Users,
                                        ][i]
                                    "
                                    class="mx-auto mb-3 size-7 text-blue-500"
                                />{{ x }}</span
                            >
                        </div>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <span
                                class="rounded-lg bg-black px-5 py-2 text-xs font-bold text-white"
                                > App Store</span
                            ><span
                                class="rounded-lg bg-black px-5 py-2 text-xs font-bold text-white"
                                >▶ Google Play</span
                            >
                        </div>
                    </div>
                </article>
            </section>
            <section
                id="about"
                class="mx-auto max-w-[1380px] px-6 py-8 lg:px-10"
            >
                <p class="text-center text-[9px] font-bold text-blue-500">
                    POURQUOI EASY SCHOOL ?
                </p>
                <h2 class="mt-2 text-center text-xl font-extrabold">
                    Une solution puissante et pensée pour votre réussite
                </h2>
                <div
                    class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                >
                    <article
                        v-for="a in assurances"
                        :key="a[1] as string"
                        class="flex gap-3 rounded-xl border p-4"
                    >
                        <component
                            :is="a[0]"
                            class="size-7 shrink-0 text-blue-600"
                        />
                        <div>
                            <h3 class="text-[11px] font-extrabold">
                                {{ a[1] }}
                            </h3>
                            <p class="mt-1 text-[9px] leading-4 text-slate-500">
                                {{ a[2] }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>
            <section
                id="tarifs"
                class="mx-auto max-w-[1380px] px-6 pb-5 lg:px-10"
            >
                <div
                    class="flex flex-col items-center justify-between gap-6 rounded-2xl bg-gradient-to-r from-[#07345e] to-[#064486] px-8 py-7 text-white md:flex-row"
                >
                    <div>
                        <h2 class="text-2xl font-black">
                            Prêt à découvrir Easy School ?
                        </h2>
                        <p
                            class="mt-2 max-w-xl text-xs leading-5 text-slate-200"
                        >
                            Demandez une démonstration personnalisée adaptée aux
                            besoins de votre établissement. Démo configurable
                            jusqu'à 15 jours.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <Link
                            href="/demo"
                            class="flex items-center gap-3 rounded-xl bg-[#12cbb2] px-6 py-3 text-sm font-bold"
                            >Demander une démo
                            <ArrowRight class="size-4" /></Link
                        ><a
                            href="#contact"
                            class="flex items-center gap-3 rounded-xl border border-white px-6 py-3 text-sm font-bold"
                            ><Phone class="size-4" /> Nous contacter</a
                        >
                    </div>
                </div>
            </section>
        </main></HomeLayout
    >
</template>

<style scoped>
@keyframes phone-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0) rotate(-1deg);
    }
    35% {
        transform: translate3d(3px, -13px, 0) rotate(1deg);
    }
    70% {
        transform: translate3d(-2px, -6px, 0) rotate(-0.5deg);
    }
}
@keyframes phone-orbit {
    to {
        transform: rotate(360deg);
    }
}
@keyframes phone-glow {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.88);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.12);
    }
}
@keyframes phone-sparkle {
    0%,
    100% {
        opacity: 0.45;
        transform: scale(0.7) rotate(0);
    }
    50% {
        opacity: 1;
        transform: scale(1.15) rotate(16deg);
    }
}
.hero-phone-float {
    animation: phone-float 5.2s ease-in-out infinite;
    will-change: transform;
}
.hero-phone-orbit {
    animation: phone-orbit 12s linear infinite;
}
.hero-phone-glow {
    animation: phone-glow 3.4s ease-in-out infinite;
}
.hero-phone-sparkle {
    animation: phone-sparkle 2.1s ease-in-out infinite;
}
.hero-phone-float:hover {
    animation-duration: 2.6s;
}
@media (prefers-reduced-motion: reduce) {
    .hero-phone-float,
    .hero-phone-orbit,
    .hero-phone-glow,
    .hero-phone-sparkle {
        animation: none;
    }
}
</style>
