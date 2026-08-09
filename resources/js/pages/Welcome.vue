<script setup lang="ts">
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    BriefcaseBusiness,
    Calculator,
    Check,
    ChevronDown,
    Clock,
    Code2,
    Globe2,
    GraduationCap,
    Megaphone,
    Monitor,
    Palette,
    Rocket,
    Star,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';

const courses = [
    {
        title: 'Développement Web',
        duration: '12 semaines',
        level: 'Intermédiaire',
        icon: Code2,
        color: 'bg-orange-50 text-orange-500',
    },
    {
        title: 'Design Graphique',
        duration: '9 semaines',
        level: 'Débutant',
        icon: Palette,
        color: 'bg-blue-50 text-blue-500',
    },
    {
        title: 'Marketing Digital',
        duration: '10 semaines',
        level: 'Intermédiaire',
        icon: Megaphone,
        color: 'bg-emerald-50 text-emerald-500',
    },
    {
        title: 'Comptabilité & Gestion',
        duration: '12 semaines',
        level: 'Intermédiaire',
        icon: Calculator,
        color: 'bg-violet-50 text-violet-500',
    },
    {
        title: 'Langues',
        duration: '8 semaines',
        level: 'Tous niveaux',
        icon: Globe2,
        color: 'bg-amber-50 text-amber-500',
    },
    {
        title: 'Bureautique',
        duration: '6 semaines',
        level: 'Débutant',
        icon: Monitor,
        color: 'bg-sky-50 text-sky-500',
    },
];
const instructors = [
    {
        name: 'Yacine B.',
        role: 'Développeur Full Stack',
        image: '/images/team/michael.webp',
    },
    {
        name: 'Sonia K.',
        role: 'Designer graphique',
        image: '/images/team/sara.webp',
    },
    {
        name: 'Mehdi H.',
        role: 'Expert marketing digital',
        image: '/images/team/emily.webp',
    },
];
const faqs = [
    [
        'Faut-il avoir des prérequis pour suivre une formation ?',
        'Chaque formation indique clairement son niveau. Plusieurs parcours sont accessibles aux débutants sans prérequis.',
    ],
    [
        'Quels sont les horaires des formations ?',
        'Nous proposons des sessions en journée, en soirée et le week-end selon le programme choisi.',
    ],
    [
        'Puis-je payer en plusieurs fois ?',
        'Oui, des facilités de paiement peuvent être proposées selon la durée de la formation.',
    ],
    [
        'Les formations sont-elles certifiées ?',
        'Une attestation ou un certificat de fin de formation est délivré après validation du parcours.',
    ],
];
const openFaq = ref<number | null>(0);
</script>

