<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Car = { id: number; make: string; model: string; license_plate: string };
type Expense = {
    id: number;
    type: 'agency' | 'maintenance';
    category: string;
    title: string;
    amount: number | string;
    expense_date: string;
    vendor?: string;
    reference?: string;
    mileage?: number;
    next_service_date?: string;
    notes?: string;
    car_id?: number;
    car?: Car;
    creator?: { name: string };
};

const props = defineProps<{
    expenses: {
        data: Expense[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    total: number;
    categories: string[];
    cars: Car[];
    filters: Record<string, string | undefined>;
    currency: { symbol: string; code: string };
}>();

const showForm = ref(false);
const editing = ref<Expense | null>(null);
const filters = ref({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    category: props.filters.category ?? '',
    car_id: props.filters.car_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});
const form = useForm({
    type: 'agency',
    category: '',
    title: '',
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    car_id: '',
    vendor: '',
    reference: '',
    mileage: '',
    next_service_date: '',
    notes: '',
});
const defaultCategories = [
    'Loyer',
    'Assurance',
    'Marketing',
    'Salaires',
    'Services',
    'Taxes',
    'Carburant',
    'Réparation',
    'Vidange',
    'Pneus',
    'Pièces',
];
const categoryOptions = computed(() =>
    [...new Set([...defaultCategories, ...props.categories])].sort(),
);

function fmtMoney(value: number | string) {
    return `${props.currency.symbol}${Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function applyFilters() {
    router.get(
        '/admin/expenses',
        Object.fromEntries(
            Object.entries(filters.value).filter(([, value]) => value),
        ),
        { preserveState: true, replace: true },
    );
}
function resetFilters() {
    filters.value = {
        search: '',
        type: '',
        category: '',
        car_id: '',
        date_from: '',
        date_to: '',
    };
    applyFilters();
}
function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.type = 'agency';
    form.expense_date = new Date().toISOString().slice(0, 10);
    showForm.value = true;
}
function openEdit(expense: Expense) {
    editing.value = expense;
    form.type = expense.type;
    form.category = expense.category;
    form.title = expense.title;
    form.amount = String(expense.amount);
    form.expense_date = expense.expense_date;
    form.car_id = expense.car_id ? String(expense.car_id) : '';
    form.vendor = expense.vendor ?? '';
    form.reference = expense.reference ?? '';
    form.mileage = expense.mileage ? String(expense.mileage) : '';
    form.next_service_date = expense.next_service_date ?? '';
    form.notes = expense.notes ?? '';
    form.clearErrors();
    showForm.value = true;
}
function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            editing.value = null;
            form.reset();
        },
    };
    if (editing.value) {
        form.put(`/admin/expenses/${editing.value.id}`, options);
    } else {
        form.post('/admin/expenses', options);
    }
}
function remove(expense: Expense) {
    if (window.confirm(`Supprimer la dépense « ${expense.title} » ?`))
        router.delete(`/admin/expenses/${expense.id}`, {
            preserveScroll: true,
        });
}
</script>

<template>
    <Head title="Dépenses" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Dépenses</h1>
                    <p class="text-sm text-muted-foreground">
                        Gérez les frais de l'agence et les coûts d'entretien des
                        véhicules.
                    </p>
                </div>
                <Button @click="showForm ? (showForm = false) : openCreate()">{{
                    showForm ? 'Fermer' : 'Ajouter une dépense'
                }}</Button>
            </div>

            <form
                v-if="showForm"
                class="grid gap-4 rounded-lg border bg-card p-5 md:grid-cols-2 lg:grid-cols-3"
                @submit.prevent="submit"
            >
                <div class="md:col-span-2 lg:col-span-3">
                    <h2 class="font-semibold">
                        {{
                            editing ? 'Modifier la dépense' : 'Nouvelle dépense'
                        }}
                    </h2>
                </div>
                <label class="grid gap-1 text-sm font-medium"
                    >Type<select
                        v-model="form.type"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="agency">Frais d'agence</option>
                        <option value="maintenance">Entretien véhicule</option>
                    </select></label
                >
                <label class="grid gap-1 text-sm font-medium"
                    >Catégorie<Input
                        v-model="form.category"
                        list="expense-categories"
                        required
                        maxlength="100"
                    /><datalist id="expense-categories">
                        <option
                            v-for="category in categoryOptions"
                            :key="category"
                            :value="category"
                        /></datalist
                    ><span
                        v-if="form.errors.category"
                        class="text-xs text-destructive"
                        >{{ form.errors.category }}</span
                    ></label
                >
                <label class="grid gap-1 text-sm font-medium"
                    >Libellé<Input
                        v-model="form.title"
                        required
                        maxlength="255"
                    /><span
                        v-if="form.errors.title"
                        class="text-xs text-destructive"
                        >{{ form.errors.title }}</span
                    ></label
                >
                <label class="grid gap-1 text-sm font-medium"
                    >Montant<Input
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    /><span
                        v-if="form.errors.amount"
                        class="text-xs text-destructive"
                        >{{ form.errors.amount }}</span
                    ></label
                >
                <label class="grid gap-1 text-sm font-medium"
                    >Date<Input
                        v-model="form.expense_date"
                        type="date"
                        required
                /></label>
                <label class="grid gap-1 text-sm font-medium"
                    >Véhicule
                    <span class="font-normal text-muted-foreground">{{
                        form.type === 'maintenance'
                            ? '(obligatoire)'
                            : '(facultatif)'
                    }}</span
                    ><select
                        v-model="form.car_id"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">Aucun véhicule</option>
                        <option
                            v-for="car in cars"
                            :key="car.id"
                            :value="String(car.id)"
                        >
                            {{ car.make }} {{ car.model }} —
                            {{ car.license_plate }}
                        </option></select
                    ><span
                        v-if="form.errors.car_id"
                        class="text-xs text-destructive"
                        >{{ form.errors.car_id }}</span
                    ></label
                >
                <label class="grid gap-1 text-sm font-medium"
                    >Fournisseur<Input v-model="form.vendor" maxlength="255"
                /></label>
                <label class="grid gap-1 text-sm font-medium"
                    >Référence / facture<Input
                        v-model="form.reference"
                        maxlength="255"
                /></label>
                <label
                    v-if="form.type === 'maintenance'"
                    class="grid gap-1 text-sm font-medium"
                    >Kilométrage<Input
                        v-model="form.mileage"
                        type="number"
                        min="0"
                /></label>
                <label
                    v-if="form.type === 'maintenance'"
                    class="grid gap-1 text-sm font-medium"
                    >Prochain entretien<Input
                        v-model="form.next_service_date"
                        type="date"
                /></label>
                <label
                    class="grid gap-1 text-sm font-medium md:col-span-2 lg:col-span-3"
                    >Détails<textarea
                        v-model="form.notes"
                        rows="3"
                        maxlength="5000"
                        class="rounded-md border bg-background px-3 py-2"
                    />
                </label>
                <div class="flex gap-2 md:col-span-2 lg:col-span-3">
                    <Button type="submit" :disabled="form.processing">{{
                        editing ? 'Enregistrer' : 'Ajouter la dépense'
                    }}</Button
                    ><Button
                        type="button"
                        variant="outline"
                        @click="showForm = false"
                        >Annuler</Button
                    >
                </div>
            </form>

            <div
                class="grid gap-3 rounded-lg border p-4 md:grid-cols-3 lg:grid-cols-6"
            >
                <Input
                    v-model="filters.search"
                    placeholder="Rechercher..."
                    @keyup.enter="applyFilters"
                />
                <select
                    v-model="filters.type"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">Tous les types</option>
                    <option value="agency">Agence</option>
                    <option value="maintenance">Entretien</option>
                </select>
                <select
                    v-model="filters.category"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">Toutes catégories</option>
                    <option
                        v-for="category in categories"
                        :key="category"
                        :value="category"
                    >
                        {{ category }}
                    </option>
                </select>
                <select
                    v-model="filters.car_id"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">Tous véhicules</option>
                    <option
                        v-for="car in cars"
                        :key="car.id"
                        :value="String(car.id)"
                    >
                        {{ car.make }} {{ car.model }}
                    </option>
                </select>
                <Input
                    v-model="filters.date_from"
                    type="date"
                    title="Date de début"
                /><Input
                    v-model="filters.date_to"
                    type="date"
                    title="Date de fin"
                />
                <div class="flex gap-2 md:col-span-3 lg:col-span-6">
                    <Button size="sm" @click="applyFilters">Filtrer</Button
                    ><Button size="sm" variant="outline" @click="resetFilters"
                        >Réinitialiser</Button
                    >
                </div>
            </div>

            <div class="rounded-lg border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    Total selon les filtres
                </p>
                <p class="text-2xl font-bold">{{ fmtMoney(total) }}</p>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Dépense</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">
                                Véhicule / fournisseur
                            </th>
                            <th class="px-4 py-3 text-right">Montant</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="expense in expenses.data" :key="expense.id">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{
                                    new Date(
                                        `${expense.expense_date}T00:00:00`,
                                    ).toLocaleDateString('fr-FR')
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ expense.title }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ expense.category
                                    }}<span v-if="expense.reference">
                                        · {{ expense.reference }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="
                                        expense.type === 'maintenance'
                                            ? 'bg-orange-100 text-orange-700'
                                            : 'bg-blue-100 text-blue-700'
                                    "
                                    >{{
                                        expense.type === 'maintenance'
                                            ? 'Entretien'
                                            : 'Agence'
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="expense.car">
                                    {{ expense.car.make }}
                                    {{ expense.car.model }} ({{
                                        expense.car.license_plate
                                    }})
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ expense.vendor || '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">
                                {{ fmtMoney(expense.amount) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    @click="openEdit(expense)"
                                    >Modifier</Button
                                ><Button
                                    size="sm"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="remove(expense)"
                                    >Supprimer</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!expenses.data.length">
                            <td
                                colspan="6"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                Aucune dépense trouvée.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap gap-1">
                <template v-for="link in expenses.links" :key="link.label"
                    ><Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded border px-3 py-1.5 text-sm"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : ''
                        "
                        ><span v-html="link.label" /></Link
                    ><span
                        v-else
                        class="rounded border px-3 py-1.5 text-sm opacity-40"
                        v-html="link.label"
                /></template>
            </div>
        </main>
    </AdminLayout>
</template>
