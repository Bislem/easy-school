<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Mail,
    Pencil,
    Phone,
    Plus,
    Search,
    UserRound,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Student {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    birth_date?: string | null;
    address?: string | null;
    notes?: string | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    students: { data: Student[]; links: PaginationLink[]; total: number };
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const modalOpen = ref(false);
const editingStudent = ref<Student | null>(null);
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    birth_date: '',
    address: '',
    notes: '',
    is_active: true,
});

function applyFilters() {
    router.get(
        '/admin/students',
        { search: search.value, status: status.value },
        { preserveState: true, replace: true },
    );
}

function openCreate() {
    editingStudent.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    modalOpen.value = true;
}

function openEdit(student: Student) {
    editingStudent.value = student;
    form.clearErrors();
    form.first_name = student.first_name;
    form.last_name = student.last_name;
    form.email = student.email;
    form.phone = student.phone;
    form.birth_date = student.birth_date ?? '';
    form.address = student.address ?? '';
    form.notes = student.notes ?? '';
    form.is_active = student.is_active;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    form.clearErrors();
}
function submit() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editingStudent.value)
        form.put(`/admin/students/${editingStudent.value.id}`, options);
    else form.post('/admin/students', options);
}
function toggleActive(student: Student) {
    router.patch(
        `/admin/students/${student.id}/toggle-active`,
        {},
        { preserveScroll: true },
    );
}
const fullName = (student: Student) =>
    `${student.first_name} ${student.last_name}`;
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
</script>

<template>
    <AdminLayout>
        <Head title="Gestion des étudiants" />
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Gestion des étudiants
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Gérez les dossiers des étudiants. Ces profils ne
                            disposent d'aucun accès à l'application.
                        </p>
                    </div>
                    <Button class="w-full sm:w-auto" @click="openCreate"
                        ><Plus class="mr-2 size-4" />Ajouter un étudiant</Button
                    >
                </div>

                <form
                    class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-[1fr_180px_auto]"
                    @submit.prevent="applyFilters"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Rechercher par nom, e-mail ou téléphone"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="1">Étudiants actifs</option>
                        <option value="0">Étudiants inactifs</option></select
                    ><Button type="submit" variant="outline">Rechercher</Button>
                </form>

                <div
                    class="hidden overflow-hidden rounded-xl border bg-card md:block"
                >
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3 font-medium">Étudiant</th>
                                <th class="px-5 py-3 font-medium">Téléphone</th>
                                <th class="px-5 py-3 font-medium">Adresse</th>
                                <th class="px-5 py-3 font-medium">Statut</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="student in students.data"
                                :key="student.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ fullName(student) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ student.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">{{ student.phone }}</td>
                                <td
                                    class="max-w-48 truncate px-5 py-4 text-muted-foreground"
                                >
                                    {{ student.address || '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            student.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-red-100 text-red-700'
                                        "
                                        >{{
                                            student.is_active
                                                ? 'Actif'
                                                : 'Inactif'
                                        }}</span
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openEdit(student)"
                                            ><Pencil class="size-4" /><span
                                                class="sr-only"
                                                >Modifier</span
                                            ></Button
                                        ><Button
                                            size="sm"
                                            :variant="
                                                student.is_active
                                                    ? 'destructive'
                                                    : 'outline'
                                            "
                                            @click="toggleActive(student)"
                                            >{{
                                                student.is_active
                                                    ? 'Désactiver'
                                                    : 'Activer'
                                            }}</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 md:hidden">
                    <article
                        v-for="student in students.data"
                        :key="student.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate font-semibold">
                                    {{ fullName(student) }}
                                </h2>
                                <p
                                    class="mt-1 flex items-center gap-1.5 truncate text-sm text-muted-foreground"
                                >
                                    <Mail class="size-3.5 shrink-0" />{{
                                        student.email
                                    }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    student.is_active
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'
                                "
                                >{{
                                    student.is_active ? 'Actif' : 'Inactif'
                                }}</span
                            >
                        </div>
                        <p
                            class="mt-3 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Phone class="size-4" />{{ student.phone }}
                        </p>
                        <p
                            v-if="student.address"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            {{ student.address }}
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <Button variant="outline" @click="openEdit(student)"
                                ><Pencil class="mr-2 size-4" />Modifier</Button
                            ><Button
                                :variant="
                                    student.is_active
                                        ? 'destructive'
                                        : 'outline'
                                "
                                @click="toggleActive(student)"
                                >{{
                                    student.is_active ? 'Désactiver' : 'Activer'
                                }}</Button
                            >
                        </div>
                    </article>
                </div>

                <div
                    v-if="students.data.length === 0"
                    class="rounded-xl border border-dashed p-10 text-center"
                >
                    <UserRound class="mx-auto size-10 text-muted-foreground" />
                    <p class="mt-3 text-muted-foreground">
                        Aucun étudiant trouvé.
                    </p>
                </div>
                <nav
                    v-if="students.links.length > 3"
                    class="flex flex-wrap justify-center gap-1"
                >
                    <Link
                        v-for="link in students.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-md border px-3 py-2 text-sm"
                        :class="{
                            'bg-primary text-primary-foreground': link.active,
                            'pointer-events-none opacity-50': !link.url,
                        }"
                        >{{ paginationLabel(link.label) }}</Link
                    >
                </nav>
            </div>
        </main>

        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="closeModal"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 shadow-2xl sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editingStudent
                                    ? "Modifier l'étudiant"
                                    : 'Ajouter un étudiant'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Ce profil est un dossier administratif sans accès de
                            connexion.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="closeModal"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="first_name">Prénom</Label
                            ><Input
                                id="first_name"
                                v-model="form.first_name"
                                class="mt-1"
                                required
                                autofocus
                            /><InputError
                                :message="form.errors.first_name"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="last_name">Nom</Label
                            ><Input
                                id="last_name"
                                v-model="form.last_name"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.last_name"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label for="email">Adresse e-mail</Label
                        ><Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1"
                            required
                        /><InputError
                            :message="form.errors.email"
                            class="mt-1"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="phone">Téléphone</Label
                            ><Input
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.phone"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="birth_date">Date de naissance</Label
                            ><Input
                                id="birth_date"
                                v-model="form.birth_date"
                                type="date"
                                class="mt-1"
                            /><InputError
                                :message="form.errors.birth_date"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label for="address">Adresse</Label
                        ><Input
                            id="address"
                            v-model="form.address"
                            class="mt-1"
                        /><InputError
                            :message="form.errors.address"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="notes">Notes administratives</Label
                        ><textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                        ></textarea
                        ><InputError
                            :message="form.errors.notes"
                            class="mt-1"
                        />
                    </div>
                    <label class="flex items-center gap-3 rounded-lg border p-3"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 rounded border-gray-300 text-primary"
                        /><span class="text-sm font-medium"
                            >Dossier étudiant actif</span
                        ></label
                    >
                    <div
                        class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModal"
                            >Annuler</Button
                        ><Button type="submit" :disabled="form.processing">{{
                            form.processing ? 'Enregistrement…' : 'Enregistrer'
                        }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
