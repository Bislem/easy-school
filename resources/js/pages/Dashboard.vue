<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    BookOpen,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    CircleDollarSign,
    Clock3,
    DoorOpen,
    FileWarning,
    GraduationCap,
    Plus,
    ReceiptText,
    TrendingUp,
    UserCheck,
    UserPlus,
    Users,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface DashboardData {
    stats: {
        students: number;
        monthly_enrollments: number;
        ongoing_courses: number;
        active_groups: number;
        monthly_expenses: number;
        teachers_today: number;
        rooms_occupied: number;
        rooms_available: number;
    };
    finance: {
        collected: number | null;
        remaining: number | null;
        overdue: number | null;
        upcoming: number | null;
    };
    attendance: {
        rate: number | null;
        recent_absences: unknown[];
        missing_documents: unknown[];
    };
    schedule: { today: any[]; upcoming: any[] };
    active_forms: any[];
    upcoming_forms: any[];
    near_capacity: any[];
    latest_enrollments: any[];
    activities: Array<{ type: string; title: string; date: string }>;
    alerts: unknown[];
    currency: { symbol: string; code: string };
    generated_at: string;
}
const props = defineProps<{ dashboard: DashboardData | null }>();
const page = usePage();
const user = page.props.auth.user as { name: string; role: string };
const isAdmin = computed(() => user.role === 'admin');
const firstName = computed(() => user.name.split(' ')[0]);
const money = (value: number) =>
    `${Number(value).toLocaleString('fr-FR', { maximumFractionDigits: 0 })} ${props.dashboard?.currency.symbol ?? 'DZD'}`;
const shortDate = (value: string) =>
    new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
    });
const relativeDate = (value: string) =>
    new Intl.RelativeTimeFormat('fr', { numeric: 'auto' }).format(
        Math.round((new Date(value).getTime() - Date.now()) / 86400000),
        'day',
    );
const formatTime = (value: string) =>
    new Date(value).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    });
const capacity = (form: any) =>
    Math.min(100, Math.round((form.confirmed_count / form.max_students) * 100));
const roleLabel = computed(() =>
    user.role === 'admin'
        ? 'Administration'
        : user.role === 'teacher'
          ? 'Espace enseignant'
          : 'Espace personnel',
);
</script>

