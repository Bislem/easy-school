<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    MapPin,
    Pencil,
    Plus,
    Search,
    Users,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Classroom {
    id: number;
    name: string;
    code: string;
    capacity: number;
    location?: string | null;
    description?: string | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    classrooms: { data: Classroom[]; links: PaginationLink[]; total: number };
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const modalOpen = ref(false);
const editingClassroom = ref<Classroom | null>(null);

const form = useForm({
    name: '',
    code: '',
    capacity: 1,
    location: '',
    description: '',
    is_active: true,
});

function applyFilters() {
    router.get(
        '/admin/classrooms',
        { search: search.value, status: status.value },
        { preserveState: true, replace: true },
    );
}

function openCreate() {
    editingClassroom.value = null;
    form.reset();
    form.clearErrors();
    form.capacity = 1;
    form.is_active = true;
    modalOpen.value = true;
}

function openEdit(classroom: Classroom) {
    editingClassroom.value = classroom;
    form.clearErrors();
    form.name = classroom.name;
    form.code = classroom.code;
    form.capacity = classroom.capacity;
    form.location = classroom.location ?? '';
    form.description = classroom.description ?? '';
    form.is_active = classroom.is_active;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    form.clearErrors();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editingClassroom.value) {
        form.put(`/admin/classrooms/${editingClassroom.value.id}`, options);
    } else {
        form.post('/admin/classrooms', options);
    }
}

function toggleActive(classroom: Classroom) {
    router.patch(
        `/admin/classrooms/${classroom.id}/toggle-active`,
        {},
        { preserveScroll: true },
    );
}

const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
</script>

<template>
    <AdminLayout>
        <Head title="Gestion des salles" />
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Gestion des salles
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Organisez les salles de classe et les espaces de
                            formation de l'établissement.
                        </p>
                    </div>
                    <Button class="w-full sm:w-auto" @click="openCreate"
                        ><Plus class="mr-2 size-4" />Ajouter une salle</Button
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
                            placeholder="Rechercher par nom, code ou emplacement"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="1">Salles actives</option>
                        <option value="0">Salles inactives</option>
                    </select>
                    <Button type="submit" variant="outline">Rechercher</Button>
                </form>

                <div
                    class="hidden overflow-hidden rounded-xl border bg-card md:block"
                >
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3 font-medium">Salle</th>
                                <th class="px-5 py-3 font-medium">Capacité</th>
                                <th class="px-5 py-3 font-medium">
                                    Emplacement
                                </th>
                                <th class="px-5 py-3 font-medium">Statut</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="classroom in classrooms.data"
                                :key="classroom.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ classroom.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Code : {{ classroom.code }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                        ><Users
                                            class="size-4 text-muted-foreground"
                                        />{{ classroom.capacity }} places</span
                                    >
                                </td>
                                <td class="px-5 py-4 text-muted-foreground">
                                    {{ classroom.location || '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            classroom.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-red-100 text-red-700'
                                        "
                                        >{{
                                            classroom.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}</span
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openEdit(classroom)"
                                            ><Pencil class="size-4" /><span
                                                class="sr-only"
                                                >Modifier</span
                                            ></Button
                                        ><Button
                                            size="sm"
                                            :variant="
                                                classroom.is_active
                                                    ? 'destructive'
                                                    : 'outline'
                                            "
                                            @click="toggleActive(classroom)"
                                            >{{
                                                classroom.is_active
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
                        v-for="classroom in classrooms.data"
                        :key="classroom.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate font-semibold">
                                    {{ classroom.name }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Code : {{ classroom.code }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    classroom.is_active
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'
                                "
                                >{{
                                    classroom.is_active ? 'Active' : 'Inactive'
                                }}</span
                            >
                        </div>
                        <div
                            class="mt-4 grid gap-2 text-sm text-muted-foreground"
                        >
                            <p class="flex items-center gap-2">
                                <Users class="size-4" />{{
                                    classroom.capacity
                                }}
                                places
                            </p>
                            <p class="flex items-center gap-2">
                                <MapPin class="size-4" />{{
                                    classroom.location ||
                                    'Emplacement non renseigné'
                                }}
                            </p>
                        </div>
                        <p
                            v-if="classroom.description"
                            class="mt-3 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ classroom.description }}
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <Button
                                variant="outline"
                                @click="openEdit(classroom)"
                                ><Pencil class="mr-2 size-4" />Modifier</Button
                            ><Button
                                :variant="
                                    classroom.is_active
                                        ? 'destructive'
                                        : 'outline'
                                "
                                @click="toggleActive(classroom)"
                                >{{
                                    classroom.is_active
                                        ? 'Désactiver'
                                        : 'Activer'
                                }}</Button
                            >
                        </div>
                    </article>
                </div>

                <div
                    v-if="classrooms.data.length === 0"
                    class="rounded-xl border border-dashed p-10 text-center"
                >
                    <Building2 class="mx-auto size-10 text-muted-foreground" />
                    <p class="mt-3 text-muted-foreground">
                        Aucune salle trouvée.
                    </p>
                </div>
                <nav
                    v-if="classrooms.links.length > 3"
                    class="flex flex-wrap justify-center gap-1"
                >
                    <Link
                        v-for="link in classrooms.links"
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
                class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 shadow-2xl sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editingClassroom
                                    ? 'Modifier la salle'
                                    : 'Ajouter une salle'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Renseignez les informations de la salle.
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
                            <Label for="name">Nom de la salle</Label
                            ><Input
                                id="name"
                                v-model="form.name"
                                class="mt-1"
                                placeholder="Salle informatique"
                                required
                                autofocus
                            /><InputError
                                :message="form.errors.name"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="code">Code</Label
                            ><Input
                                id="code"
                                v-model="form.code"
                                class="mt-1 uppercase"
                                placeholder="INFO-01"
                                required
                            /><InputError
                                :message="form.errors.code"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="capacity">Capacité</Label
                            ><Input
                                id="capacity"
                                v-model="form.capacity"
                                type="number"
                                min="1"
                                max="10000"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.capacity"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="location">Emplacement</Label
                            ><Input
                                id="location"
                                v-model="form.location"
                                class="mt-1"
                                placeholder="1er étage, aile B"
                            /><InputError
                                :message="form.errors.location"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label for="description">Description</Label
                        ><textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            maxlength="2000"
                            class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                            placeholder="Équipements ou informations utiles…"
                        ></textarea
                        ><InputError
                            :message="form.errors.description"
                            class="mt-1"
                        />
                    </div>
                    <label class="flex items-center gap-3 rounded-lg border p-3"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 rounded border-gray-300 text-primary"
                        /><span class="text-sm font-medium"
                            >Salle active et disponible</span
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
