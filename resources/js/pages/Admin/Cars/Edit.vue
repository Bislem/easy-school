<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { appConfirm } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/cars';
import { show as showReservation } from '@/routes/admin/reservations';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    car: any | null;
    imageFiles: Array<{ id: number; url: string }>;
    enums: {
        colors: Array<{ name: string; value: string; hex: string }>;
        fuelTypes: { value: string; label: string }[];
        statuses: Array<{ value: string; label: string; color: string }>;
    };
    reservations?: {
        data: Array<{
            id: number;
            reservation_number: string;
            start_date: string;
            end_date: string;
            total_days: number;
            total_amount: number | string;
            status: string;
            user?: {
                id: number;
                name: string;
                email: string;
                phone?: string | null;
            } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    reservationStatuses?: Array<{
        value: string;
        label: string;
        color: string;
    }>;
}>();

const isEdit = computed(() => !!props.car);

function reservationStatus(status: string) {
    return (
        props.reservationStatuses?.find((item) => item.value === status) ?? {
            label: status,
            color: '#6B7280',
        }
    );
}

function paginationLabel(label: string) {
    if (label.includes('Previous')) return '‹ Précédent';
    if (label.includes('Next')) return 'Suivant ›';
    return label;
}

// Form state
const carColors = computed(() =>
    props.enums.colors.map((color) => ({
        ...color,
        value: color.value.toLowerCase(),
        name: color.name.charAt(0).toUpperCase() + color.name.slice(1),
    })),
);

const fuelTypes = computed(() =>
    props.enums.fuelTypes.map((fuel) => ({
        value: fuel.value.toLowerCase(),
        label: fuel.label.charAt(0).toUpperCase() + fuel.label.slice(1),
    })),
);

const statuses = computed(() =>
    props.enums.statuses.map((status) => ({
        value: status.value,
        label: status.label,
        color: status.color,
    })),
);

// Initialize form with default values
const form = useForm({
    make: props.car?.make ?? '',
    model: props.car?.model ?? '',
    year: props.car?.year ?? '',
    license_plate: props.car?.license_plate ?? '',
    color: (props.car?.color || 'white').toLowerCase(),
    price_per_day: props.car?.price_per_day ?? '',
    security_deposit: props.car?.security_deposit ?? 0,
    mileage: props.car?.mileage ?? '',
    transmission: props.car?.transmission ?? 'automatic',
    seats: props.car?.seats ?? '',
    fuel_type: (props.car?.fuel_type || 'Essence').toLowerCase(),
    description: props.car?.description ?? '',
    status: props.car?.status ?? 'available',
    // FilePond fields
    image: [] as string[],
    image_temp_folders: [] as string[],
    image_removed_files: [] as number[],
});

const maintenanceForm = useForm({
    type: 'maintenance',
    car_id: props.car?.id ? String(props.car.id) : '',
    category: 'Entretien',
    title: '',
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    vendor: '',
    reference: '',
    mileage: props.car?.mileage ? String(props.car.mileage) : '',
    next_service_date: '',
    notes: '',
});

function addMaintenance() {
    maintenanceForm.post('/admin/expenses', {
        preserveScroll: true,
        onSuccess: () => {
            maintenanceForm.reset(
                'title',
                'amount',
                'vendor',
                'reference',
                'next_service_date',
                'notes',
            );
            maintenanceForm.category = 'Entretien';
            maintenanceForm.expense_date = new Date()
                .toISOString()
                .slice(0, 10);
        },
    });
}

async function deleteMaintenance(expense: any) {
    if (
        await appConfirm(`Supprimer l'entretien « ${expense.title} » ?`, {
            title: 'Supprimer l’entretien',
            tone: 'danger',
            confirmText: 'Supprimer',
        })
    ) {
        router.delete(`/admin/expenses/${expense.id}`, {
            preserveScroll: true,
        });
    }
}

// Single image upload handling
const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const tempFolders = ref<string[]>([]);
const removedFileIds = ref<number[]>([]);

// Sync temp folders with form for edit
watch(
    tempFolders,
    (value) => {
        form.image_temp_folders = [...value];
    },
    { deep: true },
);

function handleFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        removedFileIds.value.push(data.fileId);
        form.image_removed_files = [...removedFileIds.value];
    }
}

