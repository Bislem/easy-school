<script setup lang="ts">
import InputError from '@/components/InputError.vue';
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
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Check,
    Database,
    GraduationCap,
    Layers3,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Trash2,
    UserRoundCog,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ plans: any[]; schools: any[] }>();
const base = (usePage().props.superAdmin as any).basePath;
const showDialog = ref(false);
const editing = ref<any>(null);
const search = ref('');
const empty = () => ({
    name: '',
    description: '',
    price: 0,
    currency: 'DZD',
    billing_period: 'monthly',
    max_students: null,
    max_teachers: null,
    max_staff: null,
    max_sites: null,
    max_users: null,
    max_courses: null,
    storage_go: null,
    features: '',
    is_active: true,
    sort_order: 0,
});
const form = useForm(empty());
const visiblePlans = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? props.plans.filter((p) =>
              `${p.name} ${p.description || ''}`.toLowerCase().includes(q),
          )
        : props.plans;
});
const activePlans = computed(
    () => props.plans.filter((p) => p.is_active).length,
);
const subscribedSchools = computed(() =>
    props.plans.reduce((total, p) => total + Number(p.tenants_count || 0), 0),
);
const openCreate = () => {
    editing.value = null;
    form.defaults(empty());
    form.reset();
    form.clearErrors();
    showDialog.value = true;
};
const openEdit = (p: any) => {
    editing.value = p;
    form.clearErrors();
    Object.assign(form, {
        name: p.name,
        description: p.description || '',
        price: Number(p.price),
        currency: p.currency,
        billing_period: p.billing_period,
        max_students: p.max_students,
        max_teachers: p.max_teachers,
        max_staff: p.max_staff,
        max_sites: p.max_sites,
        max_users: p.max_users,
        max_courses: p.max_courses,
        storage_go: p.storage_go,
        features: (p.features || []).join('\n'),
        is_active: Boolean(p.is_active),
        sort_order: p.sort_order,
    });
    showDialog.value = true;
};
const save = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showDialog.value = false),
    };
    editing.value
        ? form.put(`${base}/plans/${editing.value.id}`, options)
        : form.post(`${base}/plans`, options);
};
const remove = (p: any) =>
    confirm(`Supprimer définitivement le plan « ${p.name} » ?`) &&
    router.delete(`${base}/plans/${p.id}`, { preserveScroll: true });
const assignment = useForm({
    tenant_id: '',
    subscription_plan_id: '',
    plan_expires_at: '',
});
const assign = () =>
    assignment.put(`${base}/plans/assign/school`, {
        preserveScroll: true,
        onSuccess: () => assignment.reset(),
    });
const periodLabel = (v: string) =>
    ({ monthly: '/ mois', yearly: '/ an', custom: '' })[v] || '';
const price = (p: any) =>
    new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 2 }).format(
        Number(p.price),
    );
const limit = (v: any) => v || 'Illimité';
const limits = [
    ['max_students', 'Étudiants', GraduationCap],
    ['max_teachers', 'Enseignants', Users],
    ['max_staff', 'Employés', UserRoundCog],
    ['max_sites', 'Sites', Building2],
    ['max_users', 'Utilisateurs', Users],
    ['max_courses', 'Formations', BookOpen],
] as const;
</script>

