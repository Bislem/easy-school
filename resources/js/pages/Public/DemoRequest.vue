<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WilayaCommuneSelect from '@/components/WilayaCommuneSelect.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    BookOpen,
    Building2,
    CalendarDays,
    Check,
    FileBadge,
    GraduationCap,
    Layers3,
    LoaderCircle,
    LockKeyhole,
    MessageCircle,
    Settings2,
    ShieldCheck,
    Smartphone,
    Sparkles,
    UserCog,
    Users,
    WalletCards,
} from 'lucide-vue-next';

const form = useForm({
    school_name: '',
    school_type: 'private_school',
    contact_name: '',
    contact_role: '',
    email: '',
    phone: '',
    address: '',
    wilaya: '',
    commune: '',
    website: '',
    students_count: null as number | null,
    teachers_count: null as number | null,
    staff_count: null as number | null,
    sites_count: 1,
    requested_days: 15,
    needs: '',
    modules: [] as string[],
});
const modules = [
    ['students', 'Gestion des étudiants', Users, 'text-blue-600'],
    ['hr', 'Ressources humaines', UserCog, 'text-emerald-600'],
    ['multi_sites', 'Multi-sites & campus', Layers3, 'text-sky-600'],
    ['courses', 'Formations & cours', BookOpen, 'text-violet-600'],
    ['planning', 'Plannings & emplois du temps', CalendarDays, 'text-red-500'],
    ['certificates', 'Certificats', FileBadge, 'text-emerald-600'],
    ['badges', 'Badges', Sparkles, 'text-pink-500'],
    ['mobile', 'Application mobile', Smartphone, 'text-blue-600'],
    ['finance', 'Paiements & finances', WalletCards, 'text-amber-500'],
    ['reports', 'Rapports & statistiques', BarChart3, 'text-teal-600'],
] as const;
const toggleModule = (value: string) =>
    (form.modules = form.modules.includes(value)
        ? form.modules.filter((item) => item !== value)
        : [...form.modules, value]);
const submit = () => form.post('/demo');
const inputClass =
    'h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#12bca9] focus:ring-3 focus:ring-[#12bca9]/10';
</script>

