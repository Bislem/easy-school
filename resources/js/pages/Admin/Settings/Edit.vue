<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    settings: any;
    logoFiles: Array<{ id: number; url: string }>;
}>();

const form = useForm({
    trading_name: props.settings.trading_name ?? '',
    legal_name: props.settings.legal_name ?? '',
    registration_number: props.settings.registration_number ?? '',
    tax_number: props.settings.tax_number ?? '',
    address_line_1: props.settings.address_line_1 ?? '',
    address_line_2: props.settings.address_line_2 ?? '',
    city: props.settings.city ?? '',
    postal_code: props.settings.postal_code ?? '',
    country: props.settings.country ?? '',
    phone: props.settings.phone ?? '',
    secondary_phone: props.settings.secondary_phone ?? '',
    email: props.settings.email ?? '',
    website: props.settings.website ?? '',
    primary_color: props.settings.primary_color ?? '#f97316',
    website_disabled: Boolean(props.settings.website_disabled),
    booking_disabled: Boolean(props.settings.booking_disabled),
    client_login_disabled: Boolean(props.settings.client_login_disabled),
    tax_enabled: Boolean(props.settings.tax_enabled),
    tax_rate: props.settings.tax_rate ?? 7,
    online_advance_percentage: props.settings.online_advance_percentage ?? 0,
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
});

const tempFolders = ref<string[]>([]);
const removedFileIds = ref<number[]>([]);

watch(
    tempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

function onFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        removedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...removedFileIds.value];
    }
}

