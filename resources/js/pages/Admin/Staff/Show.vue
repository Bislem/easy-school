<script setup lang="ts">
import BadgeCard from '@/components/BadgeCard.vue';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarRange,
    CreditCard,
    FileText,
    History,
    Pencil,
    UserRoundCheck,
    WalletCards,
} from 'lucide-vue-next';

const props = defineProps<{ employee: any }>();
const page = usePage();
const status: Record<string, string> = {
    active: 'Actif',
    inactive: 'Inactif',
    on_leave: 'En congé',
    terminated: 'Fin de contrat',
};
const salaryTypes: Record<string, string> = {
    monthly: 'Mensuel fixe',
    hourly: 'Horaire',
    per_session: 'Par séance',
    daily: 'Journalier',
    custom: 'Manuel',
};
const money = (value: string | number) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value));
const modules = [
    { name: 'Badge', icon: CreditCard },
    { name: 'Salaires', icon: WalletCards },
    { name: 'Présences', icon: UserRoundCheck },
    { name: 'Paiements', icon: WalletCards },
    { name: 'Documents', icon: FileText },
    { name: 'Historique', icon: History },
    ...(props.employee.is_teacher
        ? [{ name: 'Planification', icon: CalendarRange }]
        : []),
];
</script>

<template>
    <Head :title="employee.name" />
    <AdminLayout>
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <div class="flex items-center justify-between gap-3">
                    <Button as-child variant="ghost"
                        ><Link href="/admin/staff"
                            ><ArrowLeft class="mr-2 size-4" />Personnel</Link
                        ></Button
                    >
                    <Button as-child
                        ><Link :href="`/admin/staff/${employee.id}/edit`"
                            ><Pencil class="mr-2 size-4" />Modifier</Link
                        ></Button
                    >
                </div>
                <section class="rounded-xl border bg-card p-6">
                    <div class="flex flex-col gap-5 sm:flex-row">
                        <img
                            v-if="employee.photo_url"
                            :src="employee.photo_url"
                            class="size-24 rounded-xl object-cover"
                        />
                        <div
                            v-else
                            class="grid size-24 place-items-center rounded-xl bg-muted text-2xl font-semibold"
                        >
                            {{ employee.first_name[0]
                            }}{{ employee.last_name[0] }}
                        </div>
                        <div class="flex-1">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h1 class="text-2xl font-semibold">
                                        {{ employee.name }}
                                    </h1>
                                    <p class="text-muted-foreground">
                                        {{ employee.employee_type.name }} ·
                                        {{ employee.employee_code }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full px-3 py-1 text-sm"
                                    :class="
                                        employee.employment_status === 'active'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    >{{
                                        status[employee.employment_status]
                                    }}</span
                                >
                            </div>
                            <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                                <p>
                                    <span class="text-muted-foreground"
                                        >E-mail :</span
                                    >
                                    {{ employee.email || '—' }}
                                </p>
                                <p>
                                    <span class="text-muted-foreground"
                                        >Téléphone :</span
                                    >
                                    {{ employee.phone || '—' }}
                                </p>
                                <p>
                                    <span class="text-muted-foreground"
                                        >Embauche :</span
                                    >
                                    {{ employee.hire_date || '—' }}
                                </p>
                                <p>
                                    <span class="text-muted-foreground"
                                        >Connexion :</span
                                    >
                                    {{
                                        employee.user?.can_login
                                            ? 'Autorisée'
                                            : 'Non autorisée'
                                    }}
                                </p>
                                <p class="sm:col-span-2">
                                    <span class="text-muted-foreground"
                                        >Adresse :</span
                                    >
                                    {{ employee.address || '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl border bg-card p-5 lg:col-span-2">
                        <div class="flex items-center justify-between"><h2 class="font-semibold">Présences et heures validées</h2><Button as-child size="sm" variant="outline"><Link href="/admin/attendance">Ouvrir le pointage</Link></Button></div>
                        <div v-if="employee.is_teacher && employee.teaching_stats" class="mt-4 grid gap-3 sm:grid-cols-4 lg:grid-cols-7"><div v-for="item in [{l:'Planifiées',v:employee.teaching_stats.planned},{l:'Terminées',v:employee.teaching_stats.completed},{l:'Absences',v:employee.teaching_stats.absent},{l:'Retards',v:employee.teaching_stats.late},{l:'Remplacements',v:employee.teaching_stats.replacements},{l:'Heures prévues',v:employee.teaching_stats.planned_hours+'h'},{l:'Heures validées',v:employee.teaching_stats.worked_hours+'h'}]" :key="item.l" class="rounded-lg bg-muted/40 p-3"><b class="block text-lg">{{item.v}}</b><span class="text-xs text-muted-foreground">{{item.l}}</span></div></div>
                        <div v-else class="mt-4 divide-y"><div v-for="a in employee.attendances" :key="a.id" class="flex justify-between py-2 text-sm"><span>{{a.attendance_date}} · {{a.status}}</span><span>{{a.worked_minutes}} min</span></div><p v-if="!employee.attendances?.length" class="text-sm text-muted-foreground">Aucun pointage enregistré.</p></div>
                    </div>
                    <div class="rounded-xl border bg-card p-5 lg:col-span-2">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="font-semibold">Carte professionnelle</h2>
                            <Button as-child size="sm" variant="outline"
                                ><Link href="/admin/badges">Gérer</Link></Button
                            >
                        </div>
                        <BadgeCard
                            v-if="employee.badges?.[0]"
                            :badge="employee.badges[0]"
                            :school="page.props.school"
                        />
                        <p v-else class="text-sm text-muted-foreground">
                            Aucune carte professionnelle générée.
                        </p>
                        <div
                            v-if="employee.badges?.length > 1"
                            class="mt-4 text-xs text-muted-foreground"
                        >
                            Historique :
                            {{
                                employee.badges
                                    .slice(1)
                                    .map(
                                        (b) =>
                                            `${b.card_number} (${b.display_status})`,
                                    )
                                    .join(' · ')
                            }}
                        </div>
                    </div>
                    <div class="rounded-xl border bg-card p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-semibold">Salaire</h2>
                            <Button as-child size="sm" variant="outline">
                                <Link
                                    :href="`/admin/salaries?staff_id=${employee.id}`"
                                    >Gérer</Link
                                >
                            </Button>
                        </div>
                        <div
                            v-if="employee.salary_configurations?.length"
                            class="mt-4 space-y-2 text-sm"
                        >
                            <p>
                                <span class="text-muted-foreground"
                                    >Méthode :</span
                                >
                                {{
                                    salaryTypes[
                                        employee.salary_configurations[0]
                                            .salary_type
                                    ] ||
                                    employee.salary_configurations[0]
                                        .salary_type
                                }}
                            </p>
                            <p>
                                <span class="text-muted-foreground"
                                    >Taux :</span
                                >
                                {{
                                    money(
                                        employee.salary_configurations[0]
                                            .base_rate,
                                    )
                                }}
                            </p>
                            <p>
                                <span class="text-muted-foreground"
                                    >Effective depuis :</span
                                >
                                {{
                                    employee.salary_configurations[0]
                                        .effective_from
                                }}
                            </p>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">
                            Aucune configuration salariale.
                        </p>
                        <div
                            v-if="employee.salary_statements?.length"
                            class="mt-5 border-t pt-4"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                DERNIER BULLETIN
                            </p>
                            <div class="mt-2 flex justify-between text-sm">
                                <span>{{
                                    employee.salary_statements[0].reference
                                }}</span>
                                <strong>{{
                                    money(
                                        employee.salary_statements[0]
                                            .net_salary,
                                    )
                                }}</strong>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Reste :
                                {{
                                    money(
                                        employee.salary_statements[0]
                                            .remaining_amount,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">Paiements récents</h2>
                        <div
                            v-if="employee.salary_payments?.length"
                            class="mt-4 divide-y"
                        >
                            <div
                                v-for="payment in employee.salary_payments.slice(
                                    0,
                                    5,
                                )"
                                :key="payment.id"
                                class="flex items-center justify-between gap-3 py-3 text-sm"
                            >
                                <div>
                                    <p>{{ payment.reference }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ payment.paid_at }} ·
                                        {{ payment.payment_method }}
                                    </p>
                                </div>
                                <strong>{{ money(payment.amount) }}</strong>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">
                            Aucun paiement enregistré.
                        </p>
                    </div>
                </section>
                <section>
                    <h2 class="mb-3 text-lg font-semibold">
                        Modules du profil
                    </h2>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="item in modules"
                            :key="item.name"
                            class="rounded-xl border bg-card p-4"
                        >
                            <component
                                :is="item.icon"
                                class="mb-3 size-5 text-primary"
                            />
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                Fondation prête — module à compléter
                            </p>
                        </div>
                    </div>
                </section>
                <section
                    v-if="employee.notes"
                    class="rounded-xl border bg-card p-5"
                >
                    <h2 class="font-semibold">Notes</h2>
                    <p
                        class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ employee.notes }}
                    </p>
                </section>
            </div>
        </main>
    </AdminLayout>
</template>
