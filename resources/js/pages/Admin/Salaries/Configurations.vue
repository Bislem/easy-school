<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appConfirm } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    configurations: Array,
    salaryItems: Array,
    categories: Array,
    calculationTypes: Array,
    currency: Object,
});
const tab = ref<'items' | 'configurations'>('items');
const itemEditor = ref(false),
    configEditor = ref(false),
    editingItem = ref<any>(null),
    editingConfig = ref<any>(null);
const categoryLabels: any = {
    BASE_SALARY: 'Salaire de base',
    PRIME: 'Prime',
    INDEMNITY: 'Indemnité',
    OTHER_EARNING: 'Autre gain',
    DEDUCTION: 'Retenue',
};
const calculationLabels: any = {
    FIXED_MONTHLY: 'Mensuel fixe',
    HOURLY: 'Horaire',
    DAILY: 'Journalier',
    PER_SESSION: 'Par séance',
};
const natureLabels: any = {
    BASE_SALARY: 'Salaire de base',
    PRIME: 'Prime',
    INDEMNITY: 'Indemnité',
    OVERTIME: 'Heures supplémentaires',
    HOURLY_WORK: 'Travail horaire',
    FAMILY_ALLOWANCE: 'Allocation familiale',
    EXPENSE_REIMBURSEMENT: 'Remboursement de frais',
    OTHER_EARNING: 'Autre gain',
    DEDUCTION: 'Retenue',
};
const money = (v: any) =>
    `${Number(v || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} ${props.currency?.symbol}`;
const itemForm = useForm({
    name: '',
    code: '',
    category: 'BASE_SALARY',
    calculation_type: 'FIXED_MONTHLY',
    default_amount: '',
    active: true,
    description: '',
    subject_to_cnas: true,
    subject_to_irg: true,
    salary_item_nature: 'OTHER_EARNING',
    irg_treatment: 'MONTHLY',
});
const configForm = useForm({
    name: '',
    effective_from: new Date().toISOString().slice(0, 10),
    effective_to: '',
    notes: '',
    items: [] as any[],
});