function submit() {
    form.put('/admin/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="Paramètres de l'agence" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div>
                <h1 class="text-2xl font-semibold">Paramètres de l'agence</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Gérez l'identité commerciale et les informations légales
                    affichées sur les contrats de location.
                </p>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="rounded-md border p-6">
                    <h2 class="font-medium">Logo et identité publique</h2>
                    <div class="mt-4 grid gap-6 md:grid-cols-2">
                        <div>
                            <Label>Logo de l'agence</Label>
                            <div class="mt-2 max-w-sm">
                                <FileUpload
                                    v-model="tempFolders"
                                    :initial-files="logoFiles"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    collection="logo"
                                    theme="light"
                                    width="100%"
                                    @file-removed="onFileRemoved"
                                />
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Utilisez un logo clair au format PNG, JPG ou
                                WebP.
                            </p>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <Label for="trading_name"
                                    >Nom de l'agence</Label
                                >
                                <Input
                                    id="trading_name"
                                    v-model="form.trading_name"
                                    class="mt-1"
                                    required
                                />
                                <InputError
                                    :message="form.errors.trading_name"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="legal_name">Raison sociale</Label>
                                <Input
                                    id="legal_name"
                                    v-model="form.legal_name"
                                    class="mt-1"
                                    placeholder="Nom légal de l'entreprise"
                                />
                                <InputError
                                    :message="form.errors.legal_name"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="website">Site web</Label>
                                <Input
                                    id="website"
                                    v-model="form.website"
                                    type="url"
                                    class="mt-1"
                                    placeholder="https://example.com"
                                />
                                <InputError
                                    :message="form.errors.website"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="primary_color"
                                    >Couleur principale du site web</Label
                                >
                                <div class="mt-1 flex items-center gap-3">
                                    <input
                                        id="primary_color"
                                        v-model="form.primary_color"
                                        type="color"
                                        class="size-10 cursor-pointer rounded border border-input bg-transparent p-1"
                                    />
                                    <Input
                                        v-model="form.primary_color"
                                        class="font-mono uppercase"
                                        maxlength="7"
                                        pattern="#[0-9A-Fa-f]{6}"
                                        aria-label="Valeur hexadécimale de la couleur principale"
                                    />
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Utilisée pour les boutons, les liens, les
                                    mises en évidence et les dégradés du site
                                    public.
                                </p>
                                <InputError
                                    :message="form.errors.primary_color"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="rounded-md border p-6">
                        <h2 class="font-medium">Enregistrement légal</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <Label for="registration_number"
                                    >RCN (Registre de commerce numéro)</Label
                                >
                                <Input
                                    id="registration_number"
                                    v-model="form.registration_number"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.registration_number"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="tax_number"
                                    >Numéro d'identification fiscale</Label
                                >
                                <Input
                                    id="tax_number"
                                    v-model="form.tax_number"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.tax_number"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-md border p-6">
                        <h2 class="font-medium">Coordonnées</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <Label for="email">Adresse e-mail</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.email"
                                    class="mt-1"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label for="phone"
                                        >Téléphone principal</Label
                                    >
                                    <Input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label for="secondary_phone"
                                        >Téléphone secondaire</Label
                                    >
                                    <Input
                                        id="secondary_phone"
                                        v-model="form.secondary_phone"
                                        type="tel"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="rounded-md border p-6">
                    <h2 class="font-medium">Adresse enregistrée</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <Label for="address_line_1">Adresse ligne 1</Label
                            ><Input
                                id="address_line_1"
                                v-model="form.address_line_1"
                                class="mt-1"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <Label for="address_line_2">Adresse ligne 2</Label
                            ><Input
                                id="address_line_2"
                                v-model="form.address_line_2"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="city">Ville</Label
                            ><Input
                                id="city"
                                v-model="form.city"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="postal_code">Code postal</Label
                            ><Input
                                id="postal_code"
                                v-model="form.postal_code"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="country">Pays</Label
                            ><Input
                                id="country"
                                v-model="form.country"
                                class="mt-1"
                            />
                        </div>
                    </div>
                </section>

                <section class="rounded-md border p-6">
                    <h2 class="font-medium">Taxe sur les réservations</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Cette configuration s'applique aux nouvelles réservations du site et de l'administration.</p>
                    <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-md border p-4">
                        <input v-model="form.tax_enabled" type="checkbox" class="mt-1 size-4 rounded border-gray-300 text-primary focus:ring-primary" />
                        <span><span class="block font-medium">Activer la taxe</span><span class="mt-1 block text-sm text-muted-foreground">Si elle est désactivée, aucune taxe ne sera calculée ou affichée.</span></span>
                    </label>
                    <div v-if="form.tax_enabled" class="mt-4 max-w-xs">
                        <Label for="tax_rate">Taux de taxe (%)</Label>
                        <Input id="tax_rate" v-model="form.tax_rate" type="number" min="0" max="100" step="0.01" class="mt-1" required />
                        <InputError :message="form.errors.tax_rate" class="mt-1" />
                    </div>
                </section>

                <section class="rounded-md border p-6">
                    <h2 class="font-medium">Avance pour les réservations en ligne</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Pourcentage du total de location que le client doit payer avant l'approbation. Saisissez 0 pour désactiver l'avance.</p>
                    <div class="mt-4 max-w-xs">
                        <Label for="online_advance_percentage">Avance requise (%)</Label>
                        <Input id="online_advance_percentage" v-model="form.online_advance_percentage" type="number" min="0" max="100" step="0.01" class="mt-1" required />
                        <InputError :message="form.errors.online_advance_percentage" class="mt-1" />
                    </div>
                </section>

                <section class="rounded-md border p-6">
                    <h2 class="font-medium">Contrôles d'accès</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Utilisez ces contrôles lorsque l'application fonctionne
                        comme un CRM interne.
                    </p>
                    <div class="mt-5 space-y-5">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-md border p-4"
                        >
                            <input
                                v-model="form.website_disabled"
                                type="checkbox"
                                class="mt-1 size-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <span>
                                <span class="block font-medium"
                                    >Désactiver le site public</span
                                >
                                <span
                                    class="mt-1 block text-sm text-muted-foreground"
                                    >Les visiteurs verront uniquement l'écran de
                                    contact de l'agence ; les véhicules, les
                                    réservations et les pages publiques seront
                                    indisponibles. L'accès administrateur reste
                                    disponible.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-md border p-4"
                        >
                            <input
                                v-model="form.booking_disabled"
                                type="checkbox"
                                class="mt-1 size-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <span>
                                <span class="block font-medium"
                                    >Désactiver les réservations en ligne</span
                                >
                                <span
                                    class="mt-1 block text-sm text-muted-foreground"
                                    >Les visiteurs peuvent toujours parcourir le
                                    site web et le parc de véhicules, mais les
                                    boutons de réservation et les demandes de
                                    réservation en ligne sont désactivés.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-md border p-4"
                        >
                            <input
                                v-model="form.client_login_disabled"
                                type="checkbox"
                                class="mt-1 size-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <span>
                                <span class="block font-medium"
                                    >Désactiver la connexion et le portail
                                    client</span
                                >
                                <span
                                    class="mt-1 block text-sm text-muted-foreground"
                                    >Empêche l'inscription, la connexion et
                                    l'accès des clients au portail client.
                                    Utilisez cette option lorsque seul le
                                    personnel utilise le système comme
                                    CRM.</span
                                >
                            </span>
                        </label>
                    </div>
                    <InputError
                        :message="
                            form.errors.website_disabled ||
                            form.errors.booking_disabled ||
                            form.errors.client_login_disabled
                        "
                        class="mt-2"
                    />
                </section>

                <Button :disabled="form.processing"
                    >Enregistrer les paramètres de l'agence</Button
                >
            </form>
        </main>
    </AdminLayout>
</template>
