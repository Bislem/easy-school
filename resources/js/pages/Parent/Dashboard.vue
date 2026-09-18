<script setup lang="ts">
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    ChartNoAxesColumn,
    ChevronRight,
    ClipboardCheck,
    CreditCard,
    FileText,
    MessageCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{
    portal: any;
    today: any;
    quickAccess: any;
    recentActivity: any[];
}>();
const child = computed(() =>
    props.portal.children.find(
        (item: any) => item.id === props.portal.selected_child_id,
    ),
);
const nextClass = computed(() => {
    const time = new Date().toLocaleTimeString('fr-DZ', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
    return props.today?.classes?.find(
        (lesson: any) => lesson.start_time >= time,
    );
});
const quick = computed(() =>
    [
        {
            title: 'Emploi du temps',
            href: '/parent/timetable',
            icon: CalendarDays,
            text: props.today?.classes?.length
                ? `${props.today.classes.length} cours aujourd’hui`
                : 'Voir le planning',
            show: props.portal.modules.timetable,
        },
        {
            title: 'Absences',
            href: '/parent/absences',
            icon: ClipboardCheck,
            text: props.quickAccess
                ? `${props.quickAccess.absences} ce trimestre · ${props.quickAccess.unjustified_absences} non justifiée(s)`
                : '',
            show: props.portal.modules.attendance,
        },
        {
            title: 'Notes',
            href: '/parent/grades',
            icon: ChartNoAxesColumn,
            text: props.today?.grade
                ? `${props.today.grade.subject}: ${props.today.grade.value}/${props.today.grade.maximum}`
                : 'Consulter les résultats',
            show: props.portal.modules.grades,
        },
        {
            title: 'Bulletins',
            href: '/parent/report-cards',
            icon: FileText,
            text: props.quickAccess?.report_card
                ? `${props.quickAccess.report_card.period} disponible`
                : 'Aucun bulletin publié',
            show: props.portal.modules.report_cards,
        },
        {
            title: 'Paiements',
            href: '/parent/payments',
            icon: CreditCard,
            text: props.quickAccess?.payment
                ? `Dernier paiement: ${props.quickAccess.payment.amount} DA`
                : 'Aucun paiement récent',
            show: props.portal.modules.payments,
        },
        {
            title: 'Messages',
            href: '/parent/messages',
            icon: MessageCircle,
            text: 'Contacter l’établissement',
            show: props.portal.modules.messages,
        },
    ].filter((i) => i.show),
);
const formatDate = (date: string) =>
    date
        ? new Date(date).toLocaleDateString('fr-DZ', {
              day: 'numeric',
              month: 'long',
          })
        : '';
</script>
<template>
    <Head title="Espace parent" /><ParentLayout :portal="portal">
        <div class="space-y-8">
            <div>
                <p class="text-sm text-slate-500">Bienvenue,</p>
                <h1 class="text-2xl font-bold sm:text-3xl">
                    {{ portal.parent.name }}
                </h1>
            </div>
            <section
                v-if="child"
                class="overflow-hidden rounded-3xl bg-gradient-to-br from-teal-600 to-cyan-700 p-5 text-white shadow-lg sm:p-7"
            >
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <img
                        v-if="child.photo_url"
                        :src="child.photo_url"
                        class="size-20 rounded-2xl border-2 border-white/50 object-cover"
                    />
                    <div
                        v-else
                        class="grid size-20 place-items-center rounded-2xl bg-white/20 text-2xl font-bold"
                    >
                        {{
                            child.name
                                ?.split(' ')
                                .map((x: string) => x[0])
                                .join('')
                                .slice(0, 2)
                        }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-white/75">Enfant sélectionné</p>
                        <h2 class="text-2xl font-bold">{{ child.name }}</h2>
                        <p class="mt-1 text-white/85">
                            {{
                                [child.level, child.group]
                                    .filter(Boolean)
                                    .join(' · ') || 'Affectation non définie'
                            }}
                        </p>
                        <p class="text-sm text-white/70">
                            {{ child.academic_year }}
                        </p>
                    </div>
                    <Link
                        :href="`/parent/children/${child.id}`"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-teal-700"
                        >Voir le profil <ChevronRight class="size-4"
                    /></Link>
                </div>
            </section>
            <section
                v-else
                class="rounded-2xl border border-dashed bg-white p-10 text-center"
            >
                <h2 class="font-semibold">Aucun enfant associé</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Contactez l’établissement pour associer vos enfants à ce
                    compte.
                </p>
            </section>

            <template v-if="child">
                <section>
                    <h2 class="mb-4 text-xl font-bold">Aujourd’hui</h2>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <Link
                            href="/parent/timetable"
                            class="rounded-2xl border bg-white p-5 transition hover:border-primary/40"
                        >
                            <p class="text-sm font-semibold">Cours du jour</p>
                            <div v-if="today.classes.length" class="mt-3">
                                <p class="text-2xl font-bold">
                                    {{ today.classes.length }} cours
                                </p>
                                <div
                                    v-if="nextClass"
                                    class="mt-3 rounded-xl bg-slate-50 p-3"
                                >
                                    <p class="text-xs text-slate-500">
                                        Prochain
                                    </p>
                                    <b>{{ nextClass.subject || 'Cours' }}</b>
                                    <p class="text-sm text-slate-500">
                                        {{ nextClass.start_time }}
                                    </p>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-500">
                                    Les cours sont terminés pour aujourd’hui.
                                </p>
                            </div>
                            <p v-else class="mt-3 text-sm text-slate-500">
                                Aucun cours prévu aujourd’hui.
                            </p>
                        </Link>
                        <Link
                            href="/parent/absences"
                            class="rounded-2xl border bg-white p-5 transition hover:border-primary/40"
                        >
                            <p class="text-sm font-semibold">Présence</p>
                            <p
                                class="mt-3 text-sm"
                                :class="
                                    today.absence
                                        ? 'text-amber-700'
                                        : 'text-emerald-700'
                                "
                            >
                                {{
                                    today.absence
                                        ? `Absence enregistrée aujourd’hui${today.absence.subject ? ` · ${today.absence.subject}` : ''}`
                                        : 'Aucune absence enregistrée aujourd’hui'
                                }}
                            </p>
                        </Link>
                        <Link
                            href="/parent/exams"
                            class="rounded-2xl border bg-white p-5 transition hover:border-primary/40"
                        >
                            <p class="text-sm font-semibold">Prochain examen</p>
                            <div v-if="today.exam" class="mt-3">
                                <b>{{
                                    today.exam.subject || today.exam.name
                                }}</b>
                                <p class="text-sm text-slate-500">
                                    {{ formatDate(today.exam.date) }}
                                </p>
                            </div>
                            <p v-else class="mt-3 text-sm text-slate-500">
                                Aucun examen à venir.
                            </p>
                        </Link>
                        <Link
                            href="/parent/grades"
                            class="rounded-2xl border bg-white p-5 transition hover:border-primary/40"
                        >
                            <p class="text-sm font-semibold">Dernière note</p>
                            <p v-if="today.grade" class="mt-3">
                                <b>{{ today.grade.subject }}</b> ·
                                {{ today.grade.value }}/{{
                                    today.grade.maximum
                                }}
                            </p>
                            <p v-else class="mt-3 text-sm text-slate-500">
                                Aucune note publiée.
                            </p>
                        </Link>
                        <article class="rounded-2xl border bg-white p-5">
                            <p class="text-sm font-semibold">
                                Observation récente
                            </p>
                            <p v-if="today.observation" class="mt-3 text-sm">
                                {{ today.observation.message }}
                            </p>
                            <p v-else class="mt-3 text-sm text-slate-500">
                                Aucune observation récente.
                            </p>
                        </article>
                        <article class="rounded-2xl border bg-white p-5">
                            <p class="text-sm font-semibold">
                                Prochain événement
                            </p>
                            <div v-if="today.event" class="mt-3">
                                <b>{{ today.event.name }}</b>
                                <p class="text-sm text-slate-500">
                                    {{ formatDate(today.event.date) }}
                                </p>
                            </div>
                            <p v-else class="mt-3 text-sm text-slate-500">
                                Aucun événement à venir.
                            </p>
                        </article>
                    </div>
                </section>
                <section>
                    <h2 class="mb-4 text-xl font-bold">Accès rapide</h2>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <Link
                            v-for="item in quick"
                            :key="item.href"
                            :href="item.href"
                            class="group flex items-center gap-4 rounded-2xl border bg-white p-5 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                            ><div
                                class="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary"
                            >
                                <component :is="item.icon" class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <b>{{ item.title }}</b>
                                <p class="truncate text-sm text-slate-500">
                                    {{ item.text }}
                                </p>
                            </div>
                            <ChevronRight
                                class="size-5 text-slate-300 group-hover:text-primary"
                        /></Link>
                    </div>
                </section>
                <section>
                    <h2 class="mb-4 text-xl font-bold">Activité récente</h2>
                    <div class="rounded-2xl border bg-white p-5">
                        <div
                            v-for="activity in recentActivity"
                            :key="activity.id"
                            class="flex gap-3 border-b py-3 last:border-0"
                        >
                            <div
                                class="mt-1 grid size-8 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"
                            >
                                <Bell class="size-4" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ activity.title }}
                                </p>
                                <p class="text-sm text-slate-500">
                                    {{ activity.message }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="!recentActivity.length"
                            class="py-5 text-center text-sm text-slate-500"
                        >
                            Aucune activité récente.
                        </p>
                    </div>
                </section>
            </template>
        </div>
    </ParentLayout>
</template>
