<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    BookOpen,
    CalendarClock,
    CheckCircle2,
    Clock3,
    DoorOpen,
    GraduationCap,
    History,
    Mail,
    MapPin,
    Phone,
    UserRound,
    Users,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps({
    kind: String,
    section: String,
    profile: Object,
    data: { type: [Object, Array], required: true },
    children: Array,
});
const labels: any = {
    formation: 'Ma formation',
    planning: 'Mon planning',
    attendance: 'Mes présences',
    payments: 'Mes paiements',
    documents: 'Mes documents',
    notifications: 'Notifications',
    students: 'Mes étudiants',
};
const selected = ref<any>(null);
const studentTab = ref<'active' | 'history'>('active');
const planningTab = ref<'upcoming' | 'past'>('upcoming');
const displayedPlanning = computed(() =>
    planningTab.value === 'upcoming'
        ? ((props.data as any).upcoming_sessions?.data ?? [])
        : ((props.data as any).past_sessions?.data ?? []),
);
const displayedPlanningLinks = computed(() =>
    planningTab.value === 'upcoming'
        ? ((props.data as any).upcoming_sessions?.links ?? [])
        : ((props.data as any).past_sessions?.links ?? []),
);
const displayedStudents = computed(() =>
    studentTab.value === 'active'
        ? ((props.data as any).students?.data ?? [])
        : ((props.data as any).archived_students?.data ?? []),
);
const displayedStudentLinks = computed(() =>
    studentTab.value === 'active'
        ? ((props.data as any).students?.links ?? [])
        : ((props.data as any).archived_students?.links ?? []),
);
const records = useForm({ records: [] as any[] });
function choose(s: any) {
    selected.value = s;
    const enrollments = (props.data as any).students ?? [];
    records.records = enrollments
        .filter(
            (e: any) => e.training_plan_group_id === s.training_plan_group_id,
        )
        .map((e: any) => ({
            student_id: e.student.id,
            student_name: `${e.student.first_name} ${e.student.last_name}`,
            photo_url: e.student.photo_url,
            status:
                s.attendances?.find((a: any) => a.student_id === e.student.id)
                    ?.status ?? 'present',
            notes: '',
        }));
}
function saveAttendance() {
    records.put(`/portal/sessions/${selected.value.id}/attendance`);
}
function read(n: any) {
    const visit = () => n.data?.url && router.visit(n.data.url);
    if (!n.read_at)
        router.patch(
            `/portal/notifications/${n.id}/read`,
            {},
            { onSuccess: visit },
        );
    else visit();
}
const money = (v: any) => `${Number(v || 0).toLocaleString('fr-DZ')} DA`;
const planningDate = (value: string) =>
    new Date(value).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
const planningTime = (value: string) =>
    new Date(value).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
const duration = (session: any) =>
    Math.round(
        (new Date(session.ends_at).getTime() -
            new Date(session.starts_at).getTime()) /
            60000,
    );
