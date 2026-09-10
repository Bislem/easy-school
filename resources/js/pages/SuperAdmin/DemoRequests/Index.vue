<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WilayaCommuneSelect from '@/components/WilayaCommuneSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarPlus,
    CheckCircle2,
    CirclePause,
    Clock3,
    Eye,
    Filter,
    KeyRound,
    Mail,
    MapPin,
    Plus,
    RefreshCw,
    Search,
    Sparkles,
    UsersRound,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    requests: any;
    filters: any;
    counts: Record<string, number>;
    plans: Array<{
        id: number;
        name: string;
        price: string | number;
        currency: string;
        billing_period: string;
    }>;
}>();
const base = (usePage().props.superAdmin as any).basePath;
const status = ref(props.filters.status || '');
const search = ref('');
const selected = ref<any>(null);
const action = ref<'details' | 'approve' | 'reject' | ''>('');
const showCreate = ref(false);
const converting = ref<any>(null);
const approval = useForm({ days: 15 });
const rejection = useForm({ reason: '' });
const conversion = useForm({
    action: 'change',
    subscription_plan_id: '',
    months: 1,
    amount: '',
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
});
const credentialTarget = ref<any>(null);
const credentials = useForm({ delivery_email: '' });
const openCredentials = (item: any) => {
    credentialTarget.value = item;
    credentials.clearErrors();
    credentials.delivery_email = item.email || '';
};
const regenerateCredentials = () =>
    credentials.post(
        `${base}/schools/${credentialTarget.value.tenant.id}/credentials`,
        {
            preserveScroll: true,
            onSuccess: () => {
                credentialTarget.value = null;
                credentials.reset();
            },
        },
    );
const manual = useForm({
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
    students_count: 0,
    teachers_count: 0,
    staff_count: 0,
    sites_count: 1,
    requested_days: 15,
    needs: '',
    modules: [] as string[],
});

const moduleLabels: Record<string, string> = {
    students: 'Gestion des étudiants',
    hr: 'Ressources humaines',
    multi_sites: 'Multi-sites & campus',
    courses: 'Formations & cours',
    planning: 'Plannings',
    certificates: 'Certificats',
    badges: 'Badges',
    mobile: 'Application mobile',
    finance: 'Paiements & finances',
    reports: 'Rapports & statistiques',
};
const moduleOptions = Object.entries(moduleLabels);
const filtered = computed(() =>
    !search.value.trim()
        ? props.requests.data
        : props.requests.data.filter((r: any) =>
              `${r.school_name} ${r.contact_name} ${r.email} ${r.wilaya}`
                  .toLowerCase()
                  .includes(search.value.toLowerCase()),
          ),
);
const statusMeta: Record<string, { label: string; class: string }> = {
    pending: { label: 'En attente', class: 'bg-amber-100 text-amber-800' },
    approved: { label: 'Active', class: 'bg-emerald-100 text-emerald-800' },
    rejected: { label: 'Refusée', class: 'bg-red-100 text-red-700' },
    suspended: { label: 'Suspendue', class: 'bg-slate-200 text-slate-700' },
};
const typeLabel = (value: string) =>
    ({
        private_school: 'École privée',
        training_center: 'Centre de formation',
        language_school: 'École de langues',
        other: 'Autre',
    })[value] || value;
const open = (type: typeof action.value, item: any) => {
    selected.value = item;
    action.value = type;
    approval.days = Math.max(Number(item.requested_days) || 15, 1);
};
const close = () => {
    selected.value = null;
    action.value = '';
    rejection.reset();
};
const filter = () =>
    router.get(
        `${base}/demo-requests`,
        { status: status.value || undefined },
        { preserveState: true },
    );
const approve = () =>
    approval.post(`${base}/demo-requests/${selected.value.id}/approve`, {
        onSuccess: close,
    });
const reject = () =>
    rejection.post(`${base}/demo-requests/${selected.value.id}/reject`, {
        onSuccess: close,
    });