<template>
    <HomeLayout>
        <Head title="Formations professionnelles" />
        <main>
            <section
                class="relative overflow-hidden bg-gradient-to-br from-white via-white to-orange-50/70"
            >
                <div
                    class="mx-auto grid max-w-7xl items-center gap-10 px-6 py-16 lg:grid-cols-[.8fr_1.2fr] lg:py-20"
                >
                    <div class="relative z-10">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-bold text-[#f4511e] shadow-sm"
                        >
                            <Award class="size-4" /> Formations professionnelles
                            certifiantes
                        </div>
                        <h1
                            class="mt-7 text-5xl leading-[1.08] font-black tracking-tight md:text-6xl"
                        >
                            Construisez<br />votre
                            <span class="text-[#f4511e]">avenir</span>
                        </h1>
                        <p
                            class="mt-6 max-w-lg text-lg leading-8 text-slate-600"
                        >
                            Des formations pratiques, actuelles et certifiantes
                            pour développer vos compétences et accélérer votre
                            carrière.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a
                                href="#formations"
                                class="rounded-xl bg-[#f4511e] px-6 py-3.5 font-bold text-white shadow-xl shadow-orange-200 transition hover:-translate-y-0.5"
                                >Découvrir nos formations →</a
                            ><a
                                href="#contact"
                                class="rounded-xl border border-slate-200 bg-white px-6 py-3.5 font-bold transition hover:border-orange-300"
                                >Nous contacter</a
                            >
                        </div>
                    </div>
                    <img
                        src="/images/school/learning-center-hero.png"
                        alt="Centre de formation moderne avec salles de classe"
                        class="w-full mix-blend-multiply drop-shadow-2xl"
                    />
                </div>
            </section>

            <section class="relative z-10 mx-auto -mt-2 max-w-7xl px-6">
                <div
                    class="grid rounded-2xl border bg-white p-6 shadow-xl shadow-slate-200/60 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="stat in [
                            ['500+', 'apprenants formés'],
                            ['25', 'formations certifiantes'],
                            ['15', 'formateurs experts'],
                            ['92%', 'de satisfaction'],
                        ]"
                        :key="stat[0]"
                        class="border-slate-100 px-5 py-3 text-center last:border-0 lg:border-r"
                    >
                        <strong class="text-2xl">{{ stat[0] }}</strong>
                        <p class="text-xs text-slate-500">{{ stat[1] }}</p>
                    </div>
                </div>
            </section>

            <section id="formations" class="mx-auto max-w-7xl px-6 py-20">
                <h2 class="text-center text-3xl font-extrabold">
                    Des formations pour
                    <span class="text-[#f4511e]">chaque ambition</span>
                </h2>
                <div
                    class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                >
                    <article
                        v-for="course in courses"
                        :key="course.title"
                        class="group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                    >
                        <div
                            :class="course.color"
                            class="grid size-12 place-items-center rounded-xl"
                        >
                            <component :is="course.icon" class="size-6" />
                        </div>
                        <h3 class="mt-5 min-h-12 font-bold">
                            {{ course.title }}
                        </h3>
                        <p
                            class="mt-3 flex items-center gap-1 text-xs text-slate-500"
                        >
                            <Clock class="size-3.5" /> {{ course.duration }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            ● {{ course.level }}
                        </p>
                        <div class="mt-5 text-xl text-[#f4511e]">→</div>
                    </article>
                </div>
            </section>

            <section id="about" class="bg-slate-50 py-20">
                <div
                    class="mx-auto grid max-w-7xl items-center gap-12 px-6 lg:grid-cols-2"
                >
                    <div>
                        <p
                            class="text-sm font-extrabold text-[#f4511e] uppercase"
                        >
                            Pourquoi nous choisir ?
                        </p>
                        <h2 class="mt-3 text-4xl font-black">
                            Une expérience d'apprentissage orientée
                            <span class="text-[#f4511e]">résultats</span>
                        </h2>
                        <p class="mt-5 leading-7 text-slate-600">
                            Nous combinons expertise, pratique et accompagnement
                            pour vous aider à atteindre vos objectifs
                            professionnels.
                        </p>
                        <div class="mt-8 grid gap-5 sm:grid-cols-2">
                            <div
                                v-for="item in [
                                    { t: 'Formateurs expérimentés', i: Users },
                                    {
                                        t: 'Apprentissage pratique',
                                        i: BriefcaseBusiness,
                                    },
                                    { t: 'Horaires flexibles', i: Clock },
                                    { t: 'Certification reconnue', i: Award },
                                ]"
                                :key="item.t"
                                class="flex gap-3"
                            >
                                <span
                                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-orange-100 text-[#f4511e]"
                                    ><component :is="item.i" class="size-5"
                                /></span>
                                <div>
                                    <h3 class="font-bold">{{ item.t }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Un parcours pensé pour votre réussite.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-[2rem] bg-white p-8 shadow-xl">
                        <div
                            class="rounded-2xl bg-gradient-to-br from-[#101b36] to-[#24375f] p-10 text-white"
                        >
                            <GraduationCap class="size-16 text-orange-400" />
                            <h3 class="mt-8 text-3xl font-black">
                                Apprenez aujourd'hui.<br />Réussissez demain.
                            </h3>
                            <div class="mt-8 space-y-3 text-sm text-slate-200">
                                <p class="flex gap-2">
                                    <Check class="size-5 text-orange-400" />
                                    Projets concrets et cas pratiques
                                </p>
                                <p class="flex gap-2">
                                    <Check class="size-5 text-orange-400" />
                                    Suivi personnalisé
                                </p>
                                <p class="flex gap-2">
                                    <Check class="size-5 text-orange-400" />
                                    Compétences recherchées
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-20">
                <div
                    class="grid items-center gap-6 rounded-3xl border border-orange-100 bg-orange-50/60 p-7 md:grid-cols-[auto_1fr_auto]"
                >
                    <div
                        class="grid size-24 place-items-center rounded-2xl bg-[#101b36] text-orange-400"
                    >
                        <Code2 class="size-12" />
                    </div>
                    <div>
                        <p class="text-sm font-bold text-[#f4511e]">
                            Formation phare
                        </p>
                        <h2 class="mt-1 text-2xl font-black">
                            Développement Web Full Stack
                        </h2>
                        <p class="mt-3 text-sm text-slate-600">
                            12 semaines · Présentiel · Niveau intermédiaire
                        </p>
                    </div>
                    <div class="md:border-l md:pl-8">
                        <p class="text-xs text-slate-500">Tarif</p>
                        <strong class="text-2xl text-[#f4511e]"
                            >45 000 DZD</strong
                        ><a
                            href="#contact"
                            class="mt-3 block rounded-xl bg-[#f4511e] px-5 py-3 text-center text-sm font-bold text-white"
                            >Voir le programme →</a
                        >
                    </div>
                </div>
            </section>

            <section class="bg-slate-50 py-20">
                <div class="mx-auto max-w-7xl px-6">
                    <h2 class="text-center text-3xl font-extrabold">
                        Comment <span class="text-[#f4511e]">s'inscrire ?</span>
                    </h2>
                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        <div
                            v-for="(step, index) in [
                                {
                                    t: 'Choisissez une formation',
                                    d: 'Explorez nos parcours et trouvez celui qui vous correspond.',
                                    i: BookOpen,
                                },
                                {
                                    t: 'Inscrivez-vous en ligne',
                                    d: 'Créez votre compte étudiant en quelques minutes.',
                                    i: GraduationCap,
                                },
                                {
                                    t: 'Commencez votre parcours',
                                    d: 'Accédez à votre espace et démarrez votre apprentissage.',
                                    i: Rocket,
                                },
                            ]"
                            :key="step.t"
                            class="relative text-center"
                        >
                            <span
                                class="absolute top-0 left-1/2 -translate-x-10 -translate-y-3 rounded-full bg-[#f4511e] px-2 py-1 text-xs font-bold text-white"
                                >{{ index + 1 }}</span
                            >
                            <div
                                class="mx-auto grid size-20 place-items-center rounded-2xl bg-orange-100 text-[#f4511e]"
                            >
                                <component :is="step.i" class="size-9" />
                            </div>
                            <h3 class="mt-5 font-bold">{{ step.t }}</h3>
                            <p
                                class="mx-auto mt-2 max-w-xs text-sm leading-6 text-slate-500"
                            >
                                {{ step.d }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="formateurs" class="mx-auto max-w-7xl px-6 py-20">
                <h2 class="text-center text-3xl font-extrabold">
                    Nos <span class="text-[#f4511e]">formateurs</span>
                </h2>
                <div class="mx-auto mt-10 grid max-w-5xl gap-5 md:grid-cols-3">
                    <article
                        v-for="person in instructors"
                        :key="person.name"
                        class="overflow-hidden rounded-2xl border bg-white shadow-sm"
                    >
                        <img
                            :src="person.image"
                            :alt="person.name"
                            class="h-64 w-full object-cover object-top"
                        />
                        <div class="p-5">
                            <h3 class="font-bold">{{ person.name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ person.role }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="bg-orange-50/50 py-20">
                <div class="mx-auto max-w-7xl px-6">
                    <h2 class="text-center text-3xl font-extrabold">
                        Ce que disent nos
                        <span class="text-[#f4511e]">apprenants</span>
                    </h2>
                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <blockquote
                            v-for="(quote, i) in [
                                'Une formation très pratique qui m’a permis de changer de carrière.',
                                'Un accompagnement de qualité et des projets proches du monde professionnel.',
                                'Des formateurs disponibles, pédagogues et vraiment passionnés.',
                            ]"
                            :key="quote"
                            class="rounded-2xl bg-white p-6 shadow-sm"
                        >
                            <p class="leading-7 text-slate-600">
                                “{{ quote }}”
                            </p>
                            <div class="mt-5 flex items-center justify-between">
                                <strong>{{
                                    ['Amel R.', 'Karim M.', 'Lina T.'][i]
                                }}</strong
                                ><span class="flex text-[#f4511e]"
                                    ><Star
                                        v-for="n in 5"
                                        :key="n"
                                        class="size-4 fill-current"
                                /></span>
                            </div>
                        </blockquote>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-5xl px-6 py-20">
                <h2 class="text-center text-3xl font-extrabold">
                    Questions <span class="text-[#f4511e]">fréquentes</span>
                </h2>
                <div
                    class="mt-10 divide-y overflow-hidden rounded-2xl border bg-white"
                >
                    <div v-for="(faq, index) in faqs" :key="faq[0]">
                        <button
                            class="flex w-full items-center justify-between p-5 text-left font-semibold"
                            @click="openFaq = openFaq === index ? null : index"
                        >
                            {{ faq[0]
                            }}<ChevronDown
                                class="size-5 transition"
                                :class="{ 'rotate-180': openFaq === index }"
                            />
                        </button>
                        <p
                            v-if="openFaq === index"
                            class="px-5 pb-5 text-sm leading-6 text-slate-600"
                        >
                            {{ faq[1] }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 pb-20">
                <div
                    class="flex flex-col items-center justify-between gap-6 rounded-3xl bg-gradient-to-r from-[#101b36] to-[#24375f] p-9 text-white md:flex-row"
                >
                    <div>
                        <p class="text-3xl font-black">
                            Prêt à développer vos compétences ?
                        </p>
                        <p class="mt-2 text-slate-300">
                            Rejoignez des centaines d'apprenants et construisez
                            votre avenir.
                        </p>
                    </div>
                    <Link
                        href="/login"
                        class="shrink-0 rounded-xl bg-[#f4511e] px-7 py-4 font-bold shadow-lg"
                        >Se connecter →</Link
                    >
                </div>
            </section>
        </main>
    </HomeLayout>
</template>