const attendanceLabels: any = {
    present: 'Présent',
    late: 'En retard',
    absent: 'Absent',
    replaced: 'Remplacé',
    excused: 'Excusé',
    not_recorded: 'Non renseignée',
};
const salaryLabels: any = {
    paid: 'Payée',
    partially_paid: 'Partiellement payée',
    unpaid: 'Calculée, non payée',
    not_calculated: 'Non calculée',
};
</script>
<template>
    <Head :title="labels[section]" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-5">
                <h1 class="text-2xl font-semibold">{{ labels[section] }}</h1>
                <template v-if="kind === 'parent'"
                    ><section
                        v-if="section === 'notifications'"
                        class="space-y-2"
                    >
                        <button
                            v-for="n in data.notifications"
                            :key="n.id"
                            class="block w-full rounded-lg border p-4 text-left"
                            @click="read(n)"
                        >
                            <b>{{ n.title }}</b>
                            <p>{{ n.message }}</p>
                        </button>
                    </section>
                    <article
                        v-for="child in section === 'notifications'
                            ? []
                            : children"
                        :key="child.id"
                        class="rounded-xl border bg-card p-5"
                    >
                        <h2 class="font-semibold">
                            {{ child.first_name }} {{ child.last_name }}
                        </h2>
                        <div v-if="section === 'payments'" class="mt-3">
                            <p v-for="e in child.enrollments" :key="e.id">
                                {{
                                    e.form?.course?.title ||
                                    e.training_plan_group?.plan?.course
                                        ?.title ||
                                    'Formation'
                                }}
                                — reste
                                {{ money(e.remaining_balance) }}
                            </p>
                        </div>
                        <div v-else-if="section === 'attendance'" class="mt-3">
                            <p v-for="a in child.attendances" :key="a.id">
                                {{ a.session?.title }} — {{ a.status }}
                            </p>
                        </div>
                        <div v-else-if="section === 'planning'" class="mt-3">
                            <p v-for="s in child.portal_sessions" :key="s.id">
                                {{ s.title }} · {{ s.starts_at }} ·
                                {{ s.classroom?.name }}
                            </p>
                        </div>
                        <div v-else class="mt-3">
                            <p v-for="e in child.enrollments" :key="e.id">
                                {{
                                    e.form?.course?.title ||
                                    e.training_plan_group?.plan?.course
                                        ?.title ||
                                    'Formation'
                                }}
                                · {{ e.level || '—' }} · Groupe
                                {{ e.group_number || '—' }}
                            </p>
                        </div>
                    </article></template
                >
                <template v-else-if="kind === 'student'"
                    ><section v-if="section === 'formation'" class="space-y-3">
                        <article
                            v-for="e in data.enrollments"
                            :key="e.id"
                            class="rounded-xl border bg-card p-5"
                        >
                            <h2 class="font-semibold">
                                {{
                                    e.form?.course?.title ||
                                    e.training_plan_group?.plan?.course
                                        ?.title ||
                                    'Formation'
                                }}
                            </h2>
                            <p>
                                Niveau {{ e.level || '—' }} · Groupe
                                {{ e.group_number || '—' }}
                            </p>
                        </article>
                    </section>
                    <section
                        v-else-if="section === 'planning'"
                        class="space-y-3"
                    >
                        <article
                            v-for="s in data.sessions"
                            :key="s.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <b>{{ s.title }}</b>
                            <p class="text-sm text-muted-foreground">
                                {{ s.starts_at }} → {{ s.ends_at }} ·
                                {{ s.classroom?.name }}
                            </p>
                        </article>
                    </section>
                    <section
                        v-else-if="section === 'attendance'"
                        class="space-y-2"
                    >
                        <p
                            v-for="a in profile.attendances"
                            :key="a.id"
                            class="rounded-lg border p-3"
                        >
                            {{ a.session?.title }} · {{ a.status }} ·
                            {{ a.recorded_at }}
                        </p>
                    </section>
                    <section
                        v-else-if="section === 'payments'"
                        class="space-y-4"
                    >
                        <article
                            v-for="e in data.enrollments"
                            :key="e.id"
                            class="rounded-xl border bg-card p-5"
                        >
                            <h2 class="font-semibold">
                                {{
                                    e.form?.course?.title ||
                                    e.training_plan_group?.plan?.course
                                        ?.title ||
                                    'Formation'
                                }}
                            </h2>
                            <p>
                                Payé {{ money(e.total_paid) }} · Reste
                                {{ money(e.remaining_balance) }}
                            </p>
                            <div class="mt-3">
                                <p
                                    v-for="p in e.payments"
                                    :key="p.id"
                                    class="border-t py-2 text-sm"
                                >
                                    {{ p.reference }} · {{ money(p.amount) }} ·
                                    <a
                                        class="text-primary underline"
                                        :href="`/portal/payments/${p.id}/receipt`"
                                        >Reçu</a
                                    >
                                </p>
                            </div>
                        </article>
                    </section>
                    <section
                        v-else-if="section === 'documents'"
                        class="space-y-2"
                    >
                        <a
                            v-for="f in profile.files"
                            :key="f.id"
                            :href="f.url"
                            target="_blank"
                            class="block rounded-lg border p-3 text-primary"
                            >Document {{ f.id }}</a
                        >
                    </section>
                    <section v-else>
                        <button
                            v-for="n in data.notifications"
                            :key="n.id"
                            class="block w-full rounded-lg border p-4 text-left"
                            @click="read(n)"
                        >
                            <b>{{ n.title }}</b>
                            <p class="text-sm text-muted-foreground">
                                {{ n.message }}
                            </p>
                        </button>
                    </section></template
                >
                <template v-else
                    ><section v-if="section === 'students'" class="space-y-5">
                        <div
                            class="inline-flex rounded-xl border bg-muted/40 p-1"
                        >
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                                :class="
                                    studentTab === 'active'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground'
                                "
                                @click="studentTab = 'active'"
                            >
                                <Users class="size-4" />Étudiants actuels<span
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700"
                                    >{{ data.students?.total || 0 }}</span
                                >
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                                :class="
                                    studentTab === 'history'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground'
                                "
                                @click="studentTab = 'history'"
                            >
                                <Archive class="size-4" />Historique<span
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                    >{{
                                        data.archived_students?.total || 0
                                    }}</span
                                >
                            </button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="e in displayedStudents"
                                :key="e.id"
                                class="group overflow-hidden rounded-2xl border bg-card shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                            >
                                <div
                                    class="h-1.5"
                                    :class="
                                        studentTab === 'active'
                                            ? 'bg-gradient-to-r from-primary to-primary/40'
                                            : 'bg-muted'
                                    "
                                ></div>
                                <div class="p-5">
                                    <div class="flex items-start gap-4">
                                        <img
                                            v-if="
                                                studentTab === 'active' &&
                                                e.student.photo_url
                                            "
                                            :src="e.student.photo_url"
                                            :alt="`${e.student.first_name} ${e.student.last_name}`"
                                            class="size-16 rounded-2xl object-cover ring-4 ring-primary/10"
                                        />
                                        <div
                                            v-else
                                            class="grid size-16 shrink-0 place-items-center rounded-2xl bg-muted text-muted-foreground"
                                        >
                                            <UserRound class="size-7" />
                                        </div>
                                        <div class="min-w-0">
                                            <h2
                                                class="truncate text-lg font-semibold"
                                            >
                                                {{ e.student.first_name }}
                                                {{ e.student.last_name }}
                                            </h2>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{
                                                    e.student.school_level ||
                                                    e.level ||
                                                    'Niveau non renseigné'
                                                }}
                                            </p>
                                            <span
                                                class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="
                                                    studentTab === 'active'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-muted text-muted-foreground'
                                                "
                                                >{{
                                                    studentTab === 'active'
                                                        ? 'Planification active'
                                                        : 'Planification terminée'
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="mt-5 space-y-2 border-t pt-4 text-sm"
                                    >
                                        <p class="flex items-center gap-2">
                                            <BookOpen
                                                class="size-4 text-primary"
                                            /><span class="truncate">{{
                                                e.form?.course?.title ||
                                                e.training_plan_group?.plan
                                                    ?.course?.title ||
                                                'Formation'
                                            }}</span>
                                        </p>
                                        <p class="text-muted-foreground">
                                            Groupe
                                            {{
                                                e.group_number ||
                                                e.training_plan_group
                                                    ?.group_number ||
                                                '—'
                                            }}
                                        </p>
                                        <p
                                            v-if="e.student.email"
                                            class="flex items-center gap-2 text-muted-foreground"
                                        >
                                            <Mail class="size-4" /><span
                                                class="truncate"
                                                >{{ e.student.email }}</span
                                            >
                                        </p>
                                        <p
                                            v-if="
                                                studentTab === 'active' &&
                                                e.student.phone
                                            "
                                            class="flex items-center gap-2 text-muted-foreground"
                                        >
                                            <Phone class="size-4" />{{
                                                e.student.phone
                                            }}
                                        </p>
                                    </div>
                                    <Button
                                        v-if="
                                            studentTab === 'active' &&
                                            data.can_view_student_folders
                                        "
                                        as-child
                                        class="mt-5 w-full"
                                        variant="outline"
                                        ><Link
                                            :href="`/portal/students/${e.student.id}`"
                                            >Voir le dossier</Link
                                        ></Button
                                    >
                                    <p
                                        v-else-if="studentTab === 'active'"
                                        class="mt-5 rounded-lg bg-muted p-3 text-center text-xs text-muted-foreground"
                                    >
                                        Consultation du dossier désactivée par
                                        l’administration.
                                    </p>
                                    <p
                                        v-else
                                        class="mt-5 rounded-lg bg-muted p-3 text-center text-xs text-muted-foreground"
                                    >
                                        Dossier archivé non accessible.
                                    </p>
                                </div>
                            </article>
                            <div
                                v-if="!displayedStudents.length"
                                class="col-span-full rounded-2xl border border-dashed p-12 text-center text-muted-foreground"
                            >
                                {{
                                    studentTab === 'active'
                                        ? 'Aucun étudiant dans vos planifications actives.'
                                        : 'Aucun étudiant dans l’historique.'
                                }}
                            </div>
                        </div>
                        <nav
                            v-if="displayedStudentLinks.length > 3"
                            class="flex max-w-full gap-1 overflow-x-auto"
                        >
                            <Link
                                v-for="link in displayedStudentLinks"
                                :key="link.label"
                                :href="link.url || '#'"
                                preserve-scroll
                                class="rounded-md border px-3 py-2 text-sm"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        link.active,
                                    'pointer-events-none opacity-40': !link.url,
                                }"
                                ><span v-html="link.label"
                            /></Link>
                        </nav>
                    </section>
                    <section
                        v-else-if="section === 'attendance'"
                        class="grid gap-4 lg:grid-cols-2"
                    >
                        <div>
                            <button
                                v-for="s in data.sessions"
                                :key="s.id"
                                class="mb-2 block w-full rounded-lg border p-3 text-left"
                                @click="choose(s)"
                            >
                                {{ s.title }} · {{ s.starts_at }}
                            </button>
                        </div>
                        <form
                            v-if="selected"
                            class="rounded-xl border p-4"
                            @submit.prevent="saveAttendance"
                        >
                            <h2 class="font-semibold">{{ selected.title }}</h2>
                            <Button
                                type="button"
                                class="mt-3"
                                variant="outline"
                                @click="
                                    records.records.forEach(
                                        (r: any) => (r.status = 'present'),
                                    )
                                "
                                >Tous présents</Button
                            >
                            <div
                                v-for="r in records.records"
                                :key="r.student_id"
                                class="mt-3 flex gap-2"
                            >
                                <span class="flex flex-1 items-center gap-2"
                                    ><img
                                        v-if="r.photo_url"
                                        :src="r.photo_url"
                                        class="size-8 rounded-full object-cover"
                                    />{{ r.student_name }}</span
                                ><select
                                    v-model="r.status"
                                    class="rounded border bg-background px-2"
                                >
                                    <option value="present">Présent</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Retard</option>
                                    <option value="excused">Excusé</option>
                                </select>
                            </div>
                            <Button class="mt-4">Enregistrer</Button>
                        </form>
                    </section>
                    <section
                        v-else-if="section === 'planning'"
                        class="space-y-5"
                    >
                        <div
                            class="flex max-w-full gap-1 overflow-x-auto rounded-xl border bg-muted/30 p-1"
                        >
                            <button
                                type="button"
                                class="flex min-w-max items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium"
                                :class="
                                    planningTab === 'upcoming'
                                        ? 'bg-background shadow-sm'
                                        : 'text-muted-foreground'
                                "
                                @click="planningTab = 'upcoming'"
                            >
                                <CalendarClock class="size-4" />À venir<span
                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700"
                                    >{{
                                        data.upcoming_sessions?.total || 0
                                    }}</span
                                ></button
                            ><button
                                type="button"
                                class="flex min-w-max items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium"
                                :class="
                                    planningTab === 'past'
                                        ? 'bg-background shadow-sm'
                                        : 'text-muted-foreground'
                                "
                                @click="planningTab = 'past'"
                            >
                                <History class="size-4" />Séances passées<span
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                    >{{ data.past_sessions?.total || 0 }}</span
                                >
                            </button>
                        </div>
                        <article
                            v-for="s in displayedPlanning"
                            :key="s.id"
                            class="overflow-hidden rounded-2xl border bg-card shadow-sm"
                        >
                            <div
                                class="h-1.5"
                                :class="
                                    planningTab === 'upcoming'
                                        ? 'bg-gradient-to-r from-blue-500 to-primary'
                                        : 'bg-gradient-to-r from-slate-400 to-slate-200'
                                "
                            ></div>
                            <div class="p-4 sm:p-5">
                                <div
                                    class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="
                                                    s.status === 'completed'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : s.status ===
                                                            'cancelled'
                                                          ? 'bg-red-100 text-red-700'
                                                          : 'bg-blue-100 text-blue-700'
                                                "
                                                >{{
                                                    s.status === 'completed'
                                                        ? 'Terminée'
                                                        : s.status ===
                                                            'cancelled'
                                                          ? 'Annulée'
                                                          : 'Planifiée'
                                                }}</span
                                            ><span
                                                v-if="
                                                    s.portal_details
                                                        ?.is_replacement
                                                "
                                                class="rounded-full bg-violet-100 px-2.5 py-1 text-xs text-violet-700"
                                                >Remplacement</span
                                            >
                                        </div>
                                        <h2
                                            class="mt-3 text-lg font-semibold break-words"
                                        >
                                            {{ s.title }}
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                s.group?.plan?.level?.course
                                                    ?.title
                                            }}
                                            · {{ s.group?.plan?.level?.name }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ s.group?.plan?.title }} ·
                                            {{ s.group?.name }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-xl bg-muted/40 p-3 md:min-w-60"
                                    >
                                        <p class="font-medium capitalize">
                                            {{ planningDate(s.starts_at) }}
                                        </p>
                                        <p
                                            class="mt-1 flex items-center gap-2 text-sm text-primary"
                                        >
                                            <Clock3 class="size-4" />{{
                                                planningTime(s.starts_at)
                                            }}
                                            – {{ planningTime(s.ends_at) }} ·
                                            {{ duration(s) }} min
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <div class="flex gap-2 text-sm">
                                        <DoorOpen
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                        /><span
                                            ><small
                                                class="block text-muted-foreground"
                                                >Salle</small
                                            ><b>{{
                                                s.classroom?.name || 'À définir'
                                            }}</b
                                            ><small
                                                v-if="s.classroom?.location"
                                                class="block text-muted-foreground"
                                                >{{
                                                    s.classroom.location
                                                }}</small
                                            ></span
                                        >
                                    </div>
                                    <div class="flex gap-2 text-sm">
                                        <MapPin
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                        /><span
                                            ><small
                                                class="block text-muted-foreground"
                                                >Site</small
                                            ><b>{{
                                                s.classroom?.site?.name ||
                                                'Non renseigné'
                                            }}</b></span
                                        >
                                    </div>
                                    <div class="flex gap-2 text-sm">
                                        <GraduationCap
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                        /><span
                                            ><small
                                                class="block text-muted-foreground"
                                                >Étudiants</small
                                            ><b
                                                >{{
                                                    s.portal_details
                                                        ?.student_count || 0
                                                }}
                                                inscrit(s)</b
                                            ></span
                                        >
                                    </div>
                                    <div class="flex gap-2 text-sm">
                                        <CheckCircle2
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                        /><span
                                            ><small
                                                class="block text-muted-foreground"
                                                >Feuille étudiants</small
                                            ><b>{{
                                                s.attendance_status ===
                                                'validated'
                                                    ? 'Validée'
                                                    : s.attendance_status ===
                                                        'completed'
                                                      ? 'Enregistrée'
                                                      : 'En attente'
                                            }}</b></span
                                        >
                                    </div>
                                </div>
                                <div
                                    v-if="planningTab === 'past'"
                                    class="mt-4 grid gap-3 rounded-xl border bg-muted/20 p-4 sm:grid-cols-2"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-medium text-muted-foreground uppercase"
                                        >
                                            Ma présence
                                        </p>
                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="rounded-full px-2.5 py-1 text-sm font-semibold"
                                                :class="
                                                    [
                                                        'present',
                                                        'late',
                                                        'replaced',
                                                    ].includes(
                                                        s.portal_details
                                                            ?.attendance_status,
                                                    )
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : s.portal_details
                                                                ?.attendance_status ===
                                                            'absent'
                                                          ? 'bg-red-100 text-red-700'
                                                          : 'bg-amber-100 text-amber-700'
                                                "
                                                >{{
                                                    attendanceLabels[
                                                        s.portal_details
                                                            ?.attendance_status
                                                    ] ||
                                                    s.portal_details
                                                        ?.attendance_status
                                                }}</span
                                            ><span
                                                class="text-sm text-muted-foreground"
                                                >{{
                                                    s.portal_details
                                                        ?.attendance_validated
                                                        ? 'Présence validée'
                                                        : 'Non validée'
                                                }}
                                                ·
                                                {{
                                                    Math.round(
                                                        ((s.portal_details
                                                            ?.worked_minutes ||
                                                            0) /
                                                            60) *
                                                            100,
                                                    ) / 100
                                                }}
                                                h comptée(s)</span
                                            >
                                        </div>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-medium text-muted-foreground uppercase"
                                        >
                                            État de paiement
                                        </p>
                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-2"
                                        >
                                            <WalletCards class="size-4" /><span
                                                class="rounded-full px-2.5 py-1 text-sm font-semibold"
                                                :class="
                                                    s.portal_details
                                                        ?.salary_status ===
                                                    'paid'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : s.portal_details
                                                                ?.salary_status ===
                                                            'partially_paid'
                                                          ? 'bg-blue-100 text-blue-700'
                                                          : s.portal_details
                                                                  ?.salary_status ===
                                                              'unpaid'
                                                            ? 'bg-amber-100 text-amber-700'
                                                            : 'bg-muted text-muted-foreground'
                                                "
                                                >{{
                                                    salaryLabels[
                                                        s.portal_details
                                                            ?.salary_status
                                                    ]
                                                }}</span
                                            ><Link
                                                v-if="
                                                    s.portal_details
                                                        ?.salary_statement_id
                                                "
                                                href="/my/salary"
                                                class="text-sm text-primary underline"
                                                >Voir ma paie</Link
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <p
                                        v-if="s.notes"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ s.notes }}
                                    </p>
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                        class="sm:ml-auto"
                                        ><Link
                                            :href="`/admin/planifications/${s.group.plan.id}`"
                                            >Voir la planification</Link
                                        ></Button
                                    >
                                </div>
                            </div>
                        </article>
                        <div
                            v-if="!displayedPlanning.length"
                            class="rounded-2xl border border-dashed p-12 text-center text-muted-foreground"
                        >
                            <CalendarClock class="mx-auto mb-3 size-9" />
                            <p>
                                {{
                                    planningTab === 'upcoming'
                                        ? 'Aucune séance à venir.'
                                        : 'Aucune séance passée.'
                                }}
                            </p>
                        </div>
                        <nav
                            v-if="displayedPlanningLinks.length > 3"
                            class="flex max-w-full gap-1 overflow-x-auto"
                        >
                            <Link
                                v-for="link in displayedPlanningLinks"
                                :key="link.label"
                                :href="link.url || '#'"
                                preserve-scroll
                                class="rounded-md border px-3 py-2 text-sm"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        link.active,
                                    'pointer-events-none opacity-40': !link.url,
                                }"
                                ><span v-html="link.label"
                            /></Link>
                        </nav>
                    </section>
                    <section v-else>
                        <button
                            v-for="n in data.notifications"
                            :key="n.id"
                            class="block w-full rounded-lg border p-4 text-left"
                            @click="read(n)"
                        >
                            <b>{{ n.title }}</b>
                            <p>{{ n.message }}</p>
                        </button>
                    </section></template
                >
            </div>
        </main></AdminLayout
    >
</template>