<template>
    <Head title="Tableau de bord" />
    <AdminLayout>
        <main class="min-w-0 flex-1 bg-muted/20 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-[1600px] space-y-6">
                <header
                    class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-primary/80 p-5 text-primary-foreground shadow-lg sm:p-6 lg:p-8"
                >
                    <div
                        class="pointer-events-none absolute -top-24 -right-20 size-72 rounded-full bg-white/10"
                    />
                    <div
                        class="pointer-events-none absolute right-40 -bottom-28 size-56 rounded-full bg-white/5"
                    />
                    <div
                        class="relative flex min-w-0 flex-col gap-6 xl:flex-row xl:items-end xl:justify-between xl:gap-8"
                    >
                        <div class="min-w-0 xl:max-w-xl">
                            <p
                                class="text-sm font-medium text-primary-foreground/75"
                            >
                                {{ roleLabel }} ·
                                {{
                                    new Date().toLocaleDateString('fr-FR', {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                    })
                                }}
                            </p>
                            <h1
                                class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl"
                            >
                                Bonjour {{ firstName }}
                            </h1>
                            <p
                                class="mt-2 max-w-xl text-sm text-primary-foreground/80 sm:text-base"
                            >
                                Voici l’essentiel de l’activité de votre
                                établissement aujourd’hui.
                            </p>
                        </div>
                        <div
                            v-if="isAdmin"
                            class="grid w-full min-w-0 grid-cols-1 gap-2 sm:grid-cols-2 xl:w-auto xl:min-w-[34rem]"
                        >
                            <Button
                                as-child
                                variant="secondary"
                                class="h-auto min-h-10 w-full justify-start px-4 py-2.5 text-left leading-tight whitespace-normal"
                                ><Link href="/admin/enrollment-forms"
                                    ><Plus
                                        class="mr-2 size-4 shrink-0"
                                    />Nouvelle inscription</Link
                                ></Button
                            ><Button
                                as-child
                                class="h-auto min-h-10 w-full justify-start bg-white/15 px-4 py-2.5 text-left leading-tight whitespace-normal text-white hover:bg-white/25"
                                ><Link href="/admin/students"
                                    ><UserPlus
                                        class="mr-2 size-4 shrink-0"
                                    />Ajouter un apprenant</Link
                                ></Button
                            ><Button
                                disabled
                                class="h-auto min-h-10 w-full justify-start bg-white/10 px-4 py-2.5 text-left leading-tight whitespace-normal text-white/70"
                                ><Banknote
                                    class="mr-2 size-4 shrink-0"
                                />Ajouter un paiement</Button
                            ><Button
                                as-child
                                class="h-auto min-h-10 w-full justify-start bg-white/15 px-4 py-2.5 text-left leading-tight whitespace-normal text-white hover:bg-white/25"
                                ><Link href="/admin/planifications"
                                    ><CalendarClock
                                        class="mr-2 size-4 shrink-0"
                                    />Planifier une séance</Link
                                ></Button
                            >
                        </div>
                    </div>
                </header>

                <template v-if="isAdmin && dashboard">
                    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="item in [
                                {
                                    label: 'Apprenants',
                                    value: dashboard.stats.students,
                                    detail: 'actifs au total',
                                    icon: GraduationCap,
                                    tone: 'bg-blue-50 text-blue-600',
                                },
                                {
                                    label: 'Nouvelles inscriptions',
                                    value: dashboard.stats.monthly_enrollments,
                                    detail: 'ce mois-ci',
                                    icon: UserPlus,
                                    tone: 'bg-emerald-50 text-emerald-600',
                                },
                                {
                                    label: 'Formations en cours',
                                    value: dashboard.stats.ongoing_courses,
                                    detail: `${dashboard.stats.active_groups} groupes actifs`,
                                    icon: BookOpen,
                                    tone: 'bg-violet-50 text-violet-600',
                                },
                                {
                                    label: 'Dépenses du mois',
                                    value: money(
                                        dashboard.stats.monthly_expenses,
                                    ),
                                    detail: 'toutes catégories',
                                    icon: ReceiptText,
                                    tone: 'bg-orange-50 text-orange-600',
                                },
                            ]"
                            :key="item.label"
                            class="rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <p
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-bold tracking-tight"
                                    >
                                        {{ item.value }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ item.detail }}
                                    </p>
                                </div>
                                <div class="rounded-xl p-3" :class="item.tone">
                                    <component :is="item.icon" class="size-5" />
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="item in [
                                {
                                    label: 'Revenus encaissés',
                                    icon: CircleDollarSign,
                                },
                                { label: 'Reste à payer', icon: WalletCards },
                                {
                                    label: 'Paiements en retard',
                                    icon: AlertTriangle,
                                },
                                {
                                    label: 'Prochains paiements',
                                    icon: CalendarClock,
                                },
                            ]"
                            :key="item.label"
                            class="rounded-xl border border-dashed bg-card p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-muted p-2">
                                    <component
                                        :is="item.icon"
                                        class="size-4 text-muted-foreground"
                                    />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ item.label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Disponible avec le module de paiements
                                    </p>
                                </div>
                            </div>
                        </article>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                        <section class="rounded-2xl border bg-card shadow-sm">
                            <div
                                class="flex items-center justify-between border-b p-5"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Groupes actifs et progression
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Occupation des formations actuellement
                                        en cours
                                    </p>
                                </div>
                                <Link
                                    href="/admin/enrollment-forms"
                                    class="text-sm font-medium text-primary"
                                    >Tout afficher
                                    <ArrowRight class="ml-1 inline size-4"
                                /></Link>
                            </div>
                            <div class="divide-y">
                                <div
                                    v-for="form in dashboard.active_forms.slice(
                                        0,
                                        6,
                                    )"
                                    :key="form.id"
                                    class="p-5"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-2"
                                    >
                                        <div>
                                            <p class="font-medium">
                                                {{ form.course.title }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ form.groups_count }}
                                                groupe(s) ·
                                                {{ form.teacher.name
                                                }}<span v-if="form.classroom">
                                                    ·
                                                    {{
                                                        form.classroom.name
                                                    }}</span
                                                >
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                                            >{{ capacity(form) }}%</span
                                        >
                                    </div>
                                    <div
                                        class="mt-3 h-2 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="{
                                                width: `${capacity(form)}%`,
                                            }"
                                        />
                                    </div>
                                    <div
                                        class="mt-2 flex justify-between text-xs text-muted-foreground"
                                    >
                                        <span
                                            >{{
                                                form.confirmed_count
                                            }}
                                            inscrits</span
                                        ><span
                                            >{{
                                                form.max_students
                                            }}
                                            places</span
                                        >
                                    </div>
                                </div>
                                <div
                                    v-if="!dashboard.active_forms.length"
                                    class="p-10 text-center"
                                >
                                    <BookOpen
                                        class="mx-auto size-9 text-muted-foreground/50"
                                    />
                                    <p class="mt-3 font-medium">
                                        Aucune formation en cours
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        Les formations actives apparaîtront ici.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border bg-card shadow-sm">
                            <div class="border-b p-5">
                                <h2 class="font-semibold">État du jour</h2>
                                <p class="text-sm text-muted-foreground">
                                    Personnel et salles mobilisés
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-3 p-5">
                                <div class="rounded-xl bg-emerald-50 p-4">
                                    <UserCheck
                                        class="size-5 text-emerald-600"
                                    />
                                    <p class="mt-3 text-2xl font-bold">
                                        {{ dashboard.stats.teachers_today }}
                                    </p>
                                    <p class="text-xs text-emerald-700">
                                        Formateurs mobilisés
                                    </p>
                                </div>
                                <div class="rounded-xl bg-blue-50 p-4">
                                    <DoorOpen class="size-5 text-blue-600" />
                                    <p class="mt-3 text-2xl font-bold">
                                        {{ dashboard.stats.rooms_occupied }}
                                    </p>
                                    <p class="text-xs text-blue-700">
                                        Salles occupées
                                    </p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <CheckCircle2
                                        class="size-5 text-slate-600"
                                    />
                                    <p class="mt-3 text-2xl font-bold">
                                        {{ dashboard.stats.rooms_available }}
                                    </p>
                                    <p class="text-xs text-slate-600">
                                        Salles disponibles
                                    </p>
                                </div>
                                <div class="rounded-xl bg-amber-50 p-4">
                                    <CalendarDays
                                        class="size-5 text-amber-600"
                                    />
                                    <p class="mt-3 text-2xl font-bold">
                                        {{ dashboard.stats.ongoing_courses }}
                                    </p>
                                    <p class="text-xs text-amber-700">
                                        Activités aujourd’hui
                                    </p>
                                </div>
                            </div>
                            <div class="border-t p-5">
                                <p class="text-sm font-medium">
                                    Emploi du temps de la journée
                                </p>
                                <div
                                    v-if="dashboard.schedule.today.length"
                                    class="mt-3 divide-y rounded-lg border"
                                >
                                    <Link
                                        v-for="session in dashboard.schedule
                                            .today"
                                        :key="session.id"
                                        :href="`/admin/planifications/${session.group.training_plan_id}`"
                                        class="flex items-center gap-3 p-3 hover:bg-muted/40"
                                    >
                                        <span
                                            class="w-12 text-sm font-semibold text-primary"
                                            >{{
                                                formatTime(session.starts_at)
                                            }}</span
                                        >
                                        <span class="min-w-0 flex-1"
                                            ><span
                                                class="block truncate text-sm font-medium"
                                                >{{ session.title }}</span
                                            ><span
                                                class="block truncate text-xs text-muted-foreground"
                                                >{{ session.group.name }} ·
                                                {{ session.classroom.name }} ·
                                                {{ session.teacher.name }}</span
                                            ></span
                                        >
                                    </Link>
                                </div>
                                <div
                                    v-else
                                    class="mt-3 rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground"
                                >
                                    Aucune séance prévue aujourd’hui.
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
                        <section class="rounded-2xl border bg-card shadow-sm">
                            <div class="border-b p-5">
                                <h2 class="font-semibold">
                                    Formations à venir
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Prochains démarrages planifiés
                                </p>
                            </div>
                            <div class="divide-y">
                                <Link
                                    v-for="form in dashboard.upcoming_forms"
                                    :key="form.id"
                                    :href="`/admin/enrollment-forms/${form.id}`"
                                    class="flex items-center gap-4 p-4 hover:bg-muted/40"
                                    ><div
                                        class="w-12 shrink-0 rounded-lg bg-primary/10 px-2 py-2 text-center text-primary"
                                    >
                                        <p
                                            class="text-lg leading-none font-bold"
                                        >
                                            {{
                                                shortDate(
                                                    form.start_date,
                                                ).split(' ')[0]
                                            }}
                                        </p>
                                        <p class="mt-1 text-[10px] uppercase">
                                            {{
                                                shortDate(
                                                    form.start_date,
                                                ).split(' ')[1]
                                            }}
                                        </p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ form.course.title }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ form.teacher.name }} ·
                                            {{ form.confirmed_count }}/{{
                                                form.max_students
                                            }}
                                            inscrits
                                        </p>
                                    </div>
                                    <ArrowRight
                                        class="size-4 text-muted-foreground"
                                /></Link>
                                <p
                                    v-if="!dashboard.upcoming_forms.length"
                                    class="p-8 text-center text-sm text-muted-foreground"
                                >
                                    Aucun démarrage prochain.
                                </p>
                            </div>
                        </section>

                        <section class="rounded-2xl border bg-card shadow-sm">
                            <div class="border-b p-5">
                                <h2 class="font-semibold">
                                    Groupes proches de la capacité
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Seuil d’alerte à 80 %
                                </p>
                            </div>
                            <div class="divide-y">
                                <div
                                    v-for="form in dashboard.near_capacity"
                                    :key="form.id"
                                    class="p-4"
                                >
                                    <div class="flex justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium">
                                                {{ form.course.title }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ form.confirmed_count }} sur
                                                {{ form.max_students }} places
                                            </p>
                                        </div>
                                        <span
                                            class="text-sm font-bold text-orange-600"
                                            >{{ capacity(form) }}%</span
                                        >
                                    </div>
                                    <div
                                        class="mt-2 h-1.5 rounded-full bg-orange-100"
                                    >
                                        <div
                                            class="h-full rounded-full bg-orange-500"
                                            :style="{
                                                width: `${capacity(form)}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                                <div
                                    v-if="!dashboard.near_capacity.length"
                                    class="p-8 text-center"
                                >
                                    <CheckCircle2
                                        class="mx-auto size-8 text-emerald-500"
                                    />
                                    <p
                                        class="mt-2 text-sm text-muted-foreground"
                                    >
                                        Aucun groupe proche de sa limite.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-2xl border bg-card shadow-sm lg:col-span-2 xl:col-span-1"
                        >
                            <div class="border-b p-5">
                                <h2 class="font-semibold">Suivi pédagogique</h2>
                                <p class="text-sm text-muted-foreground">
                                    Présences et dossiers étudiants
                                </p>
                            </div>
                            <div class="space-y-3 p-5">
                                <div
                                    class="flex items-center justify-between rounded-xl bg-muted/50 p-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <TrendingUp
                                            class="size-5 text-primary"
                                        /><span class="text-sm"
                                            >Taux de présence global</span
                                        >
                                    </div>
                                    <span class="text-sm text-muted-foreground"
                                        >Non suivi</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-xl bg-muted/50 p-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <Clock3
                                            class="size-5 text-orange-500"
                                        /><span class="text-sm"
                                            >Absences récentes</span
                                        >
                                    </div>
                                    <span class="text-sm text-muted-foreground"
                                        >Non suivi</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-xl bg-muted/50 p-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <FileWarning
                                            class="size-5 text-rose-500"
                                        /><span class="text-sm"
                                            >Documents manquants</span
                                        >
                                    </div>
                                    <span class="text-sm text-muted-foreground"
                                        >Non suivi</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-xl bg-emerald-50 p-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <CheckCircle2
                                            class="size-5 text-emerald-600"
                                        /><span class="text-sm"
                                            >Conflits de salles/formateurs</span
                                        >
                                    </div>
                                    <span
                                        class="text-sm font-medium text-emerald-700"
                                        >Aucun détecté</span
                                    >
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section class="rounded-2xl border bg-card shadow-sm">
                            <div
                                class="flex items-center justify-between border-b p-5"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Dernières inscriptions
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Confirmations reçues récemment
                                    </p>
                                </div>
                                <Users class="size-5 text-muted-foreground" />
                            </div>
                            <div class="divide-y">
                                <div
                                    v-for="enrollment in dashboard.latest_enrollments"
                                    :key="enrollment.id"
                                    class="flex items-center gap-3 p-4"
                                >
                                    <div
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary"
                                    >
                                        {{ enrollment.first_name[0]
                                        }}{{ enrollment.last_name[0] }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ enrollment.first_name }}
                                            {{ enrollment.last_name }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ enrollment.form.course.title }} ·
                                            Groupe {{ enrollment.group_number }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            relativeDate(
                                                enrollment.confirmed_at,
                                            )
                                        }}</span
                                    >
                                </div>
                                <p
                                    v-if="!dashboard.latest_enrollments.length"
                                    class="p-8 text-center text-sm text-muted-foreground"
                                >
                                    Aucune inscription confirmée.
                                </p>
                            </div>
                        </section>
                        <section class="rounded-2xl border bg-card shadow-sm">
                            <div
                                class="flex items-center justify-between border-b p-5"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Activités récentes
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Dernières actions enregistrées
                                    </p>
                                </div>
                                <Clock3 class="size-5 text-muted-foreground" />
                            </div>
                            <div class="divide-y">
                                <div
                                    v-for="activity in dashboard.activities"
                                    :key="`${activity.type}-${activity.title}-${activity.date}`"
                                    class="flex gap-3 p-4"
                                >
                                    <span
                                        class="mt-1 size-2 shrink-0 rounded-full bg-primary"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm">
                                            {{ activity.title }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-muted-foreground"
                                        >
                                            {{ relativeDate(activity.date) }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    v-if="!dashboard.activities.length"
                                    class="p-8 text-center text-sm text-muted-foreground"
                                >
                                    Aucune activité récente.
                                </p>
                            </div>
                            <div
                                class="border-t p-4 text-center text-xs text-muted-foreground"
                            >
                                Les derniers paiements apparaîtront ici dès que
                                leur module sera disponible.
                            </div>
                        </section>
                    </div>
                </template>

                <section v-else class="grid gap-4 sm:grid-cols-2">
                    <article class="rounded-2xl border bg-card p-6">
                        <BookOpen class="size-7 text-primary" />
                        <h2 class="mt-4 font-semibold">Mes formations</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Vos formations et groupes affectés seront affichés
                            ici.
                        </p>
                    </article>
                    <article class="rounded-2xl border bg-card p-6">
                        <CalendarDays class="size-7 text-primary" />
                        <h2 class="mt-4 font-semibold">Mon emploi du temps</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Vos prochaines séances seront affichées ici.
                        </p>
                    </article>
                </section>
            </div>
        </main>
    </AdminLayout>
</template>