<template>
    <HomeLayout
        ><Head title="Demander une démonstration" />
        <main class="bg-[#f7fafc]">
            <section
                class="relative overflow-hidden bg-[#051c3a] pt-28 pb-32 text-white"
            >
                <div
                    class="absolute inset-0 [background-image:radial-gradient(circle_at_12%_40%,#0d5174_0,transparent_30%),radial-gradient(circle_at_85%_20%,#10446d_0,transparent_34%)] opacity-30"
                ></div>
                <div
                    class="relative mx-auto grid max-w-[1380px] items-center gap-12 px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-10"
                >
                    <div>
                        <span
                            class="inline-flex rounded-full border border-[#14cbb5]/30 bg-[#0c5361] px-4 py-2 text-[10px] font-black text-[#2ce0c8]"
                            >DÉMO PERSONNALISÉE & CONFIGURÉE</span
                        >
                        <h1
                            class="mt-6 text-4xl leading-tight font-black sm:text-5xl"
                        >
                            Demandez votre<br /><span class="text-[#13cbb1]"
                                >démo Easy School</span
                            >
                        </h1>
                        <p
                            class="mt-5 max-w-xl text-sm leading-7 text-slate-200"
                        >
                            Une plateforme multi-tenant conçue pour gérer
                            plusieurs établissements ou campus, les ressources
                            humaines, les dossiers des étudiants et employés,
                            les sessions, groupes, formations, certificats,
                            badges, statistiques et applications mobiles.
                        </p>
                        <div
                            class="mt-8 grid grid-cols-2 gap-4 text-[10px] sm:grid-cols-4"
                        >
                            <span
                                v-for="item in [
                                    [Building2, 'Multi-sites & campus'],
                                    [UserCog, 'RH & dossiers centralisés'],
                                    [BarChart3, 'Stats par site'],
                                    [Smartphone, 'Appli mobile'],
                                ]"
                                :key="item[1] as string"
                                class="flex items-center gap-2 border-r border-white/15"
                                ><component
                                    :is="item[0]"
                                    class="size-5 text-[#15d2bb]"
                                />{{ item[1] }}</span
                            >
                        </div>
                    </div>
                    <div
                        class="overflow-hidden rounded-2xl border border-cyan-300/30 bg-white shadow-2xl shadow-black/40"
                    >
                        <img
                            src="/images/dashborad-screenshot.png"
                            alt="Tableau de bord Easy School"
                            class="w-full"
                        />
                    </div>
                </div>
            </section>

            <section class="relative z-10 mx-auto -mt-20 max-w-[1280px] px-5">
                <div
                    class="grid overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-xl shadow-slate-300/40 lg:grid-cols-[1fr_330px]"
                >
                    <form class="p-6 sm:p-8" @submit.prevent="submit">
                        <h2 class="text-xl font-black">
                            Formulaire de demande de démo
                        </h2>
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Établissement / Organisation
                                    <span class="text-red-500">*</span></label
                                ><input
                                    v-model="form.school_name"
                                    required
                                    :class="inputClass"
                                    placeholder="Nom de votre établissement"
                                /><InputError
                                    :message="form.errors.school_name"
                                />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Nom du responsable
                                    <span class="text-red-500">*</span></label
                                ><input
                                    v-model="form.contact_name"
                                    required
                                    :class="inputClass"
                                    placeholder="Prénom et nom"
                                /><InputError
                                    :message="form.errors.contact_name"
                                />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Email professionnel
                                    <span class="text-red-500">*</span></label
                                ><input
                                    v-model="form.email"
                                    type="email"
                                    required
                                    :class="inputClass"
                                    placeholder="exemple@etablissement.dz"
                                /><InputError :message="form.errors.email" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Téléphone
                                    <span class="text-red-500">*</span></label
                                ><input
                                    v-model="form.phone"
                                    type="tel"
                                    required
                                    :class="inputClass"
                                    placeholder="+213 5 55 55 55 55"
                                /><InputError :message="form.errors.phone" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Fonction du responsable
                                    <span class="text-red-500">*</span></label
                                ><input
                                    v-model="form.contact_role"
                                    required
                                    :class="inputClass"
                                    placeholder="Directeur, responsable administratif…"
                                /><InputError
                                    :message="form.errors.contact_role"
                                />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Type d'établissement</label
                                ><select
                                    v-model="form.school_type"
                                    :class="inputClass"
                                >
                                    <option value="private_school">
                                        École privée
                                    </option>
                                    <option value="training_center">
                                        Centre de formation
                                    </option>
                                    <option value="language_school">
                                        École de langues
                                    </option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <WilayaCommuneSelect
                                    v-model:wilaya="form.wilaya"
                                    v-model:commune="form.commune"
                                    :wilaya-error="form.errors.wilaya"
                                    :commune-error="form.errors.commune"
                                    required
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-2 block text-xs font-bold"
                                    >Adresse
                                    <span class="text-red-500">*</span></label
                                ><input
                                    v-model="form.address"
                                    required
                                    :class="inputClass"
                                    placeholder="Adresse complète"
                                /><InputError :message="form.errors.address" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Site web</label
                                ><input
                                    v-model="form.website"
                                    type="url"
                                    :class="inputClass"
                                    placeholder="https://..."
                                /><InputError :message="form.errors.website" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold"
                                    >Durée souhaitée</label
                                ><input
                                    v-model="form.requested_days"
                                    type="number"
                                    min="1"
                                    required
                                    :class="inputClass"
                                />
                            </div>
                        </div>
                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div
                                v-for="field in [
                                    ['students_count', 'Nombre d’élèves'],
                                    ['teachers_count', 'Enseignants'],
                                    ['staff_count', 'Employés'],
                                    ['sites_count', 'Sites / campus'],
                                ]"
                                :key="field[0]"
                            >
                                <label class="mb-2 block text-[11px] font-bold"
                                    >{{ field[1] }}
                                    <span class="text-red-500">*</span></label
                                ><input
                                    v-model="(form as any)[field[0]]"
                                    type="number"
                                    :min="field[0] === 'sites_count' ? 1 : 0"
                                    required
                                    :class="inputClass"
                                    placeholder="Ex: 120"
                                /><InputError
                                    :message="(form.errors as any)[field[0]]"
                                />
                            </div>
                        </div>
                        <div class="mt-5">
                            <p class="mb-3 text-xs font-bold">
                                Modules que vous souhaitez explorer
                            </p>
                            <div
                                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <button
                                    v-for="item in modules"
                                    :key="item[0]"
                                    type="button"
                                    class="flex items-center gap-3 rounded-lg border p-3 text-left text-[11px] font-bold transition"
                                    :class="
                                        form.modules.includes(item[0])
                                            ? 'border-[#13bca8] bg-teal-50'
                                            : 'border-slate-200 hover:border-slate-300'
                                    "
                                    @click="toggleModule(item[0])"
                                >
                                    <component
                                        :is="item[2]"
                                        class="size-5"
                                        :class="item[3]"
                                    />{{ item[1]
                                    }}<Check
                                        v-if="form.modules.includes(item[0])"
                                        class="ml-auto size-4 text-[#0ba493]"
                                    />
                                </button>
                            </div>
                        </div>
                        <div class="mt-5">
                            <label class="mb-2 block text-xs font-bold"
                                >Message / Besoins spécifiques</label
                            ><textarea
                                v-model="form.needs"
                                rows="4"
                                class="w-full rounded-lg border border-slate-200 p-3 text-sm outline-none focus:border-[#12bca9]"
                                placeholder="Décrivez vos objectifs, besoins spécifiques ou toute information utile…"
                            ></textarea
                            ><InputError :message="form.errors.needs" />
                        </div>
                        <div
                            class="mt-5 flex flex-col items-center justify-between gap-4 sm:flex-row"
                        >
                            <p
                                class="flex items-center gap-2 text-[10px] text-slate-500"
                            >
                                <LockKeyhole class="size-4" />Vos informations
                                sont sécurisées et ne seront jamais partagées.
                            </p>
                            <button
                                :disabled="form.processing"
                                class="flex min-w-72 items-center justify-center gap-3 rounded-lg bg-gradient-to-r from-[#079f98] to-[#16d2b6] px-6 py-3 text-sm font-bold text-white"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                />Envoyer ma demande de démo
                                <ArrowRight
                                    v-if="!form.processing"
                                    class="size-4"
                                />
                            </button>
                        </div>
                    </form>
                    <aside
                        class="bg-gradient-to-b from-[#062548] to-[#064b59] p-6 text-white"
                    >
                        <h3 class="text-lg font-black">
                            Votre démo, configurée pour vous
                        </h3>
                        <p class="mt-2 text-xs leading-5 text-slate-300">
                            Nos experts préparent une démo adaptée à votre
                            établissement et vos priorités.
                        </p>
                        <div class="mt-7 grid gap-6">
                            <div
                                v-for="item in [
                                    [
                                        Settings2,
                                        'Configuration guidée',
                                        'Nous configurons votre environnement.',
                                    ],
                                    [
                                        Sparkles,
                                        'Parcours personnalisé',
                                        'Une démonstration ciblée sur vos besoins.',
                                    ],
                                    [
                                        CalendarDays,
                                        'Modules à la carte',
                                        'Choisissez les modules à explorer.',
                                    ],
                                    [
                                        ShieldCheck,
                                        'Durée maximale 15 jours',
                                        'Accès complet à votre rythme.',
                                    ],
                                ]"
                                :key="item[1] as string"
                                class="flex gap-3"
                            >
                                <component
                                    :is="item[0]"
                                    class="size-7 shrink-0 text-[#1de0c6]"
                                />
                                <div>
                                    <b class="text-xs">{{ item[1] }}</b>
                                    <p
                                        class="mt-1 text-[10px] leading-4 text-slate-300"
                                    >
                                        {{ item[2] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 rounded-xl bg-white/8 p-4">
                            <h4 class="text-xs font-bold">
                                Après votre demande
                            </h4>
                            <p
                                v-for="x in [
                                    'Un expert vous contacte sous 24h ouvrées.',
                                    'Nous planifions la démo à votre convenance.',
                                    'Vous recevez vos accès pour 15 jours maxi.',
                                ]"
                                :key="x"
                                class="mt-3 flex gap-2 text-[10px] text-slate-200"
                            >
                                <Check class="size-4 text-[#1de0c6]" />{{ x }}
                            </p>
                        </div>
                        <div
                            class="mt-5 rounded-xl bg-white/8 p-4 text-[10px] leading-5 text-slate-200"
                        >
                            <MessageCircle
                                class="mb-2 size-6 text-[#1de0c6]"
                            />“Easy School nous a permis de centraliser toutes
                            nos opérations sur plusieurs sites.”
                            <p class="mt-2 font-bold text-white">
                                Sarah K. — Directrice des opérations
                            </p>
                        </div>
                    </aside>
                </div>
            </section>

            <section class="mx-auto max-w-[1280px] px-5 py-10">
                <h2 class="text-center text-xl font-black">
                    Ce que comprend votre démo
                </h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="item in [
                            [
                                Settings2,
                                'Environnement configuré',
                                'Un espace dédié à votre établissement.',
                            ],
                            [
                                GraduationCap,
                                'Démonstration guidée',
                                'Un expert vous accompagne pas à pas.',
                            ],
                            [
                                BarChart3,
                                'Données réalistes',
                                'Des données fictives pour vous projeter.',
                            ],
                            [
                                CalendarDays,
                                'Durée de démonstration flexible',
                                'Explorez en toute autonomie.',
                            ],
                        ]"
                        :key="item[1] as string"
                        class="flex gap-4 rounded-xl border bg-white p-5"
                    >
                        <span
                            class="grid size-12 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"
                            ><component :is="item[0]" class="size-6"
                        /></span>
                        <div>
                            <h3 class="text-xs font-bold">{{ item[1] }}</h3>
                            <p
                                class="mt-2 text-[10px] leading-4 text-slate-500"
                            >
                                {{ item[2] }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>
        </main></HomeLayout
    >
</template>
