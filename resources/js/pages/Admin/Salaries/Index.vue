<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileText, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Employee {
    id: number;
    name: string;
    role: 'teacher' | 'employee';
    job_title?: string | null;
}
interface Salary {
    id: number;
    employee_id: number;
    amount: string | number;
    expense_date: string;
    salary_period: string;
    payment_method: string;
    reference?: string | null;
    notes?: string | null;
    receipt_url?: string | null;
    employee: Employee;
    files: Array<{ id: number; url: string; collection: string }>;
}
interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}
const props = defineProps<{
    salaries: { data: Salary[]; links: PageLink[] };
    employees: Employee[];
    total: number;
    filters: { search?: string; employee_id?: string; period?: string };
    currency: { symbol: string; code: string };
}>();

const modalOpen = ref(false);
const editing = ref<Salary | null>(null);
const receiptFiles = ref<Array<{ id: number; url: string }>>([]);
const receiptTempFolders = ref<string[]>([]);
const receiptRemovedFiles = ref<number[]>([]);
const filters = ref({
    search: props.filters.search ?? '',
    employee_id: props.filters.employee_id ?? '',
    period: props.filters.period ?? '',
});
const form = useForm({
    employee_id: '',
    amount: '',
    salary_period: new Date().toISOString().slice(0, 7),
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
    receipt_temp_folders: [] as string[],
    receipt_removed_files: [] as number[],
});
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
function role(employee: Employee) {
    return employee.role === 'teacher'
        ? 'Enseignant'
        : employee.job_title || 'Employé';
}
function money(value: string | number) {
    return `${Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${props.currency.symbol}`;
}
function period(value: string) {
    return new Date(`${value.slice(0, 7)}-01T00:00:00`).toLocaleDateString(
        'fr-FR',
        { month: 'long', year: 'numeric' },
    );
}
function date(value: string) {
    return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR');
}
function applyFilters() {
    router.get(
        '/admin/salaries',
        Object.fromEntries(
            Object.entries(filters.value).filter(([, value]) => value),
        ),
        { preserveState: true, replace: true },
    );
}
function resetFilters() {
    filters.value = { search: '', employee_id: '', period: '' };
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
    form.salary_period = new Date().toISOString().slice(0, 7);
    form.expense_date = new Date().toISOString().slice(0, 10);
    form.payment_method = 'bank_transfer';
    modalOpen.value = true;
}
function openEdit(salary: Salary) {
    editing.value = salary;
    form.clearErrors();
    Object.assign(form, {
        employee_id: String(salary.employee_id),
        amount: String(salary.amount),
        salary_period: salary.salary_period.slice(0, 7),
        expense_date: salary.expense_date,
        payment_method: salary.payment_method,
        reference: salary.reference ?? '',
        notes: salary.notes ?? '',
    });
    resetFiles();
    receiptFiles.value = salary.files
        .filter((file) => file.collection === 'receipt')
        .map(({ id, url }) => ({ id, url }));
    modalOpen.value = true;
}
function closeModal() {
    modalOpen.value = false;
    editing.value = null;
    form.clearErrors();
}
function onReceiptRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        receiptRemovedFiles.value.push(data.fileId);
        form.receipt_removed_files = [...receiptRemovedFiles.value];
    }
}
function submit() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editing.value) form.put(`/admin/salaries/${editing.value.id}`, options);
    else form.post('/admin/salaries', options);
}
function remove(salary: Salary) {
    if (
        window.confirm(
            `Supprimer le salaire de ${salary.employee.name} pour ${period(salary.salary_period)} ?`,
        )
    )
        router.delete(`/admin/salaries/${salary.id}`, { preserveScroll: true });
}
function paginationLabel(label: string) {
    return label
        .replace('&laquo; Previous', 'Précédent')
        .replace('Next &raquo;', 'Suivant');
}
</script>

