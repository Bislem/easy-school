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
    CircleAlert,
    FilePlus2,
    Layers3,
    Percent,
    Plus,
    ReceiptText,
    RotateCcw,
    Search,
    Users,
    WalletCards,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
const root = ref<HTMLElement | null>(null);
let stopLocaleWatch: (() => void) | undefined;
onMounted(() => {
    stopLocaleWatch = watchPublicLocale(() => root.value);
    nextTick(() => translatePublicRoot(root.value));
});
onBeforeUnmount(() => stopLocaleWatch?.());
const props = defineProps<{
    accounts: any;
    filters: any;
    stats: any;
    academicYears: any[];
    sites: any[];
    levels: any[];
    groups: any[];
    students: any[];
    feeStructures: any[];
    methods: any[];
}>();
const money = (v: any) =>
    `${Number(v || 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2 })} DZD`;
const statusLabel: any = {
    unpaid: 'Non payé',
    partial: 'Partiel',
    paid: 'Payé',
    overdue: 'En retard',
};
const filter = useForm({
    academic_year_id: String(props.filters.academic_year_id || ''),
    site_id: String(props.filters.site_id || ''),
    level_id: String(props.filters.level_id || ''),
    group_id: String(props.filters.group_id || ''),
    student_id: String(props.filters.student_id || ''),
    status: props.filters.status || '',
    search: props.filters.search || '',
});
const apply = () =>
    router.get('/admin/school-payments', filter.data(), {
        preserveState: true,
        replace: true,
    });
const visibleGroups = computed(() =>
    props.groups.filter(
        (g) =>
            !filter.level_id || String(g.school_level_id) === filter.level_id,
    ),
);
const levelLabel = (level: any) =>
    level?.specialization
        ? `${level.code || level.name} — Spécialité : ${level.specialization}`
        : level?.name || '—';
const groupMeta = (group: any) =>
    [
        group?.code,
        group?.stream?.name_fr,
        group?.classroom?.name,
        group?.classroom?.site?.name,
    ]
        .filter(Boolean)
        .join(' · ');
const structureAmount = (item: any) =>
    item.schedule_items?.reduce(
        (total: number, row: any) => total + Number(row.amount || 0),
        0,
    ) || 0;
const structureScope = (item: any) => {
    if (item.student)
        return `${item.student.first_name} ${item.student.last_name}`;
    if (item.group) return `Groupe ${item.group.name}`;
    if (item.level) return levelLabel(item.level);
    return 'Tous les élèves de l’année';
};
const resetFilters = () => {
    filter.site_id = '';
    filter.level_id = '';
    filter.group_id = '';
    filter.student_id = '';
    filter.status = '';
    filter.search = '';
    apply();
};
const modal = ref('');
const structure = useForm({
    academic_year_id: String(props.filters.academic_year_id || ''),
    school_level_id: '',
    school_group_id: '',
    student_id: '',
    name: '',
    description: '',
    components: [
        {
            code: 'registration',
            label: 'Inscription',
            amount: '',
            is_mandatory: true,
        },
    ],
    schedule: [
        {
            label: 'Inscription',
            amount: '',
            due_date: new Date().toISOString().slice(0, 10),
            component_index: 0,
        },
    ],
});
const generation = useForm({
    academic_year_id: String(props.filters.academic_year_id || ''),
    level_id: '',
    group_id: '',
    school_fee_structure_id: '',
    update_existing: false,
    optional_component_ids: [] as number[],
});
const structureGroups = computed(() =>
    props.groups.filter(
        (group) =>
            !structure.school_level_id ||
            String(group.school_level_id) === structure.school_level_id,
    ),
);
const generationGroups = computed(() =>
    props.groups.filter(
        (group) =>
            !generation.level_id ||
            String(group.school_level_id) === generation.level_id,
    ),
);
const selectedGenerationLevel = computed(() =>
    props.levels.find((level) => String(level.id) === generation.level_id),
);
const selectStructureLevel = () => {
    structure.school_group_id = '';
};
const selectGenerationLevel = () => {
    generation.group_id = '';
    preview.value = null;
};
const selectedGenerationStructure = computed(() =>
    props.feeStructures.find(
        (item) => String(item.id) === generation.school_fee_structure_id,
    ),
);
const selectGenerationStructure = () => {
    const item = selectedGenerationStructure.value;
    if (item?.school_level_id) {
        generation.level_id = String(item.school_level_id);
    }
    if (item?.school_group_id) {
        generation.group_id = String(item.school_group_id);
    } else if (
        generation.group_id &&
        !generationGroups.value.some(
            (group) => String(group.id) === generation.group_id,
        )
    ) {
        generation.group_id = '';
    }
    preview.value = null;
};
const preview = ref<any>(null);
const addComponent = () =>
    structure.components.push({
        code: 'custom',
        label: 'Autre frais',
        amount: '',
        is_mandatory: true,
    });
