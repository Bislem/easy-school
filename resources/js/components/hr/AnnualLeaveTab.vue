<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appConfirm, appPrompt } from '@/composables/useAppDialog';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ employee: any; summary: any }>();
const form = useForm({
    mode: 'days',
    month: '',
    starts_at: '',
    ends_at: '',
    reason: '',
    notes: '',
});
const labels: Record<string, string> = {
    pending: 'En attente',
    approved: 'Approuvé',
    rejected: 'Refusé',
    cancelled: 'Annulé',
    taken: 'Pris / repris',
};
const colors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-100 text-slate-700',
    taken: 'bg-emerald-100 text-emerald-800',
};
const requestedDays = computed(() => {
    if (form.mode === 'full_month') return form.month ? 30 : 0;
    if (!form.starts_at || !form.ends_at) return 0;
    return Math.max(
        0,
        Math.round(
            (new Date(form.ends_at).getTime() -
                new Date(form.starts_at).getTime()) /
                86400000,
        ) + 1,
    );
});
const submit = () =>
    form.post(`/admin/staff/${props.employee.id}/annual-leaves`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const patch = (leave: any, action: string, data: object = {}) =>
    router.patch(
        `/admin/staff/${props.employee.id}/annual-leaves/${leave.id}/${action}`,
        data,
        { preserveScroll: true },
    );
const reject = async (leave: any) => {
    const reason = await appPrompt(
        'Indiquez pourquoi cette demande ne peut pas être acceptée. Le motif sera conservé dans l’historique RH.',
        {
            title: 'Refuser le congé annuel',
            inputLabel: 'Motif du refus',
            inputPlaceholder: 'Saisissez un motif précis…',
            inputRequired: true,
            confirmText: 'Confirmer le refus',
            tone: 'danger',
        },
    );
    if (reason) patch(leave, 'reject', { rejection_reason: reason });
};
const complete = async (leave: any) => {
    const date = await appPrompt(
        'Renseignez la date à laquelle l’employé a effectivement repris son poste.',
        {
            title: 'Enregistrer la reprise',
            inputLabel: 'Date effective de reprise',
            inputRequired: true,
            inputType: 'date',
            inputValue: new Date().toISOString().slice(0, 10),
            confirmText: 'Enregistrer',
            tone: 'success',
        },
    );
    if (date) patch(leave, 'complete', { actual_return_date: date });
};
const cancel = async (leave: any) => {
    if (
        await appConfirm(
            'Cette action annulera le congé et libérera les jours réservés. L’opération restera visible dans l’historique.',
            {
                title: 'Annuler ce congé ?',
                confirmText: 'Annuler le congé',
                tone: 'warning',
            },
        )
    )
        patch(leave, 'cancel');
};
const printUrl = (leave: any, document: string) =>
    `/admin/staff/${props.employee.id}/annual-leaves/${leave.id}/print?document=${document}`;
const date = (value: string) =>
    value
        ? new Intl.DateTimeFormat('fr-DZ').format(
              new Date(`${value.substring(0, 10)}T00:00:00`),
          )
        : '—';
</script>

<template>
    <section class="space-y-6">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    Droits disponibles bruts
                </p>
                <b class="text-2xl">{{ summary.accrued }} j</b>
                <p class="text-xs text-muted-foreground">
                    <template v-if="summary.balance_as_of"
                        >Reprise {{ summary.opening_balance }} j au
                        {{ date(summary.balance_as_of) }} + </template
                    >{{ summary.completed_months }} mois × {{ summary.rate }} j
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">Pris / approuvés</p>
                <b class="text-2xl">{{ summary.used }} j</b>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">Réservés en attente</p>
                <b class="text-2xl">{{ summary.pending }} j</b>
            </div>
            <div class="rounded-xl border border-primary/30 bg-primary/5 p-4">
                <p class="text-sm text-muted-foreground">Solde disponible</p>
                <b class="text-2xl text-primary">{{ summary.available }} j</b>
            </div>
        </div>

        <form class="rounded-xl border bg-card p-5" @submit.prevent="submit">
            <h2 class="font-semibold">Nouvelle demande de congé annuel</h2>
            <p class="text-sm text-muted-foreground">
                Un mois complet consomme 30 jours. Une période en jours est
                comptée de façon calendaire, dates incluses.
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <Label>Format *</Label
                    ><select
                        v-model="form.mode"
                        class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                    >
                        <option value="days">Période en jours</option>
                        <option value="full_month">
                            Mois complet (30 jours)
                        </option>
                    </select>
                </div>
                <div v-if="form.mode === 'full_month'">
                    <Label>Mois *</Label
                    ><Input v-model="form.month" type="month" required />
                </div>
                <template v-else
                    ><div>
                        <Label>Du *</Label
                        ><Input v-model="form.starts_at" type="date" required />
                    </div>
                    <div>
                        <Label>Au *</Label
                        ><Input
                            v-model="form.ends_at"
                            type="date"
                            required
                        /></div
                ></template>
                <div class="rounded-lg bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">Durée demandée</p>
                    <b>{{ requestedDays }} jour(s)</b>
                </div>
                <div class="md:col-span-2">
                    <Label>Motif / objet</Label
                    ><Input
                        v-model="form.reason"
                        placeholder="Congé annuel..."
                    />
                </div>
                <div class="md:col-span-2">
                    <Label>Notes RH</Label
                    ><Input
                        v-model="form.notes"
                        placeholder="Remplacement, consignes..."
                    />
                </div>
            </div>
            <div
                v-if="Object.keys(form.errors).length"
                class="mt-3 rounded-md bg-destructive/10 p-3 text-sm text-destructive"
            >
                <p v-for="error in form.errors" :key="error">{{ error }}</p>
            </div>
            <Button class="mt-4" :disabled="form.processing || !requestedDays"
                >Enregistrer la demande</Button
            >
        </form>

        <div class="rounded-xl border bg-card p-5">
            <h2 class="font-semibold">Historique des congés</h2>
            <div v-if="employee.annual_leaves?.length" class="mt-4 space-y-3">
                <article
                    v-for="leave in employee.annual_leaves"
                    :key="leave.id"
                    class="rounded-lg border p-4"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <b>{{
                                    leave.mode === 'full_month'
                                        ? 'Mois complet'
                                        : 'Congé en jours'
                                }}</b
                                ><span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="colors[leave.status]"
                                    >{{ labels[leave.status] }}</span
                                >
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Du {{ date(leave.starts_at) }} au
                                {{ date(leave.ends_at) }} ·
                                <b>{{ Number(leave.days) }} jour(s)</b>
                            </p>
                            <p v-if="leave.reason" class="mt-1 text-sm">
                                {{ leave.reason }}
                            </p>
                            <p
                                v-if="leave.rejection_reason"
                                class="mt-1 text-sm text-destructive"
                            >
                                Refus : {{ leave.rejection_reason }}
                            </p>
                            <p
                                v-if="leave.actual_return_date"
                                class="mt-1 text-sm"
                            >
                                Reprise effective :
                                {{ date(leave.actual_return_date) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button as-child size="sm" variant="outline"
                                ><a :href="printUrl(leave, 'request')"
                                    >Demande PDF</a
                                ></Button
                            >
                            <Button
                                v-if="
                                    ['approved', 'taken'].includes(leave.status)
                                "
                                as-child
                                size="sm"
                                variant="outline"
                                ><a :href="printUrl(leave, 'authorization')"
                                    >Autorisation PDF</a
                                ></Button
                            >
                            <Button
                                v-if="leave.status === 'taken'"
                                as-child
                                size="sm"
                                variant="outline"
                                ><a :href="printUrl(leave, 'return')"
                                    >Reprise PDF</a
                                ></Button
                            >
                            <Button
                                v-if="leave.status === 'pending'"
                                size="sm"
                                @click="patch(leave, 'approve')"
                                >Approuver</Button
                            >
                            <Button
                                v-if="leave.status === 'pending'"
                                size="sm"
                                variant="destructive"
                                @click="reject(leave)"
                                >Refuser</Button
                            >
                            <Button
                                v-if="
                                    ['pending', 'approved'].includes(
                                        leave.status,
                                    )
                                "
                                size="sm"
                                variant="outline"
                                @click="cancel(leave)"
                                >Annuler</Button
                            >
                            <Button
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
                            Journal d'audit ({{ leave.events.length }})
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
                Aucun congé annuel enregistré.
            </p>
        </div>
        <div
            v-if="employee.leave_balance_adjustments?.length"
            class="rounded-xl border bg-card p-5"
        >
            <h2 class="font-semibold">
                Historique des reprises et corrections de solde
            </h2>
            <div class="mt-3 divide-y text-sm">
                <div
                    v-for="item in employee.leave_balance_adjustments"
                    :key="item.id"
                    class="py-3"
                >
                    <b
                        >{{ item.previous_balance ?? 'Non défini' }} j →
                        {{ item.new_balance ?? 'Non défini' }} j</b
                    ><span class="text-muted-foreground">
                        · valable au {{ date(item.new_as_of) }} · par
                        {{ item.creator?.name || 'Système' }}</span
                    >
                    <p v-if="item.reason" class="text-muted-foreground">
                        {{ item.reason }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