const extend = (item: any) =>
    confirm(`Ajouter 15 jours à la démonstration de ${item.school_name} ?`) &&
    router.patch(`${base}/demo-requests/${item.id}/extend`);
const suspend = (item: any) =>
    confirm(
        `Suspendre immédiatement la démonstration de ${item.school_name} ?`,
    ) && router.patch(`${base}/demo-requests/${item.id}/suspend`);
const openConversion = (item: any) => {
    converting.value = item;
    conversion.reset();
    conversion.action = 'change';
};
const convertToPaid = () =>
    conversion.post(
        `${base}/schools/${converting.value.tenant.id}/subscription`,
        {
            onSuccess: () => {
                converting.value = null;
            },
        },
    );
const toggleManualModule = (value: string) =>
    (manual.modules = manual.modules.includes(value)
        ? manual.modules.filter((x) => x !== value)
        : [...manual.modules, value]);
const createManual = () =>
    manual.post(`${base}/demo-requests`, {
        onSuccess: () => {
            showCreate.value = false;
            manual.reset();
        },
    });
</script>

<template>
    <SuperAdminLayout
        title="Comptes de démonstration"
        description="Créez, validez et pilotez les accès d'essai de la plateforme"
    >
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card
                v-for="item in [
                    [
                        Clock3,
                        'pending',
                        'En attente',
                        'from-amber-50 to-white',
                        'text-amber-600',
                    ],
                    [
                        CheckCircle2,
                        'approved',
                        'Actives',
                        'from-emerald-50 to-white',
                        'text-emerald-600',
                    ],
                    [
                        CirclePause,
                        'suspended',
                        'Suspendues',
                        'from-slate-100 to-white',
                        'text-slate-600',
                    ],
                    [
                        XCircle,
                        'rejected',
                        'Refusées',
                        'from-red-50 to-white',
                        'text-red-500',
                    ],
                ]"
                :key="item[1] as string"
                class="overflow-hidden"
                ><CardContent
                    class="flex items-center justify-between bg-gradient-to-br p-5"
                    :class="item[3]"
                    ><div>
                        <p
                            class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ item[2] }}
                        </p>
                        <p class="mt-2 text-3xl font-black">
                            {{ counts[item[1] as string] || 0 }}
                        </p>
                    </div>
                    <span
                        class="grid size-12 place-items-center rounded-2xl bg-white shadow-sm"
                        ><component
                            :is="item[0]"
                            class="size-6"
                            :class="item[4]" /></span></CardContent
            ></Card>
        </div>

        <div
            class="my-6 flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    /><Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Rechercher une école, un contact, un email…"
                    />
                </div>
                <div class="relative">
                    <Filter
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    /><select
                        v-model="status"
                        class="h-9 rounded-md border bg-background pr-8 pl-9 text-sm"
                        @change="filter"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="pending">En attente</option>
                        <option value="approved">Actives</option>
                        <option value="suspended">Suspendues</option>
                        <option value="rejected">Refusées</option>
                    </select>
                </div>
            </div>
            <Button class="gap-2" @click="showCreate = true"
                ><Plus class="size-4" />Créer un compte démo</Button
            >
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card
                v-for="r in filtered"
                :key="r.id"
                class="overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md"
                ><CardContent class="p-0"
                    ><div
                        class="flex items-start justify-between border-b bg-muted/20 p-5"
                    >
                        <div class="flex gap-3">
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                                ><Building2 class="size-5"
                            /></span>
                            <div>
                                <h2 class="font-black">{{ r.school_name }}</h2>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ typeLabel(r.school_type) }} ·
                                    {{ r.commune }}, {{ r.wilaya }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-black"
                            :class="statusMeta[r.status]?.class"
                            >{{ statusMeta[r.status]?.label }}</span
                        >
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <div class="space-y-2 text-xs">
                            <p class="flex gap-2">
                                <UsersRound class="size-4 text-primary" /><b>{{
                                    r.contact_name
                                }}</b>
                                · {{ r.contact_role }}
                            </p>
                            <p class="flex gap-2 text-muted-foreground">
                                <Mail class="size-4" />{{ r.email }}
                            </p>
                            <p class="flex gap-2 text-muted-foreground">
                                <MapPin class="size-4" />{{ r.address }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <span class="rounded-lg bg-muted p-2"
                                ><b class="block text-lg">{{
                                    r.students_count || 0
                                }}</b
                                ><small>Étudiants</small></span
                            ><span class="rounded-lg bg-muted p-2"
                                ><b class="block text-lg">{{
                                    r.sites_count || 0
                                }}</b
                                ><small>Sites</small></span
                            >
                        </div>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4"
                    >
                        <div class="text-xs">
                            <span v-if="r.tenant?.demo_expires_at"
                                ><Clock3
                                    class="mr-1 inline size-4 text-primary"
                                />Expire le
                                <b>{{
                                    new Date(
                                        r.tenant.demo_expires_at,
                                    ).toLocaleDateString('fr-DZ')
                                }}</b></span
                            ><span v-else class="text-muted-foreground"
                                >Demandé pour {{ r.requested_days }} jours</span
                            >
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="open('details', r)"
                                ><Eye />Détails</Button
                            ><template v-if="r.status === 'pending'"
                                ><Button size="sm" @click="open('approve', r)"
                                    >Approuver</Button
                                ><Button
                                    size="sm"
                                    variant="destructive"
                                    @click="open('reject', r)"
                                    >Refuser</Button
                                ></template
                            ><template v-else-if="r.tenant"
                                ><Button
                                    size="sm"
                                    variant="outline"
                                    @click="openCredentials(r)"
                                    ><KeyRound />Régénérer les accès</Button
                                ><Button
                                    v-if="r.tenant.account_type === 'demo'"
                                    size="sm"
                                    @click="openConversion(r)"
                                    >Convertir en client</Button
                                ><Button
                                    v-if="r.tenant.account_type === 'demo'"
                                    size="sm"
                                    variant="outline"
                                    @click="extend(r)"
                                    ><CalendarPlus />+15 jours</Button
                                ><Button
                                    v-if="
                                        r.status === 'approved' &&
                                        r.tenant.account_type === 'demo'
                                    "
                                    size="sm"
                                    variant="outline"
                                    class="text-amber-700"
                                    @click="suspend(r)"
                                    ><CirclePause />Suspendre</Button
                                ></template
                            >
                        </div>
                    </div></CardContent
                ></Card
            >
        </div>
        <Card v-if="!filtered.length"
            ><CardContent class="p-14 text-center text-muted-foreground"
                ><Sparkles class="mx-auto mb-3 size-9" />Aucun compte de
                démonstration ne correspond à votre recherche.</CardContent
            ></Card
        >
        <div v-if="requests.links?.length" class="mt-5 flex flex-wrap gap-1">
            <Button
                v-for="link in requests.links"
                :key="link.label"
                size="sm"
                variant="outline"
                :disabled="!link.url || link.active"
                @click="link.url && router.get(link.url)"
                v-html="link.label"
            />
        </div>

        <div
            v-if="credentialTarget"
            class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            @click.self="credentialTarget = null"
        >
            <Card class="w-full max-w-md"
                ><CardContent class="p-6"
                    ><h2 class="text-xl font-black">
                        Régénérer les identifiants
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Le nouveau mot de passe remplacera l’ancien pour
                        {{ credentialTarget.school_name }}.
                    </p>
                    <form
                        class="mt-5 space-y-4"
                        @submit.prevent="regenerateCredentials"
                    >
                        <div>
                            <label class="mb-1 block text-sm font-bold"
                                >Adresse de livraison</label
                            ><Input
                                v-model="credentials.delivery_email"
                                type="email"
                                required
                            /><InputError
                                :message="credentials.errors.delivery_email"
                            />
                            <p class="mt-2 text-xs text-muted-foreground">
                                Cette adresse reçoit les accès sans modifier
                                l’identifiant de connexion du client.
                            </p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="credentialTarget = null"
                                >Annuler</Button
                            ><Button :disabled="credentials.processing"
                                >Régénérer et envoyer</Button
                            >
                        </div>
                    </form></CardContent
                ></Card
            >
        </div>

        <div
            v-if="converting"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
            @click.self="converting = null"
        >
            <Card class="w-full max-w-xl">
                <CardContent class="p-6">
                    <h2 class="text-xl font-black">
                        Convertir en client payant
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ converting.school_name }} conservera toutes ses
                        données. L’accès aux paramètres de l’école sera
                        déverrouillé immédiatement.
                    </p>
                    <form
                        class="mt-5 space-y-4"
                        @submit.prevent="convertToPaid"
                    >
                        <div>
                            <label class="mb-1 block text-sm font-bold"
                                >Plan *</label
                            >
                            <select
                                v-model="conversion.subscription_plan_id"
                                required
                                class="h-10 w-full rounded-md border bg-background px-3"
                            >
                                <option value="">Sélectionner un plan</option>
                                <option
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    :value="plan.id"
                                >
                                    {{ plan.name }} · {{ plan.price }}
                                    {{ plan.currency }} /
                                    {{
                                        plan.billing_period === 'yearly'
                                            ? 'an'
                                            : plan.billing_period === 'monthly'
                                              ? 'mois'
                                              : 'période'
                                    }}
                                </option>
                            </select>
                            <InputError
                                :message="
                                    conversion.errors.subscription_plan_id
                                "
                            />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-bold"
                                    >Durée en mois *</label
                                >
                                <Input
                                    v-model="conversion.months"
                                    type="number"
                                    min="1"
                                    max="60"
                                    required
                                />
                                <InputError
                                    :message="conversion.errors.months"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold"
                                    >Montant encaissé</label
                                >
                                <Input
                                    v-model="conversion.amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold"
                                    >Moyen de paiement</label
                                >
                                <select
                                    v-model="conversion.payment_method"
                                    class="h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="bank_transfer">
                                        Virement
                                    </option>
                                    <option value="cash">Espèces</option>
                                    <option value="cheque">Chèque</option>
                                    <option value="card">Carte</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold"
                                    >Référence</label
                                >
                                <Input v-model="conversion.reference" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="converting = null"
                                >Annuler</Button
                            >
                            <Button :disabled="conversion.processing"
                                >Convertir et activer</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="selected"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
        >
            <Card
                class="my-6 w-full"
                :class="action === 'details' ? 'max-w-3xl' : 'max-w-md'"
                ><CardContent class="p-6">
                    <div v-if="action === 'details'">
                        <div class="flex items-start justify-between">
                            <div>
                                <p
                                    class="text-xs font-bold text-primary uppercase"
                                >
                                    Fiche complète
                                </p>
                                <h2 class="mt-1 text-2xl font-black">
                                    {{ selected.school_name }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Demande #{{ selected.id }} ·
                                    {{
                                        new Date(
                                            selected.created_at,
                                        ).toLocaleString('fr-DZ')
                                    }}
                                </p>
                            </div>
                            <button
                                class="text-2xl text-muted-foreground"
                                @click="close"
                            >
                                ×
                            </button>
                        </div>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <section class="rounded-xl border p-4">
                                <h3 class="font-black">Établissement</h3>
                                <dl class="mt-3 grid gap-2 text-sm">
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Type
                                        </dt>
                                        <dd>
                                            {{
                                                typeLabel(selected.school_type)
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Adresse
                                        </dt>
                                        <dd>
                                            {{ selected.address }},
                                            {{ selected.commune }},
                                            {{ selected.wilaya }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Site web
                                        </dt>
                                        <dd>
                                            {{
                                                selected.website ||
                                                'Non renseigné'
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                            <section class="rounded-xl border p-4">
                                <h3 class="font-black">Responsable</h3>
                                <dl class="mt-3 grid gap-2 text-sm">
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Nom et fonction
                                        </dt>
                                        <dd>
                                            {{ selected.contact_name }} ·
                                            {{ selected.contact_role }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Email
                                        </dt>
                                        <dd>{{ selected.email }}</dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Téléphone
                                        </dt>
                                        <dd>{{ selected.phone }}</dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                        <section class="mt-5 rounded-xl border p-4">
                            <h3 class="font-black">Capacité déclarée</h3>
                            <div
                                class="mt-3 grid grid-cols-2 gap-3 text-center sm:grid-cols-4"
                            >
                                <span
                                    v-for="x in [
                                        [selected.students_count, 'Étudiants'],
                                        [
                                            selected.teachers_count,
                                            'Enseignants',
                                        ],
                                        [selected.staff_count, 'Employés'],
                                        [selected.sites_count, 'Sites'],
                                    ]"
                                    :key="x[1]"
                                    class="rounded-lg bg-muted p-3"
                                    ><b class="block text-xl">{{ x[0] || 0 }}</b
                                    ><small>{{ x[1] }}</small></span
                                >
                            </div>
                        </section>
                        <section class="mt-5 rounded-xl border p-4">
                            <h3 class="font-black">Modules sélectionnés</h3>
                            <div
                                v-if="selected.modules?.length"
                                class="mt-3 flex flex-wrap gap-2"
                            >
                                <Badge
                                    v-for="m in selected.modules"
                                    :key="m"
                                    variant="secondary"
                                    >{{ moduleLabels[m] || m }}</Badge
                                >
                            </div>
                            <p
                                v-else
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                Aucun module spécifique sélectionné.
                            </p>
                        </section>
                        <section
                            v-if="selected.needs"
                            class="mt-5 rounded-xl bg-primary/5 p-4"
                        >
                            <h3 class="font-black">Besoins et objectifs</h3>
                            <p
                                class="mt-2 text-sm leading-6 whitespace-pre-wrap"
                            >
                                {{ selected.needs }}
                            </p>
                        </section>
                        <div class="mt-6 flex justify-end">
                            <Button variant="outline" @click="close"
                                >Fermer</Button
                            >
                        </div>
                    </div>
                    <form
                        v-else-if="action === 'approve'"
                        @submit.prevent="approve"
                    >
                        <h2 class="text-lg font-black">
                            Approuver {{ selected.school_name }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Un espace isolé sera créé et les identifiants seront
                            envoyés par email.
                        </p>
                        <label class="mt-5 block text-sm font-bold"
                            >Durée initiale</label
                        ><Input
                            v-model="approval.days"
                            type="number"
                            min="1"
                            required
                            class="mt-1"
                        /><InputError :message="approval.errors.days" />
                        <div class="mt-5 flex gap-2">
                            <Button :disabled="approval.processing"
                                >Créer le compte</Button
                            ><Button
                                type="button"
                                variant="outline"
                                @click="close"
                                >Annuler</Button
                            >
                        </div>
                    </form>
                    <form v-else @submit.prevent="reject">
                        <h2 class="text-lg font-black">Refuser la demande</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Le motif est obligatoire. Il sera conservé dans le
                            dossier et envoyé par email au responsable.
                        </p>
                        <textarea
                            v-model="rejection.reason"
                            rows="5"
                            required
                            class="mt-4 w-full rounded-md border bg-background p-3"
                            placeholder="Expliquez clairement pourquoi la demande est refusée…"
                        ></textarea
                        ><InputError :message="rejection.errors.reason" />
                        <div class="mt-5 flex gap-2">
                            <Button
                                variant="destructive"
                                :disabled="rejection.processing"
                                >Refuser et envoyer l’email</Button
                            ><Button
                                type="button"
                                variant="outline"
                                @click="close"
                                >Annuler</Button
                            >
                        </div>
                    </form>
                </CardContent></Card
            >
        </div>

        <div
            v-if="showCreate"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
        >
            <Card class="mx-auto my-6 w-full max-w-4xl"
                ><CardContent class="p-6 sm:p-8"
                    ><div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold text-primary uppercase">
                                Création directe
                            </p>
                            <h2 class="mt-1 text-2xl font-black">
                                Nouveau compte de démonstration
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Le compte sera activé immédiatement et les
                                identifiants envoyés au responsable.
                            </p>
                        </div>
                        <button
                            class="text-2xl text-muted-foreground"
                            @click="showCreate = false"
                        >
                            ×
                        </button>
                    </div>
                    <form class="mt-7 space-y-5" @submit.prevent="createManual">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="field in [
                                    ['school_name', 'Établissement', 'text'],
                                    ['contact_name', 'Responsable', 'text'],
                                    ['contact_role', 'Fonction', 'text'],
                                    ['email', 'Email professionnel', 'email'],
                                    ['phone', 'Téléphone', 'tel'],
                                    ['address', 'Adresse', 'text'],
                                    ['website', 'Site web', 'url'],
                                ]"
                                :key="field[0]"
                                :class="
                                    field[0] === 'address'
                                        ? 'sm:col-span-2'
                                        : ''
                                "
                            >
                                <label class="mb-1 block text-xs font-bold">{{
                                    field[1]
                                }}</label
                                ><Input
                                    v-model="(manual as any)[field[0]]"
                                    :type="field[2]"
                                    :required="field[0] !== 'website'"
                                /><InputError
                                    :message="(manual.errors as any)[field[0]]"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold"
                                    >Type</label
                                ><select
                                    v-model="manual.school_type"
                                    class="h-9 w-full rounded-md border bg-background px-3 text-sm"
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
                            <div>
                                <label class="mb-1 block text-xs font-bold"
                                    >Durée</label
                                ><Input
                                    v-model="manual.requested_days"
                                    type="number"
                                    min="1"
                                    required
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <WilayaCommuneSelect
                                    v-model:wilaya="manual.wilaya"
                                    v-model:commune="manual.commune"
                                    :wilaya-error="manual.errors.wilaya"
                                    :commune-error="manual.errors.commune"
                                    required
                                    compact
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div
                                v-for="f in [
                                    ['students_count', 'Étudiants'],
                                    ['teachers_count', 'Enseignants'],
                                    ['staff_count', 'Employés'],
                                    ['sites_count', 'Sites'],
                                ]"
                                :key="f[0]"
                            >
                                <label class="mb-1 block text-xs font-bold">{{
                                    f[1]
                                }}</label
                                ><Input
                                    v-model="(manual as any)[f[0]]"
                                    type="number"
                                    :min="f[0] === 'sites_count' ? 1 : 0"
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-bold"
                                >Modules accessibles</label
                            >
                            <div
                                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <button
                                    v-for="m in moduleOptions"
                                    :key="m[0]"
                                    type="button"
                                    class="rounded-lg border p-2.5 text-left text-xs"
                                    :class="
                                        manual.modules.includes(m[0])
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : ''
                                    "
                                    @click="toggleManualModule(m[0])"
                                >
                                    {{
                                        manual.modules.includes(m[0])
                                            ? '✓ '
                                            : ''
                                    }}{{ m[1] }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold"
                                >Besoins / notes internes</label
                            ><textarea
                                v-model="manual.needs"
                                rows="3"
                                class="w-full rounded-md border bg-background p-3 text-sm"
                            ></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showCreate = false"
                                >Annuler</Button
                            ><Button :disabled="manual.processing"
                                ><RefreshCw
                                    v-if="manual.processing"
                                    class="animate-spin"
                                /><Plus v-else />Créer et envoyer les
                                accès</Button
                            >
                        </div>
                    </form></CardContent
                ></Card
            >
        </div>
    </SuperAdminLayout>
</template>
