<script setup lang="ts">
import BadgeCard from '@/components/BadgeCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ChevronDown, Eye, Printer, RefreshCw, ShieldAlert, CirclePause } from 'lucide-vue-next';
const props = defineProps({
    badges: { type: Object, required: true },
    students: { type: Array, required: true },
    staff: { type: Array, required: true },
    templates: { type: Array, required: true },
    statuses: { type: Array, required: true },
    filters: { type: Object, required: true },
});
const page = usePage();
const open = ref(false),
    selected = ref<number[]>([]),
    preview = ref<any>(null),
    search = ref((props.filters as any).search ?? ''),
    type = ref((props.filters as any).type ?? ''),
    status = ref((props.filters as any).status ?? '');
const form = useForm({
    person_type: 'student',
    person_id: '',
    badge_template_id: (props.templates[0] as any)?.id ?? '',
    issue_date: new Date().toISOString().slice(0, 10),
    expiration_date: '',
    barcode_enabled: false,
});
const people = computed(() =>
    form.person_type === 'student' ? props.students : props.staff,
);
const labels: any = {
    active: 'Active',
    expired: 'Expirée',
    suspended: 'Suspendue',
    lost: 'Perdue',
    replaced: 'Remplacée',
    cancelled: 'Annulée',
};
function filter() {
    router.get(
        '/admin/badges',
        {
            search: search.value || undefined,
            type: type.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}
function generate() {
    form.post('/admin/badges', {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
function setStatus(b: any, value: string) {
    const reason = prompt(`Motif (${labels[value]})`);
    if (reason !== null)
        router.patch(`/admin/badges/${b.id}/status`, { status: value, reason });
}
function reissue(b: any) {
    const reason = prompt('Motif du remplacement / renouvellement');
    if (reason)
        router.post(`/admin/badges/${b.id}/reissue`, {
            reason,
            issue_date: new Date().toISOString().slice(0, 10),
            expiration_date: '',
        });
}
</script>
<template>
    <Head title="Badges" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header class="flex justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold">Badges et cartes</h1>
                        <p class="text-muted-foreground">
                            Cartes étudiants et professionnelles.
                        </p>
                    </div>
                    <Button @click="open = true">Générer une carte</Button>
                </header>
                <section
                    class="flex flex-wrap gap-2 rounded-xl border bg-card p-4"
                >
                    <Input
                        v-model="search"
                        class="max-w-sm"
                        placeholder="Nº de carte ou personne"
                        @keyup.enter="filter"
                    /><select
                        v-model="type"
                        class="rounded-md border bg-background px-3"
                        @change="filter"
                    >
                        <option value="">Étudiants et personnel</option>
                        <option value="student">Étudiants</option>
                        <option value="staff">Personnel</option></select
                    ><select
                        v-model="status"
                        class="rounded-md border bg-background px-3"
                        @change="filter"
                    >
                        <option value="">Tous les statuts</option>
                        <option v-for="s in statuses" :key="s" :value="s">
                            {{ labels[s] }}
                        </option>
                    </select>
                    <form
                        action="/admin/badges/print-batch"
                        method="post"
                        target="_blank"
                    >
                        <input
                            type="hidden"
                            name="_token"
                            :value="page.props.csrf_token"
                        /><input
                            v-for="id in selected"
                            :key="id"
                            type="hidden"
                            name="ids[]"
                            :value="id"
                        /><Button variant="outline" :disabled="!selected.length"
                            >Imprimer la sélection ({{
                                selected.length
                            }})</Button
                        >
                    </form>
                </section>
                <section class="overflow-x-auto rounded-xl border bg-card">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="p-3"></th>
                                <th>Carte</th>
                                <th>Personne</th>
                                <th>Fonction / formation</th>
                                <th>Validité</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="b in badges.data"
                                :key="b.id"
                                class="border-b"
                            >
                                <td class="p-3">
                                    <input
                                        v-model="selected"
                                        type="checkbox"
                                        :value="b.id"
                                    />
                                </td>
                                <td class="font-mono">{{ b.card_number }}</td>
                                <td>
                                    {{ b.first_name }} {{ b.last_name }}
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            b.person_type === 'student'
                                                ? 'Étudiant'
                                                : 'Personnel'
                                        }}
                                    </div>
                                </td>
                                <td>
                                    {{ b.role_label }}
                                    <div class="text-xs text-muted-foreground">
                                        {{ b.formation_label }}
                                        {{ b.group_label }}
                                    </div>
                                </td>
                                <td>
                                    {{ b.issue_date }}
                                    <div class="text-xs">
                                        {{
                                            b.expiration_date ||
                                            'Sans expiration'
                                        }}
                                    </div>
                                </td>
                                <td>{{ labels[b.display_status] }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <DropdownMenu><DropdownMenuTrigger as-child><Button size="sm" variant="outline">Actions<ChevronDown class="ml-2 size-4"/></Button></DropdownMenuTrigger><DropdownMenuContent align="end" class="w-52"><DropdownMenuLabel>{{b.first_name}} {{b.last_name}}</DropdownMenuLabel><DropdownMenuSeparator/><DropdownMenuItem @select="preview=b"><Eye class="mr-2 size-4"/>Aperçu</DropdownMenuItem><DropdownMenuItem as-child><a :href="`/admin/badges/${b.id}/print`"><Printer class="mr-2 size-4"/>Imprimer</a></DropdownMenuItem><DropdownMenuItem @select="reissue(b)"><RefreshCw class="mr-2 size-4"/>Rééditer</DropdownMenuItem><DropdownMenuSeparator/><DropdownMenuItem @select="setStatus(b,'lost')"><ShieldAlert class="mr-2 size-4"/>Marquer perdue</DropdownMenuItem><DropdownMenuItem @select="setStatus(b,'suspended')"><CirclePause class="mr-2 size-4"/>Suspendre</DropdownMenuItem></DropdownMenuContent></DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <div
                    v-if="open"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
                >
                    <form
                        class="w-full max-w-lg space-y-3 rounded-xl bg-background p-6"
                        @submit.prevent="generate"
                    >
                        <h2 class="text-lg font-semibold">Générer une carte</h2>
                        <select
                            v-model="form.person_type"
                            class="h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option value="student">Étudiant</option>
                            <option value="staff">
                                Personnel / enseignant
                            </option></select
                        ><select
                            v-model="form.person_id"
                            class="h-9 w-full rounded-md border bg-background px-3"
                            required
                        >
                            <option value="">Sélectionner</option>
                            <option
                                v-for="p in people"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.first_name }} {{ p.last_name
                                }}{{
                                    p.employee_type
                                        ? ' — ' + p.employee_type.name
                                        : ''
                                }}
                            </option></select
                        ><select
                            v-model="form.badge_template_id"
                            class="h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="t in templates"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                            </option>
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <Input
                                v-model="form.issue_date"
                                type="date"
                                required
                            /><Input
                                v-model="form.expiration_date"
                                type="date"
                            />
                        </div>
                        <label class="flex gap-2 text-sm"
                            ><input
                                v-model="form.barcode_enabled"
                                type="checkbox"
                            />Ajouter la référence code-barres</label
                        ><Button class="w-full">Générer</Button
                        ><Button
                            type="button"
                            variant="ghost"
                            class="w-full"
                            @click="open = false"
                            >Fermer</Button
                        >
                    </form>
                </div>
                <div
                    v-if="preview"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4"
                    @click.self="preview = null"
                >
                    <div class="w-full max-w-xl space-y-3">
                        <BadgeCard
                            :badge="preview"
                            :school="page.props.school"
                        /><Button
                            class="w-full"
                            variant="secondary"
                            @click="preview = null"
                            >Fermer</Button
                        >
                    </div>
                </div>
            </div>
        </main></AdminLayout
    >
</template>