function submit() {
    if (isEdit.value) {
        form.put(update(props.car.id).url);
    } else {
        // for create, pass image temp folders via `image`
        form.image = [...tempFolders.value];
        form.post(store().url, {
            onSuccess: () => {
                form.reset();
                tempFolders.value = [];
                fileUploadRef.value?.resetFiles();
            },
        });
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Modifier le véhicule' : 'Créer un véhicule'" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? 'Modifier le véhicule' : 'Créer un véhicule' }}
                </h1>
                <Link :href="index()">
                    <Button variant="outline">Retour</Button>
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div v-if="isEdit" class="rounded-md border">
                    <div class="border-b px-4 py-3 font-medium">
                        Historique du réservoir
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table
                            v-if="car?.fuel_tank_records?.length"
                            class="min-w-full divide-y divide-gray-200 text-sm"
                        >
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-gray-500"
                                    >
                                        Événement
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-gray-500"
                                    >
                                        Niveau
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-gray-500"
                                    >
                                        Réservation
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-gray-500"
                                    >
                                        Enregistré le
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-gray-500"
                                    >
                                        Enregistré par
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-gray-500"
                                    >
                                        Remarques
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="record in car.fuel_tank_records"
                                    :key="record.id"
                                >
                                    <td class="px-3 py-2 font-medium">
                                        {{
                                            record.record_type ===
                                            'rental_start'
                                                ? 'Début de location'
                                                : 'Fin de location'
                                        }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ record.fuel_level }}%
                                    </td>
                                    <td class="px-3 py-2">
                                        {{
                                            record.reservation
                                                ?.reservation_number || '—'
                                        }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{
                                            new Date(
                                                record.recorded_at,
                                            ).toLocaleString()
                                        }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ record.recorded_by?.name || '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ record.notes || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="text-sm text-muted-foreground">
                            Aucun enregistrement de réservoir n'a été enregistré
                            pour ce véhicule.
                        </p>
                    </div>
                </div>
                <div class="space-y-6">
                    <!-- Image and Status Section -->
                    <div class="flex flex-col gap-6 md:flex-row md:gap-8">
                        <!-- Image -->
                        <div class="w-full md:w-1/2">
                            <Label>Image</Label>
                            <div class="mt-2">
                                <FileUpload
                                    ref="fileUploadRef"
                                    v-model="tempFolders"
                                    :initial-files="imageFiles || []"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    collection="image"
                                    theme="light"
                                    width="100%"
                                    @file-removed="handleFileRemoved"
                                />
                            </div>
                        </div>
                        <!-- Status and Price and Color -->
                        <div class="w-full space-y-4 py-0 md:w-1/2 md:py-6">
                            <!-- Status -->
                            <div>
                                <Label for="status">Statut</Label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full rounded-md border border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:ring-blue-500 focus:outline-none sm:text-sm"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status.value"
                                        :value="status.value"
                                        :class="`text-${status.color}-700 bg-${status.color}-100`"
                                    >
                                        {{ status.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.status"
                                    class="mt-1"
                                />
                            </div>
                            <!-- Price Per Day -->
                            <div>
                                <Label for="price_per_day">Prix /Jour</Label>
                                <Input
                                    id="price_per_day"
                                    v-model="form.price_per_day"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Ex. 50,00"
                                />
                                <InputError
                                    :message="form.errors.price_per_day"
                                    class="mt-1"
                                />
                            </div>

                            <div>
                                <Label for="security_deposit"
                                    >Caution / dépôt de garantie</Label
                                >
                                <Input
                                    id="security_deposit"
                                    v-model="form.security_deposit"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Saisissez 0 si aucune caution n'est requise.
                                </p>
                                <InputError
                                    :message="form.errors.security_deposit"
                                    class="mt-1"
                                />
                            </div>

                            <!-- Color -->
                            <div>
                                <Label class="mb-2 block">Couleur</Label>
                                <div
                                    class="flex flex-row flex-wrap items-center gap-2"
                                >
                                    <div
                                        v-for="color in carColors"
                                        :key="color.value"
                                        class="flex items-center"
                                    >
                                        <input
                                            type="radio"
                                            :id="'color-' + color.value"
                                            v-model="form.color"
                                            :value="color.value"
                                            class="peer sr-only"
                                        />
                                        <label
                                            :for="'color-' + color.value"
                                            class="flex w-full min-w-fit cursor-pointer items-center justify-between rounded-md border p-2 text-sm font-medium peer-checked:border-blue-500 peer-checked:ring-1 peer-checked:ring-blue-500 hover:bg-gray-50 dark:hover:bg-gray-800"
                                            :title="color.name"
                                        >
                                            <span class="mr-1">{{
                                                color.name
                                            }}</span>
                                            <span
                                                class="inline-block !h-4 !w-4 rounded-full border border-gray-300"
                                                :style="{
                                                    backgroundColor: color.hex,
                                                }"
                                            ></span>
                                        </label>
                                    </div>
                                </div>
                                <InputError
                                    :message="form.errors.color"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Form Fields Grid -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Make -->
                        <div>
                            <Label for="make">Marque</Label>
                            <Input
                                id="make"
                                v-model="form.make"
                                placeholder="Ex. Toyota, Honda, Ford, BMW…"
                            />
                            <InputError
                                :message="form.errors.make"
                                class="mt-1"
                            />
                        </div>

                        <!-- Model -->
                        <div>
                            <Label for="model">Modèle</Label>
                            <Input
                                id="model"
                                v-model="form.model"
                                placeholder="Ex. Camry, Civic, F-150, X5…"
                            />
                            <InputError
                                :message="form.errors.model"
                                class="mt-1"
                            />
                        </div>

                        <!-- Year -->
                        <div>
                            <Label for="year">Année</Label>
                            <Input
                                id="year"
                                v-model="form.year"
                                type="number"
                                :min="1900"
                                :max="new Date().getFullYear() + 1"
                                placeholder="Ex. 2023"
                            />
                            <InputError
                                :message="form.errors.year"
                                class="mt-1"
                            />
                        </div>

                        <!-- License Plate -->
                        <div>
                            <Label for="license_plate"
                                >Plaque d'immatriculation</Label
                            >
                            <Input
                                id="license_plate"
                                v-model="form.license_plate"
                                placeholder="Ex. ABC-1234 ou 123-ABC-45"
                            />
                            <InputError
                                :message="form.errors.license_plate"
                                class="mt-1"
                            />
                        </div>

                        <!-- Mileage -->
                        <div>
                            <Label for="mileage">Kilométrage</Label>
                            <Input
                                id="mileage"
                                v-model="form.mileage"
                                type="number"
                                min="0"
                                step="1000"
                                placeholder="Ex. 15 000"
                            />
                            <InputError
                                :message="form.errors.mileage"
                                class="mt-1"
                            />
                        </div>

                        <!-- Transmission -->
                        <div>
                            <Label for="transmission">Transmission</Label>
                            <select
                                id="transmission"
                                v-model="form.transmission"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            >
                                <option value="automatic">Automatique</option>
                                <option value="manual">Manuel</option>
                            </select>
                            <InputError
                                :message="form.errors.transmission"
                                class="mt-1"
                            />
                        </div>

                        <!-- Seats -->
                        <div>
                            <Label for="seats">Sièges</Label>
                            <Input
                                id="seats"
                                v-model="form.seats"
                                type="number"
                                min="1"
                                max="20"
                                placeholder="Ex. 5"
                            />
                            <InputError
                                :message="form.errors.seats"
                                class="mt-1"
                            />
                        </div>

                        <!-- Fuel Type -->
                        <div>
                            <Label for="fuel_type">Type de carburant</Label>
                            <select
                                id="fuel_type"
                                v-model="form.fuel_type"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            >
                                <option
                                    v-for="fuel in fuelTypes"
                                    :key="fuel.value"
                                    :value="fuel.value"
                                >
                                    {{ fuel.label }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.fuel_type"
                                class="mt-1"
                            />
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                                placeholder="Décrivez le véhicule, ses équipements, son état et toute remarque particulière…"
                            ></textarea>
                            <InputError
                                :message="form.errors.description"
                                class="mt-1"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? isEdit
                                    ? 'Enregistrement...'
                                    : 'Creation...'
                                : isEdit
                                  ? 'Enregistrer les modifications'
                                  : 'Créer un véhicule'
                        }}
                    </Button>
                    <Link :href="index()">
                        <Button type="button" variant="outline">Annuler</Button>
                    </Link>
                </div>
            </form>

            <section v-if="isEdit" class="space-y-4 rounded-lg border p-5">
                <div>
                    <h2 class="text-lg font-semibold">
                        Entretien et coûts de maintenance
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Chaque coût ajouté ici est automatiquement comptabilisé
                        dans le module Dépenses et dans les rapports.
                    </p>
                </div>

                <form
                    class="grid gap-4 md:grid-cols-2 lg:grid-cols-4"
                    @submit.prevent="addMaintenance"
                >
                    <label class="grid gap-1 text-sm font-medium"
                        >Type d'entretien<Input
                            v-model="maintenanceForm.category"
                            list="maintenance-categories"
                            required /><datalist id="maintenance-categories">
                            <option value="Vidange" />
                            <option value="Réparation" />
                            <option value="Pneus" />
                            <option value="Freins" />
                            <option value="Révision" />
                            <option value="Pièces" />
                            <option value="Lavage" /></datalist
                    ></label>
                    <label class="grid gap-1 text-sm font-medium"
                        >Détail / libellé<Input
                            v-model="maintenanceForm.title"
                            required
                            placeholder="Ex. Vidange moteur" /><InputError
                            :message="maintenanceForm.errors.title"
                    /></label>
                    <label class="grid gap-1 text-sm font-medium"
                        >Montant<Input
                            v-model="maintenanceForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required /><InputError
                            :message="maintenanceForm.errors.amount"
                    /></label>
                    <label class="grid gap-1 text-sm font-medium"
                        >Date<Input
                            v-model="maintenanceForm.expense_date"
                            type="date"
                            required
                    /></label>
                    <label class="grid gap-1 text-sm font-medium"
                        >Prestataire<Input v-model="maintenanceForm.vendor"
                    /></label>
                    <label class="grid gap-1 text-sm font-medium"
                        >Référence / facture<Input
                            v-model="maintenanceForm.reference"
                    /></label>
                    <label class="grid gap-1 text-sm font-medium"
                        >Kilométrage<Input
                            v-model="maintenanceForm.mileage"
                            type="number"
                            min="0"
                    /></label>
                    <label class="grid gap-1 text-sm font-medium"
                        >Prochain entretien<Input
                            v-model="maintenanceForm.next_service_date"
                            type="date"
                    /></label>
                    <label
                        class="grid gap-1 text-sm font-medium md:col-span-2 lg:col-span-4"
                        >Notes<textarea
                            v-model="maintenanceForm.notes"
                            rows="2"
                            class="rounded-md border bg-background px-3 py-2"
                        />
                    </label>
                    <div class="lg:col-span-4">
                        <Button
                            type="submit"
                            :disabled="maintenanceForm.processing"
                            >Ajouter le coût d'entretien</Button
                        >
                    </div>
                </form>

                <div class="overflow-x-auto rounded-md border">
                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Entretien</th>
                                <th class="px-3 py-2 text-left">Prestataire</th>
                                <th class="px-3 py-2 text-left">Kilométrage</th>
                                <th class="px-3 py-2 text-right">Montant</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="expense in car.expenses"
                                :key="expense.id"
                            >
                                <td class="px-3 py-2">
                                    {{
                                        new Date(
                                            `${expense.expense_date}T00:00:00`,
                                        ).toLocaleDateString('fr-FR')
                                    }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium">
                                        {{ expense.title }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ expense.category
                                        }}<span v-if="expense.notes">
                                            · {{ expense.notes }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    {{ expense.vendor || '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    {{
                                        expense.mileage
                                            ? `${expense.mileage.toLocaleString('fr-FR')} km`
                                            : '—'
                                    }}
                                </td>
                                <td class="px-3 py-2 text-right font-semibold">
                                    {{
                                        Number(expense.amount).toLocaleString(
                                            'fr-FR',
                                            { minimumFractionDigits: 2 },
                                        )
                                    }}
                                    DZD
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive"
                                        @click="deleteMaintenance(expense)"
                                        >Supprimer</Button
                                    >
                                </td>
                            </tr>
                            <tr v-if="!car.expenses?.length">
                                <td
                                    colspan="6"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    Aucun coût d'entretien enregistré.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="isEdit && reservations" class="rounded-lg border">
                <div
                    class="flex flex-col gap-1 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <h2 class="text-lg font-semibold">
                        Historique des réservations
                    </h2>
                    <span class="text-sm text-muted-foreground"
                        >{{ reservations.total }} réservation(s)</span
                    >
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    Référence
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Client
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Période
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Durée
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Statut
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Montant
                                </th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="reservation in reservations.data"
                                :key="reservation.id"
                            >
                                <td
                                    class="px-4 py-3 font-medium whitespace-nowrap"
                                >
                                    {{ reservation.reservation_number }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ reservation.user?.name || '—' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            reservation.user?.phone ||
                                            reservation.user?.email ||
                                            '—'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{
                                        new Date(
                                            `${reservation.start_date.slice(0, 10)}T00:00:00`,
                                        ).toLocaleDateString('fr-FR')
                                    }}
                                    –
                                    {{
                                        new Date(
                                            `${reservation.end_date.slice(0, 10)}T00:00:00`,
                                        ).toLocaleDateString('fr-FR')
                                    }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ reservation.total_days }} jour(s)
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap"
                                        :style="{
                                            color: reservationStatus(
                                                reservation.status,
                                            ).color,
                                            backgroundColor: `${reservationStatus(reservation.status).color}18`,
                                        }"
                                    >
                                        {{
                                            reservationStatus(
                                                reservation.status,
                                            ).label
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold whitespace-nowrap"
                                >
                                    {{
                                        Number(
                                            reservation.total_amount,
                                        ).toLocaleString('fr-FR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        })
                                    }}
                                    DZD
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="
                                            showReservation(reservation.id).url
                                        "
                                        ><Button size="sm" variant="outline"
                                            >Voir</Button
                                        ></Link
                                    >
                                </td>
                            </tr>
                            <tr v-if="!reservations.data.length">
                                <td
                                    colspan="7"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Aucune réservation enregistrée pour ce
                                    véhicule.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="reservations.links.length > 3"
                    class="flex flex-wrap gap-2 border-t px-4 py-3"
                >
                    <Link
                        v-for="(link, index) in reservations.links"
                        :key="index"
                        :href="link.url || ''"
                        preserve-scroll
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            !link.url && 'pointer-events-none opacity-50',
                        ]"
                        >{{ paginationLabel(link.label) }}</Link
                    >
                </nav>
            </section>
        </main>
    </AdminLayout>
</template>
