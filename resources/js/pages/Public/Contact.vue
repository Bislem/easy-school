<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    Clock3,
    Headphones,
    LoaderCircle,
    Mail,
    MapPin,
    MessageSquareText,
    Phone,
    Send,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    contact: { email: string; phone: string; hours: string; address: string };
}>();
const page = usePage();
const success = computed(() => (page.props.flash as any)?.success);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    organization: '',
    subject: 'information',
    message: '',
});
const submit = () =>
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
const field =
    'h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#12bca9] focus:ring-4 focus:ring-[#12bca9]/10';
</script>

<template>
    <HomeLayout
        ><Head title="Contact — Easy School" />
        <main class="bg-[#f7fafc]">
            <section
                class="relative overflow-hidden bg-[#051c3a] pt-28 pb-32 text-white"
            >
                <div
                    class="absolute inset-0 [background-image:radial-gradient(circle_at_12%_40%,#0d5174_0,transparent_30%),radial-gradient(circle_at_85%_20%,#10446d_0,transparent_34%)] opacity-30"
                />
                <div
                    class="relative mx-auto grid max-w-[1280px] items-center gap-12 px-6 lg:grid-cols-[1fr_.8fr]"
                >
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-[#14cbb5]/30 bg-[#0c5361] px-4 py-2 text-[10px] font-black text-[#2ce0c8]"
                            ><Sparkles class="size-4" /> UNE ÉQUIPE À VOTRE
                            ÉCOUTE</span
                        >
                        <h1
                            class="mt-6 text-4xl leading-tight font-black sm:text-5xl"
                        >
                            Construisons une gestion<br /><span
                                class="text-[#13cbb1]"
                                >plus simple ensemble.</span
                            >
                        </h1>
                        <p
                            class="mt-5 max-w-xl text-sm leading-7 text-slate-200"
                        >
                            Une question sur Easy School, un projet de
                            digitalisation ou plusieurs campus à connecter ?
                            Parlez-nous de votre besoin. Notre équipe vous
                            répond avec une solution concrète.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="item in [
                                [
                                    Headphones,
                                    'Conseil humain',
                                    'Un expert vous accompagne',
                                ],
                                [
                                    ShieldCheck,
                                    'Projet cadré',
                                    'Vos besoins avant la technique',
                                ],
                                [
                                    Building2,
                                    'Pensé pour l’Algérie',
                                    'Support local et réactif',
                                ],
                                [
                                    MessageSquareText,
                                    'Réponse rapide',
                                    'Sous un jour ouvré',
                                ],
                            ]"
                            :key="item[1] as string"
                            class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur"
                        >
                            <component
                                :is="item[0]"
                                class="size-6 text-[#19d3bb]"
                            />
                            <h3 class="mt-3 text-sm font-black">
                                {{ item[1] }}
                            </h3>
                            <p class="mt-1 text-[11px] text-slate-300">
                                {{ item[2] }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="relative z-10 mx-auto -mt-20 max-w-[1180px] px-5 pb-20"
            >
                <div
                    class="grid overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-2xl shadow-slate-300/40 lg:grid-cols-[1fr_340px]"
                >
                    <form class="p-6 sm:p-9" @submit.prevent="submit">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-black tracking-wider text-teal-600 uppercase"
                                >
                                    Votre projet
                                </p>
                                <h2 class="mt-1 text-2xl font-black">
                                    Dites-nous comment vous aider
                                </h2>
                            </div>
                            <Send
                                class="hidden size-7 text-teal-500 sm:block"
                            />
                        </div>
                        <div
                            v-if="success"
                            class="mt-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800"
                        >
                            <CheckCircle2 class="size-5 shrink-0" />{{
                                success
                            }}
                        </div>
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Nom complet *</label
                                ><input
                                    v-model="form.name"
                                    required
                                    :class="field"
                                    placeholder="Votre nom"
                                /><InputError :message="form.errors.name" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Email professionnel *</label
                                ><input
                                    v-model="form.email"
                                    required
                                    type="email"
                                    :class="field"
                                    placeholder="vous@ecole.dz"
                                /><InputError :message="form.errors.email" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Téléphone</label
                                ><input
                                    v-model="form.phone"
                                    type="tel"
                                    :class="field"
                                    placeholder="+213 ..."
                                /><InputError :message="form.errors.phone" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Établissement / organisation</label
                                ><input
                                    v-model="form.organization"
                                    :class="field"
                                    placeholder="Nom de votre structure"
                                /><InputError
                                    :message="form.errors.organization"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-2 block text-xs font-bold"
                                    >Sujet *</label
                                ><select v-model="form.subject" :class="field">
                                    <option value="information">
                                        Informations sur Easy School
                                    </option>
                                    <option value="pricing">
                                        Tarifs et abonnement
                                    </option>
                                    <option value="partnership">
                                        Partenariat
                                    </option>
                                    <option value="support">Assistance</option>
                                    <option value="other">
                                        Autre demande
                                    </option></select
                                ><InputError :message="form.errors.subject" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-2 block text-xs font-bold"
                                    >Votre message *</label
                                ><textarea
                                    v-model="form.message"
                                    required
                                    rows="6"
                                    class="w-full rounded-xl border border-slate-200 p-4 text-sm transition outline-none focus:border-[#12bca9] focus:ring-4 focus:ring-[#12bca9]/10"
                                    placeholder="Parlez-nous de votre besoin, du nombre d’élèves et de vos objectifs..."
                                /><InputError :message="form.errors.message" />
                            </div>
                        </div>
                        <button
                            :disabled="form.processing"
                            class="mt-5 flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#0ebda8] to-[#0cd0b4] px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-teal-200 disabled:opacity-60"
                        >
                            <LoaderCircle
                                v-if="form.processing"
                                class="size-4 animate-spin"
                            /><Send v-else class="size-4" />Envoyer mon message
                        </button>
                    </form>
                    <aside class="bg-[#092b4d] p-7 text-white sm:p-9">
                        <p
                            class="text-xs font-black tracking-wider text-[#23d5bd] uppercase"
                        >
                            Easetech Agency
                        </p>
                        <h2 class="mt-2 text-2xl font-black">
                            Venez nous parler.
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-slate-300">
                            Nous concevons des solutions digitales utiles,
                            durables et adaptées aux réalités de votre
                            organisation.
                        </p>
                        <div class="mt-8 space-y-5">
                            <a
                                :href="`mailto:${contact.email}`"
                                class="flex gap-3"
                                ><span
                                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10"
                                    ><Mail
                                        class="size-5 text-[#20d2ba]" /></span
                                ><span
                                    ><small class="block text-slate-400"
                                        >Email</small
                                    ><b class="text-sm">{{
                                        contact.email
                                    }}</b></span
                                ></a
                            >
                            <a :href="`tel:${contact.phone}`" class="flex gap-3"
                                ><span
                                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10"
                                    ><Phone
                                        class="size-5 text-[#20d2ba]" /></span
                                ><span
                                    ><small class="block text-slate-400"
                                        >Téléphone</small
                                    ><b class="text-sm">{{
                                        contact.phone
                                    }}</b></span
                                ></a
                            >
                            <div class="flex gap-3">
                                <span
                                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10"
                                    ><MapPin
                                        class="size-5 text-[#20d2ba]" /></span
                                ><span
                                    ><small class="block text-slate-400"
                                        >Adresse</small
                                    ><b class="text-sm">{{
                                        contact.address
                                    }}</b></span
                                >
                            </div>
                            <div class="flex gap-3">
                                <span
                                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10"
                                    ><Clock3
                                        class="size-5 text-[#20d2ba]" /></span
                                ><span
                                    ><small class="block text-slate-400"
                                        >Disponibilité</small
                                    ><b class="text-sm">{{
                                        contact.hours
                                    }}</b></span
                                >
                            </div>
                        </div>
                        <div
                            class="mt-9 rounded-2xl border border-white/10 bg-white/5 p-5"
                        >
                            <p class="text-xs font-bold">
                                Vous préférez voir la plateforme ?
                            </p>
                            <Link
                                href="/demo"
                                class="mt-3 flex items-center gap-2 text-sm font-black text-[#23d5bd]"
                                >Demander une démo <ArrowRight class="size-4"
                            /></Link>
                        </div>
                    </aside>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>