function openItem(item: any = null) {
    editingItem.value = item;
    itemForm.clearErrors();
    itemForm.defaults(
        item
            ? {
                  name: item.name,
                  code: item.code ?? '',
                  category: item.category,
                  calculation_type: item.calculation_type,
                  default_amount: String(item.default_amount ?? ''),
                  active: item.active,
                  description: item.description ?? '',
                  subject_to_cnas: item.subject_to_cnas,
                  subject_to_irg: item.subject_to_irg,
                  salary_item_nature: item.salary_item_nature,
                  irg_treatment: item.irg_treatment,
              }
            : {
                  name: '',
                  code: '',
                  category: 'BASE_SALARY',
                  calculation_type: 'FIXED_MONTHLY',
                  default_amount: '',
                  active: true,
                  description: '',
                  subject_to_cnas: true,
                  subject_to_irg: true,
                  salary_item_nature: 'OTHER_EARNING',
                  irg_treatment: 'MONTHLY',
              },
    );
    itemForm.reset();
    itemEditor.value = true;
}
function saveItem() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (itemEditor.value = false),
    };
    editingItem.value
        ? itemForm.put(`/admin/salaries/items/${editingItem.value.id}`, options)
        : itemForm.post('/admin/salaries/items', options);
}
async function removeItem(item: any) {
    if (
        await appConfirm(`Supprimer la rubrique « ${item.name} » ?`, {
            title: 'Supprimer la rubrique',
            tone: 'danger',
            confirmText: 'Supprimer',
        })
    )
        router.delete(`/admin/salaries/items/${item.id}`, {
            preserveScroll: true,
        });
}
function openConfig(config: any = null) {
    editingConfig.value = config;
    configForm.clearErrors();
    configForm.defaults(
        config
            ? {
                  name: config.name,
                  effective_from: config.effective_from,
                  effective_to: config.effective_to ?? '',
                  notes: config.notes ?? '',
                  items: config.items.map((i: any, index: number) => ({
                      salary_item_id: i.id,
                      amount_override: i.pivot.amount_override ?? '',
                      display_order: index,
                  })),
              }
            : {
                  name: '',
                  effective_from: new Date().toISOString().slice(0, 10),
                  effective_to: '',
                  notes: '',
                  items: [],
              },
    );
    configForm.reset();
    configEditor.value = true;
}
function addConfigItem() {
    const used = new Set(configForm.items.map((i) => String(i.salary_item_id)));
    const item = (props.salaryItems as any[]).find(
        (i) => i.active && !used.has(String(i.id)),
    );
    if (item)
        configForm.items.push({
            salary_item_id: item.id,
            amount_override: '',
            display_order: configForm.items.length,
        });
}
function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= configForm.items.length) return;
    [configForm.items[index], configForm.items[target]] = [
        configForm.items[target],
        configForm.items[index],
    ];
    configForm.items.forEach((i, index) => (i.display_order = index));
}
function itemById(id: any) {
    return (props.salaryItems as any[]).find(
        (i) => String(i.id) === String(id),
    );
}
function saveConfig() {
    configForm.items.forEach((i, index) => (i.display_order = index));
    const options = {
        preserveScroll: true,
        onSuccess: () => (configEditor.value = false),
    };
    editingConfig.value
        ? configForm.put(
              `/admin/salaries/configurations/${editingConfig.value.id}`,
              options,
          )
        : configForm.post('/admin/salaries/configurations', options);
}
async function removeConfig(config: any) {
    if (
        await appConfirm(`Supprimer la configuration « ${config.name} » ?`, {
            title: 'Supprimer la configuration',
            tone: 'danger',
            confirmText: 'Supprimer',
        })
    )
        router.delete(`/admin/salaries/configurations/${config.id}`, {
            preserveScroll: true,
        });
}
</script>
<template>
    <Head title="Paramètres de paie" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <header class="flex items-center justify-between">
                    <div>
                        <Link
                            href="/admin/salaries"
                            class="mb-2 inline-flex items-center text-sm text-muted-foreground"
                            ><ArrowLeft class="mr-1 size-4" />Retour à la
                            paie</Link
                        >
                        <h1 class="text-2xl font-semibold">
                            Paramètres de paie
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Rubriques réutilisables et modèles facultatifs.
                        </p>
                    </div>
                    <Button @click="tab === 'items' ? openItem() : openConfig()"
                        ><Plus class="mr-2 size-4" />{{
                            tab === 'items'
                                ? 'Nouvelle rubrique'
                                : 'Nouvelle configuration'
                        }}</Button
                    >
                </header>
                <div class="flex gap-2 border-b">
                    <button
                        class="px-4 py-2 text-sm font-medium"
                        :class="
                            tab === 'items'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-muted-foreground'
                        "
                        @click="tab = 'items'"
                    >
                        Rubriques de salaire</button
                    ><button
                        class="px-4 py-2 text-sm font-medium"
                        :class="
                            tab === 'configurations'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-muted-foreground'
                        "
                        @click="tab = 'configurations'"
                    >
                        Configurations de salaire
                    </button>
                </div>
                <section
                    v-if="tab === 'items'"
                    class="overflow-hidden rounded-xl border bg-card"
                >
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-3">Rubrique</th>
                                <th>Type</th>
                                <th>Calcul</th>
                                <th>Montant / Taux</th>
                                <th>État</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in salaryItems"
                                :key="item.id"
                                class="border-t"
                            >
                                <td class="p-3">
                                    <b>{{ item.name }}</b
                                    ><small
                                        v-if="item.code"
                                        class="block text-muted-foreground"
                                        >{{ item.code }}</small
                                    >
                                </td>
                                <td>{{ categoryLabels[item.category] }}</td>
                                <td>
                                    {{
                                        calculationLabels[item.calculation_type]
                                    }}
                                </td>
                                <td>
                                    {{ money(item.default_amount)
                                    }}<span
                                        v-if="
                                            item.calculation_type === 'HOURLY'
                                        "
                                    >
                                        / h</span
                                    >
                                </td>
                                <td>
                                    {{ item.active ? 'Active' : 'Inactive' }}
                                </td>
                                <td class="space-x-1 text-right">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        @click="openItem(item)"
                                        ><Pencil class="size-4" /></Button
                                    ><Button
                                        size="icon"
                                        variant="ghost"
                                        class="text-destructive"
                                        @click="removeItem(item)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </td>
                            </tr>
                            <tr v-if="!salaryItems?.length">
                                <td
                                    colspan="6"
                                    class="p-10 text-center text-muted-foreground"
                                >
                                    Créez votre première rubrique de salaire.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section v-else class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="config in configurations"
                        :key="config.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex justify-between">
                            <div>
                                <h2 class="font-semibold">{{ config.name }}</h2>
                                <p class="text-xs text-muted-foreground">
                                    Valable depuis {{ config.effective_from }}
                                </p>
                            </div>
                            <div>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    @click="openConfig(config)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    size="icon"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="removeConfig(config)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                        </div>
                        <div class="mt-3 divide-y rounded-lg border">
                            <div
                                v-for="item in config.items"
                                :key="item.id"
                                class="flex justify-between p-2 text-sm"
                            >
                                <span
                                    >{{ item.name
                                    }}<small
                                        class="block text-muted-foreground"
                                        >{{
                                            calculationLabels[
                                                item.calculation_type
                                            ]
                                        }}</small
                                    ></span
                                ><b>{{
                                    money(
                                        item.pivot.amount_override ??
                                            item.default_amount,
                                    )
                                }}</b>
                            </div>
                        </div>
                    </article>
                    <p
                        v-if="!configurations?.length"
                        class="col-span-2 rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                    >
                        Les configurations sont facultatives. Créez-en une pour
                        précharger plusieurs rubriques.
                    </p>
                </section>
            </div>
        </main>
        <div
            v-if="itemEditor"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4"
        >
            <form
                class="mx-auto my-8 max-w-xl space-y-4 rounded-xl bg-background p-6"
                @submit.prevent="saveItem"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">
                        {{ editingItem ? 'Modifier' : 'Créer' }} une rubrique
                    </h2>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="itemEditor = false"
                        ><X
                    /></Button>
                </div>
                <label class="block"
                    ><Label>Nom</Label
                    ><Input v-model="itemForm.name" /><InputError
                        :message="itemForm.errors.name"
                /></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        ><Label>Code (facultatif)</Label
                        ><Input v-model="itemForm.code" /></label
                    ><label
                        ><Label>Type</Label
                        ><select
                            v-model="itemForm.category"
                            class="h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option v-for="c in categories" :value="c">
                                {{ categoryLabels[c] }}
                            </option>
                        </select></label
                    ><label
                        ><Label>Mode de calcul</Label
                        ><select
                            v-model="itemForm.calculation_type"
                            class="h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option v-for="c in calculationTypes" :value="c">
                                {{ calculationLabels[c] }}
                            </option>
                        </select></label
                    ><label
                        ><Label>Montant / taux par défaut</Label
                        ><Input
                            v-model="itemForm.default_amount"
                            type="number"
                            min="0"
                            step="0.01"
                    /></label>
                    <label
                        ><Label>Nature paie</Label
                        ><select
                            v-model="itemForm.salary_item_nature"
                            class="h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="(label, value) in natureLabels"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select></label
                    >
                    <label
                        ><Label>Traitement IRG</Label
                        ><select
                            v-model="itemForm.irg_treatment"
                            class="h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option value="MONTHLY">
                                Rémunération mensuelle
                            </option>
                            <option value="OCCASIONAL">
                                Rémunération occasionnelle
                            </option>
                            <option value="EXEMPT">Exonérée</option>
                        </select></label
                    >
                </div>
                <label class="block"
                    ><Label>Description</Label
                    ><textarea
                        v-model="itemForm.description"
                        class="w-full rounded-md border bg-background p-2"
                        rows="3"
                    />
                </label>
                <div class="flex flex-wrap gap-5 rounded-lg border p-3">
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="itemForm.subject_to_cnas"
                            type="checkbox"
                        />
                        Soumise à la CNAS</label
                    ><label class="flex items-center gap-2"
                        ><input
                            v-model="itemForm.subject_to_irg"
                            type="checkbox"
                        />
                        Soumise à l’IRG</label
                    >
                </div>
                <label class="flex items-center gap-2"
                    ><input v-model="itemForm.active" type="checkbox" />
                    Rubrique active</label
                ><InputError :message="itemForm.errors.item" />
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="itemEditor = false"
                        >Annuler</Button
                    ><Button :disabled="itemForm.processing"
                        >Enregistrer</Button
                    >
                </div>
            </form>
        </div>
        <div
            v-if="configEditor"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4"
        >
            <form
                class="mx-auto my-8 max-w-3xl space-y-4 rounded-xl bg-background p-6"
                @submit.prevent="saveConfig"
            >
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">
                        {{ editingConfig ? 'Modifier' : 'Créer' }} une
                        configuration
                    </h2>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="configEditor = false"
                        ><X
                    /></Button>
                </div>
                <label class="block"
                    ><Label>Nom</Label
                    ><Input v-model="configForm.name" /><InputError
                        :message="configForm.errors.name"
                /></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        ><Label>Valide à partir du</Label
                        ><Input
                            v-model="configForm.effective_from"
                            type="date" /></label
                    ><label
                        ><Label>Valide jusqu'au (facultatif)</Label
                        ><Input v-model="configForm.effective_to" type="date"
                    /></label>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <Label>Rubriques</Label
                        ><Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="addConfigItem"
                            ><Plus class="mr-1 size-4" />Ajouter</Button
                        >
                    </div>
                    <div class="overflow-hidden rounded-lg border">
                        <div
                            class="grid grid-cols-[1fr_150px_100px] gap-2 bg-muted p-2 text-xs font-semibold"
                        >
                            <span>Rubrique · Type · Calcul</span
                            ><span>Montant / Taux</span><span>Ordre</span>
                        </div>
                        <div
                            v-for="(row, index) in configForm.items"
                            :key="index"
                            class="grid grid-cols-[1fr_150px_100px] items-center gap-2 border-t p-2"
                        >
                            <select
                                v-model="row.salary_item_id"
                                class="h-9 rounded-md border bg-background px-2"
                            >
                                <option
                                    v-for="item in salaryItems"
                                    :value="item.id"
                                >
                                    {{ item.name }} ·
                                    {{ categoryLabels[item.category] }} ·
                                    {{
                                        calculationLabels[item.calculation_type]
                                    }}
                                </option></select
                            ><Input
                                v-model="row.amount_override"
                                type="number"
                                min="0"
                                step="0.01"
                                :placeholder="
                                    String(
                                        itemById(row.salary_item_id)
                                            ?.default_amount ?? '',
                                    )
                                "
                            />
                            <div>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    @click="move(index, -1)"
                                    ><ArrowUp class="size-4" /></Button
                                ><Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    @click="move(index, 1)"
                                    ><ArrowDown class="size-4" /></Button
                                ><Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="configForm.items.splice(index, 1)"
                                    ><X class="size-4"
                                /></Button>
                            </div>
                        </div>
                    </div>
                    <InputError :message="configForm.errors.items" />
                </div>
                <label class="block"
                    ><Label>Notes</Label
                    ><textarea
                        v-model="configForm.notes"
                        class="w-full rounded-md border bg-background p-2"
                        rows="3"
                    /></label
                ><InputError :message="configForm.errors.configuration" />
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="configEditor = false"
                        >Annuler</Button
                    ><Button :disabled="configForm.processing"
                        >Enregistrer</Button
                    >
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
