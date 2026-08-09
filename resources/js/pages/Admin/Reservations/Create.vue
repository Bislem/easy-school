<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type Client = { id: number; name: string; email: string; phone?: string; is_active: boolean };
type Car = {
    id: number;
    make: string;
    model: string;
    year: number;
    license_plate: string;
    price_per_day: number | string;
    security_deposit: number | string;
};

const props = defineProps<{
    clients: Client[];
    cars: Car[];
    statuses: Array<{ value: string; label: string; color: string }>;
    currency: { symbol: string; code: string };
    tax: { enabled: boolean; rate: number };
}>();

const today = new Date().toISOString().slice(0, 10);
const form = useForm({
    client_mode: 'existing',
    user_id: '',
    client_name: '',
    client_email: '',
    client_phone: '',
    client_password: '',
    client_password_confirmation: '',
    car_id: '',
    start_date: today,
    end_date: today,
    pickup_time: '09:00',
    return_time: '18:00',
    pickup_location: 'Agence principale',
    return_location: 'Agence principale',
    daily_rate: '',
    discount_amount: '0',
    status: 'confirmed',
    notes: '',
});

const selectedCar = computed(() =>
    props.cars.find((car) => car.id === Number(form.car_id)),
);
const totalDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date + 'T00:00:00');
    const end = new Date(form.end_date + 'T00:00:00');
    return end < start
        ? 0
        : Math.floor((end.getTime() - start.getTime()) / 86400000) + 1;
});
const subtotal = computed(() => totalDays.value * Number(form.daily_rate || 0));
const taxAmount = computed(
    () => props.tax.enabled ? (subtotal.value * Number(props.tax.rate || 0)) / 100 : 0,
);
const totalAmount = computed(() =>
    Math.max(
        0,
        subtotal.value + taxAmount.value - Number(form.discount_amount || 0),
    ),
);
const money = (value: number) =>
    props.currency.symbol +
    value.toLocaleString('fr-FR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

watch(
    () => form.car_id,
    () => {
        if (selectedCar.value)
            form.daily_rate = String(selectedCar.value.price_per_day);
    },
);
watch(
    () => form.client_mode,
    (mode) => {
        form.clearErrors();
        if (mode === 'existing') {
            form.client_name = '';
            form.client_email = '';
            form.client_phone = '';
            form.client_password = '';
            form.client_password_confirmation = '';
        } else form.user_id = '';
    },
);
</script>

<template>
    <Head title="Ajouter une réservation" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        Ajouter une réservation
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Affectez la réservation à un client existant ou créez
                        son compte.
                    </p>
                </div>
                <Link href="/admin/reservations"
                    ><Button variant="outline">Retour</Button></Link
                >
            </div>

            <form
                class="space-y-6"
                @submit.prevent="form.post('/admin/reservations')"
            >
                <section class="space-y-4 rounded-lg border p-5">
                    <div>
                        <h2 class="font-semibold">Client</h2>
                        <p class="text-sm text-muted-foreground">
                            Choisissez comment affecter le client.
                        </p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer gap-3 rounded-md border p-4"
                            :class="
                                form.client_mode === 'existing'
                                    ? 'border-primary bg-primary/5'
                                    : ''
                            "
                        >
                            <input
                                v-model="form.client_mode"
                                type="radio"
                                value="existing"
                                class="mt-1"
                            />
                            <span
                                ><strong class="block">Client existant</strong
                                ><span class="text-sm text-muted-foreground"
                                    >Choisir un compte déjà enregistré.</span
                                ></span
                            >
                        </label>
                        <label
                            class="flex cursor-pointer gap-3 rounded-md border p-4"
                            :class="
                                form.client_mode === 'new'
                                    ? 'border-primary bg-primary/5'
                                    : ''
                            "
                        >
                            <input
                                v-model="form.client_mode"
                                type="radio"
                                value="new"
                                class="mt-1"
                            />
                            <span
                                ><strong class="block">Nouveau client</strong
                                ><span class="text-sm text-muted-foreground"
                                    >Créer le compte avec la réservation.</span
                                ></span
                            >
                        </label>
                    </div>
                    <div v-if="form.client_mode === 'existing'">
                        <Label for="user_id">Client</Label>
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            required
                            class="mt-1 h-10 w-full rounded-md border bg-background px-3"
                        >
                            <option value="">Sélectionnez un client</option>
                            <option
                                v-for="client in clients"
                                :key="client.id"
                                :value="String(client.id)"
                            >
                                {{ client.name }} — {{ client.email
                                }}{{ client.is_active ? '' : ' (suspendu)' }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.user_id"
                            class="mt-1"
                        />
                    </div>
                    <div v-else class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="client_name">Nom complet</Label
                            ><Input
                                id="client_name"
                                v-model="form.client_name"
                                required
                            /><InputError
                                :message="form.errors.client_name"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="client_email">Adresse e-mail</Label
                            ><Input
                                id="client_email"
                                v-model="form.client_email"
                                type="email"
                                required
                            /><InputError
                                :message="form.errors.client_email"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="client_phone">Numéro de téléphone</Label>
                            <Input id="client_phone" v-model="form.client_phone" type="tel" maxlength="50" required />
                            <InputError :message="form.errors.client_phone" class="mt-1" />
                        </div>
                        <div>
                            <Label for="client_password"
                                >Mot de passe temporaire</Label
                            ><Input
                                id="client_password"
                                v-model="form.client_password"
                                type="password"
                                minlength="8"
                                required
                            /><InputError
                                :message="form.errors.client_password"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="client_password_confirmation"
                                >Confirmer le mot de passe</Label
                            ><Input
                                id="client_password_confirmation"
                                v-model="form.client_password_confirmation"
                                type="password"
                                minlength="8"
                                required
                            />
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-lg border p-5">
                    <h2 class="font-semibold">Véhicule et période</h2>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <Label for="car_id">Véhicule</Label>
                            <select
                                id="car_id"
                                v-model="form.car_id"
                                required
                                class="mt-1 h-10 w-full rounded-md border bg-background px-3"
                            >
                                <option value="">
                                    Sélectionnez un véhicule
                                </option>
                                <option
                                    v-for="car in cars"
                                    :key="car.id"
                                    :value="String(car.id)"
                                >
                                    {{ car.year }} {{ car.make }}
                                    {{ car.model }} —
                                    {{ car.license_plate }} ({{
                                        money(Number(car.price_per_day))
                                    }}/jour)
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.car_id"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="start_date">Date de début</Label
                            ><Input
                                id="start_date"
                                v-model="form.start_date"
                                type="date"
                                required
                            /><InputError
                                :message="form.errors.start_date"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="end_date">Date de fin</Label
                            ><Input
                                id="end_date"
                                v-model="form.end_date"
                                type="date"
                                :min="form.start_date"
                                required
                            /><InputError
                                :message="form.errors.end_date"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="pickup_time">Heure de départ</Label
                            ><Input
                                id="pickup_time"
                                v-model="form.pickup_time"
                                type="time"
                            />
                        </div>
                        <div>
                            <Label for="return_time">Heure de retour</Label
                            ><Input
                                id="return_time"
                                v-model="form.return_time"
                                type="time"
                            />
                        </div>
                        <div>
                            <Label for="status">Statut initial</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 h-10 w-full rounded-md border bg-background px-3"
                            >
                                <option
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label for="pickup_location">Lieu de départ</Label
                            ><Input
                                id="pickup_location"
                                v-model="form.pickup_location"
                                required
                            />
                        </div>
                        <div>
                            <Label for="return_location">Lieu de retour</Label
                            ><Input
                                id="return_location"
                                v-model="form.return_location"
                                required
                            />
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-lg border p-5">
                    <h2 class="font-semibold">Tarification</h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label for="daily_rate">Prix par jour</Label
                            ><Input
                                id="daily_rate"
                                v-model="form.daily_rate"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                            /><InputError
                                :message="form.errors.daily_rate"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Taxe de l'agence</Label>
                            <div class="mt-1 flex h-9 items-center rounded-md border bg-muted/40 px-3 text-sm">{{ tax.enabled ? `${tax.rate}%` : 'Désactivée' }}</div>
                        </div>
                        <div>
                            <Label for="discount_amount">Remise</Label
                            ><Input
                                id="discount_amount"
                                v-model="form.discount_amount"
                                type="number"
                                min="0"
                                step="0.01"
                            /><InputError
                                :message="form.errors.discount_amount"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div
                        class="grid gap-3 rounded-md bg-muted/50 p-4 sm:grid-cols-4"
                    >
                        <div>
                            <p class="text-xs text-muted-foreground">Durée</p>
                            <p class="font-semibold">{{ totalDays }} jour(s)</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Sous-total
                            </p>
                            <p class="font-semibold">{{ money(subtotal) }}</p>
                        </div>
                        <div v-if="tax.enabled">
                            <p class="text-xs text-muted-foreground">Taxes</p>
                            <p class="font-semibold">{{ money(taxAmount) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Total</p>
                            <p class="text-lg font-bold">
                                {{ money(totalAmount) }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="Number(selectedCar?.security_deposit) > 0"
                        class="rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900"
                    >
                        <div class="flex items-center justify-between">
                            <span>Caution remboursable du véhicule</span>
                            <strong>{{ money(Number(selectedCar?.security_deposit)) }}</strong>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-sm">
                            <span>Montant à prévoir avec caution</span>
                            <strong>{{ money(totalAmount + Number(selectedCar?.security_deposit)) }}</strong>
                        </div>
                    </div>
                    <div>
                        <Label for="notes">Notes internes</Label
                        ><textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            maxlength="5000"
                            class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        />
                    </div>
                </section>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing">{{
                        form.processing ? 'Création...' : 'Créer la réservation'
                    }}</Button>
                    <Link href="/admin/reservations"
                        ><Button type="button" variant="outline"
                            >Annuler</Button
                        ></Link
                    >
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
