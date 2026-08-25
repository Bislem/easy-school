<script setup lang="ts">
import { appPrompt } from '@/composables/useAppDialog';
import BadgeCard from '@/components/BadgeCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { BriefcaseBusiness, Check, ChevronDown, ChevronsUpDown, CirclePause, Eye, GraduationCap, Printer, RefreshCw, Search, ShieldAlert, X } from 'lucide-vue-next';
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
    personSearch = ref(''),
    personPickerOpen = ref(false),
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
const selectedPerson = computed(() => people.value.find((person: any) => String(person.id) === String(form.person_id)));
const filteredPeople = computed(() => {
    const query = personSearch.value.trim().toLowerCase();
    return people.value.filter((person: any) => !query || `${person.first_name} ${person.last_name} ${person.email ?? person.user?.email ?? ''} ${person.employee_type?.name ?? ''} ${person.employee_code ?? ''}`.toLowerCase().includes(query));
});
watch(() => form.person_type, () => {
    form.person_id = '';
    personSearch.value = '';
    personPickerOpen.value = false;
});
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
function choosePerson(person: any) {
    form.person_id = String(person.id);
    personSearch.value = '';
    personPickerOpen.value = false;
}
async function setStatus(b: any, value: string) {
    const reason = await appPrompt(`Indiquez le motif du changement vers « ${labels[value]} ».`, { title: 'Modifier le statut', inputLabel: 'Motif', confirmText: 'Enregistrer' });
    if (reason !== null)
        router.patch(`/admin/badges/${b.id}/status`, { status: value, reason });
}
async function reissue(b: any) {
    const reason = await appPrompt('Indiquez le motif du remplacement ou du renouvellement.', { title: 'Réémettre le badge', inputLabel: 'Motif', inputRequired: true, confirmText: 'Réémettre' });
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
                <nav v-if="badges.links?.length > 3" class="flex max-w-full gap-1 overflow-x-auto"><Link v-for="link in badges.links" :key="link.label" :href="link.url||'#'" preserve-scroll class="rounded-md border px-3 py-2 text-sm" :class="{'bg-primary text-primary-foreground':link.active,'pointer-events-none opacity-40':!link.url}" v-html="link.label"/></nav>
                <div
                    v-if="open"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
                >
                    <form
                        class="w-full max-w-lg space-y-3 rounded-xl bg-background p-6"
                        @submit.prevent="generate"
                    >
                        <div class="flex items-start justify-between"><div><h2 class="text-lg font-semibold">Générer une carte</h2><p class="text-sm text-muted-foreground">Choisissez le type de personne puis recherchez son profil.</p></div><Button type="button" size="icon" variant="ghost" @click="open=false"><X class="size-4"/></Button></div>
                        <div><p class="mb-2 text-sm font-medium">Type de carte</p><div class="grid grid-cols-2 gap-3"><button v-for="option in [{value:'student',label:'Étudiant',description:'Carte scolaire',icon:GraduationCap},{value:'staff',label:'Personnel',description:'Enseignant ou employé',icon:BriefcaseBusiness}]" :key="option.value" type="button" class="relative rounded-xl border-2 p-4 text-left transition" :class="form.person_type===option.value?'border-primary bg-primary/5 shadow-sm':'border-muted hover:border-primary/40 hover:bg-muted/30'" @click="form.person_type=option.value"><span class="mb-3 inline-flex rounded-lg p-2" :class="form.person_type===option.value?'bg-primary text-primary-foreground':'bg-muted text-muted-foreground'"><component :is="option.icon" class="size-5"/></span><b class="block">{{option.label}}</b><small class="text-muted-foreground">{{option.description}}</small><span v-if="form.person_type===option.value" class="absolute right-3 top-3 rounded-full bg-primary p-1 text-primary-foreground"><Check class="size-3"/></span></button></div></div>
                        <div class="relative"><p class="mb-2 text-sm font-medium">{{form.person_type==='student'?'Étudiant':'Enseignant ou employé'}}</p><button type="button" class="flex h-11 w-full items-center justify-between rounded-md border bg-background px-3 text-left text-sm" @click="personPickerOpen=!personPickerOpen"><span v-if="selectedPerson"><b>{{selectedPerson.first_name}} {{selectedPerson.last_name}}</b><span v-if="selectedPerson.employee_type" class="ml-1 text-muted-foreground">· {{selectedPerson.employee_type.name}}</span></span><span v-else class="text-muted-foreground">Rechercher et sélectionner…</span><ChevronsUpDown class="size-4 text-muted-foreground"/></button><div v-if="personPickerOpen" class="absolute z-30 mt-1 w-full rounded-lg border bg-popover p-2 shadow-xl"><div class="relative mb-2"><Search class="absolute left-3 top-2.5 size-4 text-muted-foreground"/><Input v-model="personSearch" type="search" autofocus class="pl-9" :placeholder="form.person_type==='student'?'Nom de l’étudiant…':'Nom, matricule ou fonction…'"/></div><div class="max-h-60 overflow-y-auto"><button v-for="person in filteredPeople" :key="person.id" type="button" class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm hover:bg-muted" @click="choosePerson(person)"><Check class="size-4 shrink-0" :class="String(form.person_id)===String(person.id)?'opacity-100':'opacity-0'"/><span><b>{{person.first_name}} {{person.last_name}}</b><small v-if="person.employee_type || person.employee_code" class="block text-muted-foreground">{{person.employee_code}}<span v-if="person.employee_code&&person.employee_type"> · </span>{{person.employee_type?.name}}</small></span></button><p v-if="!filteredPeople.length" class="p-4 text-center text-sm text-muted-foreground">Aucun profil trouvé.</p></div></div><input v-model="form.person_id" type="hidden" required/></div>
                        <select
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
