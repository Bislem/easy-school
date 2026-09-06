<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WilayaCommuneSelect from '@/components/WilayaCommuneSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    Building2,
    CalendarClock,
    CreditCard,
    DoorOpen,
    Download,
    GraduationCap,
    LoaderCircle,
    Save,
    Trash2,
    Users,
    WalletCards,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    school: any;
    administrator: any | null;
    stats: any;
    plans: any[];
    payments: any[];
}>();
const basePath = (usePage().props.superAdmin as any).basePath as string;
const schoolForm = useForm({
    name: props.school.name,
    email: props.school.email || '',
    phone: props.school.phone || '',
    address: props.school.address || '',
    wilaya: props.school.wilaya || '',
    commune: props.school.commune || '',
    logo: null as File | null,
});
const adminForm = useForm({
    name: props.administrator?.name || '',
    email: props.administrator?.email || '',
    phone: props.administrator?.phone || '',
    password: '',
    is_active: props.administrator?.is_active ?? true,
});
const schoolFields = [
    ['name', 'Nom'],
    ['email', 'E-mail'],
    ['phone', 'Téléphone'],
    ['address', 'Adresse'],
] as const;
const adminFields = [
    ['name', 'Nom'],
    ['email', 'E-mail'],
    ['phone', 'Téléphone'],
    ['password', 'Nouveau mot de passe'],
] as const;
const updateSchool = () =>
    schoolForm.post(`${basePath}/schools/${props.school.id}`, {
        forceFormData: true,
        preserveScroll: true,
    });
const updateAdmin = () =>
    adminForm.put(`${basePath}/schools/${props.school.id}/administrator`, {
        preserveScroll: true,
    });
const changeStatus = () =>
    router.patch(
        `${basePath}/schools/${props.school.id}/status`,
        { status: props.school.status === 'active' ? 'suspended' : 'active' },
        { preserveScroll: true },
    );
const archive = () => {
    if (confirm('Archiver cette école ?'))
        router.delete(`${basePath}/schools/${props.school.id}`);
};
const showSubscription = ref(false);
const subscriptionForm = useForm({
    action: 'renew',
    subscription_plan_id: props.school.subscription_plan_id || '',
    months: 1,
    amount: '',
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
    proof: null as File | null,
});
const manageSubscription = (action: 'renew' | 'extend' | 'change') => {
    subscriptionForm.action = action;
    subscriptionForm.subscription_plan_id =
        props.school.subscription_plan_id || '';
    showSubscription.value = true;
};
const submitSubscription = () =>
    subscriptionForm.post(
        `${basePath}/schools/${props.school.id}/subscription`,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showSubscription.value = false;
                subscriptionForm.reset('amount', 'reference', 'notes', 'proof');
            },
        },
    );
const money = (value: any, currency = 'DZD') =>
    `${new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 2 }).format(Number(value || 0))} ${currency}`;
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-DZ', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : 'Sans échéance';
const statCards = [
    ['Étudiants', props.stats.students, GraduationCap],
    ['Personnel', props.stats.staff, Users],
    ['Sites', props.stats.sites, Building2],
    ['Salles', props.stats.rooms, DoorOpen],
    ['Formations', props.stats.courses, BookOpen],
    ['Plans pédagogiques', props.stats.plans, BookOpen],
    ['Sessions', props.stats.sessions, CalendarClock],
    ['Revenu école', money(props.stats.revenue), WalletCards],
];
</script>

