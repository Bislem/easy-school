<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface SchoolSettings {
    trading_name: string;
    legal_name?: string | null;
    registration_number?: string | null;
    address_line_1?: string | null;
    address_line_2?: string | null;
    city?: string | null;
    postal_code?: string | null;
    country?: string | null;
    phone?: string | null;
    secondary_phone?: string | null;
    email?: string | null;
    website?: string | null;
    primary_color?: string | null;
    teacher_login_disabled?: boolean;
}

const props = defineProps<{
    settings: SchoolSettings;
    logoFiles: Array<{ id: number; url: string }>;
    faviconFiles: Array<{ id: number; url: string }>;
}>();

const form = useForm({
    trading_name: props.settings.trading_name ?? '',
    legal_name: props.settings.legal_name ?? '',
    registration_number: props.settings.registration_number ?? '',
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
    teacher_login_disabled: Boolean(props.settings.teacher_login_disabled),
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
    favicon_temp_folders: [] as string[],
    favicon_removed_files: [] as number[],
});

const tempFolders = ref<string[]>([]);
const removedFileIds = ref<number[]>([]);
const faviconTempFolders = ref<string[]>([]);
const faviconRemovedFileIds = ref<number[]>([]);

watch(tempFolders, (value) => (form.logo_temp_folders = [...value]), {
    deep: true,
});
watch(faviconTempFolders, (value) => (form.favicon_temp_folders = [...value]), {
    deep: true,
});

function onFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        removedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...removedFileIds.value];
    }
}

function onFaviconRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        faviconRemovedFileIds.value.push(data.fileId);
        form.favicon_removed_files = [...faviconRemovedFileIds.value];
    }
}

function submit() {
    form.put('/admin/settings', { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <Head title="Paramètres de l'école" />

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <div>
                    <h1 class="text-2xl font-semibold">
                        Paramètres de l'école
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Gérez l'identité de l'école, ses coordonnées et l'accès
                        des enseignants.
                    </p>
                </div>

                <form class="space-y-6" @submit.prevent="submit">
                    <section class="rounded-xl border bg-card p-4 sm:p-6">
                        <h2 class="font-semibold">
                            Identité et image de marque
                        </h2>
                        <div class="mt-5 grid gap-6 md:grid-cols-2">
                            <div class="space-y-6">
                                <div>
                                    <Label>Logo de l'école</Label>
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
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        PNG, JPG, SVG ou WebP. Taille maximale :
                                        5 Mo.
                                    </p>
                                </div>
                                <div class="border-t pt-5">
                                    <Label>Favicon</Label>
                                    <div class="mt-2 max-w-sm">
                                        <FileUpload
                                            v-model="faviconTempFolders"
                                            :initial-files="faviconFiles"
                                            :allow-multiple="false"
                                            :max-files="1"
                                            :max-file-size="1024 * 1024"
                                            :allowed-file-types="[
                                                'image/png',
                                                'image/svg+xml',
                                                'image/x-icon',
                                                'image/vnd.microsoft.icon',
                                            ]"
                                            collection="favicon"
                                            theme="light"
                                            width="100%"
                                            @file-removed="onFaviconRemoved"
                                        />
                                    </div>
                                    <InputError
                                        :message="
                                            form.errors.favicon_temp_folders
                                        "
                                        class="mt-1"
                                    />
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Icône carrée PNG, SVG ou ICO. Format
                                        conseillé : 32 × 32 ou 64 × 64 px,
                                        maximum 1 Mo.
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <Label for="trading_name"
                                        >Nom de l'école</Label
                                    ><Input
                                        id="trading_name"
                                        v-model="form.trading_name"
                                        class="mt-1"
                                        required
                                    /><InputError
                                        :message="form.errors.trading_name"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label for="legal_name"
                                        >Raison sociale</Label
                                    ><Input
                                        id="legal_name"
                                        v-model="form.legal_name"
                                        class="mt-1"
                                    /><InputError
                                        :message="form.errors.legal_name"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label for="registration_number"
                                        >Numéro d'enregistrement</Label
                                    ><Input
                                        id="registration_number"
                                        v-model="form.registration_number"
                                        class="mt-1"
                                    /><InputError
                                        :message="
                                            form.errors.registration_number
                                        "
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label for="primary_color"
                                        >Couleur principale</Label
                                    >
                                    <div class="mt-1 flex items-center gap-3">
                                        <input
                                            id="primary_color"
                                            v-model="form.primary_color"
                                            type="color"
                                            class="size-10 shrink-0 cursor-pointer rounded border p-1"
                                        /><Input
                                            v-model="form.primary_color"
                                            class="font-mono uppercase"
                                            maxlength="7"
                                            pattern="#[0-9A-Fa-f]{6}"
                                        />
                                    </div>
                                    <InputError
                                        :message="form.errors.primary_color"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border bg-card p-4 sm:p-6">
                        <h2 class="font-semibold">Coordonnées</h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="email">Email</Label
                                ><Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1"
                                /><InputError
                                    :message="form.errors.email"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="website">Site web</Label
                                ><Input
                                    id="website"
                                    v-model="form.website"
                                    type="url"
                                    class="mt-1"
                                    placeholder="https://school.example"
                                /><InputError
                                    :message="form.errors.website"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="phone">Téléphone principal</Label
                                ><Input
                                    id="phone"
                                    v-model="form.phone"
                                    type="tel"
                                    class="mt-1"
                                /><InputError
                                    :message="form.errors.phone"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="secondary_phone"
                                    >Téléphone secondaire</Label
                                ><Input
                                    id="secondary_phone"
                                    v-model="form.secondary_phone"
                                    type="tel"
                                    class="mt-1"
                                /><InputError
                                    :message="form.errors.secondary_phone"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border bg-card p-4 sm:p-6">
                        <h2 class="font-semibold">Adresse</h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <Label for="address_line_1"
                                    >Adresse — ligne 1</Label
                                ><Input
                                    id="address_line_1"
                                    v-model="form.address_line_1"
                                    class="mt-1"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <Label for="address_line_2"
                                    >Adresse — ligne 2</Label
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

                    <section class="rounded-xl border bg-card p-4 sm:p-6">
                        <h2 class="font-semibold">Contrôle d'accès</h2>
                        <label
                            class="mt-5 flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                        >
                            <input
                                v-model="form.teacher_login_disabled"
                                type="checkbox"
                                class="mt-1 size-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <span
                                ><span class="block font-medium"
                                    >Désactiver la connexion des
                                    enseignants</span
                                ><span
                                    class="mt-1 block text-sm text-muted-foreground"
                                    >Les enseignants ne pourront plus se
                                    connecter jusqu'à la réactivation de cet
                                    accès. L'accès administrateur reste
                                    disponible.</span
                                ></span
                            >
                        </label>
                        <InputError
                            :message="form.errors.teacher_login_disabled"
                            class="mt-1"
                        />
                    </section>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">{{
                            form.processing
                                ? 'Enregistrement…'
                                : 'Enregistrer les paramètres'
                        }}</Button>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
