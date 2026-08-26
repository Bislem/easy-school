<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appConfirm, appPrompt } from '@/composables/useAppDialog';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<{ employee: any; summary: any }>();
const form = useForm({
    category: 'illness',
    starts_at: '',
    ends_at: '',
    certificate_received: false,
    certificate_reference: '',
    certificate_issued_at: '',
    health_professional: '',
    administrative_notes: '',
});
const categories: Record<string, string> = {
    illness: 'Maladie',
    work_accident: 'Accident du travail',
    hospitalization: 'Hospitalisation',
    medical_recovery: 'Convalescence',
};
const labels: Record<string, string> = {
    pending: 'En attente',
    approved: 'Validé',
    rejected: 'Refusé',
    cancelled: 'Annulé',
    taken: 'Terminé / repris',
};
const colors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-100 text-slate-700',
    taken: 'bg-emerald-100 text-emerald-800',
};
const days = computed(() =>
    !form.starts_at || !form.ends_at
        ? 0
        : Math.max(
              0,
              Math.round(
                  (new Date(form.ends_at).getTime() -
                      new Date(form.starts_at).getTime()) /
                      86400000,
              ) + 1,
          ),
);
const date = (v: string) =>
    v
        ? new Intl.DateTimeFormat('fr-DZ').format(
              new Date(`${v.substring(0, 10)}T00:00:00`),
          )
        : '—';
