<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Driver {
    id: number;
    full_name: string;
    phone: string;
    email?: string | null;
    driving_license_url: string;
    approval_status: 'pending' | 'approved' | 'rejected';
    rejection_reason?: string | null;
}

const props = defineProps<{ drivers: Driver[]; maximumDrivers: number }>();
const editingId = ref<number | null>(null);
const form = useForm({
    full_name: '',
    phone: '',
    email: '',
    driving_license: null as File | null,
});

const canAdd = computed(() => props.drivers.length < props.maximumDrivers);

function selectLicense(event: Event) {
    form.driving_license = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function edit(driver: Driver) {
    editingId.value = driver.id;
    form.clearErrors();
    form.full_name = driver.full_name;
    form.phone = driver.phone;
    form.email = driver.email ?? '';
    form.driving_license = null;
}

function reset() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    const url = editingId.value ? `/settings/drivers/${editingId.value}` : '/settings/drivers';
    form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: reset });
}

function remove(driver: Driver) {
    if (confirm(`Supprimer ${driver.full_name} de vos conducteurs ?`)) {
        useForm({}).delete(`/settings/drivers/${driver.id}`, { preserveScroll: true });
    }
}

const statusLabel = (status: Driver['approval_status']) => ({
    pending: 'En attente', approved: 'Approuvé', rejected: 'Refusé',
}[status]);
</script>

<template>
    <AppLayout>
        <Head title="Mes conducteurs" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Conducteurs supplémentaires" description="Enregistrez jusqu’à 3 conducteurs. Seuls les conducteurs approuvés peuvent être affectés à une réservation." />

                <div class="space-y-3">
                    <article v-for="driver in drivers" :key="driver.id" class="rounded-lg border p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold">{{ driver.full_name }}</h3>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="{
                                        'bg-green-100 text-green-800': driver.approval_status === 'approved',
                                        'bg-amber-100 text-amber-800': driver.approval_status === 'pending',
                                        'bg-red-100 text-red-800': driver.approval_status === 'rejected',
                                    }">{{ statusLabel(driver.approval_status) }}</span>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">{{ driver.phone }}<span v-if="driver.email"> · {{ driver.email }}</span></p>
                                <a :href="driver.driving_license_url" target="_blank" class="mt-2 inline-block text-sm text-orange-600 underline">Voir le permis</a>
                                <p v-if="driver.rejection_reason" class="mt-2 text-sm text-red-600">Motif : {{ driver.rejection_reason }}</p>
                            </div>
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="edit(driver)">Modifier</Button>
                                <Button variant="destructive" size="sm" @click="remove(driver)">Supprimer</Button>
                            </div>
                        </div>
                    </article>
                    <p v-if="!drivers.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">Aucun conducteur enregistré.</p>
                </div>

                <form v-if="canAdd || editingId" class="space-y-4 rounded-lg border p-5" @submit.prevent="submit">
                    <h3 class="font-semibold">{{ editingId ? 'Modifier le conducteur' : 'Ajouter un conducteur' }}</h3>
                    <p v-if="editingId" class="text-sm text-amber-700">Toute modification nécessite une nouvelle approbation.</p>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Nom complet *</label>
                        <input v-model="form.full_name" required class="w-full rounded-md border px-3 py-2" />
                        <InputError :message="form.errors.full_name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Téléphone *</label>
                            <input v-model="form.phone" required type="tel" class="w-full rounded-md border px-3 py-2" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">E-mail (optionnel)</label>
                            <input v-model="form.email" type="email" class="w-full rounded-md border px-3 py-2" />
                            <InputError :message="form.errors.email" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Photo du permis {{ editingId ? '(laisser vide pour conserver)' : '*' }}</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" :required="!editingId" @change="selectLicense" />
                        <InputError :message="form.errors.driving_license" />
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">{{ editingId ? 'Enregistrer' : 'Ajouter pour validation' }}</Button>
                        <Button v-if="editingId" type="button" variant="outline" @click="reset">Annuler</Button>
                    </div>
                </form>
                <p v-else class="rounded-lg bg-muted p-4 text-sm">Vous avez atteint la limite de {{ maximumDrivers }} conducteurs.</p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