<template>
    <SuperAdminLayout
        :title="school.name"
        description="Configuration et accès de l'école"
    >
        <div class="mb-5 flex items-center justify-between">
            <Button variant="ghost" as-child
                ><Link :href="`${basePath}/schools`"
                    ><ArrowLeft />Retour aux écoles</Link
                ></Button
            ><Badge
                :variant="school.status === 'active' ? 'default' : 'secondary'"
                >{{
                    school.status === 'active'
                        ? 'École active'
                        : 'École suspendue'
                }}</Badge
            >
        </div>
        <section
            class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-r from-[#061b3a] to-[#08747a] p-6 text-white shadow-xl"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="grid size-16 place-items-center overflow-hidden rounded-2xl bg-white/10 text-xl font-black"
                    >
                        <img
                            v-if="school.logo_url"
                            :src="school.logo_url"
                            class="size-full object-cover"
                        /><span v-else>{{
                            school.name.slice(0, 2).toUpperCase()
                        }}</span>
                    </div>
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-cyan-200 uppercase"
                        >
                            Client #{{ school.id }}
                        </p>
                        <h2 class="text-2xl font-black">{{ school.name }}</h2>
                        <p class="text-sm text-blue-100/70">
                            {{
                                [school.commune, school.wilaya]
                                    .filter(Boolean)
                                    .join(', ') || school.email
                            }}
                        </p>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                    <p class="text-xs text-blue-100/70">Abonnement actuel</p>
                    <p class="text-lg font-black">
                        {{ school.subscription_plan?.name || 'Aucun plan' }}
                    </p>
                    <p class="text-xs text-cyan-100">
                        Expire le {{ date(school.plan_expires_at) }}
                    </p>
                </div>
            </div>
        </section>
        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="item in statCards" :key="item[0] as string"
                ><CardContent class="flex items-center gap-3 p-4"
                    ><span
                        class="grid size-10 place-items-center rounded-xl bg-teal-50 text-[#089c8d]"
                        ><component :is="item[2]" class="size-5"
                    /></span>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{ item[0] }}
                        </p>
                        <p class="text-xl font-black">{{ item[1] }}</p>
                    </div></CardContent
                ></Card
            >
        </div>
        <div class="mb-6 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
            <Card
                ><CardHeader><CardTitle>Abonnement</CardTitle></CardHeader
                ><CardContent
                    ><div class="rounded-2xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Plan actuel
                                </p>
                                <p class="text-xl font-black">
                                    {{
                                        school.subscription_plan?.name ||
                                        'Non attribué'
                                    }}
                                </p>
                            </div>
                            <CreditCard class="size-8 text-[#089c8d]" />
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-muted-foreground">Début</p>
                                <b>{{ date(school.plan_started_at) }}</b>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Échéance</p>
                                <b>{{ date(school.plan_expires_at) }}</b>
                            </div>
                            <div>
                                <p class="text-muted-foreground">
                                    Total encaissé
                                </p>
                                <b>{{ money(stats.subscription_paid) }}</b>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Type</p>
                                <b>{{
                                    school.account_type === 'demo'
                                        ? 'Démonstration'
                                        : 'Client payant'
                                }}</b>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button size="sm" @click="manageSubscription('renew')"
                            >Renouveler</Button
                        ><Button
                            size="sm"
                            variant="outline"
                            @click="manageSubscription('extend')"
                            >Prolonger</Button
                        ><Button
                            size="sm"
                            variant="outline"
                            @click="manageSubscription('change')"
                            >Changer de plan</Button
                        ><Button
                            size="sm"
                            :variant="
                                school.status === 'active'
                                    ? 'destructive'
                                    : 'default'
                            "
                            @click="changeStatus"
                            >{{
                                school.status === 'active'
                                    ? 'Suspendre'
                                    : 'Réactiver'
                            }}</Button
                        >
                    </div></CardContent
                ></Card
            >
            <Card
                ><CardHeader
                    ><CardTitle>Paiements d’abonnement</CardTitle></CardHeader
                ><CardContent
                    ><div class="max-h-80 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-muted-foreground"
                                >
                                    <th class="pb-3">Date</th>
                                    <th>Plan</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="payment in payments"
                                    :key="payment.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3">
                                        {{ date(payment.paid_at) }}
                                    </td>
                                    <td>{{ payment.plan?.name || '—' }}</td>
                                    <td class="capitalize">
                                        {{ payment.type }}
                                    </td>
                                    <td class="font-bold">
                                        {{
                                            money(
                                                payment.amount,
                                                payment.currency,
                                            )
                                        }}
                                    </td>
                                    <td class="text-right">
                                        <Button
                                            v-if="payment.proof_path"
                                            size="icon"
                                            variant="ghost"
                                            as-child
                                            ><a
                                                :href="`${basePath}/schools/${school.id}/payments/${payment.id}/proof`"
                                                ><Download class="size-4" /></a
                                        ></Button>
                                    </td>
                                </tr>
                                <tr v-if="!payments.length">
                                    <td
                                        colspan="5"
                                        class="py-10 text-center text-muted-foreground"
                                    >
                                        Aucun paiement enregistré.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div></CardContent
                ></Card
            >
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <Card
                ><CardHeader
                    ><CardTitle>Informations de l'école</CardTitle></CardHeader
                ><CardContent
                    ><form @submit.prevent="updateSchool">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-for="field in schoolFields" :key="field[0]">
                                <Label :for="`school-${field[0]}`">{{
                                    field[1]
                                }}</Label
                                ><Input
                                    :id="`school-${field[0]}`"
                                    v-model="(schoolForm as any)[field[0]]"
                                    :type="
                                        field[0] === 'email' ? 'email' : 'text'
                                    "
                                /><InputError
                                    :message="
                                        (schoolForm.errors as any)[field[0]]
                                    "
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <WilayaCommuneSelect
                                    v-model:wilaya="schoolForm.wilaya"
                                    v-model:commune="schoolForm.commune"
                                    :wilaya-error="schoolForm.errors.wilaya"
                                    :commune-error="schoolForm.errors.commune"
                                    compact
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <Label for="school-logo">Logo</Label
                                ><Input
                                    id="school-logo"
                                    type="file"
                                    accept="image/*"
                                    @change="
                                        schoolForm.logo =
                                            ($event.target as HTMLInputElement)
                                                .files?.[0] || null
                                    "
                                />
                            </div>
                        </div>
                        <Button class="mt-5" :disabled="schoolForm.processing"
                            ><LoaderCircle
                                v-if="schoolForm.processing"
                                class="animate-spin"
                            /><Save v-else />Enregistrer</Button
                        >
                    </form></CardContent
                ></Card
            >
            <Card
                ><CardHeader
                    ><CardTitle>Administrateur principal</CardTitle></CardHeader
                ><CardContent
                    ><form @submit.prevent="updateAdmin">
                        <div class="space-y-4">
                            <div v-for="field in adminFields" :key="field[0]">
                                <Label :for="`admin-${field[0]}`">{{
                                    field[1]
                                }}</Label
                                ><Input
                                    :id="`admin-${field[0]}`"
                                    v-model="(adminForm as any)[field[0]]"
                                    :type="
                                        field[0] === 'password'
                                            ? 'password'
                                            : field[0] === 'email'
                                              ? 'email'
                                              : 'text'
                                    "
                                /><InputError
                                    :message="
                                        (adminForm.errors as any)[field[0]]
                                    "
                                />
                            </div>
                            <label class="flex items-center gap-3"
                                ><Checkbox
                                    v-model="adminForm.is_active"
                                />Compte actif</label
                            >
                        </div>
                        <Button class="mt-5" :disabled="adminForm.processing"
                            ><Save />Enregistrer l'administrateur</Button
                        >
                    </form></CardContent
                ></Card
            >
        </div>
        <Card class="mt-6"
            ><CardContent
                class="flex flex-col items-start justify-between gap-4 p-5 sm:flex-row sm:items-center"
                ><div>
                    <p class="font-bold">Actions sur l'école</p>
                    <p class="text-sm text-muted-foreground">
                        Suspendez temporairement l'accès ou archivez
                        définitivement l'espace.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="changeStatus">{{
                        school.status === 'active' ? 'Suspendre' : 'Réactiver'
                    }}</Button
                    ><Button variant="destructive" @click="archive"
                        ><Trash2 />Archiver</Button
                    >
                </div></CardContent
            ></Card
        >
        <Dialog v-model:open="showSubscription"
            ><DialogContent class="max-w-xl"
                ><DialogHeader
                    ><DialogTitle>Gérer l’abonnement</DialogTitle
                    ><DialogDescription
                        >Renouvelez, prolongez ou changez l’offre de
                        {{ school.name }}. Un règlement peut être enregistré
                        avec sa preuve.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="submitSubscription">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="option in [
                                ['renew', 'Renouveler'],
                                ['extend', 'Prolonger'],
                                ['change', 'Changer'],
                            ]"
                            :key="option[0]"
                            type="button"
                            class="rounded-xl border p-3 text-sm font-bold"
                            :class="
                                subscriptionForm.action === option[0]
                                    ? 'border-[#12cbb2] bg-teal-50 text-teal-800'
                                    : ''
                            "
                            @click="subscriptionForm.action = option[0] as any"
                        >
                            {{ option[1] }}
                        </button>
                    </div>
                    <div>
                        <Label>Plan *</Label
                        ><select
                            v-model="subscriptionForm.subscription_plan_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                            required
                        >
                            <option value="">Sélectionner</option>
                            <option
                                v-for="plan in plans"
                                :key="plan.id"
                                :value="plan.id"
                            >
                                {{ plan.name }} ·
                                {{ money(plan.price, plan.currency) }}
                            </option></select
                        ><InputError
                            :message="
                                subscriptionForm.errors.subscription_plan_id
                            "
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Durée en mois *</Label
                            ><Input
                                v-model="subscriptionForm.months"
                                type="number"
                                min="1"
                                max="60"
                                required
                            />
                        </div>
                        <div>
                            <Label>Montant encaissé</Label
                            ><Input
                                v-model="subscriptionForm.amount"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                        </div>
                        <div>
                            <Label>Moyen de paiement</Label
                            ><select
                                v-model="subscriptionForm.payment_method"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                            >
                                <option value="bank_transfer">Virement</option>
                                <option value="cash">Espèces</option>
                                <option value="cheque">Chèque</option>
                                <option value="card">Carte</option>
                            </select>
                        </div>
                        <div>
                            <Label>Référence</Label
                            ><Input v-model="subscriptionForm.reference" />
                        </div>
                        <div class="sm:col-span-2">
                            <Label>Preuve de paiement</Label
                            ><Input
                                type="file"
                                accept=".pdf,image/*"
                                @change="
                                    subscriptionForm.proof =
                                        ($event.target as HTMLInputElement)
                                            .files?.[0] || null
                                "
                            /><InputError
                                :message="subscriptionForm.errors.proof"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label>Notes</Label
                            ><textarea
                                v-model="subscriptionForm.notes"
                                rows="3"
                                class="mt-1 w-full rounded-md border bg-background p-3"
                            />
                        </div>
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="showSubscription = false"
                            >Annuler</Button
                        ><Button
                            :disabled="subscriptionForm.processing"
                            class="bg-[#12cbb2] text-[#052b3f] hover:bg-[#38dfc9]"
                            >Enregistrer l’abonnement</Button
                        ></DialogFooter
                    >
                </form></DialogContent
            ></Dialog
        >
    </SuperAdminLayout>
</template>
