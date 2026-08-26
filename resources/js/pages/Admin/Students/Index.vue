<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, Plus, Search, X } from 'lucide-vue-next';
import { ref } from 'vue';
const props = defineProps<{
    students: any;
    courses: any[];
    levels: string[];
    groups: number[];
    studentStatuses: string[];
    filters: any;
}>();
const labels: Record<string, string> = {
    active: 'Actif',
    enrolled: 'Présent / inscrit',
    waiting: 'En attente',
    stopped: 'Arrêté',
    suspended: 'Suspendu',
    completed: 'Terminé',
    cancelled: 'Annulé',
};
const filters = ref({
    search: props.filters.search ?? '',
    course_id: props.filters.course_id ?? '',
    level: props.filters.level ?? '',
    group: props.filters.group ?? '',
    student_status: props.filters.student_status ?? '',
    registered_from: props.filters.registered_from ?? '',
    registered_to: props.filters.registered_to ?? '',
});
const modal = ref(false);
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    parent_phone: '',
    birth_date: '',
    registration_date: new Date().toISOString().slice(0, 10),
    school_level: '',
    address: '',
    notes: '',
    status: 'active',
    is_active: true,
    photo: null as File | null,
});
function apply() {
    router.get('/admin/students', filters.value, {
        preserveState: true,
        replace: true,
    });
}
function submit() {
    form.post('/admin/students', {
        forceFormData: true,
        onSuccess: () => (modal.value = false),
    });
}
function photo(e: Event) {
    form.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function pageLabel(v: string) {
    return v
        .replace('&laquo; Previous', 'Précédent')
        .replace('Next &raquo;', 'Suivant');
}
</script>
<template>
    <Head title="Étudiants" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Dossiers étudiants
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Un dossier peut contenir plusieurs inscriptions au
                            fil du temps.
                        </p>
                    </div>
                    <Button @click="modal = true"
                        ><Plus class="mr-2 size-4" />Ajouter un étudiant</Button
                    >
                </header>
                <form
                    class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
                    @submit.prevent="apply"
                >
                    <div class="relative sm:col-span-2">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="filters.search"
                            class="pl-9"
                            placeholder="Nom, e-mail ou téléphone"
                        />
                    </div>
                    <select
                        v-model="filters.course_id"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">Toutes les formations</option>
                        <option
                            v-for="c in courses"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.title }}
                        </option></select
                    ><select
                        v-model="filters.student_status"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">Tous les statuts</option>
                        <option
                            v-for="s in studentStatuses"
                            :key="s"
                            :value="s"
                        >
                            {{ labels[s] }}
                        </option></select
                    ><select
                        v-model="filters.level"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">Tous les niveaux</option>
                        <option v-for="l in levels" :key="l" :value="l">
                            {{ l }}
                        </option></select
                    ><select
                        v-model="filters.group"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">Tous les groupes</option>
                        <option v-for="g in groups" :key="g" :value="String(g)">
                            Groupe {{ g }}
                        </option></select
                    ><Input
                        v-model="filters.registered_from"
                        type="date"
                    /><Input v-model="filters.registered_to" type="date" />
                    <div class="flex justify-end sm:col-span-2 lg:col-span-4">
                        <Button variant="outline">Appliquer les filtres</Button>
                    </div>
                </form>
                <section class="overflow-hidden rounded-xl border bg-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40 text-left">
                                <tr>
                                    <th class="p-4">Étudiant</th>
                                    <th class="p-4">Formation actuelle</th>
                                    <th class="p-4">Niveau / groupe</th>
                                    <th class="p-4">Statut</th>
                                    <th class="p-4 text-right">Dossier</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="student in students.data"
                                    :key="student.id"
                                >
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <img
                                                v-if="student.photo_url"
                                                :src="student.photo_url"
                                                class="size-10 rounded-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="grid size-10 place-items-center rounded-full bg-muted"
                                            >
                                                {{ student.first_name[0]
                                                }}{{ student.last_name[0] }}
                                            </div>
                                            <div>
                                                <p class="font-medium">
                                                    {{ student.full_name }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ student.phone }} ·
                                                    {{
                                                        student.email ||
                                                        'Sans e-mail'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        {{
                                            student.enrollments[0]?.form?.course
                                                ?.title ||
                                            student.enrollments[0]
                                                ?.training_plan_group?.plan
                                                ?.course?.title ||
                                            '—'
                                        }}
                                    </td>
                                    <td class="p-4">
                                        {{
                                            student.enrollments[0]?.level || '—'
                                        }}
                                        /
                                        {{
                                            student.enrollments[0]?.group_number
                                                ? `Groupe ${student.enrollments[0].group_number}`
                                                : '—'
                                        }}
                                    </td>
                                    <td class="p-4">
                                        <span
                                            class="rounded-full bg-muted px-2 py-1 text-xs"
                                            >{{ labels[student.status] }}</span
                                        >
                                    </td>
                                    <td class="p-4 text-right">
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="outline"
                                            ><Link
                                                :href="`/admin/students/${student.id}`"
                                                ><Eye
                                                    class="mr-2 size-4"
                                                />Ouvrir</Link
                                            ></Button
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!students.data.length">
                                    <td
                                        colspan="5"
                                        class="p-10 text-center text-muted-foreground"
                                    >
                                        Aucun étudiant trouvé.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <nav class="flex flex-wrap justify-center gap-1">
                    <Link
                        v-for="l in students.links"
                        :key="l.label"
                        :href="l.url || '#'"
                        class="rounded-md border px-3 py-2 text-sm"
                        :class="{
                            'bg-primary text-primary-foreground': l.active,
                            'pointer-events-none opacity-40': !l.url,
                        }"
                        v-html="pageLabel(l.label)"
                    />
                </nav>
            </div>
        </main>
        <div
            v-if="modal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4"
        >
            <div class="mx-auto my-8 max-w-2xl rounded-xl bg-background p-6">
                <div class="flex justify-between">
                    <h2 class="text-xl font-semibold">
                        Nouveau dossier étudiant
                    </h2>
                    <Button variant="ghost" size="icon" @click="modal = false"
                        ><X
                    /></Button>
                </div>
                <form
                    class="mt-5 grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div>
                        <Label>Prénom</Label
                        ><Input v-model="form.first_name" required /><InputError
                            :message="form.errors.first_name"
                        />
                    </div>
                    <div>
                        <Label>Nom</Label
                        ><Input v-model="form.last_name" required />
                    </div>
                    <div>
                        <Label>Téléphone</Label
                        ><Input v-model="form.phone" required />
                    </div>
                    <div>
                        <Label>Téléphone parent</Label
                        ><Input v-model="form.parent_phone" />
                    </div>
                    <div>
                        <Label>E-mail</Label
                        ><Input v-model="form.email" type="email" /><InputError
                            :message="form.errors.email"
                        />
                    </div>
                    <div>
                        <Label>Date de naissance</Label
                        ><Input v-model="form.birth_date" type="date" />
                    </div>
                    <div>
                        <Label>Date d’inscription</Label
                        ><Input v-model="form.registration_date" type="date" />
                    </div>
                    <div>
                        <Label>Niveau scolaire</Label
                        ><Input v-model="form.school_level" />
                    </div>
                    <div>
                        <Label>Photo</Label
                        ><Input type="file" accept="image/*" @change="photo" />
                    </div>
                    <div>
                        <Label>Statut</Label
                        ><select
                            v-model="form.status"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="s in studentStatuses"
                                :key="s"
                                :value="s"
                            >
                                {{ labels[s] }}
                            </option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <Label>Adresse</Label><Input v-model="form.address" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label>Notes</Label
                        ><textarea
                            v-model="form.notes"
                            class="mt-1 min-h-24 w-full rounded-md border bg-background p-3"
                        />
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="modal = false"
                            >Annuler</Button
                        ><Button :disabled="form.processing">Créer</Button>
                    </div>
                </form>
            </div>
        </div></AdminLayout
    >
</template>
