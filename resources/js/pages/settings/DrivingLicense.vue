<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ClientLayout from '@/layouts/ClientLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    license: {
        birth_date?: string | null;
        number?: string | null;
        delivered_at?: string | null;
        authority?: string | null;
        document_url?: string | null;
    };
}>();
</script>

<template>
    <ClientLayout>
        <Head title="Permis de conduire" />
        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Permis de conduire"
                    description="Consultez et mettez à jour votre dossier de permis"
                />

                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Toute modification soumettra votre dossier à une nouvelle vérification. Votre accès client sera temporairement suspendu jusqu'à l'approbation de l'agence.
                </div>

                <Form
                    action="/settings/driving-license"
                    method="post"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="birth_date">Date de naissance</Label>
                            <Input id="birth_date" name="birth_date" type="date" :default-value="license.birth_date ?? ''" required />
                            <InputError :message="errors.birth_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="driving_license_number">Numéro du permis</Label>
                            <Input id="driving_license_number" name="driving_license_number" :default-value="license.number ?? ''" maxlength="100" required />
                            <InputError :message="errors.driving_license_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="driving_license_delivered_at">Date de délivrance</Label>
                            <Input id="driving_license_delivered_at" name="driving_license_delivered_at" type="date" :default-value="license.delivered_at ?? ''" required />
                            <InputError :message="errors.driving_license_delivered_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="driving_license_authority">Autorité de délivrance</Label>
                            <Input id="driving_license_authority" name="driving_license_authority" :default-value="license.authority ?? ''" maxlength="255" required />
                            <InputError :message="errors.driving_license_authority" />
                        </div>
                    </div>

                    <div class="grid gap-2 rounded-md border p-4">
                        <Label for="driving_license_copy">Copie du permis</Label>
                        <a v-if="license.document_url" :href="license.document_url" target="_blank" rel="noopener" class="text-sm font-medium text-primary underline">Voir le document actuel</a>
                        <input id="driving_license_copy" name="driving_license_copy" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" :required="!license.document_url" class="block w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p class="text-xs text-muted-foreground">Laissez vide pour conserver le document actuel. JPG, PNG, WebP ou PDF, maximum 5 Mo.</p>
                        <InputError :message="errors.driving_license_copy" />
                    </div>

                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Envoi...' : 'Mettre à jour le permis' }}
                    </Button>
                </Form>
            </div>
        </SettingsLayout>
    </ClientLayout>
</template>
