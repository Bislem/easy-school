<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { appConfirm } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileText, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Expense {
    id: number;
    category: string;
    title: string;
    amount: number | string;
    expense_date: string;
    vendor?: string | null;
    payment_method: string;
    reference?: string | null;
    notes?: string | null;
    receipt_url?: string | null;
    files: Array<{ id: number; url: string; collection: string }>;
    creator?: { name: string };
}
interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    expenses: { data: Expense[]; links: PageLink[] };
    total: number;
    categories: string[];
    filters: Record<string, string | undefined>;
    currency: { symbol: string; code: string };
}>();

const modalOpen = ref(false);
const editing = ref<Expense | null>(null);
const receiptFiles = ref<Array<{ id: number; url: string }>>([]);
const receiptTempFolders = ref<string[]>([]);
const receiptRemovedFiles = ref<number[]>([]);
const filters = ref({
    search: props.filters.search ?? '',
    category: props.filters.category ?? '',
    payment_method: props.filters.payment_method ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});
const form = useForm({
    category: '',
    title: '',
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    vendor: '',
    payment_method: 'cash',
    reference: '',
    notes: '',
    receipt_temp_folders: [] as string[],
    receipt_removed_files: [] as number[],
});
const standardCategories = [
    'Fournitures scolaires',
    'Salaires',
    'Loyer',
    'Électricité et eau',
    'Internet et téléphone',
    'Entretien',
    'Équipement',
    'Logiciels',
    'Transport',
    'Événements',
    'Communication',
    'Autre',
];
const categoryOptions = computed(() =>
    [...new Set([...standardCategories, ...props.categories])].sort(),
);
const paymentLabels: Record<string, string> = {
    cash: 'Espèces',
    bank_transfer: 'Virement bancaire',
    cheque: 'Chèque',
    card: 'Carte bancaire',
    other: 'Autre',
};

watch(receiptTempFolders, (value) => (form.receipt_temp_folders = [...value]), {
    deep: true,
});
function onReceiptRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        receiptRemovedFiles.value.push(data.fileId);
        form.receipt_removed_files = [...receiptRemovedFiles.value];
    }
}
function money(value: number | string) {
    return `${Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${props.currency.symbol}`;
}
function date(value: string) {
    return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR');
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
        category: '',
        payment_method: '',
        date_from: '',
        date_to: '',
    };
    applyFilters();
}
function resetFiles() {
    receiptFiles.value = [];
    receiptTempFolders.value = [];
    receiptRemovedFiles.value = [];
    form.receipt_temp_folders = [];
    form.receipt_removed_files = [];
}
function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    resetFiles();
    form.payment_method = 'cash';
    form.expense_date = new Date().toISOString().slice(0, 10);
    modalOpen.value = true;
}
function openEdit(expense: Expense) {
    editing.value = expense;
    form.clearErrors();
    Object.assign(form, {
        category: expense.category,
        title: expense.title,
        amount: String(expense.amount),
        expense_date: expense.expense_date,
        vendor: expense.vendor ?? '',
        payment_method: expense.payment_method,
        reference: expense.reference ?? '',
        notes: expense.notes ?? '',
    });
    resetFiles();
    receiptFiles.value = expense.files
        .filter((file) => file.collection === 'receipt')
        .map((file) => ({ id: file.id, url: file.url }));
    modalOpen.value = true;
}
function closeModal() {
    modalOpen.value = false;
    editing.value = null;
    form.clearErrors();
}
function submit() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editing.value) {
        form.put(`/admin/expenses/${editing.value.id}`, options);
    } else {
        form.post('/admin/expenses', options);
    }
}
async function remove(expense: Expense) {
    if (
        await appConfirm(
            `Supprimer définitivement la dépense « ${expense.title} » ?`,
            {
                title: 'Supprimer la dépense',
                tone: 'danger',
                confirmText: 'Supprimer',
            },
        )
    )
        router.delete(`/admin/expenses/${expense.id}`, {
            preserveScroll: true,
        });
}
function paginationLabel(label: string) {
    return label
        .replace('&laquo; Previous', 'Précédent')
        .replace('Next &raquo;', 'Suivant');
}
</script>

