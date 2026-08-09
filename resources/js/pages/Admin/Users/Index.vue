<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, UserCheck, UserX, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface ManagedUser {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    birth_date?: string | null;
    role: 'admin' | 'teacher';
    is_active: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    users: { data: ManagedUser[]; links: PaginationLink[]; total: number };
    filters: { search?: string; role?: string };
}>();

const page = usePage();
const currentUserId = page.props.auth.user.id;
const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
const modalOpen = ref(false);
const editingUser = ref<ManagedUser | null>(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    birth_date: '',
    role: 'teacher' as 'admin' | 'teacher',
    is_active: true,
    password: '',
    password_confirmation: '',
});

function applyFilters() {
    router.get(
        '/admin/users',
        { search: search.value, role: roleFilter.value },
        { preserveState: true, replace: true },
    );
}

function openCreate() {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'teacher';
    form.is_active = true;
    modalOpen.value = true;
}

function openEdit(user: ManagedUser) {
    editingUser.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.phone = user.phone ?? '';
    form.birth_date = user.birth_date ?? '';
    form.role = user.role;
    form.is_active = user.is_active;
    form.password = '';
    form.password_confirmation = '';
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    form.clearErrors();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editingUser.value) {
        form.put(`/admin/users/${editingUser.value.id}`, options);
    } else {
        form.post('/admin/users', options);
    }
}

function toggleActive(user: ManagedUser) {
    router.patch(
        `/admin/users/${user.id}/toggle-active`,
        {},
        { preserveScroll: true },
    );
}

const roleLabel = (role: ManagedUser['role']) =>
    role === 'admin' ? 'Administrateur' : 'Enseignant';
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
</script>

<template>
    <AdminLayout>
        <Head title="Gestion des utilisateurs" />

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Gestion des utilisateurs
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Créez et gérez les comptes administrateurs et
                            enseignants.
                        </p>
                    </div>
                    <Button
                        type="button"
                        class="w-full sm:w-auto"
                        @click="openCreate"
                        ><Plus class="mr-2 size-4" />Ajouter un
                        utilisateur</Button
                    >
                </div>

                <div
                    v-if="page.props.errors.user"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                >
                    {{ page.props.errors.user }}
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
                        v-model="roleFilter"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Tous les rôles</option>
                        <option value="admin">Administrateurs</option>
                        <option value="teacher">Enseignants</option>
                    </select>
                    <Button type="submit" variant="outline">Rechercher</Button>
                </form>

                <div
                    class="hidden overflow-hidden rounded-xl border bg-card md:block"
                >
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3 font-medium">
                                    Utilisateur
                                </th>
                                <th class="px-5 py-3 font-medium">Rôle</th>
                                <th class="px-5 py-3 font-medium">Téléphone</th>
                                <th class="px-5 py-3 font-medium">Statut</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ user.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ user.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    {{ roleLabel(user.role) }}
                                </td>
                                <td class="px-5 py-4 text-muted-foreground">
                                    {{ user.phone || '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            user.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-red-100 text-red-700'
                                        "
                                        >{{
                                            user.is_active ? 'Actif' : 'Inactif'
                                        }}</span
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openEdit(user)"
                                            ><Pencil class="size-4" /><span
                                                class="sr-only"
                                                >Modifier</span
                                            ></Button
                                        ><Button
                                            size="sm"
                                            :variant="
                                                user.is_active
                                                    ? 'destructive'
                                                    : 'outline'
                                            "
                                            :disabled="
                                                user.id === currentUserId
                                            "
                                            @click="toggleActive(user)"
                                            ><UserX
                                                v-if="user.is_active"
                                                class="mr-1 size-4"
                                            /><UserCheck
                                                v-else
                                                class="mr-1 size-4"
                                            />{{
                                                user.is_active
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
                        v-for="user in users.data"
                        :key="user.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate font-semibold">
                                    {{ user.name }}
                                </h2>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    {{ user.email }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    user.is_active
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'
                                "
                                >{{
                                    user.is_active ? 'Actif' : 'Inactif'
                                }}</span
                            >
                        </div>
                        <div class="mt-3 text-sm text-muted-foreground">
                            <p>{{ roleLabel(user.role) }}</p>
                            <p>{{ user.phone || 'Aucun téléphone' }}</p>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <Button variant="outline" @click="openEdit(user)"
                                ><Pencil class="mr-2 size-4" />Modifier</Button
                            ><Button
                                :variant="
                                    user.is_active ? 'destructive' : 'outline'
                                "
                                :disabled="user.id === currentUserId"
                                @click="toggleActive(user)"
                                >{{
                                    user.is_active ? 'Désactiver' : 'Activer'
                                }}</Button
                            >
                        </div>
                    </article>
                </div>

                <div
                    v-if="users.data.length === 0"
                    class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                >
                    Aucun utilisateur trouvé.
                </div>
                <nav
                    v-if="users.links.length > 3"
                    class="flex flex-wrap justify-center gap-1"
                >
                    <Link
                        v-for="link in users.links"
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
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            @click.self="closeModal"
        >
            <div
                class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 shadow-2xl sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editingUser
                                    ? "Modifier l'utilisateur"
                                    : 'Ajouter un utilisateur'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                editingUser
                                    ? 'Laissez le mot de passe vide pour le conserver.'
                                    : 'Tous les champs marqués sont obligatoires.'
                            }}
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
                    <div>
                        <Label for="name">Nom complet</Label
                        ><Input
                            id="name"
                            v-model="form.name"
                            class="mt-1"
                            required
                            autofocus
                        /><InputError
                            :message="form.errors.name"
                            class="mt-1"
                        />
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
                        <Label for="role">Rôle</Label
                        ><select
                            id="role"
                            v-model="form.role"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="teacher">Enseignant</option>
                            <option value="admin">
                                Administrateur
                            </option></select
                        ><InputError :message="form.errors.role" class="mt-1" />
                    </div>
                    <label class="flex items-center gap-3 rounded-lg border p-3"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 rounded border-gray-300 text-primary"
                        /><span class="text-sm font-medium"
                            >Compte actif</span
                        ></label
                    >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="password">{{
                                editingUser
                                    ? 'Nouveau mot de passe'
                                    : 'Mot de passe'
                            }}</Label
                            ><Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1"
                                :required="!editingUser"
                                autocomplete="new-password"
                            /><InputError
                                :message="form.errors.password"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="password_confirmation"
                                >Confirmation</Label
                            ><Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1"
                                :required="!editingUser"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>
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
