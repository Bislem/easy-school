<script setup lang="ts">
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Apple,
    ArrowRight,
    BarChart3,
    Bell,
    Building2,
    CalendarCheck,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock3,
    FileBadge,
    FileText,
    GraduationCap,
    Layers3,
    LockKeyhole,
    Megaphone,
    MessageCircle,
    Monitor,
    School,
    ShieldCheck,
    Smartphone,
    Sparkles,
    UserRoundCheck,
    Users,
    UsersRound,
} from 'lucide-vue-next';

const productPillars = [
    {
        eyebrow: 'SCOLARITÉ',
        title: 'Du premier contact au bulletin',
        description:
            'Centralisez inscriptions, dossiers, classes, notes, absences et documents dans un parcours continu.',
        icon: GraduationCap,
        color: 'teal',
        items: [
            'Inscriptions',
            'Dossiers élèves',
            'Notes & bulletins',
            'Présences',
        ],
    },
    {
        eyebrow: 'ORGANISATION',
        title: 'Une journée scolaire bien orchestrée',
        description:
            'Construisez les emplois du temps, coordonnez les équipes et détectez les conflits avant publication.',
        icon: CalendarCheck,
        color: 'blue',
        items: [
            'Emplois du temps',
            'Classes & groupes',
            'Salles & ressources',
            'Annonces',
        ],
    },
    {
        eyebrow: 'ADMINISTRATION',
        title: 'Les opérations sous contrôle',
        description:
            'Gérez les équipes, les accès, les paiements et les documents sans multiplier les fichiers.',
        icon: Layers3,
        color: 'violet',
        items: [
            'Ressources humaines',
            'Paiements',
            'Rôles & permissions',
            'Rapports',
        ],
    },
];

const dailyFlow = [
    {
        number: '01',
        title: 'L’administration organise',
        description:
            'Paramètres, dossiers, planning, finance et accès sont pilotés depuis un espace central.',
        icon: Building2,
    },
    {
        number: '02',
        title: 'Les équipes collaborent',
        description:
            'Chaque membre retrouve les classes, outils et informations utiles à son rôle.',
        icon: UsersRound,
    },
    {
        number: '03',
        title: 'Les parents restent informés',
        description:
            'Notes, absences, emploi du temps et annonces sont disponibles dans leur portail web.',
        icon: UserRoundCheck,
    },
];

const assurances = [
    {
        title: 'Accès précis',
        description:
            'Des rôles et permissions adaptés aux responsabilités de chaque utilisateur.',
        icon: LockKeyhole,
    },
    {
        title: 'Données isolées',
        description:
            'Chaque établissement conserve son propre environnement, ses réglages et ses données.',
        icon: ShieldCheck,
    },
    {
        title: 'Multi-sites natif',
        description:
            'Une vue globale avec des périmètres et indicateurs propres à chaque campus.',
        icon: School,
    },
    {
        title: 'Traçabilité',
        description:
            'Les actions sensibles et les changements importants restent consultables.',
        icon: FileText,
    },
];

const portalFeatures = [
    ['Notes et bulletins', BarChart3],
    ['Absences et justificatifs', CalendarCheck],
    ['Emploi du temps', CalendarDays],
    ['Annonces de l’école', Megaphone],
];
</script>

