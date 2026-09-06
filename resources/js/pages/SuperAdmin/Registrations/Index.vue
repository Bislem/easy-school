<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock3,
    ExternalLink,
    FileCheck2,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    registrations: any;
    filters: any;
    counts: Record<string, number>;
}>();
const base = (usePage().props.superAdmin as any).basePath;
const status = ref(props.filters.status || '');
const selected = ref<any>(null);
const rejection = useForm({ reason: '' });
const filter = () =>
    router.get(
        `${base}/registrations`,
        { status: status.value || undefined },
        { preserveState: true },
    );
const approve = (registration: any) =>
    confirm(`Activer le compte de ${registration.name} ?`) &&
    router.post(`${base}/registrations/${registration.id}/approve`);
const reject = () =>
    rejection.post(`${base}/registrations/${selected.value.id}/reject`, {
        onSuccess: () => {
            selected.value = null;
            rejection.reset();
        },
    });
const label = (value: string) =>
    ({ pending: 'En attente', active: 'Approuvée', rejected: 'Refusée' })[
        value
    ] || value;
const badgeVariant = (value: string) =>
    value === 'active'
        ? 'default'
        : value === 'rejected'
          ? 'destructive'
          : 'secondary';
</script>

<template>
    <SuperAdminLayout
        title="Nouvelles inscriptions"
        description="Vérification des paiements et activation des nouveaux établissements"
    >
        <div class="mb-5 grid gap-3 sm:grid-cols-3">
            <Card
                v-for="item in [
                    [Clock3, 'pending', 'En attente'],
                    [CheckCircle2, 'active', 'Approuvées'],
                    [XCircle, 'rejected', 'Refusées'],
                ]"
                :key="item[1] as string"
                ><CardContent class="flex items-center justify-between p-5"
                    ><div>
                        <p class="text-sm text-muted-foreground">
                            {{ item[2] }}
                        </p>
                        <p class="mt-1 text-3xl font-black">
                            {{ counts[item[1] as string] || 0 }}
                        </p>
                    </div>
                    <component
                        :is="item[0]"
                        class="size-7 text-primary" /></CardContent
            ></Card>
        </div>

        <div class="mb-4 flex justify-end">
            <select
                v-model="status"
                class="h-9 rounded-md border bg-background px-3 text-sm"
                @change="filter"
            >
                <option value="">Tous les statuts</option>
                <option value="pending">En attente</option>
                <option value="active">Approuvées</option>
                <option value="rejected">Refusées</option>
            </select>
        </div>

        <Card
            ><CardContent class="overflow-x-auto p-0"
                ><table class="w-full min-w-[980px] text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="p-4">Établissement</th>
                            <th class="p-4">Coordonnées</th>
                            <th class="p-4">Plan choisi</th>
                            <th class="p-4">Paiement</th>
                            <th class="p-4">Statut</th>
                            <th class="p-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in registrations.data"
                            :key="r.id"
                            class="border-b last:border-0"
                        >
                            <td class="p-4">
                                <p class="font-bold">{{ r.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ r.address || 'Adresse non renseignée'
                                    }}<br />{{
                                        [r.commune, r.wilaya]
                                            .filter(Boolean)
                                            .join(', ')
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    Envoyée le
                                    {{
                                        new Date(
                                            r.registration_submitted_at,
                                        ).toLocaleDateString('fr-DZ')
                                    }}
                                </p>
                            </td>
                            <td class="p-4">
                                <p>{{ r.email }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ r.phone || '—' }}
                                </p>
                            </td>
                            <td class="p-4">
                                <b>{{ r.subscription_plan?.name || '—' }}</b>
                                <p
                                    v-if="r.subscription_plan"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ r.subscription_plan.price }}
                                    {{ r.subscription_plan.currency }} ·
                                    {{ r.subscription_plan.billing_period }}
                                </p>
                            </td>
                            <td class="p-4">
                                <a
                                    :href="`${base}/registrations/${r.id}/payment-proof`"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 font-semibold text-primary hover:underline"
                                    ><FileCheck2 class="size-4" />Voir le
                                    justificatif <ExternalLink class="size-3"
                                /></a>
                            </td>
                            <td class="p-4">
                                <Badge
                                    :variant="badgeVariant(r.status) as any"
                                    >{{ label(r.status) }}</Badge
                                >
                                <p
                                    v-if="r.registration_rejection_reason"
                                    class="mt-2 max-w-48 text-xs text-destructive"
                                >
                                    {{ r.registration_rejection_reason }}
                                </p>
                            </td>
                            <td class="p-4">
                                <div
                                    v-if="r.status === 'pending'"
                                    class="flex gap-2"
                                >
                                    <Button size="sm" @click="approve(r)"
                                        >Approuver</Button
                                    ><Button
                                        size="sm"
                                        variant="outline"
                                        @click="selected = r"
                                        >Refuser</Button
                                    >
                                </div>
                                <Button
                                    v-else-if="r.status === 'rejected'"
                                    size="sm"
                                    @click="approve(r)"
                                    >Approuver après correction</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!registrations.data.length">
                            <td
                                colspan="6"
                                class="p-12 text-center text-muted-foreground"
                            >
                                Aucune inscription trouvée.
                            </td>
                        </tr>
                    </tbody>
                </table></CardContent
            ></Card
        >
        <div
            v-if="registrations.links?.length"
            class="mt-4 flex flex-wrap gap-1"
        >
            <Button
                v-for="link in registrations.links"
                :key="link.label"
                variant="outline"
                size="sm"
                :disabled="!link.url || link.active"
                @click="link.url && router.get(link.url)"
                v-html="link.label"
            />
        </div>

        <div
            v-if="selected"
            class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
        >
            <Card class="w-full max-w-md"
                ><CardContent class="p-6"
                    ><form @submit.prevent="reject">
                        <h2 class="text-lg font-black">
                            Refuser {{ selected.name }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Expliquez pourquoi le paiement ne peut pas être
                            validé. Ce message sera visible par le client.
                        </p>
                        <textarea
                            v-model="rejection.reason"
                            required
                            rows="5"
                            class="mt-4 w-full rounded-md border bg-background p-3 text-sm"
                            placeholder="Motif du refus…"
                        ></textarea
                        ><InputError :message="rejection.errors.reason" />
                        <div class="mt-4 flex gap-2">
                            <Button
                                variant="destructive"
                                :disabled="rejection.processing"
                                >Confirmer le refus</Button
                            ><Button
                                type="button"
                                variant="outline"
                                @click="selected = null"
                                >Annuler</Button
                            >
                        </div>
                    </form></CardContent
                ></Card
            >
        </div>
    </SuperAdminLayout>
</template>
