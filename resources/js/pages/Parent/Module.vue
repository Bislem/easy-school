<script setup lang="ts">
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    CalendarRange,
    ChartNoAxesColumn,
    ClipboardCheck,
    CreditCard,
    FileText,
    MessageCircle,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ portal: any; module: string }>();
const meta: any = {
    timetable: ['Emploi du temps', CalendarRange],
    attendance: ['Absences', ClipboardCheck],
    grades: ['Notes & examens', ChartNoAxesColumn],
    'report-cards': ['Bulletins', FileText],
    homework: ['Devoirs', FileText],
    events: ['Événements', CalendarRange],
    announcements: ['Annonces', FileText],
    payments: ['Paiements', CreditCard],
    messages: ['Messages', MessageCircle],
    profile: ['Profil', UserRound],
};
const title = computed(() => meta[props.module]?.[0] || 'Espace parent');
const icon = computed(() => meta[props.module]?.[1] || FileText);
</script>
<template>
    <Head :title="title" /><ParentLayout :portal="portal"
        ><div>
            <h1 class="text-2xl font-bold sm:text-3xl">{{ title }}</h1>
            <section
                class="mt-6 rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <div
                    class="mx-auto grid size-14 place-items-center rounded-2xl bg-primary/10 text-primary"
                >
                    <component :is="icon" class="size-7" />
                </div>
                <h2 class="mt-4 font-semibold">
                    Aucune information disponible
                </h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    Les informations de ce module pour l’enfant et l’année
                    scolaire sélectionnés apparaîtront ici dès qu’elles seront
                    disponibles.
                </p>
            </section>
        </div></ParentLayout
    >
</template>
