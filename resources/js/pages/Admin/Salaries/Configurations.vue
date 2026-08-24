<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Settings, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    configurations: { type: Array, required: true },
    salaryTypes: { type: Array, required: true },
    currency: { type: Object, required: true },
});
const labels: Record<string, string> = { monthly: 'Mensuel fixe', hourly: 'Horaire', per_session: 'Par séance', daily: 'Journalier', custom: 'Manuel' };
const editorOpen = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ name: '', salary_type: 'monthly', base_rate: '', effective_from: new Date().toISOString().slice(0, 10), effective_to: '', notes: '' });
const money = (value: any) => `${Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} ${props.currency.symbol}`;

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    editorOpen.value = true;
}
function openEdit(configuration: any) {
    editingId.value = configuration.id;
    form.name = configuration.name;
    form.salary_type = configuration.salary_type;
    form.base_rate = String(configuration.base_rate);
    form.effective_from = configuration.effective_from;
    form.effective_to = configuration.effective_to ?? '';
    form.notes = configuration.notes ?? '';
    form.clearErrors();
    editorOpen.value = true;
}
function save() {
    const options = { preserveScroll: true, onSuccess: () => (editorOpen.value = false) };
    if (editingId.value) form.put(`/admin/salaries/configurations/${editingId.value}`, options);
    else form.post('/admin/salaries/configurations', options);
}
function remove(configuration: any) {
    if (window.confirm(`Supprimer la configuration « ${configuration.name} » ?`)) router.delete(`/admin/salaries/configurations/${configuration.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Paramètres de paie" />
    <AdminLayout><main class="flex-1 p-4 sm:p-6 lg:p-8"><div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><Button as-child variant="ghost" size="sm" class="mb-2 -ml-3"><Link href="/admin/salaries"><ArrowLeft class="mr-2 size-4"/>Retour aux paiements</Link></Button><h1 class="flex items-center gap-2 text-2xl font-semibold"><Settings class="size-6"/>Paramètres de paie</h1><p class="text-sm text-muted-foreground">Gérez les modes et taux de calcul disponibles pour les bulletins.</p></div><Button @click="openCreate"><Plus class="mr-2 size-4"/>Nouvelle configuration</Button></header>
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="configuration in configurations" :key="configuration.id" class="rounded-xl border bg-card p-5 shadow-sm"><div class="flex items-start justify-between gap-3"><div><b class="text-base">{{ configuration.name }}</b><p class="text-sm text-muted-foreground">{{ labels[configuration.salary_type] }}</p></div><span class="rounded-lg bg-primary/10 p-2 text-primary"><Settings class="size-5"/></span></div><p class="mt-5 text-2xl font-semibold">{{ money(configuration.base_rate) }}</p><p class="mt-1 text-xs text-muted-foreground">Valable du {{ configuration.effective_from }} au {{ configuration.effective_to || 'sans date de fin' }}</p><p v-if="configuration.notes" class="mt-3 text-sm text-muted-foreground">{{ configuration.notes }}</p><div class="mt-4 flex items-center justify-between border-t pt-4"><span class="text-xs text-muted-foreground">{{ configuration.statements_count }} bulletin(s)</span><div class="flex gap-1"><Button size="sm" variant="outline" @click="openEdit(configuration)"><Pencil class="mr-1 size-4"/>Modifier</Button><Button size="icon" variant="ghost" class="text-destructive" :disabled="configuration.statements_count > 0" :title="configuration.statements_count > 0 ? 'Configuration utilisée : définissez plutôt une date de fin' : 'Supprimer'" @click="remove(configuration)"><Trash2 class="size-4"/></Button></div></div></article><div v-if="!configurations.length" class="col-span-full rounded-xl border border-dashed bg-card p-12 text-center"><Settings class="mx-auto mb-3 size-8 text-muted-foreground"/><b>Aucune configuration salariale</b><p class="mt-1 text-sm text-muted-foreground">Créez la première configuration pour commencer à calculer les salaires.</p></div></section>
    </div></main>
    <div v-if="editorOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4"><form class="mx-auto my-10 max-w-xl space-y-4 rounded-xl bg-background p-6 shadow-xl" @submit.prevent="save"><div class="flex items-center justify-between"><div><h2 class="text-xl font-semibold">{{ editingId ? 'Modifier la configuration' : 'Nouvelle configuration' }}</h2><p class="text-sm text-muted-foreground">Définissez le mode de rémunération et sa période de validité.</p></div><Button type="button" size="icon" variant="ghost" @click="editorOpen = false"><X/></Button></div><label class="block"><Label>Nom</Label><Input v-model="form.name" required placeholder="Ex. Formateur horaire standard"/><InputError :message="form.errors.name"/></label><label class="block"><Label>Type de salaire</Label><select v-model="form.salary_type" class="h-9 w-full rounded-md border bg-background px-3"><option v-for="type in salaryTypes" :key="type" :value="type">{{ labels[type] }}</option></select><InputError :message="form.errors.salary_type"/></label><label class="block"><Label>Taux de base</Label><Input v-model="form.base_rate" required type="number" min="0" step="0.01"/><InputError :message="form.errors.base_rate"/></label><div class="grid gap-3 sm:grid-cols-2"><label><Label>Date de début</Label><Input v-model="form.effective_from" required type="date"/><InputError :message="form.errors.effective_from"/></label><label><Label>Date de fin</Label><Input v-model="form.effective_to" type="date"/><InputError :message="form.errors.effective_to"/></label></div><label class="block"><Label>Notes</Label><textarea v-model="form.notes" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" placeholder="Informations facultatives…"></textarea><InputError :message="form.errors.notes"/></label><InputError :message="form.errors.configuration"/><div class="flex justify-end gap-2"><Button type="button" variant="outline" @click="editorOpen = false">Annuler</Button><Button :disabled="form.processing">{{ editingId ? 'Enregistrer les modifications' : 'Créer la configuration' }}</Button></div></form></div>
    </AdminLayout>
</template>
