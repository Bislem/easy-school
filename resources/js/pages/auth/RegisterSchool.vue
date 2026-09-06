<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WilayaCommuneSelect from '@/components/WilayaCommuneSelect.vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    CheckCircle2,
    Eye,
    EyeOff,
    GraduationCap,
    Headphones,
    Layers3,
    LoaderCircle,
    LockKeyhole,
    Mail,
    MapPin,
    Phone,
    RefreshCw,
    ShieldCheck,
    Upload,
    UserRound,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    plans: Array<{
        id: number;
        name: string;
        description?: string | null;
        price: string;
        currency: string;
        billing_period: string;
        features?: string[] | null;
        max_students?: number | null;
        max_sites?: number | null;
    }>;
}>();

const showPassword = ref(false);
const showConfirmation = ref(false);
const wilaya = ref('');
const commune = ref('');
const selectedPlan = ref<number | null>(props.plans[0]?.id ?? null);
const fieldClass =
    'h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10';
</script>

<template>
    <Head title="Créer votre établissement" />

    <main class="min-h-screen bg-[#051c3a] text-white">
        <div
            class="pointer-events-none absolute inset-0 overflow-hidden [background-image:radial-gradient(circle_at_18%_25%,#0a4770_0,transparent_30%),radial-gradient(circle_at_82%_15%,#113e69_0,transparent_27%)] opacity-40"
        ></div>

        <div
            class="relative mx-auto max-w-[1380px] px-5 pt-6 pb-9 sm:px-8 lg:px-12"
        >
            <header class="flex items-center justify-between">
                <Link
                    href="/"
                    class="flex items-center gap-3 text-xl font-black sm:text-2xl"
                    ><GraduationCap class="size-10 text-[#16d1bc]" />Easy
                    School</Link
                >
                <div class="flex items-center gap-3">
                    <Link
                        href="/login"
                        class="hidden rounded-xl border border-[#16d1bc] px-4 py-2 text-sm font-bold text-[#16d1bc] transition hover:bg-[#16d1bc] hover:text-[#05203e] sm:inline-flex"
                        >Se connecter</Link
                    >
                    <Link
                        href="/"
                        class="flex items-center gap-2 text-sm font-semibold text-white/90 hover:text-[#16d1bc]"
                        ><ArrowLeft class="size-4" /> Retour à l'accueil</Link
                    >
                </div>
            </header>

            <div
                class="mt-10 grid items-start gap-10 lg:grid-cols-[.88fr_1.12fr] xl:gap-16"
            >
                <section class="pt-3 lg:sticky lg:top-8 lg:pt-5">
                    <p class="font-bold text-[#15cfb8]">
                        Rejoignez Easy School
                    </p>
                    <h1
                        class="mt-5 max-w-xl text-4xl leading-tight font-black tracking-tight sm:text-5xl"
                    >
                        Créez votre espace de gestion<br /><span
                            class="text-[#16cdb5]"
                            >scolaire en quelques minutes</span
                        >
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-7 text-slate-300">
                        Centralisez votre établissement, vos équipes et vos
                        apprenants dans un espace sécurisé prêt à l'emploi.
                    </p>

                    <div class="mt-7 grid gap-4">
                        <div
                            v-for="item in [
                                [
                                    ShieldCheck,
                                    'Un espace sécurisé et privé',
                                    'Vos données sont isolées et protégées.',
                                ],
                                [
                                    Layers3,
                                    'Tous vos modules réunis',
                                    'Inscriptions, planning, finance, RH et portails.',
                                ],
                                [
                                    Headphones,
                                    'Accompagnement au démarrage',
                                    'Notre équipe vous guide dans la prise en main.',
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
                                <h2 class="text-sm font-bold">{{ item[1] }}</h2>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-400"
                                >
                                    {{ item[2] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative mt-8 hidden h-[500px] overflow-hidden rounded-2xl border border-cyan-300/20 shadow-2xl shadow-black/40 lg:block"
                    >
                        <img
                            src="/images/side-panel-image.png"
                            alt="Campus Easy School et ses étudiants"
                            class="h-full w-full object-cover object-[center_60%]"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-[#041b38]/75 via-transparent to-transparent"
                        ></div>
                        <div
                            class="absolute right-5 bottom-5 left-5 rounded-xl border border-white/15 bg-[#061f3e]/80 p-4 backdrop-blur-md"
                        >
                            <p class="text-sm font-bold">
                                Votre établissement, votre espace
                            </p>
                            <p class="mt-1 text-xs text-slate-300">
                                Une configuration personnalisée pour votre
                                équipe et vos apprenants.
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[22px] bg-white p-6 text-[#092044] shadow-2xl shadow-black/30 sm:p-9 xl:p-11"
                >
                    <div class="flex flex-col items-center text-center">
                        <span
                            class="grid size-20 place-items-center rounded-full bg-[#e9faf8] text-[#0e9e99]"
                            ><Building2 class="size-9"
                        /></span>
                        <h2 class="mt-5 text-2xl font-black sm:text-3xl">
                            Créer votre établissement
                        </h2>
                        <p class="mt-2 text-sm text-slate-500">
                            Renseignez les informations nécessaires pour ouvrir
                            votre espace.
                        </p>
                    </div>

                    <Form
                        action="/register-school"
                        method="post"
                        enctype="multipart/form-data"
                        v-slot="{ errors, processing }"
                        class="mt-8 space-y-6"
                    >
                        <fieldset class="space-y-4">
                            <legend
                                class="mb-4 flex items-center gap-2 text-sm font-black"
                            >
                                <Layers3 class="size-5 text-[#0aa39c]" />
                                Choisissez votre plan
                            </legend>
                            <div
                                v-if="plans.length"
                                class="grid gap-3 sm:grid-cols-2"
                            >
                                <label
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="relative cursor-pointer rounded-xl border p-4 transition"
                                    :class="
                                        selectedPlan === plan.id
                                            ? 'border-[#12bba8] bg-teal-50/60 ring-2 ring-[#12bba8]/10'
                                            : 'border-slate-200 hover:border-slate-300'
                                    "
                                >
                                    <input
                                        v-model="selectedPlan"
                                        type="radio"
                                        name="subscription_plan_id"
                                        :value="plan.id"
                                        required
                                        class="sr-only"
                                    />
                                    <span
                                        v-if="selectedPlan === plan.id"
                                        class="absolute top-3 right-3 grid size-6 place-items-center rounded-full bg-[#12bba8] text-white"
                                        ><CheckCircle2 class="size-4"
                                    /></span>
                                    <b class="text-base">{{ plan.name }}</b>
                                    <p class="mt-2">
                                        <span
                                            class="text-2xl font-black text-[#078f88]"
                                            >{{
                                                Number(
                                                    plan.price,
                                                ).toLocaleString('fr-DZ')
                                            }}</span
                                        >
                                        <span class="text-xs text-slate-500"
                                            >{{ plan.currency }} /
                                            {{
                                                plan.billing_period === 'yearly'
                                                    ? 'an'
                                                    : plan.billing_period ===
                                                        'monthly'
                                                      ? 'mois'
                                                      : 'période'
                                            }}</span
                                        >
                                    </p>
                                    <p
                                        v-if="plan.description"
                                        class="mt-2 text-xs leading-5 text-slate-500"
                                    >
                                        {{ plan.description }}
                                    </p>
                                    <div
                                        class="mt-3 flex flex-wrap gap-2 text-[10px] text-slate-500"
                                    >
                                        <span
                                            v-if="plan.max_students"
                                            class="rounded bg-white px-2 py-1"
                                            >{{
                                                plan.max_students
                                            }}
                                            étudiants</span
                                        ><span
                                            v-if="plan.max_sites"
                                            class="rounded bg-white px-2 py-1"
                                            >{{ plan.max_sites }} site(s)</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <div
                                v-else
                                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                            >
                                Aucun plan n'est actuellement disponible.
                                Contactez notre équipe avant de poursuivre.
                            </div>
                            <InputError
                                :message="errors.subscription_plan_id"
                            />
                        </fieldset>

                        <div class="h-px bg-slate-100"></div>
                        <fieldset class="space-y-4">
                            <legend
                                class="mb-4 flex items-center gap-2 text-sm font-black"
                            >
                                <Building2 class="size-5 text-[#0aa39c]" />
                                Informations de l'établissement
                            </legend>
                            <div>
                                <label
                                    for="name"
                                    class="mb-2 block text-xs font-bold"
                                    >Nom de l'école
                                    <span class="text-red-500">*</span></label
                                >
                                <div class="relative">
                                    <Building2
                                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                    /><input
                                        id="name"
                                        name="name"
                                        required
                                        autofocus
                                        placeholder="Ex. École Les Horizons"
                                        :class="[fieldClass, 'pl-12']"
                                    />
                                </div>
                                <InputError
                                    :message="errors.name"
                                    class="mt-1"
                                />
                            </div>
                            <WilayaCommuneSelect
                                v-model:wilaya="wilaya"
                                v-model:commune="commune"
                                :wilaya-error="errors.wilaya"
                                :commune-error="errors.commune"
                            />
                            <div>
                                <label
                                    for="address"
                                    class="mb-2 block text-xs font-bold"
                                    >Adresse</label
                                >
                                <div class="relative">
                                    <MapPin
                                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                    /><input
                                        id="address"
                                        name="address"
                                        placeholder="Adresse complète de l'établissement"
                                        :class="[fieldClass, 'pl-12']"
                                    />
                                </div>
                                <InputError
                                    :message="errors.address"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <label
                                    for="logo"
                                    class="mb-2 block text-xs font-bold"
                                    >Logo de l'école</label
                                ><label
                                    for="logo"
                                    class="flex min-h-20 cursor-pointer items-center gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 transition hover:border-[#14b9a8] hover:bg-teal-50/40"
                                    ><span
                                        class="grid size-10 place-items-center rounded-lg bg-white text-[#0aa39c] shadow-sm"
                                        ><Upload class="size-5" /></span
                                    ><span
                                        ><b class="block text-xs"
                                            >Choisir une image</b
                                        ><span
                                            class="text-[11px] text-slate-500"
                                            >PNG, JPG ou WebP — 5 Mo
                                            maximum</span
                                        ></span
                                    ><input
                                        id="logo"
                                        name="logo"
                                        type="file"
                                        accept="image/*"
                                        class="sr-only" /></label
                                ><InputError
                                    :message="errors.logo"
                                    class="mt-1"
                                />
                            </div>
                        </fieldset>

                        <div class="h-px bg-slate-100"></div>
                        <fieldset class="space-y-4">
                            <legend
                                class="mb-4 flex items-center gap-2 text-sm font-black"
                            >
                                <Upload class="size-5 text-[#0aa39c]" />
                                Justificatif de paiement
                            </legend>
                            <p class="text-xs leading-5 text-slate-500">
                                Effectuez le paiement correspondant au plan
                                choisi, puis joignez votre reçu ou preuve de
                                virement. Votre compte restera en attente
                                jusqu'à sa validation.
                            </p>
                            <label
                                for="payment_proof"
                                class="flex min-h-24 cursor-pointer items-center gap-4 rounded-xl border border-dashed border-[#14b9a8] bg-teal-50/50 px-4 transition hover:bg-teal-50"
                                ><span
                                    class="grid size-11 place-items-center rounded-lg bg-white text-[#0aa39c] shadow-sm"
                                    ><Upload class="size-5" /></span
                                ><span
                                    ><b class="block text-xs"
                                        >Joindre le justificatif de paiement
                                        *</b
                                    ><span class="text-[11px] text-slate-500"
                                        >PDF, PNG, JPG ou WebP — 10 Mo
                                        maximum</span
                                    ></span
                                ><input
                                    id="payment_proof"
                                    name="payment_proof"
                                    type="file"
                                    accept=".pdf,image/png,image/jpeg,image/webp"
                                    required
                                    class="sr-only"
                            /></label>
                            <InputError :message="errors.payment_proof" />
                        </fieldset>

                        <div class="h-px bg-slate-100"></div>
                        <fieldset class="space-y-4">
                            <legend
                                class="mb-4 flex items-center gap-2 text-sm font-black"
                            >
                                <UserRound class="size-5 text-[#0aa39c]" />
                                Compte administrateur
                            </legend>
                            <div>
                                <label
                                    for="admin_name"
                                    class="mb-2 block text-xs font-bold"
                                    >Nom de l'administrateur
                                    <span class="text-red-500">*</span></label
                                >
                                <div class="relative">
                                    <UserRound
                                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                    /><input
                                        id="admin_name"
                                        name="admin_name"
                                        required
                                        autocomplete="name"
                                        placeholder="Votre nom complet"
                                        :class="[fieldClass, 'pl-12']"
                                    />
                                </div>
                                <InputError
                                    :message="errors.admin_name"
                                    class="mt-1"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="email"
                                        class="mb-2 block text-xs font-bold"
                                        >Adresse e-mail
                                        <span class="text-red-500"
                                            >*</span
                                        ></label
                                    >
                                    <div class="relative">
                                        <Mail
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            autocomplete="email"
                                            placeholder="admin@ecole.dz"
                                            :class="[fieldClass, 'pl-12']"
                                        />
                                    </div>
                                    <InputError
                                        :message="errors.email"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <label
                                        for="phone"
                                        class="mb-2 block text-xs font-bold"
                                        >Téléphone</label
                                    >
                                    <div class="relative">
                                        <Phone
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            placeholder="+213 5 00 00 00 00"
                                            :class="[fieldClass, 'pl-12']"
                                        />
                                    </div>
                                    <InputError
                                        :message="errors.phone"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="password"
                                        class="mb-2 block text-xs font-bold"
                                        >Mot de passe
                                        <span class="text-red-500"
                                            >*</span
                                        ></label
                                    >
                                    <div class="relative">
                                        <LockKeyhole
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="password"
                                            name="password"
                                            :type="
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            autocomplete="new-password"
                                            placeholder="8 caractères minimum"
                                            :class="[fieldClass, 'pr-12 pl-12']"
                                        /><button
                                            type="button"
                                            class="absolute top-1/2 right-4 -translate-y-1/2 text-slate-400"
                                            @click="
                                                showPassword = !showPassword
                                            "
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
                                <div>
                                    <label
                                        for="password_confirmation"
                                        class="mb-2 block text-xs font-bold"
                                        >Confirmation
                                        <span class="text-red-500"
                                            >*</span
                                        ></label
                                    >
                                    <div class="relative">
                                        <LockKeyhole
                                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                                        /><input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            :type="
                                                showConfirmation
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            autocomplete="new-password"
                                            placeholder="Confirmez le mot de passe"
                                            :class="[fieldClass, 'pr-12 pl-12']"
                                        /><button
                                            type="button"
                                            class="absolute top-1/2 right-4 -translate-y-1/2 text-slate-400"
                                            @click="
                                                showConfirmation =
                                                    !showConfirmation
                                            "
                                        >
                                            <EyeOff
                                                v-if="showConfirmation"
                                                class="size-5"
                                            /><Eye v-else class="size-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <div
                            class="rounded-xl border border-cyan-100 bg-cyan-50/60 p-4 text-xs leading-5 text-slate-600"
                        >
                            <p class="flex gap-2">
                                <CheckCircle2
                                    class="mt-0.5 size-4 shrink-0 text-[#0aa39c]"
                                />
                                En créant votre espace, vous acceptez nos
                                conditions d'utilisation et notre politique de
                                confidentialité.
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="processing || !plans.length"
                            class="flex h-13 w-full items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-[#099e9a] to-[#17d2b8] font-bold text-white shadow-lg shadow-teal-100 transition hover:-translate-y-0.5 disabled:opacity-60"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-5 animate-spin"
                            /><span>{{
                                processing
                                    ? 'Création en cours…'
                                    : 'Créer mon espace'
                            }}</span
                            ><ArrowRight v-if="!processing" class="size-5" />
                        </button>
                        <p class="text-center text-sm text-slate-500">
                            Vous avez déjà un compte ?
                            <Link
                                href="/login"
                                class="font-bold text-[#078f88] hover:underline"
                                >Se connecter</Link
                            >
                        </p>
                    </Form>
                </section>
            </div>
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
                class="mx-auto flex max-w-[1380px] flex-col justify-between gap-5 px-7 py-8 text-xs text-slate-400 sm:flex-row lg:px-12"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-base font-black text-white"
                    >
                        <GraduationCap class="size-7 text-[#16d1bc]" />Easy
                        School
                    </div>
                    <p class="mt-3 max-w-md leading-5">
                        La plateforme tout-en-un pour la gestion et la
                        digitalisation de votre établissement.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                    <span>Centre d'aide</span><span>Confidentialité</span
                    ><span>Conditions d'utilisation</span
                    ><span>© 2026 Easy School</span>
                </div>
            </div>
        </footer>
    </main>
</template>