const addSchedule = () =>
    structure.schedule.push({
        label: 'Échéance',
        amount: '',
        due_date: new Date().toISOString().slice(0, 10),
        component_index: 0,
    });
const saveStructure = () =>
    structure.post('/admin/school-payments/fee-structures', {
        onSuccess: () => {
            modal.value = '';
            structure.reset();
        },
    });
async function previewGeneration() {
    const response = await fetch('/admin/school-payments/generation/preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN':
                (
                    document.querySelector(
                        'meta[name=csrf-token]',
                    ) as HTMLMetaElement
                )?.content || '',
            Accept: 'application/json',
        },
        body: JSON.stringify(generation.data()),
    });
    preview.value = await response.json();
}
const generate = () =>
    generation.post('/admin/school-payments/generate', {
        onSuccess: () => {
            modal.value = '';
            preview.value = null;
        },
    });
const generateFromStructure = (item: any) => {
    generation.school_fee_structure_id = String(item.id);
    generation.level_id = item.school_level_id
        ? String(item.school_level_id)
        : '';
    generation.group_id = item.school_group_id
        ? String(item.school_group_id)
        : '';
    preview.value = null;
    modal.value = 'generate';
};
</script>
<template>
    <Head title="Paiements scolaires" /><AdminLayout
        ><div ref="root">
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-[1500px] space-y-6">
                    <header
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h1 class="text-2xl font-black">
                                Paiements scolaires
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                Comptes financiers individuels, échéances et
                                encaissements.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                @click="modal = 'structure'"
                                ><Plus class="mr-2 size-4" />Structure
                                tarifaire</Button
                            ><Button @click="modal = 'generate'"
                                ><FilePlus2 class="mr-2 size-4" />Générer les
                                frais élèves</Button
                            >
                        </div>
                    </header>
                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <article
                            v-for="card in [
                                {
                                    k: 'expected',
                                    l: 'Total attendu',
                                    i: ReceiptText,
                                },
                                { k: 'collected', l: 'Encaissé', i: Banknote },
                                {
                                    k: 'outstanding',
                                    l: 'Restant',
                                    i: WalletCards,
                                },
                                {
                                    k: 'overdue',
                                    l: 'En retard',
                                    i: CircleAlert,
                                },
                                { k: 'collection_rate', l: 'Taux', i: Percent },
                                {
                                    k: 'unpaid_students',
                                    l: 'Élèves débiteurs',
                                    i: Users,
                                },
                            ]"
                            :key="card.k"
                            class="rounded-xl border bg-card p-4"
                        >
                            <component
                                :is="card.i"
                                class="mb-2 size-5 text-primary"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ card.l }}
                            </p>
                            <b class="text-xl">{{
                                card.k === 'collection_rate'
                                    ? `${stats[card.k]} %`
                                    : card.k === 'unpaid_students'
                                      ? stats[card.k]
                                      : money(stats[card.k])
                            }}</b>
                        </article>
                    </section>
                    <section class="rounded-2xl border bg-card shadow-sm">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b p-5"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                                    ><Layers3 class="size-5"
                                /></span>
                                <div>
                                    <h2 class="font-black">
                                        Structures tarifaires
                                    </h2>
                                    <p class="text-xs text-muted-foreground">
                                        Barèmes disponibles pour l’année
                                        scolaire sélectionnée.
                                    </p>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="modal = 'structure'"
                                ><Plus class="mr-2 size-4" />Nouvelle
                                structure</Button
                            >
                        </div>
                        <div
                            v-if="feeStructures.length"
                            class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <article
                                v-for="item in feeStructures"
                                :key="item.id"
                                class="rounded-2xl border bg-background p-4 transition hover:border-primary/40 hover:shadow-md"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <h3 class="font-black">
                                            {{ item.name }}
                                        </h3>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ structureScope(item) }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700"
                                        >{{
                                            item.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}</span
                                    >
                                </div>
                                <p
                                    v-if="item.description"
                                    class="mt-3 line-clamp-2 text-sm text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                                <div
                                    class="mt-4 grid grid-cols-3 gap-2 text-center"
                                >
                                    <div class="rounded-xl bg-muted/60 p-2">
                                        <b>{{ item.components.length }}</b>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Composants
                                        </p>
                                    </div>
                                    <div class="rounded-xl bg-muted/60 p-2">
                                        <b>{{ item.schedule_items.length }}</b>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Échéances
                                        </p>
                                    </div>
                                    <div class="rounded-xl bg-muted/60 p-2">
                                        <b class="text-xs">{{
                                            money(structureAmount(item))
                                        }}</b>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Total
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="component in item.components.slice(
                                            0,
                                            4,
                                        )"
                                        :key="component.id"
                                        class="rounded-full border px-2 py-1 text-[10px]"
                                        >{{ component.label }} ·
                                        {{ money(component.amount) }}</span
                                    >
                                </div>
                                <Button
                                    class="mt-4 w-full"
                                    size="sm"
                                    variant="outline"
                                    @click="generateFromStructure(item)"
                                    >Utiliser cette structure</Button
                                >
                            </article>
                        </div>
                        <div
                            v-else
                            class="p-10 text-center text-muted-foreground"
                        >
                            <Layers3 class="mx-auto mb-3 size-10 opacity-30" />
                            <p class="font-semibold">
                                Aucune structure tarifaire pour cette année.
                            </p>
                            <p class="mt-1 text-sm">
                                Créez d’abord une structure avant de générer les
                                frais des élèves.
                            </p>
                        </div>
                    </section>
                    <form
                        class="rounded-2xl border bg-card p-5 shadow-sm"
                        @submit.prevent="apply"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h2 class="font-black">Filtres des comptes</h2>
                                <p class="text-xs text-muted-foreground">
                                    Affinez les résultats par contexte scolaire.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="resetFilters"
                                ><RotateCcw
                                    class="mr-2 size-4"
                                />Réinitialiser</Button
                            >
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-xs font-bold">Élève</span>
                                <div class="relative">
                                    <Search
                                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                                    /><Input
                                        v-model="filter.search"
                                        class="pl-9"
                                        placeholder="Rechercher un élève…"
                                    />
                                </div>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold"
                                    >Année scolaire</span
                                >
                                <select
                                    v-model="filter.academic_year_id"
                                    class="h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option
                                        v-for="y in academicYears"
                                        :key="y.id"
                                        :value="String(y.id)"
                                    >
                                        {{ y.name }}
                                    </option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold">Site</span>
                                <select
                                    v-model="filter.site_id"
                                    class="h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="">Tous les sites</option>
                                    <option
                                        v-for="s in sites"
                                        :key="s.id"
                                        :value="String(s.id)"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold"
                                    >Niveau et spécialité</span
                                >
                                <select
                                    v-model="filter.level_id"
                                    class="h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="">Tous les niveaux</option>
                                    <option
                                        v-for="l in levels"
                                        :key="l.id"
                                        :value="String(l.id)"
                                    >
                                        {{ levelLabel(l) }}
                                    </option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold">Groupe</span>
                                <select
                                    v-model="filter.group_id"
                                    class="h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="">Tous les groupes</option>
                                    <option
                                        v-for="g in visibleGroups"
                                        :key="g.id"
                                        :value="String(g.id)"
                                    >
                                        {{ g.name
                                        }}{{
                                            groupMeta(g)
                                                ? ` · ${groupMeta(g)}`
                                                : ''
                                        }}
                                    </option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold"
                                    >Statut de paiement</span
                                >
                                <select
                                    v-model="filter.status"
                                    class="h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="">Tous les statuts</option>
                                    <option
                                        v-for="(label, key) in statusLabel"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold"
                                    >Élève précis</span
                                >
                                <select
                                    v-model="filter.student_id"
                                    class="h-9 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="">Tous les élèves</option>
                                    <option
                                        v-for="student in students"
                                        :key="student.id"
                                        :value="String(student.id)"
                                    >
                                        {{ student.first_name }}
                                        {{ student.last_name }}
                                    </option>
                                </select>
                            </label>
                            <div class="flex items-end">
                                <Button type="submit" class="w-full"
                                    >Appliquer les filtres</Button
                                >
                            </div>
                        </div>
                    </form>
                    <div class="overflow-hidden rounded-xl border bg-card">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50 text-left">
                                    <tr>
                                        <th class="p-3">Élève</th>
                                        <th>Niveau</th>
                                        <th>Groupe</th>
                                        <th>Attendu</th>
                                        <th>Payé</th>
                                        <th>Restant</th>
                                        <th>Prochaine échéance</th>
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
                                        <td class="py-3">
                                            <b>{{
                                                levelLabel(a.accountable?.level)
                                            }}</b>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    a.accountable?.level
                                                        ?.code || '—'
                                                }}
                                            </p>
                                        </td>
                                        <td class="min-w-48 py-3">
                                            <b>{{
                                                a.accountable?.group?.name ||
                                                '—'
                                            }}</b>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    groupMeta(
                                                        a.accountable?.group,
                                                    ) ||
                                                    'Aucun détail supplémentaire'
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    a.accountable?.group
                                                        ?.capacity
                                                "
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                Capacité :
                                                {{
                                                    a.accountable.group.capacity
                                                }}
                                                élèves
                                            </p>
                                        </td>
                                        <td>{{ money(a.expected_total) }}</td>
                                        <td class="text-emerald-700">
                                            {{ money(a.paid_total) }}
                                        </td>
                                        <td class="font-bold">
                                            {{ money(a.balance) }}
                                        </td>
                                        <td>{{ a.next_due_date || '—' }}</td>
                                        <td>
                                            <span
                                                class="rounded-full bg-muted px-2 py-1 text-xs"
                                                >{{
                                                    statusLabel[a.status]
                                                }}</span
                                            >
                                        </td>
                                        <td class="p-3">
                                            <Button as-child size="sm"
                                                ><Link
                                                    :href="`/admin/school-payments/accounts/${a.id}`"
                                                    >Ouvrir</Link
                                                ></Button
                                            >
                                        </td>
                                    </tr>
                                    <tr v-if="!accounts.data.length">
                                        <td
                                            colspan="9"
                                            class="p-10 text-center text-muted-foreground"
                                        >
                                            Aucun compte financier généré pour
                                            ces filtres.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap gap-2 border-t p-3">
                            <Link
                                v-for="link in accounts.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="rounded border px-3 py-1 text-xs"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        link.active,
                                    'pointer-events-none opacity-40': !link.url,
                                }"
                                v-html="link.label"
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
                    class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-background p-6 shadow-2xl"
                >
                    <div class="flex justify-between">
                        <h2 class="text-xl font-black">
                            {{
                                modal === 'structure'
                                    ? 'Nouvelle structure tarifaire'
                                    : 'Générer les frais élèves'
                            }}
                        </h2>
                        <button @click="modal = ''" aria-label="Fermer">
                            <X />
                        </button>
                    </div>
                    <form
                        v-if="modal === 'structure'"
                        class="mt-5 space-y-5"
                        @submit.prevent="saveStructure"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <Input
                                v-model="structure.name"
                                required
                                placeholder="Nom de la structure"
                            /><select
                                v-model="structure.academic_year_id"
                                required
                                class="h-9 rounded-md border px-3"
                            >
                                <option
                                    v-for="y in academicYears"
                                    :key="y.id"
                                    :value="String(y.id)"
                                >
                                    {{ y.name }}
                                </option></select
                            ><select
                                v-model="structure.school_level_id"
                                class="h-9 rounded-md border px-3"
                                @change="selectStructureLevel"
                            >
                                <option value="">
                                    Tous niveaux / override ciblé
                                </option>
                                <option
                                    v-for="l in levels"
                                    :key="l.id"
                                    :value="String(l.id)"
                                >
                                    {{ levelLabel(l) }}
                                </option></select
                            ><select
                                v-model="structure.school_group_id"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">
                                    Aucun groupe spécifique
                                </option>
                                <option
                                    v-for="g in structureGroups"
                                    :key="g.id"
                                    :value="String(g.id)"
                                >
                                    {{ g.name
                                    }}{{
                                        groupMeta(g) ? ` · ${groupMeta(g)}` : ''
                                    }}
                                </option></select
                            ><select
                                v-model="structure.student_id"
                                class="h-9 rounded-md border px-3"
                            >
                                <option value="">Aucun élève spécifique</option>
                                <option
                                    v-for="s in students"
                                    :key="s.id"
                                    :value="String(s.id)"
                                >
                                    {{ s.first_name }} {{ s.last_name }}
                                </option></select
                            ><Input
                                v-model="structure.description"
                                placeholder="Description"
                            />
                        </div>
                        <div>
                            <div class="mb-2 flex justify-between">
                                <b>Composants</b
                                ><Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="addComponent"
                                    >Ajouter</Button
                                >
                            </div>
                            <div
                                v-for="(c, i) in structure.components"
                                :key="i"
                                class="mb-2 grid gap-2 sm:grid-cols-4"
                            >
                                <Input
                                    v-model="c.code"
                                    required
                                    placeholder="Code"
                                /><Input
                                    v-model="c.label"
                                    required
                                    placeholder="Libellé"
                                /><Input
                                    v-model="c.amount"
                                    required
                                    type="number"
                                    min="0"
                                    placeholder="Montant"
                                /><label class="flex items-center gap-2 text-sm"
                                    ><input
                                        v-model="c.is_mandatory"
                                        type="checkbox"
                                    />Obligatoire</label
                                >
                            </div>
                        </div>
                        <div>
                            <div class="mb-2 flex justify-between">
                                <b>Échéancier libre</b
                                ><Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="addSchedule"
                                    >Ajouter</Button
                                >
                            </div>
                            <div
                                v-for="(s, i) in structure.schedule"
                                :key="i"
                                class="mb-2 grid gap-2 sm:grid-cols-4"
                            >
                                <Input
                                    v-model="s.label"
                                    required
                                    placeholder="Libellé"
                                /><Input
                                    v-model="s.amount"
                                    required
                                    type="number"
                                    min="0.01"
                                    placeholder="Montant"
                                /><Input
                                    v-model="s.due_date"
                                    required
                                    type="date"
                                /><select
                                    v-model="s.component_index"
                                    class="h-9 rounded-md border px-2"
                                >
                                    <option
                                        v-for="(c, ci) in structure.components"
                                        :key="ci"
                                        :value="ci"
                                    >
                                        {{ c.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <Button :disabled="structure.processing" class="w-full"
                            >Créer la structure</Button
                        >
                    </form>
                    <form
                        v-else
                        class="mt-5 space-y-4"
                        @submit.prevent="generate"
                    >
                        <div class="rounded-xl border bg-muted/40 p-4">
                            <p class="text-xs font-bold text-muted-foreground">
                                Niveau et spécialité ciblés
                            </p>
                            <p class="mt-1 font-black">
                                {{
                                    selectedGenerationLevel
                                        ? levelLabel(selectedGenerationLevel)
                                        : 'Tous les niveaux'
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Chaque spécialité de 1AS, 2AS ou 3AS est traitée
                                comme un niveau financier distinct.
                            </p>
                        </div>
                        <select
                            v-model="generation.academic_year_id"
                            required
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option
                                v-for="y in academicYears"
                                :key="y.id"
                                :value="String(y.id)"
                            >
                                {{ y.name }}
                            </option></select
                        ><select
                            v-model="generation.level_id"
                            class="h-10 w-full rounded-md border px-3"
                            @change="selectGenerationLevel"
                        >
                            <option value="">Tous les niveaux</option>
                            <option
                                v-for="l in levels"
                                :key="l.id"
                                :value="String(l.id)"
                            >
                                {{ levelLabel(l) }}
                            </option></select
                        ><select
                            v-model="generation.group_id"
                            class="h-10 w-full rounded-md border px-3"
                        >
                            <option value="">Tous les groupes</option>
                            <option
                                v-for="g in generationGroups"
                                :key="g.id"
                                :value="String(g.id)"
                            >
                                {{ g.name
                                }}{{ groupMeta(g) ? ` · ${groupMeta(g)}` : '' }}
                            </option></select
                        ><select
                            v-model="generation.school_fee_structure_id"
                            required
                            class="h-10 w-full rounded-md border px-3"
                            @change="selectGenerationStructure"
                        >
                            <option value="">Choisir une structure</option>
                            <option
                                v-for="s in feeStructures"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }} · {{ structureScope(s) }} ·
                                {{ money(structureAmount(s)) }}
                            </option></select
                        ><label class="flex items-center gap-2"
                            ><input
                                v-model="generation.update_existing"
                                type="checkbox"
                            />Mettre à jour uniquement les comptes existants
                            sans transaction</label
                        >
                        <div
                            v-if="
                                selectedGenerationStructure?.components?.some(
                                    (component: any) => !component.is_mandatory,
                                )
                            "
                            class="rounded-xl border p-3"
                        >
                            <b class="text-sm"
                                >Composants optionnels à inclure</b
                            >
                            <label
                                v-for="component in selectedGenerationStructure.components.filter(
                                    (item: any) => !item.is_mandatory,
                                )"
                                :key="component.id"
                                class="mt-2 flex items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="generation.optional_component_ids"
                                    type="checkbox"
                                    :value="component.id"
                                />{{ component.label }} —
                                {{ money(component.amount) }}
                            </label>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="previewGeneration"
                            >Calculer les élèves concernés</Button
                        >
                        <div
                            v-if="preview"
                            class="rounded-xl bg-muted p-4 text-sm"
                        >
                            <b>{{ preview.students }} élève(s)</b> concernés ·
                            {{ preview.new }} nouveau(x) ·
                            {{ preview.existing }} déjà généré(s).
                            <p class="mt-2">
                                Les comptes avec des transactions seront
                                toujours ignorés afin de préserver l’historique.
                            </p>
                        </div>
                        <Button
                            :disabled="!preview || generation.processing"
                            class="w-full"
                            >Confirmer la génération</Button
                        >
                    </form>
                </section>
            </div>
        </div></AdminLayout
    >
</template>
