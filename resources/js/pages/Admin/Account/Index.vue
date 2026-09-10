<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    CalendarClock,
    Check,
    Database,
    HardDrive,
    PackageCheck,
    School,
} from 'lucide-vue-next';

defineProps<{ account: any; storage: any; plan: any | null }>();
const bytes = (value: number | null) => {
    if (value === null) return 'Illimité';
    if (!value) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const power = Math.min(
        Math.floor(Math.log(value) / Math.log(1024)),
        units.length - 1,
    );
    return `${(value / 1024 ** power).toFixed(power > 1 ? 1 : 0)} ${units[power]}`;
};
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-DZ', { dateStyle: 'long' }).format(
              new Date(value),
          )
        : 'Sans échéance';
const labels: Record<string, string> = {
    active: 'Actif',
    suspended: 'Suspendu',
    expired: 'Expiré',
    demo: 'Démonstration',
};
const categoryLabels: Record<string, string> = {
    student_documents: 'Documents étudiants',
    employee_hr_documents: 'Documents RH',
    profile_images: 'Images et profils',
    certificates_report_cards: 'Certificats et bulletins',
    attachments: 'Pièces jointes',
    other: 'Autres',
};
const limitLabels: Record<string, string> = {
    students: 'Étudiants',
    teachers: 'Enseignants',
    staff: 'Employés',
    sites: 'Sites',
    users: 'Utilisateurs',
    courses: 'Formations',
    storage_bytes: 'Stockage',
};
</script>

<template>
    <AdminLayout
        ><Head title="Compte / Abonnement" />
        <main class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <header>
                <p class="text-sm font-bold text-primary">MON ÉTABLISSEMENT</p>
                <h1 class="text-3xl font-black">Compte / Abonnement</h1>
                <p class="mt-1 text-muted-foreground">
                    Consultez votre formule, son échéance et votre consommation.
                </p>
            </header>
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card
                    ><CardContent class="flex items-center gap-4 p-5"
                        ><School class="size-8 text-primary" />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Établissement
                            </p>
                            <b>{{ account.name }}</b>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4 p-5"
                        ><PackageCheck class="size-8 text-primary" />
                        <div>
                            <p class="text-xs text-muted-foreground">Formule</p>
                            <b>{{
                                plan?.name ||
                                (account.account_type === 'demo'
                                    ? 'Démonstration'
                                    : 'Aucune')
                            }}</b>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4 p-5"
                        ><CalendarClock class="size-8 text-primary" />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Échéance
                            </p>
                            <b>{{ date(account.expires_at) }}</b>
                            <p
                                v-if="account.remaining_days !== null"
                                class="text-xs text-muted-foreground"
                            >
                                {{ account.remaining_days }} jour(s) restant(s)
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4 p-5"
                        ><Database class="size-8 text-primary" />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                État du compte
                            </p>
                            <Badge class="mt-1">{{
                                labels[account.status]
                            }}</Badge>
                        </div></CardContent
                    ></Card
                >
            </section>
            <section class="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
                <Card
                    ><CardHeader
                        ><CardTitle class="flex items-center gap-2"
                            ><HardDrive class="size-5" />Stockage</CardTitle
                        ></CardHeader
                    ><CardContent>
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p class="text-3xl font-black">
                                    {{ bytes(storage.used_bytes) }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    utilisés sur
                                    {{ bytes(storage.limit_bytes) }}
                                </p>
                            </div>
                            <b
                                class="text-2xl"
                                :class="
                                    storage.percentage >= 90
                                        ? 'text-red-600'
                                        : storage.percentage >= 75
                                          ? 'text-amber-600'
                                          : 'text-emerald-600'
                                "
                                >{{ storage.percentage }}%</b
                            >
                        </div>
                        <div
                            class="mt-4 h-4 overflow-hidden rounded-full bg-slate-100"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :class="
                                    storage.percentage >= 90
                                        ? 'bg-red-500'
                                        : storage.percentage >= 75
                                          ? 'bg-amber-500'
                                          : 'bg-emerald-500'
                                "
                                :style="{
                                    width: `${Math.min(storage.percentage, 100)}%`,
                                }"
                            />
                        </div>
                        <div class="mt-6 space-y-3">
                            <div
                                v-for="item in storage.breakdown"
                                :key="item.category"
                                class="flex justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm"
                            >
                                <span
                                    >{{
                                        categoryLabels[item.category] ||
                                        item.category
                                    }}
                                    <small class="text-muted-foreground"
                                        >({{ item.files }})</small
                                    ></span
                                ><b>{{ bytes(item.bytes) }}</b>
                            </div>
                            <p
                                v-if="!storage.breakdown.length"
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                Aucun fichier enregistré.
                            </p>
                        </div>
                    </CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Limites et fonctionnalités</CardTitle
                        ></CardHeader
                    ><CardContent class="space-y-5"
                        ><p
                            v-if="plan?.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ plan.description }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <div
                                v-for="(value, key) in plan?.limits || {}"
                                :key="key"
                                class="rounded-xl border p-3"
                            >
                                <p class="text-xs text-muted-foreground">
                                    {{ limitLabels[String(key)] || key }}
                                </p>
                                <b>{{
                                    key === 'storage_bytes'
                                        ? bytes(Number(value))
                                        : value
                                }}</b>
                            </div>
                        </div>
                        <div v-if="plan?.features?.length" class="space-y-2">
                            <p class="font-bold">Modules inclus</p>
                            <p
                                v-for="feature in plan.features"
                                :key="feature"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Check class="size-4 text-emerald-600" />{{
                                    feature
                                }}
                            </p>
                        </div></CardContent
                    ></Card
                >
            </section>
        </main>
    </AdminLayout>
</template>