<template>
    <SuperAdminLayout title="Plans et abonnements"
        ><div class="space-y-7">
            <section
                class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#061b3a] via-[#082d58] to-[#086b73] p-6 text-white shadow-xl md:p-8"
            >
                <div
                    class="absolute -top-20 -right-16 size-64 rounded-full bg-cyan-300/10 blur-3xl"
                />
                <div
                    class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="max-w-2xl">
                        <Badge
                            class="mb-4 border-white/15 bg-white/10 text-cyan-100"
                            >Catalogue SaaS</Badge
                        >
                        <h1
                            class="text-3xl font-black tracking-tight md:text-4xl"
                        >
                            Des offres claires, prêtes à évoluer.
                        </h1>
                        <p class="mt-3 text-sm leading-6 text-blue-100/80">
                            Configurez les tarifs, capacités et fonctionnalités
                            proposées à vos établissements.
                        </p>
                    </div>
                    <Button
                        class="h-11 bg-[#12cbb2] px-5 font-bold text-[#052b3f] hover:bg-[#37dfc8]"
                        @click="openCreate"
                        ><Plus class="mr-2 size-4" /> Créer un plan</Button
                    >
                </div>
                <div class="relative mt-7 grid gap-3 sm:grid-cols-3">
                    <div
                        v-for="item in [
                            ['Plans configurés', plans.length],
                            ['Offres actives', activePlans],
                            ['Écoles abonnées', subscribedSchools],
                        ]"
                        :key="item[0]"
                        class="rounded-2xl border border-white/10 bg-white/10 p-4"
                    >
                        <p class="text-xs text-blue-100/70">{{ item[0] }}</p>
                        <p class="mt-1 text-2xl font-black">{{ item[1] }}</p>
                    </div>
                </div>
            </section>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-xl font-black">Catalogue des plans</h2>
                    <p class="text-sm text-muted-foreground">
                        Gérez chaque offre depuis sa carte.
                    </p>
                </div>
                <div class="relative w-full sm:w-72">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    /><Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Rechercher un plan…"
                    />
                </div>
            </div>
            <section
                v-if="visiblePlans.length"
                class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3"
            >
                <Card
                    v-for="(p, index) in visiblePlans"
                    :key="p.id"
                    class="group relative overflow-hidden border-slate-200 shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                    ><div
                        class="h-1.5"
                        :class="
                            index % 3 === 1
                                ? 'bg-violet-500'
                                : index % 3 === 2
                                  ? 'bg-amber-400'
                                  : 'bg-[#12cbb2]'
                        "
                    />
                    <CardContent class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-xl font-black">
                                        {{ p.name }}
                                    </h3>
                                    <Badge
                                        :class="
                                            p.is_active
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-slate-100 text-slate-600'
                                        "
                                        >{{
                                            p.is_active ? 'Actif' : 'Masqué'
                                        }}</Badge
                                    >
                                </div>
                                <p
                                    class="mt-2 min-h-10 text-sm leading-5 text-muted-foreground"
                                >
                                    {{
                                        p.description ||
                                        'Une offre adaptée à votre catalogue.'
                                    }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    @click="openEdit(p)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    size="icon"
                                    variant="ghost"
                                    class="text-destructive"
                                    :disabled="Number(p.tenants_count) > 0"
                                    @click="remove(p)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                        </div>
                        <div class="mt-5 flex items-end gap-2 border-b pb-5">
                            <span class="text-3xl font-black">{{
                                price(p)
                            }}</span
                            ><span
                                class="pb-1 text-sm font-semibold text-muted-foreground"
                                >{{ p.currency }}
                                {{ periodLabel(p.billing_period) }}</span
                            >
                        </div>
                        <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <GraduationCap
                                    class="mx-auto size-4 text-[#089c8d]"
                                />
                                <p class="mt-1 text-sm font-black">
                                    {{ limit(p.max_students) }}
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    Étudiants
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <Building2
                                    class="mx-auto size-4 text-[#089c8d]"
                                />
                                <p class="mt-1 text-sm font-black">
                                    {{ limit(p.max_sites) }}
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    Sites
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <Database
                                    class="mx-auto size-4 text-[#089c8d]"
                                />
                                <p class="mt-1 text-sm font-black">
                                    {{ p.storage_go || '∞' }} Go
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    Stockage
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 space-y-2">
                            <p
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Fonctionnalités incluses
                            </p>
                            <div
                                v-if="p.features?.length"
                                class="grid gap-2 sm:grid-cols-2"
                            >
                                <div
                                    v-for="feature in p.features.slice(0, 6)"
                                    :key="feature"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <span
                                        class="grid size-5 place-items-center rounded-full bg-emerald-50"
                                        ><Check
                                            class="size-3 text-emerald-600" /></span
                                    ><span class="truncate">{{ feature }}</span>
                                </div>
                            </div>
                            <p
                                v-else
                                class="text-sm text-muted-foreground italic"
                            >
                                Aucune fonctionnalité renseignée.
                            </p>
                        </div>
                        <div
                            class="mt-5 flex items-center justify-between rounded-xl bg-[#06254a] px-4 py-3 text-white"
                        >
                            <span class="text-sm font-semibold"
                                >{{ p.tenants_count }} école(s)</span
                            ><Button
                                size="sm"
                                class="bg-white/10 hover:bg-white/20"
                                @click="openEdit(p)"
                                >Configurer</Button
                            >
                        </div>
                    </CardContent></Card
                >
            </section>
            <div
                v-else
                class="rounded-3xl border border-dashed p-12 text-center"
            >
                <Layers3 class="mx-auto size-10 text-muted-foreground" />
                <h3 class="mt-3 font-bold">Aucun plan trouvé</h3>
                <p class="text-sm text-muted-foreground">
                    Créez votre première offre ou modifiez la recherche.
                </p>
            </div>
            <Card class="overflow-hidden border-0 bg-slate-50 shadow-sm"
                ><CardContent class="p-6"
                    ><div class="mb-5 flex items-center gap-3">
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-[#06254a] text-white"
                            ><Sparkles class="size-5"
                        /></span>
                        <div>
                            <h2 class="font-black">Attribuer un abonnement</h2>
                            <p class="text-sm text-muted-foreground">
                                Associez rapidement une offre à un
                                établissement.
                            </p>
                        </div>
                    </div>
                    <form
                        class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_auto]"
                        @submit.prevent="assign"
                    >
                        <select
                            v-model="assignment.tenant_id"
                            class="h-10 rounded-md border bg-white px-3"
                            required
                        >
                            <option value="">Sélectionner une école</option>
                            <option
                                v-for="s in schools"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name
                                }}{{
                                    s.subscription_plan
                                        ? ` · ${s.subscription_plan.name}`
                                        : ''
                                }}
                            </option></select
                        ><select
                            v-model="assignment.subscription_plan_id"
                            class="h-10 rounded-md border bg-white px-3"
                        >
                            <option value="">Aucun plan</option>
                            <option
                                v-for="p in plans"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.name }}
                            </option></select
                        ><Input
                            v-model="assignment.plan_expires_at"
                            type="date"
                        /><Button
                            class="h-10 bg-[#12cbb2] text-[#052b3f] hover:bg-[#37dfc8]"
                            >Attribuer</Button
                        >
                    </form></CardContent
                ></Card
            >
        </div>
        <Dialog v-model:open="showDialog"
            ><DialogContent class="max-h-[92vh] max-w-3xl overflow-y-auto"
                ><DialogHeader
                    ><DialogTitle>{{
                        editing ? `Modifier ${editing.name}` : 'Créer un plan'
                    }}</DialogTitle
                    ><DialogDescription
                        >Définissez le tarif, les limites et les services inclus
                        dans cette offre.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-6" @submit.prevent="save">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Nom du plan *</Label
                            ><Input
                                v-model="form.name"
                                class="mt-1"
                                required
                                placeholder="Ex. Professionnel"
                            /><InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <Label>Ordre d’affichage *</Label
                            ><Input
                                v-model="form.sort_order"
                                class="mt-1"
                                type="number"
                                min="0"
                                required
                            /><InputError :message="form.errors.sort_order" />
                        </div>
                        <div class="sm:col-span-2">
                            <Label>Description</Label
                            ><textarea
                                v-model="form.description"
                                rows="3"
                                class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                                placeholder="Présentez brièvement la valeur de ce plan…"
                            /><InputError :message="form.errors.description" />
                        </div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <h3 class="mb-3 text-sm font-black">Tarification</h3>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <Label>Prix *</Label
                                ><Input
                                    v-model="form.price"
                                    class="mt-1"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                /><InputError :message="form.errors.price" />
                            </div>
                            <div>
                                <Label>Devise *</Label
                                ><select
                                    v-model="form.currency"
                                    class="mt-1 h-9 w-full rounded-md border bg-white px-3"
                                >
                                    <option>DZD</option>
                                    <option>EUR</option>
                                    <option>USD</option>
                                </select>
                            </div>
                            <div>
                                <Label>Période *</Label
                                ><select
                                    v-model="form.billing_period"
                                    class="mt-1 h-9 w-full rounded-md border bg-white px-3"
                                >
                                    <option value="monthly">Mensuel</option>
                                    <option value="yearly">Annuel</option>
                                    <option value="custom">Personnalisé</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-black">
                                Capacités du plan
                            </h3>
                            <span class="text-xs text-muted-foreground"
                                >Vide = illimité</span
                            >
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="l in limits" :key="l[0]">
                                <Label class="flex items-center gap-2"
                                    ><component
                                        :is="l[2]"
                                        class="size-4 text-[#089c8d]"
                                    />{{ l[1] }}</Label
                                ><Input
                                    v-model="(form as any)[l[0]]"
                                    class="mt-1"
                                    type="number"
                                    min="1"
                                    placeholder="Illimité"
                                />
                            </div>
                            <div>
                                <Label class="flex items-center gap-2"
                                    ><Database
                                        class="size-4 text-[#089c8d]"
                                    />Stockage (Go)</Label
                                ><Input
                                    v-model="form.storage_go"
                                    class="mt-1"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="Illimité"
                                /><InputError
                                    :message="form.errors.storage_go"
                                />
                            </div>
                        </div>
                    </div>
                    <div>
                        <Label>Fonctionnalités incluses</Label
                        ><textarea
                            v-model="form.features"
                            rows="5"
                            class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                            placeholder="Une fonctionnalité par ligne"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Ajoutez une fonctionnalité par ligne.
                        </p>
                        <InputError :message="form.errors.features" />
                    </div>
                    <label
                        class="flex cursor-pointer items-center justify-between rounded-xl border p-4"
                        ><div>
                            <p class="font-bold">Plan disponible</p>
                            <p class="text-xs text-muted-foreground">
                                Visible lors de l’inscription des
                                établissements.
                            </p>
                        </div>
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-5 accent-[#12cbb2]"
                    /></label>
                    <div
                        v-if="Object.keys(form.errors).length"
                        class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
                    >
                        Vérifiez les champs signalés avant d’enregistrer.
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="showDialog = false"
                            >Annuler</Button
                        ><Button
                            class="bg-[#12cbb2] text-[#052b3f] hover:bg-[#37dfc8]"
                            :disabled="form.processing"
                            >{{
                                form.processing
                                    ? 'Enregistrement…'
                                    : editing
                                      ? 'Enregistrer les modifications'
                                      : 'Créer le plan'
                            }}</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent></Dialog
        ></SuperAdminLayout
    >
</template>
