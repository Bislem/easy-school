<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ client: any | null }>();
const isEdit = computed(() => Boolean(props.client));

function dateForInput(value: unknown): string {
    if (!value) return '';
    const text = String(value);
    const match = text.match(/^\d{4}-\d{2}-\d{2}/);
    return match?.[0] ?? '';
}

const form = useForm({
    name: props.client?.name ?? '',
    email: props.client?.email ?? '',
    phone: props.client?.phone ?? '',
    password: '',
    password_confirmation: '',
    birth_date: dateForInput(props.client?.birth_date),
    driving_license_number: props.client?.driving_license_number ?? '',
    driving_license_delivered_at: dateForInput(
        props.client?.driving_license_delivered_at,
    ),
    driving_license_authority: props.client?.driving_license_authority ?? '',
    driving_license_copy: null as File | null,
    approval_status: props.client?.approval_status ?? 'approved',
    rejection_reason: props.client?.rejection_reason ?? '',
});

function selectDocument(event: Event) {
    form.driving_license_copy =
        (event.target as HTMLInputElement).files?.[0] ?? null;
}
function submit() {
    const options = { forceFormData: true };
    if (isEdit.value) {
        form.post('/admin/clients/' + props.client.id, options);
    } else {
        form.post('/admin/clients', options);
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Modifier le client' : 'Créer un client'" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ isEdit ? 'Modifier le client' : 'Créer un client' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Informations du compte et dossier du permis de conduire.
                    </p>
                </div>
                <Link
                    :href="
                        isEdit
                            ? '/admin/clients/' + client.id
                            : '/admin/clients'
                    "
                    ><Button variant="outline">Retour</Button></Link
                >
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="space-y-4 rounded-md border p-5">
                    <h2 class="font-medium">Informations du compte</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="name">Nom complet</Label
                            ><Input
                                id="name"
                                v-model="form.name"
                                required
                            /><InputError
                                :message="form.errors.name"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="email">Adresse e-mail</Label
                            ><Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                            /><InputError
                                :message="form.errors.email"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="phone">Numéro de téléphone</Label
                            ><Input
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                maxlength="50"
                                required
                            /><InputError
                                :message="form.errors.phone"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="approval_status"
                                >Statut du compte</Label
                            >
                            <select
                                id="approval_status"
                                v-model="form.approval_status"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                            >
                                <option value="approved">Approuvé</option>
                                <option value="pending">En attente</option>
                                <option value="rejected">Refusé</option>
                                <option value="suspended">Suspendu</option>
                            </select>
                        </div>
                        <div>
                            <Label for="password">{{
                                isEdit
                                    ? 'Nouveau mot de passe (facultatif)'
                                    : 'Mot de passe'
                            }}</Label
                            ><Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                minlength="8"
                                :required="!isEdit"
                                autocomplete="new-password"
                            /><InputError
                                :message="form.errors.password"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="password_confirmation"
                                >Confirmer le mot de passe</Label
                            ><Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                minlength="8"
                                :required="!isEdit || Boolean(form.password)"
                                autocomplete="new-password"
                            />
                        </div>
                        <div
                            v-if="form.approval_status === 'rejected'"
                            class="md:col-span-2"
                        >
                            <Label for="rejection_reason">Motif du refus</Label
                            ><textarea
                                id="rejection_reason"
                                v-model="form.rejection_reason"
                                rows="3"
                                class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                            />
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-md border p-5">
                    <h2 class="font-medium">Permis de conduire</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="birth_date">Date de naissance</Label
                            ><Input
                                id="birth_date"
                                v-model="form.birth_date"
                                type="date"
                                required
                            /><InputError
                                :message="form.errors.birth_date"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="driving_license_number"
                                >Numéro du permis</Label
                            ><Input
                                id="driving_license_number"
                                v-model="form.driving_license_number"
                                maxlength="100"
                                required
                            /><InputError
                                :message="form.errors.driving_license_number"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="driving_license_delivered_at"
                                >Date de délivrance</Label
                            ><Input
                                id="driving_license_delivered_at"
                                v-model="form.driving_license_delivered_at"
                                type="date"
                                required
                            /><InputError
                                :message="
                                    form.errors.driving_license_delivered_at
                                "
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="driving_license_authority"
                                >Autorité de délivrance</Label
                            ><Input
                                id="driving_license_authority"
                                v-model="form.driving_license_authority"
                                maxlength="255"
                                required
                            /><InputError
                                :message="form.errors.driving_license_authority"
                                class="mt-1"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <Label for="driving_license_copy">{{
                                isEdit
                                    ? 'Remplacer la copie du permis'
                                    : 'Copie du permis'
                            }}</Label>
                            <a
                                v-if="client?.driving_license_url"
                                :href="client.driving_license_url"
                                target="_blank"
                                rel="noopener"
                                class="ml-3 text-sm text-primary underline"
                                >Voir le document actuel</a
                            >
                            <input
                                id="driving_license_copy"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                :required="!client?.driving_license_url"
                                class="mt-1 block w-full rounded-md border bg-background px-3 py-2 text-sm"
                                @change="selectDocument"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                JPG, PNG, WebP ou PDF, maximum 5 Mo.
                            </p>
                            <InputError
                                :message="form.errors.driving_license_copy"
                                class="mt-1"
                            />
                        </div>
                    </div>
                </section>
                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing">{{
                        form.processing
                            ? 'Enregistrement...'
                            : isEdit
                              ? 'Enregistrer les modifications'
                              : 'Créer le client'
                    }}</Button
                    ><Link
                        :href="
                            isEdit
                                ? '/admin/clients/' + client.id
                                : '/admin/clients'
                        "
                        ><Button type="button" variant="outline"
                            >Annuler</Button
                        ></Link
                    >
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