<template>
    <Head title="Salaires" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">Salaires</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Gérez les salaires des enseignants et des employés.
                        Chaque salaire est aussi une dépense.
                    </p>
                </div>
                <Button class="w-full sm:w-auto" @click="openCreate"
                    ><Plus class="mr-2 size-4" />Enregistrer un salaire</Button
                >
            </header>
            <section class="rounded-xl border bg-card p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="filters.search"
                            class="pl-9"
                            placeholder="Nom, fonction, référence…"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <select
                        v-model="filters.employee_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tout le personnel</option>
                        <option
                            v-for="employee in employees"
                            :key="employee.id"
                            :value="String(employee.id)"
                        >
                            {{ employee.name }} — {{ role(employee) }}
                        </option></select
                    ><Input v-model="filters.period" type="month" />
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
                </div>
            </section>
            <section class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    Total des salaires affichés
                </p>
                <p class="mt-1 text-2xl font-bold">{{ money(total) }}</p>
            </section>
            <section
                class="hidden overflow-x-auto rounded-xl border bg-card md:block"
            >
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left">Personnel</th>
                            <th class="px-4 py-3 text-left">Période</th>
                            <th class="px-4 py-3 text-left">Paiement</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-right">Montant</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="salary in salaries.data" :key="salary.id">
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{ salary.employee.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ role(salary.employee) }}
                                </p>
                            </td>
                            <td class="px-4 py-3 capitalize">
                                {{ period(salary.salary_period) }}
                            </td>
                            <td class="px-4 py-3">
                                <p>
                                    {{ paymentLabels[salary.payment_method] }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ salary.reference || 'Sans référence' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                {{ date(salary.expense_date) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">
                                {{ money(salary.amount) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Button
                                    v-if="salary.receipt_url"
                                    as-child
                                    size="icon"
                                    variant="ghost"
                                    ><a
                                        :href="salary.receipt_url"
                                        target="_blank"
                                        title="Voir le justificatif"
                                        ><FileText class="size-4" /></a></Button
                                ><Button
                                    size="icon"
                                    variant="ghost"
                                    @click="openEdit(salary)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    size="icon"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="remove(salary)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </td>
                        </tr>
                        <tr v-if="!salaries.data.length">
                            <td
                                colspan="6"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                Aucun salaire trouvé.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <section class="space-y-3 md:hidden">
                <article
                    v-for="salary in salaries.data"
                    :key="salary.id"
                    class="rounded-xl border bg-card p-4"
                >
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-semibold">
                                {{ salary.employee.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ role(salary.employee) }}
                            </p>
                        </div>
                        <p class="font-bold whitespace-nowrap">
                            {{ money(salary.amount) }}
                        </p>
                    </div>
                    <div class="mt-3 text-sm">
                        <p class="capitalize">
                            {{ period(salary.salary_period) }}
                        </p>
                        <p class="text-muted-foreground">
                            Payé le {{ date(salary.expense_date) }} ·
                            {{ paymentLabels[salary.payment_method] }}
                        </p>
                    </div>
                    <div class="mt-3 flex justify-end gap-1">
                        <Button
                            v-if="salary.receipt_url"
                            as-child
                            size="sm"
                            variant="outline"
                            ><a :href="salary.receipt_url" target="_blank"
                                ><FileText class="mr-2 size-4" />Justificatif</a
                            ></Button
                        ><Button
                            size="icon"
                            variant="ghost"
                            @click="openEdit(salary)"
                            ><Pencil class="size-4" /></Button
                        ><Button
                            size="icon"
                            variant="ghost"
                            class="text-destructive"
                            @click="remove(salary)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                </article>
                <p
                    v-if="!salaries.data.length"
                    class="py-10 text-center text-muted-foreground"
                >
                    Aucun salaire trouvé.
                </p>
            </section>
            <nav v-if="salaries.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="link in salaries.links" :key="link.label"
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
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editing
                                    ? 'Modifier le salaire'
                                    : 'Enregistrer un salaire'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Cette opération créera également une dépense
                            scolaire.
                        </p>
                    </div>
                    <Button size="icon" variant="ghost" @click="closeModal"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <Label for="employee">Membre du personnel</Label
                        ><select
                            id="employee"
                            v-model="form.employee_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option
                                v-for="employee in employees"
                                :key="employee.id"
                                :value="String(employee.id)"
                            >
                                {{ employee.name }} — {{ role(employee) }}
                            </option></select
                        ><InputError
                            :message="form.errors.employee_id"
                            class="mt-1"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="salary_period">Mois concerné</Label
                            ><Input
                                id="salary_period"
                                v-model="form.salary_period"
                                class="mt-1"
                                type="month"
                                required
                            /><InputError
                                :message="form.errors.salary_period"
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
                            <Label for="expense_date">Date de paiement</Label
                            ><Input
                                id="expense_date"
                                v-model="form.expense_date"
                                class="mt-1"
                                type="date"
                                required
                            />
                        </div>
                        <div>
                            <Label for="payment_method">Mode de paiement</Label
                            ><select
                                id="payment_method"
                                v-model="form.payment_method"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="(label, key) in paymentLabels"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <Label for="reference">Référence</Label
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
                                class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                maxlength="5000"
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
                                width="100%"
                                @file-removed="onReceiptRemoved"
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
                                : 'Enregistrer le salaire'
                        }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