<template>
    <HomeLayout>
        <Head title="Easy School — La gestion scolaire, enfin fluide" />

        <main class="overflow-hidden">
            <section
                class="relative bg-[#061d3b] pt-28 pb-20 text-white lg:min-h-[760px] lg:pt-32"
            >
                <div
                    class="pointer-events-none absolute inset-0 [background-image:radial-gradient(circle_at_9%_40%,rgba(18,203,178,.18),transparent_30%),radial-gradient(circle_at_88%_15%,rgba(21,104,157,.32),transparent_34%)] opacity-60"
                ></div>
                <div
                    class="hero-grid pointer-events-none absolute inset-0 opacity-[.06]"
                ></div>
                <div
                    class="pointer-events-none absolute top-24 -right-40 size-[520px] rounded-full border border-dashed border-cyan-300/20"
                ></div>

                <div
                    class="relative mx-auto grid max-w-[1380px] items-center gap-14 px-6 lg:grid-cols-[.83fr_1.17fr] lg:px-10"
                >
                    <div class="max-w-2xl py-8 lg:py-14">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#23dcc2]/25 bg-[#23dcc2]/10 px-3.5 py-2 text-[11px] font-extrabold tracking-[.16em] text-[#6af0dc]"
                        >
                            <span class="relative flex size-2"
                                ><span
                                    class="absolute inline-flex size-full animate-ping rounded-full bg-[#21d8bf] opacity-60"
                                ></span
                                ><span
                                    class="relative inline-flex size-2 rounded-full bg-[#21d8bf]"
                                ></span
                            ></span>
                            CONÇU POUR LES ÉTABLISSEMENTS D’AUJOURD’HUI
                        </div>
                        <h1
                            class="mt-7 text-[42px] leading-[1.04] font-black tracking-[-.035em] sm:text-6xl lg:text-[66px]"
                        >
                            Toute votre école.<br /><span
                                class="bg-gradient-to-r from-[#1bd2ba] to-[#71ead9] bg-clip-text text-transparent"
                                >Enfin au même endroit.</span
                            >
                        </h1>
                        <p
                            class="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8"
                        >
                            Easy School relie la scolarité, les équipes, la
                            finance et les parents dans une plateforme claire,
                            sécurisée et pensée pour le quotidien.
                        </p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                href="/demo"
                                class="group inline-flex items-center justify-center gap-3 rounded-xl bg-[#16cdb3] px-6 py-3.5 text-sm font-extrabold text-[#052d38] shadow-xl shadow-black/20 transition hover:-translate-y-0.5 hover:bg-[#2adde0]"
                            >
                                Découvrir Easy School
                                <ArrowRight
                                    class="size-4 transition group-hover:translate-x-1"
                                />
                            </Link>
                            <Link
                                href="/parent/login"
                                class="inline-flex items-center justify-center gap-3 rounded-xl border border-white/20 bg-white/5 px-6 py-3.5 text-sm font-bold text-white backdrop-blur transition hover:border-white/35 hover:bg-white/10"
                            >
                                <Users class="size-4 text-[#50e0cd]" /> Espace
                                parents
                            </Link>
                        </div>
                        <div
                            class="mt-9 flex flex-wrap gap-x-6 gap-y-3 text-xs text-slate-300"
                        >
                            <span
                                v-for="item in [
                                    'Démo personnalisée',
                                    'Mise en place accompagnée',
                                    'Support local',
                                ]"
                                :key="item"
                                class="flex items-center gap-2"
                                ><Check class="size-4 text-[#22d8bf]" />{{
                                    item
                                }}</span
                            >
                        </div>
                    </div>

                    <div class="relative lg:pt-5">
                        <div
                            class="absolute inset-x-10 bottom-0 h-40 rounded-full bg-cyan-400/20 blur-[80px]"
                        ></div>
                        <div
                            class="hero-dashboard relative overflow-hidden rounded-[22px] border border-white/20 bg-white shadow-[0_40px_100px_-24px_rgba(0,0,0,.85)] ring-1 ring-cyan-300/20"
                        >
                            <div
                                class="flex h-10 items-center gap-2 border-b border-slate-200 bg-slate-50 px-4"
                            >
                                <span
                                    class="size-2.5 rounded-full bg-[#ff6b63]"
                                ></span
                                ><span
                                    class="size-2.5 rounded-full bg-[#ffc34a]"
                                ></span
                                ><span
                                    class="size-2.5 rounded-full bg-[#32ca6d]"
                                ></span>
                                <span
                                    class="mx-auto flex h-5 w-2/5 items-center justify-center rounded-md bg-white text-[8px] font-semibold text-slate-400 shadow-sm"
                                    >app.easyschool.dz</span
                                >
                            </div>
                            <img
                                src="/images/dashborad-screenshot.png"
                                alt="Aperçu du tableau de bord Easy School"
                                class="block h-[330px] w-full object-cover object-left-top sm:h-[430px] lg:h-[470px]"
                            />
                        </div>
                        <div
                            class="hero-float absolute -bottom-7 -left-4 z-20 rounded-2xl border border-white/70 bg-white/95 p-4 text-[#0a2246] shadow-2xl backdrop-blur sm:-left-8"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid size-10 place-items-center rounded-xl bg-emerald-50"
                                    ><CheckCircle2
                                        class="size-5 text-emerald-600"
                                /></span>
                                <div>
                                    <b class="block text-xs"
                                        >Tout est centralisé</b
                                    ><span class="text-[10px] text-slate-500"
                                        >Une donnée, un seul endroit</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div
                            class="hero-float-delayed absolute -top-5 -right-3 z-20 hidden rounded-2xl border border-white/15 bg-[#0b3156]/95 p-4 shadow-2xl backdrop-blur sm:block lg:-right-7"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid size-9 place-items-center rounded-xl bg-cyan-300/10"
                                    ><ShieldCheck class="size-5 text-cyan-300"
                                /></span>
                                <div>
                                    <b class="block text-xs text-white"
                                        >Accès sécurisés</b
                                    ><span class="text-[10px] text-slate-300"
                                        >Selon chaque rôle</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="relative z-10 -mt-1 border-b border-slate-200 bg-white"
            >
                <div
                    class="mx-auto grid max-w-[1180px] grid-cols-2 divide-x divide-y divide-slate-100 px-6 py-2 sm:grid-cols-4 sm:divide-y-0"
                >
                    <div
                        v-for="(item, index) in [
                            [
                                Monitor,
                                '100% en ligne',
                                'Aucun logiciel à installer',
                            ],
                            [
                                Building2,
                                'Multi-établissements',
                                'Une vue claire par site',
                            ],
                            [
                                ShieldCheck,
                                'Accès maîtrisés',
                                'Rôles et permissions',
                            ],
                            [
                                Smartphone,
                                'Sur tous vos écrans',
                                'Ordinateur, tablette, mobile',
                            ],
                        ]"
                        :key="item[1] as string"
                        class="flex items-center gap-3 px-3 py-6 sm:px-6"
                        :class="index === 2 ? 'border-l-0 sm:border-l' : ''"
                    >
                        <component
                            :is="item[0]"
                            class="size-5 shrink-0 text-[#0db9a4]"
                        />
                        <div>
                            <b class="block text-xs text-[#092447]">{{
                                item[1]
                            }}</b
                            ><span class="text-[10px] text-slate-400">{{
                                item[2]
                            }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="modules" class="bg-[#f7fafc] py-20 lg:py-28">
                <div class="mx-auto max-w-[1320px] px-6 lg:px-10">
                    <div class="mx-auto max-w-3xl text-center">
                        <span
                            class="text-xs font-black tracking-[.18em] text-[#0ba996]"
                            >UNE PLATEFORME, UN FIL CONDUCTEUR</span
                        >
                        <h2
                            class="mt-4 text-3xl leading-tight font-black tracking-tight text-[#071f3e] sm:text-5xl"
                        >
                            Moins d’outils dispersés.<br />Plus de temps pour
                            l’essentiel.
                        </h2>
                        <p
                            class="mx-auto mt-5 max-w-2xl text-sm leading-7 text-slate-500 sm:text-base"
                        >
                            Chaque module partage le même contexte.
                            L’information circule sans ressaisie, de
                            l’inscription jusqu’au suivi des parents.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-5 lg:grid-cols-3">
                        <article
                            v-for="pillar in productPillars"
                            :key="pillar.title"
                            class="group relative overflow-hidden rounded-[24px] border border-slate-200 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-900/5"
                        >
                            <div
                                class="absolute top-0 right-0 size-32 rounded-full opacity-40 blur-3xl"
                                :class="
                                    pillar.color === 'teal'
                                        ? 'bg-teal-200'
                                        : pillar.color === 'blue'
                                          ? 'bg-blue-200'
                                          : 'bg-violet-200'
                                "
                            ></div>
                            <span
                                class="relative grid size-13 place-items-center rounded-2xl text-white shadow-lg"
                                :class="
                                    pillar.color === 'teal'
                                        ? 'bg-[#0bb8a4]'
                                        : pillar.color === 'blue'
                                          ? 'bg-[#2769d8]'
                                          : 'bg-[#7654d4]'
                                "
                                ><component :is="pillar.icon" class="size-6"
                            /></span>
                            <p
                                class="mt-6 text-[10px] font-black tracking-[.16em]"
                                :class="
                                    pillar.color === 'teal'
                                        ? 'text-teal-600'
                                        : pillar.color === 'blue'
                                          ? 'text-blue-600'
                                          : 'text-violet-600'
                                "
                            >
                                {{ pillar.eyebrow }}
                            </p>
                            <h3 class="mt-2 text-xl font-black text-[#092447]">
                                {{ pillar.title }}
                            </h3>
                            <p
                                class="mt-3 min-h-18 text-sm leading-6 text-slate-500"
                            >
                                {{ pillar.description }}
                            </p>
                            <div
                                class="mt-6 grid grid-cols-2 gap-2 border-t border-slate-100 pt-5"
                            >
                                <span
                                    v-for="item in pillar.items"
                                    :key="item"
                                    class="flex items-center gap-2 text-[11px] font-semibold text-slate-600"
                                    ><span
                                        class="size-1.5 rounded-full bg-[#13bca7]"
                                    ></span
                                    >{{ item }}</span
                                >
                            </div>
                        </article>
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-[1.25fr_.75fr]">
                        <article
                            class="relative overflow-hidden rounded-[24px] bg-[#082746] p-7 text-white sm:p-9"
                        >
                            <div
                                class="absolute -right-10 -bottom-20 size-64 rounded-full bg-[#16cdb3]/20 blur-3xl"
                            ></div>
                            <div
                                class="relative grid items-center gap-8 sm:grid-cols-[1fr_.75fr]"
                            >
                                <div>
                                    <span
                                        class="text-[10px] font-black tracking-[.16em] text-cyan-300"
                                        >PILOTAGE MULTI-SITES</span
                                    >
                                    <h3 class="mt-3 text-2xl font-black">
                                        Une vision globale.<br />Des réalités
                                        locales.
                                    </h3>
                                    <p
                                        class="mt-3 text-sm leading-6 text-slate-300"
                                    >
                                        Comparez les sites, adaptez les accès et
                                        conservez des règles propres à chaque
                                        établissement.
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div
                                        v-for="(site, i) in [
                                            'Campus central',
                                            'Site Est',
                                            'Site Ouest',
                                            'Centre langues',
                                        ]"
                                        :key="site"
                                        class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur"
                                    >
                                        <span
                                            class="text-[9px] text-slate-400"
                                            >{{ site }}</span
                                        >
                                        <div
                                            class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10"
                                        >
                                            <div
                                                class="h-full rounded-full bg-[#21d8bf]"
                                                :style="{
                                                    width: `${88 - i * 11}%`,
                                                }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <article
                            class="rounded-[24px] border border-amber-100 bg-gradient-to-br from-[#fffaf0] to-white p-7 sm:p-9"
                        >
                            <span
                                class="grid size-12 place-items-center rounded-2xl bg-amber-100 text-amber-700"
                                ><FileBadge class="size-6"
                            /></span>
                            <h3 class="mt-5 text-xl font-black text-[#092447]">
                                Documents prêts en quelques clics
                            </h3>
                            <p class="mt-3 text-sm leading-6 text-slate-500">
                                Certificats, badges, bulletins, reçus et
                                justificatifs restent liés au bon dossier.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="bg-white py-20 lg:py-28">
                <div class="mx-auto max-w-[1240px] px-6 lg:px-10">
                    <div
                        class="grid items-end gap-8 lg:grid-cols-[.85fr_1.15fr]"
                    >
                        <div>
                            <span
                                class="text-xs font-black tracking-[.18em] text-[#0ba996]"
                                >UNE MÊME INFORMATION, AU BON MOMENT</span
                            >
                            <h2
                                class="mt-4 text-3xl leading-tight font-black text-[#071f3e] sm:text-4xl"
                            >
                                De l’administration aux familles, tout reste
                                aligné.
                            </h2>
                        </div>
                        <p
                            class="max-w-2xl text-sm leading-7 text-slate-500 sm:text-base"
                        >
                            Easy School organise le passage de l’information
                            entre chaque acteur. Moins de relances, moins de
                            doubles saisies, et une vue adaptée à chacun.
                        </p>
                    </div>
                    <div class="relative mt-14 grid gap-5 lg:grid-cols-3">
                        <div
                            class="absolute top-12 right-[16%] left-[16%] hidden border-t border-dashed border-teal-300 lg:block"
                        ></div>
                        <article
                            v-for="step in dailyFlow"
                            :key="step.number"
                            class="relative rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="grid size-12 place-items-center rounded-2xl bg-[#edfafa] text-[#0cae9b]"
                                    ><component :is="step.icon" class="size-6"
                                /></span>
                                <span
                                    class="font-mono text-xs font-black text-slate-300"
                                    >{{ step.number }}</span
                                >
                            </div>
                            <h3 class="mt-5 text-lg font-black text-[#092447]">
                                {{ step.title }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                {{ step.description }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="parents" class="bg-[#f7fafc] py-20 lg:py-28">
                <div class="mx-auto max-w-[1320px] px-6 lg:px-10">
                    <article
                        class="relative overflow-hidden rounded-[32px] bg-[#071f3e] text-white shadow-2xl shadow-blue-950/15"
                    >
                        <div
                            class="absolute -top-32 -right-20 size-96 rounded-full bg-[#12cbb2]/20 blur-[90px]"
                        ></div>
                        <div
                            class="relative grid items-center gap-12 p-7 sm:p-10 lg:grid-cols-[.78fr_1.22fr] lg:p-14"
                        >
                            <div>
                                <span
                                    class="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1.5 text-[10px] font-black tracking-[.15em] text-cyan-300"
                                    ><span
                                        class="size-1.5 rounded-full bg-[#20d8bf]"
                                    ></span>
                                    PORTAIL WEB DISPONIBLE</span
                                >
                                <h2
                                    class="mt-5 text-3xl leading-tight font-black sm:text-4xl"
                                >
                                    Les parents suivent.<br /><span
                                        class="text-[#20d8bf]"
                                        >L’école garde le lien.</span
                                    >
                                </h2>
                                <p
                                    class="mt-4 max-w-lg text-sm leading-7 text-slate-300"
                                >
                                    Aucune installation nécessaire. Depuis leur
                                    navigateur, les parents accèdent aux
                                    informations publiées par l’établissement,
                                    sur ordinateur, tablette ou téléphone.
                                </p>
                                <div
                                    class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2"
                                >
                                    <span
                                        v-for="feature in portalFeatures"
                                        :key="feature[0] as string"
                                        class="flex items-center gap-2 text-xs text-slate-200"
                                        ><component
                                            :is="feature[1]"
                                            class="size-4 shrink-0 text-[#20d8bf]"
                                        />{{ feature[0] }}</span
                                    >
                                </div>
                                <Link
                                    href="/parent/login"
                                    class="group mt-8 inline-flex items-center gap-3 rounded-xl bg-[#16cdb3] px-5 py-3 text-sm font-extrabold text-[#052e3a] shadow-lg transition hover:bg-[#28ddc4]"
                                    >Ouvrir l’espace parents
                                    <ArrowRight
                                        class="size-4 transition group-hover:translate-x-1"
                                /></Link>
                            </div>

                            <div>
                                <div
                                    class="overflow-hidden rounded-2xl border border-white/15 bg-[#f5f8fc] text-[#0a2246] shadow-2xl shadow-black/30"
                                >
                                    <div
                                        class="flex h-9 items-center gap-1.5 border-b border-slate-200 bg-white px-4"
                                    >
                                        <span
                                            class="size-2 rounded-full bg-red-400"
                                        ></span
                                        ><span
                                            class="size-2 rounded-full bg-amber-400"
                                        ></span
                                        ><span
                                            class="size-2 rounded-full bg-emerald-400"
                                        ></span
                                        ><span
                                            class="mx-auto rounded-md bg-slate-50 px-8 py-1 text-[8px] font-bold text-slate-400"
                                            >parent.easyschool.dz</span
                                        >
                                    </div>
                                    <div
                                        class="grid min-h-[320px] sm:grid-cols-[140px_1fr]"
                                    >
                                        <div
                                            class="hidden bg-[#0a2a4b] p-4 text-[9px] text-slate-300 sm:block"
                                        >
                                            <div
                                                class="mb-6 flex items-center gap-2 font-bold text-white"
                                            >
                                                <GraduationCap
                                                    class="size-4 text-[#20d8bf]"
                                                />
                                                Easy School
                                            </div>
                                            <div
                                                v-for="(item, i) in [
                                                    'Vue d’ensemble',
                                                    'Mes enfants',
                                                    'Emploi du temps',
                                                    'Absences',
                                                    'Notes',
                                                    'Annonces',
                                                ]"
                                                :key="item"
                                                class="mb-1 rounded-md px-2 py-2"
                                                :class="
                                                    i === 0
                                                        ? 'bg-[#12cbb2] font-bold text-[#052e3a]'
                                                        : ''
                                                "
                                            >
                                                {{ item }}
                                            </div>
                                        </div>
                                        <div class="p-4 sm:p-5">
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div>
                                                    <p
                                                        class="text-[9px] text-slate-400"
                                                    >
                                                        Bonjour Samira,
                                                    </p>
                                                    <b class="text-sm"
                                                        >Vue d’ensemble</b
                                                    >
                                                </div>
                                                <span class="relative"
                                                    ><Bell
                                                        class="size-4 text-slate-400" /><span
                                                        class="absolute -top-1 -right-1 size-1.5 rounded-full bg-red-500"
                                                    ></span
                                                ></span>
                                            </div>
                                            <div
                                                class="mt-4 rounded-xl bg-gradient-to-r from-[#0c4166] to-[#0d6970] p-4 text-white"
                                            >
                                                <p
                                                    class="text-[9px] text-cyan-100"
                                                >
                                                    Enfant sélectionné
                                                </p>
                                                <div
                                                    class="mt-1 flex items-center justify-between"
                                                >
                                                    <b>Yacine Belkacem</b
                                                    ><span
                                                        class="rounded-full bg-white/15 px-2 py-1 text-[8px]"
                                                        >4e année</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="mt-3 grid grid-cols-3 gap-2"
                                            >
                                                <div
                                                    v-for="stat in [
                                                        ['Moyenne', '16,45'],
                                                        ['Absences', '02'],
                                                        ['À venir', '03'],
                                                    ]"
                                                    :key="stat[0]"
                                                    class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm"
                                                >
                                                    <p
                                                        class="text-[8px] text-slate-400"
                                                    >
                                                        {{ stat[0] }}
                                                    </p>
                                                    <b
                                                        class="mt-1 block text-sm"
                                                        >{{ stat[1] }}</b
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="mt-3 rounded-lg border border-slate-200 bg-white p-3"
                                            >
                                                <div
                                                    class="flex items-center justify-between text-[9px]"
                                                >
                                                    <b>Dernières actualités</b
                                                    ><span class="text-teal-600"
                                                        >Tout voir</span
                                                    >
                                                </div>
                                                <div
                                                    class="mt-3 flex items-center gap-3"
                                                >
                                                    <span
                                                        class="grid size-8 place-items-center rounded-lg bg-teal-50"
                                                        ><CalendarDays
                                                            class="size-4 text-teal-600"
                                                    /></span>
                                                    <div>
                                                        <b
                                                            class="block text-[9px]"
                                                            >Réunion
                                                            parents–enseignants</b
                                                        ><span
                                                            class="text-[8px] text-slate-400"
                                                            >Jeudi, 16:30</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                                >
                                    <div
                                        class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="grid size-10 place-items-center rounded-xl bg-white/10"
                                                ><Smartphone
                                                    class="size-5 text-cyan-300"
                                            /></span>
                                            <div>
                                                <b class="block text-xs"
                                                    >Applications mobiles en
                                                    préparation</b
                                                ><span
                                                    class="text-[10px] text-slate-400"
                                                    >Le portail web est
                                                    disponible dès
                                                    maintenant</span
                                                >
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div
                                                class="flex min-w-[132px] items-center gap-2 rounded-xl border border-white/15 bg-white/[.07] px-3 py-2.5"
                                            >
                                                <Apple
                                                    class="size-6 fill-white text-white"
                                                    :stroke-width="1.5"
                                                />
                                                <div>
                                                    <span
                                                        class="block text-[8px] font-semibold tracking-wide text-slate-400"
                                                        >BIENTÔT SUR</span
                                                    ><b
                                                        class="block text-xs text-white"
                                                        >iOS</b
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="flex min-w-[132px] items-center gap-2 rounded-xl border border-white/15 bg-white/[.07] px-3 py-2.5"
                                            >
                                                <svg
                                                    viewBox="0 0 32 32"
                                                    class="size-6"
                                                    aria-label="Android"
                                                >
                                                    <path
                                                        d="M8.6 11.2h14.8v11.4a2 2 0 0 1-2 2h-1.1v3.2a1.2 1.2 0 0 1-2.4 0v-3.2h-3.8v3.2a1.2 1.2 0 0 1-2.4 0v-3.2h-1.1a2 2 0 0 1-2-2V11.2Z"
                                                        fill="#3ddc84"
                                                    />
                                                    <path
                                                        d="M8.7 9.7a7.5 7.5 0 0 1 2.5-4.5L9.3 2.4a.7.7 0 0 1 1.2-.8l1.8 2.7A8.4 8.4 0 0 1 16 3.5c1.3 0 2.6.3 3.7.8l1.8-2.7a.7.7 0 0 1 1.2.8l-1.9 2.8a7.5 7.5 0 0 1 2.5 4.5H8.7Z"
                                                        fill="#3ddc84"
                                                    />
                                                    <circle
                                                        cx="12.5"
                                                        cy="7"
                                                        r=".8"
                                                        fill="#071f3e"
                                                    />
                                                    <circle
                                                        cx="19.5"
                                                        cy="7"
                                                        r=".8"
                                                        fill="#071f3e"
                                                    />
                                                    <path
                                                        d="M5.8 12.1a1.2 1.2 0 0 1 1.2 1.2v7.3a1.2 1.2 0 1 1-2.4 0v-7.3a1.2 1.2 0 0 1 1.2-1.2Zm20.4 0a1.2 1.2 0 0 1 1.2 1.2v7.3a1.2 1.2 0 1 1-2.4 0v-7.3a1.2 1.2 0 0 1 1.2-1.2Z"
                                                        fill="#3ddc84"
                                                    />
                                                </svg>
                                                <div>
                                                    <span
                                                        class="block text-[8px] font-semibold tracking-wide text-slate-400"
                                                        >BIENTÔT SUR</span
                                                    ><b
                                                        class="block text-xs text-white"
                                                        >Android</b
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section id="securite" class="bg-white py-20 lg:py-28">
                <div class="mx-auto max-w-[1240px] px-6 lg:px-10">
                    <div
                        class="grid gap-12 lg:grid-cols-[.72fr_1.28fr] lg:items-center"
                    >
                        <div>
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1.5 text-[10px] font-black tracking-[.14em] text-teal-700"
                                ><ShieldCheck class="size-4" /> SÉCURITÉ PAR
                                CONCEPTION</span
                            >
                            <h2
                                class="mt-5 text-3xl leading-tight font-black text-[#071f3e] sm:text-4xl"
                            >
                                La bonne information.<br />Aux bonnes personnes.
                            </h2>
                            <p class="mt-4 text-sm leading-7 text-slate-500">
                                Easy School structure les accès autour des
                                rôles, des établissements et des
                                responsabilités. Chacun travaille dans un
                                périmètre clair.
                            </p>
                            <Link
                                href="/contact"
                                class="mt-7 inline-flex items-center gap-2 text-sm font-extrabold text-[#099b8c]"
                                >Parler de votre organisation
                                <ArrowRight class="size-4"
                            /></Link>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <article
                                v-for="item in assurances"
                                :key="item.title"
                                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-teal-200 hover:shadow-lg"
                            >
                                <span
                                    class="grid size-11 place-items-center rounded-xl bg-[#082b50] text-[#20d4bd]"
                                    ><component :is="item.icon" class="size-5"
                                /></span>
                                <h3 class="mt-5 font-black text-[#092447]">
                                    {{ item.title }}
                                </h3>
                                <p
                                    class="mt-2 text-xs leading-5 text-slate-500"
                                >
                                    {{ item.description }}
                                </p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="tarifs"
                class="bg-[#f7fafc] px-6 py-16 lg:px-10 lg:py-24"
            >
                <div
                    class="relative mx-auto max-w-[1280px] overflow-hidden rounded-[32px] bg-[#061d3b] text-white shadow-2xl shadow-blue-950/20"
                >
                    <div
                        class="absolute inset-0 bg-[radial-gradient(circle_at_85%_0%,rgba(19,203,177,.23),transparent_38%)]"
                    ></div>
                    <div
                        class="relative grid items-center gap-10 p-8 sm:p-12 lg:grid-cols-[1fr_.78fr] lg:p-16"
                    >
                        <div>
                            <span
                                class="text-xs font-black tracking-[.18em] text-[#22d8bf]"
                                >VOTRE ÉCOLE, VOTRE PARCOURS</span
                            >
                            <h2
                                class="mt-4 max-w-2xl text-3xl leading-tight font-black sm:text-5xl"
                            >
                                Prêt à voir ce que votre quotidien pourrait
                                devenir ?
                            </h2>
                            <p
                                class="mt-5 max-w-xl text-sm leading-7 text-slate-300 sm:text-base"
                            >
                                Nous préparons une démonstration autour de vos
                                priorités, de votre organisation et des modules
                                qui comptent vraiment pour vous.
                            </p>
                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href="/demo"
                                    class="group inline-flex items-center justify-center gap-3 rounded-xl bg-[#12cbb2] px-6 py-3.5 text-sm font-extrabold text-[#061d3b] transition hover:bg-[#2bdcc4]"
                                    >Demander ma démonstration
                                    <ArrowRight
                                        class="size-4 transition group-hover:translate-x-1"
                                /></Link>
                                <Link
                                    href="/contact"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/5 px-6 py-3.5 text-sm font-bold transition hover:bg-white/10"
                                    ><MessageCircle class="size-4" />Parler à
                                    l’équipe</Link
                                >
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                            <div
                                v-for="item in [
                                    [
                                        Clock3,
                                        'Échange ciblé',
                                        'Vos besoins avant la technique',
                                    ],
                                    [
                                        Sparkles,
                                        'Démo configurée',
                                        'Un environnement qui vous ressemble',
                                    ],
                                    [
                                        CheckCircle2,
                                        'Sans engagement',
                                        'Explorez avant de décider',
                                    ],
                                ]"
                                :key="item[1] as string"
                                class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                            >
                                <span
                                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#14c8b0]/15"
                                    ><component
                                        :is="item[0]"
                                        class="size-5 text-[#39dfca]"
                                /></span>
                                <div>
                                    <b class="block text-xs">{{ item[1] }}</b
                                    ><span class="text-[10px] text-slate-400">{{
                                        item[2]
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>

<style scoped>
.hero-grid {
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.8) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.8) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: linear-gradient(to bottom, black, transparent 90%);
}

@keyframes dashboard-in {
    from {
        opacity: 0;
        transform: translate3d(0, 22px, 0) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0) scale(1);
    }
}

@keyframes gentle-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -7px, 0);
    }
}

.hero-dashboard {
    animation: dashboard-in 0.8s cubic-bezier(0.2, 0.75, 0.25, 1) both;
}
.hero-float {
    animation: gentle-float 4.5s ease-in-out infinite;
}
.hero-float-delayed {
    animation: gentle-float 5s ease-in-out 1.2s infinite;
}

@media (prefers-reduced-motion: reduce) {
    .hero-dashboard,
    .hero-float,
    .hero-float-delayed {
        animation: none;
    }
}
</style>