const submit = () =>
    form.post(`/admin/staff/${props.employee.id}/sick-leaves`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const patch = (leave: any, action: string, data: object = {}) =>
    router.patch(
        `/admin/staff/${props.employee.id}/sick-leaves/${leave.id}/${action}`,
        data,
        { preserveScroll: true },
    );
const reject = async (leave: any) => {
    const reason = await appPrompt(
        'Indiquez le motif administratif du refus. Il sera conservé dans le journal confidentiel.',
        {
            title: 'Refuser le congé maladie',
            inputLabel: 'Motif du refus',
            inputPlaceholder: 'Justificatif invalide, période incorrecte…',
            inputRequired: true,
            confirmText: 'Confirmer le refus',
            tone: 'danger',
        },
    );
    if (reason) patch(leave, 'reject', { rejection_reason: reason });
};
const complete = async (leave: any) => {
    const returnDate = await appPrompt(
        'Renseignez la date à laquelle l’employé a effectivement repris son poste.',
        {
            title: 'Reprise après congé maladie',
            inputLabel: 'Date effective de reprise',
            inputRequired: true,
            inputType: 'date',
            inputValue: new Date().toISOString().slice(0, 10),
            confirmText: 'Continuer',
            tone: 'success',
        },
    );
    if (!returnDate) return;
    const fit = await appConfirm(
        'Confirmez-vous que l’aptitude ou la reprise médicale a été vérifiée lorsqu’elle est légalement requise ?',
        {
            title: 'Aptitude à la reprise',
            confirmText: 'Aptitude confirmée',
            cancelText: 'Non confirmée',
            tone: 'warning',
        },
    );
    patch(leave, 'complete', {
        actual_return_date: returnDate,
        fit_to_return_confirmed: fit,
    });
};
const cancel = async (leave: any) => {
    const confirmed = await appConfirm(
        'Le congé maladie sera annulé mais restera visible dans le journal RH confidentiel.',
        {
            title: 'Annuler ce congé maladie ?',
            confirmText: 'Confirmer l’annulation',
            tone: 'warning',
        },
    );
    if (confirmed) patch(leave, 'cancel');
};
const printUrl = (leave: any, document: string) =>
    `/admin/staff/${props.employee.id}/sick-leaves/${leave.id}/print?document=${document}`;
</script>
<template>
    <section class="space-y-6">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    Jours maladie cumulés
                </p>
                <b class="text-2xl">{{ summary.total_days }} j</b>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    Année {{ new Date().getFullYear() }}
                </p>
                <b class="text-2xl">{{ summary.current_year_days }} j</b>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">En attente</p>
                <b class="text-2xl">{{ summary.pending }}</b>
            </div>
            <div
                class="rounded-xl border p-4"
                :class="
                    summary.missing_certificates
                        ? 'border-amber-300 bg-amber-50'
                        : 'bg-card'
                "
            >
                <p class="text-sm text-muted-foreground">
                    Justificatifs manquants
                </p>
                <b class="text-2xl">{{ summary.missing_certificates }}</b>
            </div>
        </div>
        <form class="rounded-xl border bg-card p-5" @submit.prevent="submit">
            <h2 class="font-semibold">Nouvel arrêt / congé maladie</h2>
            <p class="text-sm text-muted-foreground">
                Les informations médicales restent limitées aux données
                administratives nécessaires. Déposez le certificat dans l’onglet
                Documents, catégorie « Document médical ».
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <Label>Nature *</Label
                    ><select
                        v-model="form.category"
                        class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="(label, key) in categories"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div>
                    <Label>Du *</Label
                    ><Input v-model="form.starts_at" type="date" required />
                </div>
                <div>
                    <Label>Au *</Label
                    ><Input v-model="form.ends_at" type="date" required />
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Durée calendaire
                    </p>
                    <b>{{ days }} jour(s)</b>
                </div>
                <label class="flex items-center gap-2 rounded-lg border p-3"
                    ><input
                        v-model="form.certificate_received"
                        type="checkbox"
                    />
                    Certificat reçu</label
                >
                <div>
                    <Label>Référence du certificat</Label
                    ><Input v-model="form.certificate_reference" />
                </div>
                <div>
                    <Label>Date d’émission</Label
                    ><Input v-model="form.certificate_issued_at" type="date" />
                </div>
                <div>
                    <Label>Médecin / établissement</Label
                    ><Input v-model="form.health_professional" />
                </div>
                <div class="md:col-span-2 lg:col-span-4">
                    <Label>Notes administratives confidentielles</Label
                    ><Input
                        v-model="form.administrative_notes"
                        placeholder="Organisation du remplacement, formalités..."
                    />
                </div>
            </div>
            <div
                v-if="Object.keys(form.errors).length"
                class="mt-3 rounded-md bg-destructive/10 p-3 text-sm text-destructive"
            >
                <p v-for="error in form.errors" :key="error">{{ error }}</p>
            </div>
            <Button class="mt-4" :disabled="form.processing || !days"
                >Enregistrer l’arrêt</Button
            >
        </form>
        <div class="rounded-xl border bg-card p-5">
            <h2 class="font-semibold">Historique des congés maladie</h2>
            <div v-if="employee.sick_leaves?.length" class="mt-4 space-y-3">
                <article
                    v-for="leave in employee.sick_leaves"
                    :key="leave.id"
                    class="rounded-lg border p-4"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <b>{{ categories[leave.category] }}</b
                                ><span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="colors[leave.status]"
                                    >{{ labels[leave.status] }}</span
                                ><span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="
                                        leave.certificate_received
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-amber-100 text-amber-800'
                                    "
                                    >{{
                                        leave.certificate_received
                                            ? 'Certificat reçu'
                                            : 'Justificatif manquant'
                                    }}</span
                                >
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Du {{ date(leave.starts_at) }} au
                                {{ date(leave.ends_at) }} ·
                                <b>{{ leave.days }} jour(s)</b>
                            </p>
                            <p
                                v-if="leave.certificate_reference"
                                class="text-sm"
                            >
                                Certificat {{ leave.certificate_reference
                                }}<span v-if="leave.health_professional">
                                    · {{ leave.health_professional }}</span
                                >
                            </p>
                            <p
                                v-if="leave.rejection_reason"
                                class="text-sm text-destructive"
                            >
                                Refus : {{ leave.rejection_reason }}
                            </p>
                            <p v-if="leave.actual_return_date" class="text-sm">
                                Reprise : {{ date(leave.actual_return_date) }} ·
                                Aptitude
                                {{
                                    leave.fit_to_return_confirmed
                                        ? 'confirmée'
                                        : 'non renseignée'
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button as-child size="sm" variant="outline"
                                ><a :href="printUrl(leave, 'declaration')"
                                    >Déclaration PDF</a
                                ></Button
                            ><Button
                                v-if="
                                    ['approved', 'taken'].includes(leave.status)
                                "
                                as-child
                                size="sm"
                                variant="outline"
                                ><a :href="printUrl(leave, 'decision')"
                                    >Décision PDF</a
                                ></Button
                            ><Button
                                v-if="leave.status === 'taken'"
                                as-child
                                size="sm"
                                variant="outline"
                                ><a :href="printUrl(leave, 'return')"
                                    >Reprise PDF</a
                                ></Button
                            ><Button
                                v-if="leave.status === 'pending'"
                                size="sm"
                                @click="patch(leave, 'approve')"
                                >Valider</Button
                            ><Button
                                v-if="leave.status === 'pending'"
                                size="sm"
                                variant="destructive"
                                @click="reject(leave)"
                                >Refuser</Button
                            ><Button
                                v-if="
                                    ['pending', 'approved'].includes(
                                        leave.status,
                                    )
                                "
                                size="sm"
                                variant="outline"
                                @click="cancel(leave)"
                                >Annuler</Button
                            ><Button
                                v-if="leave.status === 'approved'"
                                size="sm"
                                variant="secondary"
                                @click="complete(leave)"
                                >Enregistrer la reprise</Button
                            >
                        </div>
                    </div>
                    <details
                        v-if="leave.events?.length"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        <summary class="cursor-pointer">
                            Journal d’audit ({{ leave.events.length }})
                        </summary>
                        <p
                            v-for="event in leave.events"
                            :key="event.id"
                            class="mt-1"
                        >
                            {{ event.created_at }} —
                            {{ event.actor?.name || 'Système' }} :
                            {{ event.event
                            }}<span v-if="event.notes">
                                — {{ event.notes }}</span
                            >
                        </p>
                    </details>
                </article>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Aucun congé maladie enregistré.
            </p>
        </div>
    </section>
</template>
