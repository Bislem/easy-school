<script setup lang="ts">
import {
    publicLocale,
    setPublicLocale,
    type PublicLocale,
} from '@/composables/usePublicLocale';
import { Head, Link } from '@inertiajs/vue3';
import { Archive, Clock3, GraduationCap, Mail, Phone } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    school: { name: string; trial_started_at: string; demo_expires_at: string };
    contact: { email?: string; phone?: string };
}>();
const copy = computed(
    () =>
        ({
            fr: {
                title: 'Essai expiré',
                intro: `La période d’essai de 30 jours de ${props.school.name} est terminée.`,
                data: 'Toutes les données de votre école sont toujours conservées en toute sécurité.',
                action: 'Contactez-nous pour activer un abonnement et retrouver immédiatement votre espace.',
                contact: 'Activer mon abonnement',
                logout: 'Se déconnecter',
            },
            en: {
                title: 'Trial Expired',
                intro: `${props.school.name}’s 30-day trial has ended.`,
                data: 'All your school data remains safely stored.',
                action: 'Contact us to activate a subscription and immediately restore access.',
                contact: 'Activate my subscription',
                logout: 'Sign out',
            },
            ar: {
                title: 'انتهت الفترة التجريبية',
                intro: `انتهت الفترة التجريبية لمدة 30 يوماً الخاصة بـ ${props.school.name}.`,
                data: 'جميع بيانات مدرستك ما زالت محفوظة بأمان.',
                action: 'تواصل معنا لتفعيل اشتراك واستعادة الدخول إلى مساحتك فوراً.',
                contact: 'تفعيل اشتراكي',
                logout: 'تسجيل الخروج',
            },
        })[publicLocale.value],
);
const languages: PublicLocale[] = ['fr', 'en', 'ar'];
</script>

<template>
    <Head :title="copy.title" />
    <main
        class="grid min-h-screen place-items-center bg-[#061d3b] p-6 text-white"
        :dir="publicLocale === 'ar' ? 'rtl' : 'ltr'"
    >
        <section
            class="w-full max-w-xl rounded-3xl bg-white p-8 text-center text-[#092044] shadow-2xl sm:p-12"
        >
            <div class="mb-6 flex items-center justify-between">
                <span class="flex items-center gap-2 font-black"
                    ><GraduationCap class="size-7 text-teal-600" /> Easy
                    School</span
                >
                <div class="flex gap-1">
                    <button
                        v-for="locale in languages"
                        :key="locale"
                        class="rounded px-2 py-1 text-xs font-bold"
                        :class="
                            publicLocale === locale
                                ? 'bg-teal-600 text-white'
                                : 'bg-slate-100'
                        "
                        @click="setPublicLocale(locale)"
                    >
                        {{ locale.toUpperCase() }}
                    </button>
                </div>
            </div>
            <Clock3 class="mx-auto size-16 text-amber-500" />
            <h1 class="mt-5 text-3xl font-black">{{ copy.title }}</h1>
            <p class="mt-4 text-slate-600">{{ copy.intro }}</p>
            <div
                class="mt-6 flex gap-3 rounded-2xl bg-emerald-50 p-4 text-left text-sm text-emerald-900"
            >
                <Archive class="size-5 shrink-0" />
                <p>{{ copy.data }}</p>
            </div>
            <p class="mt-6 text-sm text-slate-600">{{ copy.action }}</p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <a
                    v-if="contact.email"
                    :href="`mailto:${contact.email}`"
                    class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-3 font-bold text-white"
                    ><Mail class="size-4" />{{ copy.contact }}</a
                ><a
                    v-if="contact.phone"
                    :href="`tel:${contact.phone}`"
                    class="inline-flex items-center gap-2 rounded-xl border px-5 py-3 font-bold"
                    ><Phone class="size-4" />{{ contact.phone }}</a
                >
            </div>
            <Link
                href="/logout"
                method="post"
                as="button"
                class="mt-7 text-sm text-slate-500 underline"
                >{{ copy.logout }}</Link
            >
        </section>
    </main>
</template>
