<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    translatePublicRoot,
    watchPublicLocale,
} from '@/composables/usePublicLocale';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Download,
    FilePlus2,
    Percent,
    Plus,
    ReceiptText,
    Search,
    WalletCards,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps<{
    accounts: any;
    filters: any;
    stats: any;
    formations: any[];
    sessions: any[];
    groups: any[];
    students: any[];
    sites: any[];
    pricingConfigs: any[];
    methods: any[];
}>();
const root = ref<HTMLElement | null>(null);
let stop: (() => void) | undefined;
onMounted(() => {
    stop = watchPublicLocale(() => root.value);
    nextTick(() => translatePublicRoot(root.value));
});
onBeforeUnmount(() => stop?.());
const money = (v: any) =>
    `${Number(v || 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2 })} DZD`;
const labels: any = {
    unpaid: 'Non payé',
    partial: 'Partiel',
    paid: 'Payé',
    overdue: 'En retard',
};
const filters = useForm({
    formation_id: String(props.filters.formation_id || ''),
    session_id: String(props.filters.session_id || ''),
    group_id: String(props.filters.group_id || ''),
    student_id: String(props.filters.student_id || ''),
    site_id: String(props.filters.site_id || ''),
    status: props.filters.status || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    search: props.filters.search || '',
});
const apply = () =>
    router.get('/admin/formation-payments', filters.data(), {
        preserveState: true,
        replace: true,
    });
const filteredSessions = computed(() =>
    props.sessions.filter(
        (s) =>
            !filters.formation_id ||
            String(s.course_id) === filters.formation_id,
    ),
);
const modal = ref('');
const pricing = useForm({
    course_id: '',
    enrollment_form_id: '',
    training_plan_group_id: '',
    name: '',
    total_price: '',
    schedule: [
        {
            label: 'Paiement intégral',
            amount: '',
            due_date: new Date().toISOString().slice(0, 10),
        },
    ],
});
const generation = useForm({
    formation_pricing_config_id: '',
    session_id: '',
    group_id: '',
    update_existing: false,
});
const preview = ref<any>(null);
const selectedPricing = computed(() =>
    props.pricingConfigs.find(
        (p) => String(p.id) === generation.formation_pricing_config_id,
    ),
);
const addSchedule = () =>
    pricing.schedule.push({
        label: 'Échéance',
        amount: '',
        due_date: new Date().toISOString().slice(0, 10),
    });
const savePricing = () =>
    pricing.post('/admin/formation-payments/pricing', {
        onSuccess: () => {
            modal.value = '';
            pricing.reset();
        },
    });
async function calculate() {
    const p = selectedPricing.value;
    const response = await fetch(
        '/admin/formation-payments/generation/preview',
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name=csrf-token]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
            body: JSON.stringify({
                formation_id: p?.course_id,
                session_id: generation.session_id || null,
                group_id: generation.group_id || null,
            }),
        },
    );
    preview.value = await response.json();
}
const generate = () =>
    generation.post('/admin/formation-payments/generate', {
        onSuccess: () => {
            modal.value = '';
            preview.value = null;
        },
    });
