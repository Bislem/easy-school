<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WilayaCommuneSelect from '@/components/WilayaCommuneSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    Building2,
    CalendarClock,
    CreditCard,
    Eye,
    LoaderCircle,
    MapPin,
    Plus,
    Search,
    UsersRound,
    WalletCards,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    schools: any;
    filters: any;
    plans: any[];
    summary: any;
}>();
const base = (usePage().props.superAdmin as any).basePath as string;
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const showCreate = ref(false);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    wilaya: '',
    commune: '',
    logo: null as File | null,
    admin_name: '',
    admin_email: '',
    admin_phone: '',
    password: '',
    subscription_plan_id: '',
    plan_expires_at: '',
    payment_amount: '',
    payment_method: 'bank_transfer',
    payment_reference: '',
    payment_proof: null as File | null,
});
const filter = () =>
    router.get(
        `${base}/schools`,
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true },
    );
const submit = () =>
    form.post(`${base}/schools`, {
        forceFormData: true,
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
const money = (value: any) =>
    new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 0 }).format(
        Number(value || 0),
    );
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-DZ', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : 'Sans échéance';
const bytes = (value: number | null) => {
    if (value === null) return '∞';
    if (!value) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(Math.floor(Math.log(value) / Math.log(1024)), 4);
    return `${(value / 1024 ** i).toFixed(i > 1 ? 1 : 0)} ${units[i]}`;
};
</script>