<template>
    <Head title="Dépenses de l’école" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">Dépenses de l’école</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Enregistrez et suivez toutes les charges de
                        l’établissement.
                    </p>
                </div>
                <Button class="w-full sm:w-auto" @click="openCreate"
                    ><Plus class="mr-2 size-4" />Ajouter une dépense</Button
                >
            </header>

            <section class="rounded-xl border bg-card p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="relative sm:col-span-2">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="filters.search"
                            class="pl-9"
                            placeholder="Libellé, fournisseur, référence…"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <select
                        v-model="filters.category"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Toutes les catégories</option>
                        <option
                            v-for="category in categoryOptions"
                            :key="category"
                            :value="category"
                        >
                            {{ category }}
                        </option>
                    </select>
                    <select
                        v-model="filters.payment_method"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tous les paiements</option>
                        <option
                            v-for="(label, key) in paymentLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                    <div class="flex gap-2">
                        <Button class="flex-1" size="sm" @click="applyFilters"
                            >Filtrer</Button
                        ><Button
                            class="flex-1"
                            size="sm"
                            variant="outline"
                            @click="resetFilters"
                            >Effacer</Button
                        >
                    </div>
                    <div>
                        <Label>Date de début</Label
                        ><Input
                            v-model="filters.date_from"
                            class="mt-1"
                            type="date"
                        />
                    </div>
                    <div>
                        <Label>Date de fin</Label
                        ><Input
                            v-model="filters.date_to"
                            class="mt-1"
                            type="date"
                        />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    Total des dépenses affichées
                </p>
                <p class="mt-1 text-2xl font-bold">{{ money(total) }}</p>
            </section>

            <section
                class="hidden overflow-x-auto rounded-xl border bg-card md:block"
            >
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Dépense</th>
                            <th class="px-4 py-3 text-left">Fournisseur</th>
                            <th class="px-4 py-3 text-left">Paiement</th>
                            <th class="px-4 py-3 text-right">Montant</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="expense in expenses.data" :key="expense.id">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ date(expense.expense_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ expense.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ expense.category
                                    }}<span v-if="expense.reference">
                                        · {{ expense.reference }}</span
                                    >
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                {{ expense.vendor || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ paymentLabels[expense.payment_method] }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-semibold whitespace-nowrap"
                            >
                                {{ money(expense.amount) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Button
                                    v-if="expense.receipt_url"
                                    as-child
                                    size="icon"
                                    variant="ghost"
                                    ><a
                                        :href="expense.receipt_url"
                                        target="_blank"
                                        title="Voir le justificatif"
                                        ><FileText class="size-4" /></a></Button
                                ><Button
                                    size="icon"
                                    variant="ghost"
                                    title="Modifier"
                                    @click="openEdit(expense)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    size="icon"
                                    variant="ghost"
                                    class="text-destructive"
                                    title="Supprimer"
                                    @click="remove(expense)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </td>
                        </tr>
                        <tr v-if="!expenses.data.length">
                            <td
                                colspan="6"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                Aucune dépense trouvée.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="space-y-3 md:hidden">
                <article
                    v-for="expense in expenses.data"
                    :key="expense.id"
                    class="rounded-xl border bg-card p-4"
                >
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ expense.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ expense.category }} ·
                                {{ date(expense.expense_date) }}
                            </p>
                        </div>
                        <p class="font-bold whitespace-nowrap">
                            {{ money(expense.amount) }}
                        </p>
                    </div>
                    <div class="mt-3 text-sm">
                        <p>
                            {{ expense.vendor || 'Fournisseur non renseigné' }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ paymentLabels[expense.payment_method] }}
                        </p>
                    </div>
                    <div class="mt-3 flex justify-end gap-1">
                        <Button
                            v-if="expense.receipt_url"
                            as-child
                            size="sm"
                            variant="outline"
                            ><a :href="expense.receipt_url" target="_blank"
                                ><FileText class="mr-2 size-4" />Justificatif</a
                            ></Button
                        ><Button
                            size="icon"
                            variant="ghost"
                            @click="openEdit(expense)"
                            ><Pencil class="size-4" /></Button
                        ><Button
                            size="icon"
                            variant="ghost"
                            class="text-destructive"
                            @click="remove(expense)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                </article>
                <p
                    v-if="!expenses.data.length"
                    class="py-10 text-center text-muted-foreground"
                >
                    Aucune dépense trouvée.
                </p>
            </section>

            <nav v-if="expenses.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="link in expenses.links" :key="link.label"
                    ><Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border px-3 py-2 text-sm"
                        :class="{
                            'bg-primary text-primary-foreground': link.active,
                        }"
                        >{{ paginationLabel(link.label) }}</Link
                    ><span
                        v-else
                        class="rounded-md border px-3 py-2 text-sm opacity-40"
                        >{{ paginationLabel(link.label) }}</span
                    ></template
                >
            </nav>
        </main>

        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="closeModal"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-2xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editing
                                    ? 'Modifier la dépense'
                                    : 'Nouvelle dépense'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Renseignez les informations comptables disponibles.
                        </p>
                    </div>
                    <Button size="icon" variant="ghost" @click="closeModal"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <Label for="title">Libellé</Label
                            ><Input
                                id="title"
                                v-model="form.title"
                                class="mt-1"
                                required
                                maxlength="255"
                                placeholder="Achat de fournitures"
                            /><InputError
                                :message="form.errors.title"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="category">Catégorie</Label
                            ><Input
                                id="category"
                                v-model="form.category"
                                class="mt-1"
                                list="expense-categories"
                                required
                                maxlength="100"
                            /><datalist id="expense-categories">
                                <option
                                    v-for="category in categoryOptions"
                                    :key="category"
                                    :value="category"
                                /></datalist
                            ><InputError
                                :message="form.errors.category"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="amount">Montant</Label
                            ><Input
                                id="amount"
                                v-model="form.amount"
                                class="mt-1"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                            /><InputError
                                :message="form.errors.amount"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="expense_date">Date</Label
                            ><Input
                                id="expense_date"
                                v-model="form.expense_date"
                                class="mt-1"
                                type="date"
                                required
                            /><InputError
                                :message="form.errors.expense_date"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="payment_method">Mode de paiement</Label
                            ><select
                                id="payment_method"
                                v-model="form.payment_method"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                required
                            >
                                <option
                                    v-for="(label, key) in paymentLabels"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option></select
                            ><InputError
                                :message="form.errors.payment_method"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="vendor"
                                >Fournisseur / bénéficiaire</Label
                            ><Input
                                id="vendor"
                                v-model="form.vendor"
                                class="mt-1"
                                maxlength="255"
                            />
                        </div>
                        <div>
                            <Label for="reference">Référence / facture</Label
                            ><Input
                                id="reference"
                                v-model="form.reference"
                                class="mt-1"
                                maxlength="255"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label for="notes">Notes</Label
                            ><textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="5000"
                                class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label>Justificatif (facultatif)</Label
                            ><FileUpload
                                :key="editing?.id ?? 'new'"
                                v-model="receiptTempFolders"
                                :initial-files="receiptFiles"
                                :allow-multiple="false"
                                :max-files="1"
                                :allowed-file-types="[
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                    'application/pdf',
                                ]"
                                collection="receipt"
                                theme="light"
                                width="100%"
                                @file-removed="onReceiptRemoved"
                            /><InputError
                                :message="form.errors.receipt_temp_folders"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div
                        class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModal"
                            >Annuler</Button
                        ><Button type="submit" :disabled="form.processing">{{
                            editing
                                ? 'Enregistrer les modifications'
                                : 'Ajouter la dépense'
                        }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
