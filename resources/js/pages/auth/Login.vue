<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { request } from '@/routes/password';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    ChevronDown,
    ChevronUp,
    Eye,
    EyeOff,
    GraduationCap,
    Headphones,
    Layers3,
    LoaderCircle,
    LockKeyhole,
    Mail,
    RefreshCw,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{ status?: string; canResetPassword: boolean }>();

const showPassword = ref(false);
const isDemoOpen = ref(false);
</script>

<template>
    <Head title="Connexion" />

    <main class="min-h-screen bg-[#051c3a] text-white">
        <div
            class="pointer-events-none absolute inset-0 overflow-hidden [background-image:radial-gradient(circle_at_18%_25%,#0a4770_0,transparent_30%),radial-gradient(circle_at_82%_15%,#113e69_0,transparent_27%)] opacity-40"
        ></div>

        <div
            class="relative mx-auto max-w-[1380px] px-5 pt-6 pb-8 sm:px-8 lg:px-12"
        >
            <header class="flex items-center justify-between">
                <Link
                    href="/"
                    class="flex items-center gap-3 text-xl font-black sm:text-2xl"
                >
                    <GraduationCap class="size-10 text-[#16d1bc]" />
                    Easy School
                </Link>
                <div class="flex items-center gap-3">
                    <Link
                        href="/register-school"
                        class="hidden rounded-xl border border-[#16d1bc] px-4 py-2 text-sm font-bold text-[#16d1bc] transition hover:bg-[#16d1bc] hover:text-[#05203e] sm:inline-flex"
                        >Créer un établissement</Link
                    >
                    <Link
                        href="/"
                        class="flex items-center gap-2 text-sm font-semibold text-white/90 hover:text-[#16d1bc]"
                    >
                        <ArrowLeft class="size-4" /> Retour à l'accueil
                    </Link>
                </div>
            </header>

            <div
                class="mt-10 grid items-start gap-10 lg:grid-cols-[1.12fr_.88fr] xl:gap-16"
            >
                <section class="pt-3 lg:pt-5">
                    <p class="font-bold text-[#15cfb8]">
                        Bienvenue sur Easy School
                    </p>
                    <h1
                        class="mt-5 max-w-2xl text-4xl leading-tight font-black tracking-tight sm:text-5xl"
                    >
                        Votre plateforme de gestion<br />
                        <span class="text-[#16cdb5]"
                            >scolaire, simple et puissante</span
                        >
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base leading-7 text-slate-300"
                    >
                        Connectez-vous à votre espace pour piloter votre
                        établissement, collaborer avec vos équipes et offrir le
                        meilleur à vos élèves.
                    </p>

                    <div
                        class="mt-7 grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3"
                    >
                        <div
                            v-for="item in [
                                [
                                    ShieldCheck,
                                    'Une plateforme sécurisée',
                                    'Vos données sont protégées.',
                                ],
                                [
                                    UsersRound,
                                    'Adoptée par les écoles',
                                    'Une solution pensée pour vous.',
                                ],
                                [
                                    Headphones,
                                    'Un accompagnement humain',
                                    'Notre équipe reste disponible.',
                                ],
                            ]"
                            :key="item[1] as string"
                            class="flex gap-3"
                        >
                            <component
                                :is="item[0]"
                                class="mt-0.5 size-7 shrink-0 text-[#15cfb8]"
                            />
                            <div>
                                <h2 class="text-xs font-bold">{{ item[1] }}</h2>
                                <p
                                    class="mt-1 text-[11px] leading-4 text-slate-400"
                                >
                                    {{ item[2] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative mt-8 hidden h-[430px] overflow-hidden rounded-2xl border border-cyan-300/20 shadow-2xl shadow-black/40 lg:block"
                    >
                        <img
                            src="/images/side-panel-image.png"
                            alt="Campus Easy School et ses étudiants"
                            class="h-full w-full object-cover object-[center_62%]"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-[#041b38]/70 via-transparent to-transparent"
                        ></div>
                        <div
                            class="absolute right-5 bottom-5 left-5 flex items-center justify-between rounded-xl border border-white/15 bg-[#061f3e]/80 p-4 backdrop-blur-md"
                        >
                            <div>
                                <p class="text-sm font-bold">
                                    La réussite commence ici
                                </p>
                                <p class="mt-1 text-xs text-slate-300">
                                    Une seule plateforme pour toute votre
                                    communauté scolaire.
                                </p>
                            </div>
                            <GraduationCap
                                class="size-9 shrink-0 text-[#16d1bc]"
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[22px] bg-white p-6 text-[#092044] shadow-2xl shadow-black/30 sm:p-9 xl:p-11"
                >
                    <div class="text-center">
                        <span
                            class="mx-auto grid size-20 place-items-center rounded-full bg-[#e9faf8] text-[#0e9e99]"
                            ><LockKeyhole class="size-9"
                        /></span>
                        <h2 class="mt-6 text-2xl font-black sm:text-3xl">
                            Connexion à votre espace
                        </h2>
                        <p class="mt-2 text-sm text-slate-500">
                            Accédez en toute sécurité à votre plateforme Easy
                            School.
                        </p>
                    </div>

                    <div
                        v-if="status"
                        class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center text-sm font-medium text-emerald-700"
                    >
                        {{ status }}
                    </div>

                    <Form
                        v-bind="AuthenticatedSessionController.store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="mt-7 space-y-5"
                    >
                        <div>
                            <label
                                for="email"
                                class="mb-2 block text-xs font-bold"
                                >Adresse e-mail</label
                            >
                            <div class="relative">
                                <Mail
                                    class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                /><input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    placeholder="prenom.nom@etablissement.edu"
                                    class="h-13 w-full rounded-xl border border-slate-200 bg-white pr-4 pl-12 text-sm transition outline-none focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10"
                                />
                            </div>
                            <InputError :message="errors.email" class="mt-1" />
                        </div>
                        <div>
                            <div class="mb-2 flex justify-between">
                                <label for="password" class="text-xs font-bold"
                                    >Mot de passe</label
                                ><TextLink
                                    v-if="canResetPassword"
                                    :href="request()"
                                    class="text-xs font-bold text-[#079c94]"
                                    >Mot de passe oublié ?</TextLink
                                >
                            </div>
                            <div class="relative">
                                <LockKeyhole
                                    class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                /><input
                                    id="password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Votre mot de passe"
                                    class="h-13 w-full rounded-xl border border-slate-200 bg-white pr-12 pl-12 text-sm transition outline-none focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10"
                                /><button
                                    type="button"
                                    class="absolute top-1/2 right-4 -translate-y-1/2 text-slate-400 hover:text-[#079c94]"
                                    :aria-label="
                                        showPassword
                                            ? 'Masquer le mot de passe'
                                            : 'Afficher le mot de passe'
                                    "
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff
                                        v-if="showPassword"
                                        class="size-5"
                                    /><Eye v-else class="size-5" />
                                </button>
                            </div>
                            <InputError
                                :message="errors.password"
                                class="mt-1"
                            />
                        </div>
                        <label
                            for="remember"
                            class="flex cursor-pointer items-center gap-3 text-sm text-slate-700"
                            ><Checkbox id="remember" name="remember" /><span
                                >Se souvenir de moi</span
                            ></label
                        >
                        <button
                            type="submit"
                            :disabled="processing"
                            data-test="login-button"
                            class="flex h-13 w-full items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-[#099e9a] to-[#17d2b8] font-bold text-white shadow-lg shadow-teal-100 transition hover:-translate-y-0.5 disabled:opacity-60"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-5 animate-spin"
                            /><span>{{
                                processing ? 'Connexion…' : 'Se connecter'
                            }}</span
                            ><ArrowRight v-if="!processing" class="size-5" />
                        </button>
                    </Form>

                    <div
                        class="my-6 flex items-center gap-4 text-[11px] text-slate-400"
                    >
                        <span class="h-px flex-1 bg-slate-200"></span>OU<span
                            class="h-px flex-1 bg-slate-200"
                        ></span>
                    </div>
                    <a
                        href="#email"
                        class="flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-[#14c7b2] hover:bg-teal-50/40"
                        ><UserRound class="size-9 shrink-0 text-[#0aa39c]" />
                        <div class="flex-1">
                            <p class="text-sm font-bold">
                                Vous êtes parent ou élève ?
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Utilisez les identifiants de votre portail dédié
                            </p>
                        </div>
                        <ArrowRight class="size-5"
                    /></a>
                    <Link
                        href="/register-school"
                        class="mt-3 flex items-center justify-center gap-2 rounded-xl border border-[#11aa9f] px-4 py-3 text-sm font-bold text-[#078f88] transition hover:bg-teal-50"
                    >
                        <GraduationCap class="size-5" /> Enregistrer un nouvel
                        établissement
                    </Link>

                    <button
                        type="button"
                        class="mt-4 flex w-full items-center justify-between rounded-xl bg-[#effbfa] p-4 text-left"
                        @click="isDemoOpen = !isDemoOpen"
                    >
                        <span class="flex items-center gap-3"
                            ><ShieldCheck class="size-7 text-[#0aa39c]" /><span
                                ><b class="block text-xs"
                                    >Comptes de démonstration</b
                                ><span class="text-[11px] text-slate-500"
                                    >Essayez les accès administrateur et
                                    enseignant</span
                                ></span
                            ></span
                        ><ChevronUp
                            v-if="isDemoOpen"
                            class="size-4"
                        /><ChevronDown v-else class="size-4" />
                    </button>
                    <div
                        v-show="isDemoOpen"
                        class="mt-2 grid gap-2 sm:grid-cols-2"
                    >
                        <div
                            v-for="account in [
                                ['Administrateur', 'admin@easyschool.test'],
                                ['Enseignant', 'teacher@easyschool.test'],
                            ]"
                            :key="account[0]"
                            class="rounded-lg border bg-slate-50 p-3 text-[11px]"
                        >
                            <b>{{ account[0] }}</b>
                            <p class="mt-1 break-all text-slate-500">
                                {{ account[1] }}
                            </p>
                            <p class="text-slate-500">
                                Mot de passe : password
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-5 flex gap-3 rounded-xl border border-cyan-100 bg-gradient-to-r from-cyan-50 to-white p-4"
                    >
                        <ShieldCheck class="size-7 shrink-0 text-[#0aa39c]" />
                        <div>
                            <p class="text-xs font-bold">Connexion sécurisée</p>
                            <p
                                class="mt-1 text-[11px] leading-4 text-slate-500"
                            >
                                Vos informations sont chiffrées et protégées.
                                Nous ne partageons jamais vos données.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <section
                class="mt-7 flex flex-col items-center justify-between gap-5 rounded-2xl border border-cyan-300/30 bg-[#082849]/80 p-6 sm:flex-row"
            >
                <div class="flex items-center gap-5">
                    <span
                        class="grid size-14 place-items-center rounded-xl border border-white/15"
                        ><CalendarDays class="size-8"
                    /></span>
                    <div>
                        <h2 class="text-lg font-bold">
                            Vous représentez un nouvel établissement ?
                        </h2>
                        <p class="mt-1 text-sm text-slate-300">
                            Demandez une démo guidée et découvrez Easy School en
                            action.
                        </p>
                    </div>
                </div>
                <Link
                    href="/demo"
                    class="flex shrink-0 items-center gap-3 rounded-xl border border-[#15cfb8] px-7 py-3 text-sm font-bold text-[#15cfb8] hover:bg-[#15cfb8] hover:text-[#05203e]"
                    >Demander une démo <CalendarDays class="size-4"
                /></Link>
            </section>
        </div>

        <section class="relative bg-white text-[#092044]">
            <div
                class="mx-auto grid max-w-[1380px] gap-5 px-6 py-7 sm:grid-cols-2 lg:grid-cols-4 lg:px-12"
            >
                <div
                    v-for="item in [
                        [
                            ShieldCheck,
                            'Conforme & sécurisé',
                            'Hébergement sécurisé et conforme aux normes.',
                        ],
                        [
                            Layers3,
                            'Sauvegardes quotidiennes',
                            'Vos données sont sauvegardées automatiquement.',
                        ],
                        [
                            Headphones,
                            'Support réactif',
                            'Notre équipe vous répond rapidement.',
                        ],
                        [
                            RefreshCw,
                            'Mises à jour régulières',
                            'De nouvelles fonctionnalités toute l’année.',
                        ],
                    ]"
                    :key="item[1] as string"
                    class="flex gap-4"
                >
                    <span
                        class="grid size-12 shrink-0 place-items-center rounded-xl bg-teal-50 text-[#0aa39c]"
                        ><component :is="item[0]" class="size-6"
                    /></span>
                    <div>
                        <h3 class="text-xs font-bold">{{ item[1] }}</h3>
                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                            {{ item[2] }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="relative border-t border-white/10 bg-[#061b37]">
            <div
                class="mx-auto grid max-w-[1380px] gap-8 px-7 py-9 sm:grid-cols-2 lg:grid-cols-5 lg:px-12"
            >
                <div>
                    <div class="flex items-center gap-2 font-black">
                        <GraduationCap class="size-7 text-[#16d1bc]" />Easy
                        School
                    </div>
                    <p class="mt-4 text-xs leading-5 text-slate-400">
                        La plateforme tout-en-un pour la gestion et la
                        digitalisation de votre établissement.
                    </p>
                </div>
                <div
                    v-for="group in [
                        [
                            'Plateforme',
                            'Fonctionnalités',
                            'Tarifs',
                            'Sécurité',
                            'Établissements',
                        ],
                        [
                            'Ressources',
                            'Centre d’aide',
                            'Guides',
                            'Webinaires',
                            'Blog',
                        ],
                        [
                            'Entreprise',
                            'À propos',
                            'Carrières',
                            'Partenaires',
                            'Contact',
                        ],
                        [
                            'Besoin d’aide ?',
                            'support@easyschool.dz',
                            '+213 550 123 456',
                            'Lun – Ven : 9h à 18h',
                        ],
                    ]"
                    :key="group[0]"
                >
                    <h3 class="text-xs font-bold">{{ group[0] }}</h3>
                    <div class="mt-4 grid gap-2 text-xs text-slate-400">
                        <span v-for="item in group.slice(1)" :key="item">{{
                            item
                        }}</span>
                    </div>
                </div>
            </div>
            <div
                class="mx-auto flex max-w-[1380px] flex-col justify-between gap-3 border-t border-white/10 px-7 py-5 text-xs text-slate-400 sm:flex-row lg:px-12"
            >
                <span>© 2026 Easy School – Tous droits réservés.</span
                ><span
                    >Confidentialité &nbsp;&nbsp; Conditions d’utilisation
                    &nbsp;&nbsp; Mentions légales</span
                >
            </div>
        </footer>
    </main>
</template>