<template>
    <SuperAdminLayout
        title="Gestion des clients"
        description="Pilotez les établissements, abonnements et accès"
    >
        <div class="space-y-6">
            <section
                class="rounded-3xl bg-gradient-to-r from-[#061b3a] via-[#08335d] to-[#08747a] p-6 text-white shadow-xl"
            >
                <div
                    class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
                >
                    <div>
                        <Badge class="mb-3 bg-white/10 text-cyan-100"
                            >Portefeuille clients</Badge
                        >
                        <h2 class="text-3xl font-black">
                            Vos clients, en un seul regard.
                        </h2>
                        <p class="mt-2 text-sm text-blue-100/75">
                            Suivez leur activité, leur abonnement et leur santé
                            financière.
                        </p>
                    </div>
                    <Button
                        class="bg-[#12cbb2] font-bold text-[#052b3f] hover:bg-[#38dfc9]"
                        @click="showCreate = true"
                        ><Plus />Nouveau client</Button
                    >
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-4">
                    <div
                        v-for="item in [
                            ['Clients', summary.total, Building2],
                            ['Actifs', summary.active, UsersRound],
                            ['Suspendus', summary.suspended, CalendarClock],
                            [
                                'Revenu du mois',
                                `${money(summary.monthly_revenue)} DA`,
                                WalletCards,
                            ],
                        ]"
                        :key="item[0] as string"
                        class="rounded-2xl border border-white/10 bg-white/10 p-4"
                    >
                        <component
                            :is="item[2]"
                            class="size-4 text-[#12cbb2]"
                        />
                        <p class="mt-2 text-xs text-blue-100/70">
                            {{ item[0] }}
                        </p>
                        <p class="text-xl font-black">{{ item[1] }}</p>
                    </div>
                </div>
            </section>
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-between">
                <form class="flex flex-1 gap-2" @submit.prevent="filter">
                    <div class="relative max-w-md flex-1">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Nom, e-mail ou identifiant…"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="active">Actifs</option>
                        <option value="suspended">Suspendus</option></select
                    ><Button variant="outline">Filtrer</Button>
                </form>
            </div>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="client in schools.data"
                    :key="client.id"
                    class="overflow-hidden transition hover:-translate-y-1 hover:shadow-lg"
                    ><div
                        class="h-1.5"
                        :class="
                            client.status === 'active'
                                ? 'bg-[#12cbb2]'
                                : 'bg-amber-400'
                        "
                    />
                    <CardContent class="p-5"
                        ><div class="flex items-start gap-3">
                            <div
                                class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-[#06254a] font-black text-white"
                            >
                                <img
                                    v-if="client.logo_url"
                                    :src="client.logo_url"
                                    class="size-full object-cover"
                                /><span v-else>{{
                                    client.name.slice(0, 2).toUpperCase()
                                }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <h3 class="truncate font-black">
                                        {{ client.name }}
                                    </h3>
                                    <Badge
                                        :class="
                                            client.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-amber-100 text-amber-700'
                                        "
                                        >{{
                                            client.account_status === 'demo'
                                                ? 'Démo'
                                                : client.account_status ===
                                                    'expired'
                                                  ? 'Expiré'
                                                  : client.status === 'active'
                                                    ? 'Actif'
                                                    : 'Suspendu'
                                        }}</Badge
                                    >
                                </div>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ client.email || client.slug }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex items-center gap-2 rounded-xl bg-slate-50 p-3"
                        >
                            <CreditCard class="size-4 text-[#089c8d]" />
                            <div class="flex-1">
                                <p class="text-xs text-muted-foreground">
                                    Abonnement
                                </p>
                                <p class="text-sm font-bold">
                                    {{
                                        client.subscription_plan?.name ||
                                        'Aucun plan'
                                    }}
                                </p>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ date(client.plan_expires_at) }}
                            </p>
                        </div>
                        <div class="mt-3 rounded-xl border p-3">
                            <div class="flex justify-between text-xs">
                                <span>Stockage</span
                                ><b
                                    >{{ bytes(client.storage_used_bytes) }} /
                                    {{ bytes(client.storage_limit_bytes) }}</b
                                >
                            </div>
                            <div
                                class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="
                                        client.storage_percentage >= 90
                                            ? 'bg-red-500'
                                            : client.storage_percentage >= 75
                                              ? 'bg-amber-500'
                                              : 'bg-emerald-500'
                                    "
                                    :style="{
                                        width: `${client.storage_percentage}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 divide-x text-center">
                            <div>
                                <p class="font-black">
                                    {{ client.students_count }}
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    Étudiants
                                </p>
                            </div>
                            <div>
                                <p class="font-black">
                                    {{ client.staff_count }}
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    Personnel
                                </p>
                            </div>
                            <div>
                                <p class="font-black">
                                    {{ client.users_count }}
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    Utilisateurs
                                </p>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex items-center justify-between border-t pt-4"
                        >
                            <span
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                ><MapPin class="size-3" />{{
                                    [client.commune, client.wilaya]
                                        .filter(Boolean)
                                        .join(', ') || 'Localisation inconnue'
                                }}</span
                            ><Button size="sm" as-child
                                ><Link :href="`${base}/schools/${client.id}`"
                                    ><Eye />Voir le client</Link
                                ></Button
                            >
                        </div></CardContent
                    ></Card
                >
            </div>
            <div
                v-if="!schools.data.length"
                class="rounded-3xl border border-dashed p-14 text-center text-muted-foreground"
            >
                <Building2 class="mx-auto mb-2 size-10" />Aucun client trouvé.
            </div>
            <div v-if="schools.links?.length" class="flex flex-wrap gap-1">
                <Button
                    v-for="link in schools.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url || link.active"
                    @click="link.url && router.get(link.url)"
                    v-html="link.label"
                />
            </div>
        </div>
        <Dialog v-model:open="showCreate"
            ><DialogContent class="max-h-[92vh] max-w-4xl overflow-y-auto"
                ><DialogHeader
                    ><DialogTitle>Ajouter un nouveau client</DialogTitle
                    ><DialogDescription
                        >Créez son espace, son administrateur et son abonnement
                        initial.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-6" @submit.prevent="submit">
                    <section>
                        <h3 class="mb-3 font-black">Établissement</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Nom *</Label
                                ><Input
                                    v-model="form.name"
                                    required
                                /><InputError :message="form.errors.name" />
                            </div>
                            <div>
                                <Label>E-mail</Label
                                ><Input
                                    v-model="form.email"
                                    type="email"
                                /><InputError :message="form.errors.email" />
                            </div>
                            <div>
                                <Label>Téléphone</Label
                                ><Input v-model="form.phone" />
                            </div>
                            <div>
                                <Label>Adresse</Label
                                ><Input v-model="form.address" />
                            </div>
                            <div class="sm:col-span-2">
                                <WilayaCommuneSelect
                                    v-model:wilaya="form.wilaya"
                                    v-model:commune="form.commune"
                                    :wilaya-error="form.errors.wilaya"
                                    :commune-error="form.errors.commune"
                                    compact
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <Label>Logo</Label
                                ><Input
                                    type="file"
                                    accept="image/*"
                                    @change="
                                        form.logo =
                                            ($event.target as HTMLInputElement)
                                                .files?.[0] || null
                                    "
                                />
                            </div>
                        </div>
                    </section>
                    <section class="rounded-2xl bg-slate-50 p-4">
                        <h3 class="mb-3 font-black">
                            Administrateur principal
                        </h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Nom *</Label
                                ><Input
                                    v-model="form.admin_name"
                                    required
                                /><InputError
                                    :message="form.errors.admin_name"
                                />
                            </div>
                            <div>
                                <Label>E-mail *</Label
                                ><Input
                                    v-model="form.admin_email"
                                    type="email"
                                    required
                                /><InputError
                                    :message="form.errors.admin_email"
                                />
                            </div>
                            <div>
                                <Label>Téléphone</Label
                                ><Input v-model="form.admin_phone" />
                            </div>
                            <div>
                                <Label>Mot de passe *</Label
                                ><Input
                                    v-model="form.password"
                                    type="password"
                                    required
                                /><InputError :message="form.errors.password" />
                            </div>
                        </div>
                    </section>
                    <section>
                        <h3 class="mb-3 font-black">Abonnement et règlement</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Plan *</Label
                                ><select
                                    v-model="form.subscription_plan_id"
                                    required
                                    class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="">
                                        Sélectionner un plan
                                    </option>
                                    <option
                                        v-for="plan in plans"
                                        :key="plan.id"
                                        :value="plan.id"
                                    >
                                        {{ plan.name }} ·
                                        {{ money(plan.price) }}
                                        {{ plan.currency }}
                                    </option></select
                                ><InputError
                                    :message="form.errors.subscription_plan_id"
                                />
                            </div>
                            <div>
                                <Label>Expiration personnalisée</Label
                                ><Input
                                    v-model="form.plan_expires_at"
                                    type="date"
                                />
                            </div>
                            <div>
                                <Label>Montant payé</Label
                                ><Input
                                    v-model="form.payment_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </div>
                            <div>
                                <Label>Moyen de paiement</Label
                                ><select
                                    v-model="form.payment_method"
                                    class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="bank_transfer">
                                        Virement bancaire
                                    </option>
                                    <option value="cash">Espèces</option>
                                    <option value="cheque">Chèque</option>
                                    <option value="card">Carte</option>
                                </select>
                            </div>
                            <div>
                                <Label>Référence</Label
                                ><Input v-model="form.payment_reference" />
                            </div>
                            <div>
                                <Label>Preuve de paiement</Label
                                ><Input
                                    type="file"
                                    accept=".pdf,image/*"
                                    @change="
                                        form.payment_proof =
                                            ($event.target as HTMLInputElement)
                                                .files?.[0] || null
                                    "
                                /><InputError
                                    :message="form.errors.payment_proof"
                                />
                            </div>
                        </div>
                    </section>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="showCreate = false"
                            >Annuler</Button
                        ><Button
                            :disabled="form.processing"
                            class="bg-[#12cbb2] text-[#052b3f] hover:bg-[#38dfc9]"
                            ><LoaderCircle
                                v-if="form.processing"
                                class="animate-spin"
                            />Créer le client</Button
                        ></DialogFooter
                    >
                </form></DialogContent
            ></Dialog
        ></SuperAdminLayout
    >
</template>