</script>
<template>
    <Head title="Paiements formations" /><AdminLayout
        ><div ref="root">
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-[1500px] space-y-6">
                    <header
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h1 class="text-2xl font-black">
                                Paiements formations
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                Facturation indépendante par inscription,
                                session et formation.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button as-child variant="outline"
                                ><a href="/admin/formation-payments/export"
                                    ><Download class="mr-2 size-4" />Exporter</a
                                ></Button
                            ><Button
                                variant="outline"
                                @click="modal = 'pricing'"
                                ><Plus
                                    class="mr-2 size-4"
                                />Tarification</Button
                            ><Button @click="modal = 'generate'"
                                ><FilePlus2 class="mr-2 size-4" />Générer la
                                facturation</Button
                            >
                        </div>
                    </header>
                    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <article
                            v-for="c in [
                                {
                                    k: 'expected',
                                    l: 'Revenu attendu',
                                    i: ReceiptText,
                                },
                                { k: 'collected', l: 'Encaissé', i: Banknote },
                                {
                                    k: 'remaining',
                                    l: 'Restant',
                                    i: WalletCards,
                                },
                                {
                                    k: 'overdue',
                                    l: 'En retard',
                                    i: WalletCards,
                                },
                                {
                                    k: 'collection_rate',
                                    l: 'Taux de collecte',
                                    i: Percent,
                                },
                            ]"
                            :key="c.k"
                            class="rounded-xl border bg-card p-4"
                        >
                            <component
                                :is="c.i"
                                class="mb-2 size-5 text-primary"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ c.l }}
                            </p>
                            <b class="text-xl">{{
                                c.k === 'collection_rate'
                                    ? `${stats[c.k]} %`
                                    : money(stats[c.k])
                            }}</b>
                        </article>
                    </section>
                    <form
                        class="rounded-xl border bg-card p-4"
                        @submit.prevent="apply"
                    >
                        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                            <div class="relative">
                                <Search
                                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                                /><Input
                                    v-model="filters.search"
                                    class="pl-9"
                                    placeholder="Rechercher un étudiant…"
                                />
                            </div>
                            <select
                                v-model="filters.formation_id"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">Toutes les formations</option>
                                <option
                                    v-for="f in formations"
                                    :key="f.id"
                                    :value="String(f.id)"
                                >
                                    {{ f.title }}
                                </option></select
                            ><select
                                v-model="filters.session_id"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">Toutes les sessions</option>
                                <option
                                    v-for="s in filteredSessions"
                                    :key="s.id"
                                    :value="String(s.id)"
                                >
                                    {{ s.title }}
                                </option></select
                            ><select
                                v-model="filters.group_id"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">Tous les groupes</option>
                                <option
                                    v-for="g in groups"
                                    :key="g.id"
                                    :value="String(g.id)"
                                >
                                    {{ g.name }}
                                </option></select
                            ><select
                                v-model="filters.student_id"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">Tous les étudiants</option>
                                <option
                                    v-for="s in students"
                                    :key="s.id"
                                    :value="String(s.id)"
                                >
                                    {{ s.first_name }} {{ s.last_name }}
                                </option></select
                            ><select
                                v-model="filters.site_id"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">Tous les sites</option>
                                <option
                                    v-for="s in sites"
                                    :key="s.id"
                                    :value="String(s.id)"
                                >
                                    {{ s.name }}
                                </option></select
                            ><select
                                v-model="filters.status"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">Tous les statuts</option>
                                <option
                                    v-for="(l, k) in labels"
                                    :key="k"
                                    :value="k"
                                >
                                    {{ l }}
                                </option></select
                            ><Input
                                v-model="filters.date_from"
                                type="date"
                            /><Input
                                v-model="filters.date_to"
                                type="date"
                            /><Button>Filtrer</Button>
                        </div>
                    </form>
                    <div class="overflow-hidden rounded-xl border bg-card">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50 text-left">
                                    <tr>
                                        <th class="p-3">Étudiant</th>
                                        <th>Formation</th>
                                        <th>Session</th>
                                        <th>Groupe</th>
                                        <th>Prix total</th>
                                        <th>Payé</th>
                                        <th>Restant</th>
                                        <th>Prochain paiement</th>
                                        <th>Statut</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="a in accounts.data"
                                        :key="a.id"
                                        class="border-t"
                                    >
                                        <td class="p-3 font-bold">
                                            {{
                                                a.student?.full_name ||
                                                `${a.student?.first_name} ${a.student?.last_name}`
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                a.accountable?.form?.course
                                                    ?.title || '—'
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                a.accountable?.form?.title ||
                                                '—'
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                a.accountable
                                                    ?.training_plan_group
                                                    ?.name || '—'
                                            }}
                                        </td>
                                        <td>{{ money(a.expected_total) }}</td>
                                        <td class="text-emerald-700">
                                            {{ money(a.paid_total) }}
                                        </td>
                                        <td class="font-bold">
                                            {{ money(a.balance) }}
                                        </td>
                                        <td>{{ a.next_due_date || '—' }}</td>
                                        <td>{{ labels[a.status] }}</td>
                                        <td>
                                            <Button as-child size="sm"
                                                ><Link
                                                    :href="`/admin/formation-payments/accounts/${a.id}`"
                                                    >Ouvrir</Link
                                                ></Button
                                            >
                                        </td>
                                    </tr>
                                    <tr v-if="!accounts.data.length">
                                        <td
                                            colspan="10"
                                            class="p-10 text-center text-muted-foreground"
                                        >
                                            Aucune facturation générée.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex gap-2 border-t p-3">
                            <Link
                                v-for="l in accounts.links"
                                :key="l.label"
                                :href="l.url || '#'"
                                class="rounded border px-3 py-1 text-xs"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        l.active,
                                    'pointer-events-none opacity-40': !l.url,
                                }"
                                v-html="l.label"
                            />
                        </div>
                    </div>
                </div>
            </main>
            <div
                v-if="modal"
                class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
            >
                <section
                    class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-background p-6"
                >
                    <div class="flex justify-between">
                        <h2 class="text-xl font-black">
                            {{
                                modal === 'pricing'
                                    ? 'Tarification formation/session'
                                    : 'Générer la facturation'
                            }}
                        </h2>
                        <button @click="modal = ''" aria-label="Fermer">
                            <X />
                        </button>
                    </div>
                    <form
                        v-if="modal === 'pricing'"
                        class="mt-5 space-y-4"
                        @submit.prevent="savePricing"
                    >
                        <select
                            v-model="pricing.course_id"
                            required
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="">Formation</option>
                            <option
                                v-for="f in formations"
                                :key="f.id"
                                :value="String(f.id)"
                            >
                                {{ f.title }}
                            </option></select
                        ><select
                            v-model="pricing.enrollment_form_id"
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="">Toutes sessions</option>
                            <option
                                v-for="s in sessions.filter(
                                    (x) =>
                                        !pricing.course_id ||
                                        String(x.course_id) ===
                                            pricing.course_id,
                                )"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.title }}
                            </option></select
                        ><select
                            v-model="pricing.training_plan_group_id"
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="">Tous groupes</option>
                            <option
                                v-for="g in groups"
                                :key="g.id"
                                :value="String(g.id)"
                            >
                                {{ g.name }}
                            </option></select
                        ><Input
                            v-model="pricing.name"
                            required
                            placeholder="Nom de la tarification"
                        /><Input
                            v-model="pricing.total_price"
                            required
                            type="number"
                            min="0.01"
                            placeholder="Prix total DZD"
                        />
                        <div>
                            <div class="mb-2 flex justify-between">
                                <b>Échéancier personnalisé</b
                                ><Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="addSchedule"
                                    >Ajouter</Button
                                >
                            </div>
                            <div
                                v-for="(s, i) in pricing.schedule"
                                :key="i"
                                class="mb-2 grid grid-cols-3 gap-2"
                            >
                                <Input v-model="s.label" required /><Input
                                    v-model="s.amount"
                                    required
                                    type="number"
                                    min="0.01"
                                /><Input
                                    v-model="s.due_date"
                                    required
                                    type="date"
                                />
                            </div>
                        </div>
                        <Button class="w-full">Enregistrer</Button>
                    </form>
                    <form
                        v-else
                        class="mt-5 space-y-4"
                        @submit.prevent="generate"
                    >
                        <select
                            v-model="generation.formation_pricing_config_id"
                            required
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="">Tarification</option>
                            <option
                                v-for="p in pricingConfigs"
                                :key="p.id"
                                :value="String(p.id)"
                            >
                                {{ p.name }} — {{ p.course?.title }}
                            </option></select
                        ><select
                            v-model="generation.session_id"
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="">
                                Toutes sessions applicables
                            </option>
                            <option
                                v-for="s in sessions"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.title }}
                            </option></select
                        ><select
                            v-model="generation.group_id"
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="">Tous groupes</option>
                            <option
                                v-for="g in groups"
                                :key="g.id"
                                :value="String(g.id)"
                            >
                                {{ g.name }}
                            </option></select
                        ><label class="flex gap-2"
                            ><input
                                v-model="generation.update_existing"
                                type="checkbox"
                            />Mettre à jour les comptes sans transaction</label
                        ><Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="calculate"
                            >Calculer les inscriptions concernées</Button
                        >
                        <div v-if="preview" class="rounded-xl bg-muted p-4">
                            {{ preview.enrollments }} inscription(s) ·
                            {{ preview.new }} nouvelle(s) ·
                            {{ preview.existing }} existante(s). Les comptes
                            avec historique seront ignorés.
                        </div>
                        <Button :disabled="!preview" class="w-full"
                            >Confirmer la génération</Button
                        >
                    </form>
                </section>
            </div>
        </div></AdminLayout
    >
</template>
