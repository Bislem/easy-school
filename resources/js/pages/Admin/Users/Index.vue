<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    BriefcaseBusiness,
    CalendarDays,
    Eye,
    Mail,
    Pencil,
    Phone,
    Plus,
    Search,
    UserCheck,
    UserX,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface ManagedUser {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    birth_date?: string | null;
    role: 'admin' | 'teacher' | 'employee';
    job_title?: string | null;
    can_login: boolean;
    is_active: boolean;
    created_at: string;
    staff?: {
        id: number;
        employee_code: string;
        employment_status: string;
        hire_date?: string | null;
        photo_url?: string | null;
        social_security_number?: string | null;
        employee_type?: { name: string } | null;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    users: { data: ManagedUser[]; links: PaginationLink[]; total: number };
    filters: { search?: string; role?: string; employee_type?: string; employment_status?: string; access?: string };
    employeeTypes: { id: number; name: string }[];
    stats: { total: number; active: number; teachers: number; employees: number };
}>();

const page = usePage();
const currentUserId = page.props.auth.user.id;
const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
const typeFilter = ref(props.filters.employee_type ?? '');
const statusFilter = ref(props.filters.employment_status ?? '');
const accessFilter = ref(props.filters.access ?? '');
const modalOpen = ref(false);
const editingUser = ref<ManagedUser | null>(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    birth_date: '',
    role: 'teacher' as 'admin' | 'teacher' | 'employee',
    job_title: '',
    can_login: true,
    is_active: true,
    password: '',
    password_confirmation: '',
});

function applyFilters() {
    router.get(
        '/admin/users',
        { search: search.value, role: roleFilter.value, employee_type: typeFilter.value, employment_status: statusFilter.value, access: accessFilter.value },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    search.value = ''; roleFilter.value = ''; typeFilter.value = ''; statusFilter.value = ''; accessFilter.value = '';
    applyFilters();
}

const initials = (name: string) => name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
const employmentLabel = (status?: string) => ({ active: 'En poste', inactive: 'Inactif', on_leave: 'En congé', terminated: 'Contrat terminé' }[status || ''] || 'Administrateur');

function openCreate() {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'teacher';
    form.is_active = true;
    form.can_login = true;
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
    form.job_title = user.job_title ?? '';
    form.can_login = user.can_login;
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

function onRoleChange() {
    form.can_login = form.role !== 'employee';
    if (form.role !== 'employee') form.job_title = '';
}

const roleLabel = (role: ManagedUser['role']) =>
    role === 'admin'
        ? 'Administrateur'
        : role === 'teacher'
          ? 'Enseignant'
          : 'Employé';
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
</script>

<template>
    <AdminLayout>
        <Head title="Personnel" />

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">Personnel</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Gérez les employés, enseignants, administrateurs et
                            leurs accès.
                        </p>
                    </div>
                    <Button
                        type="button"
                        class="w-full sm:w-auto"
                        @click="openCreate"
                        ><Plus class="mr-2 size-4" />Ajouter un membre</Button
                    >
                </div>

                <div
                    v-if="page.props.errors.user"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                >
                    {{ page.props.errors.user }}
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Personnel total</p><b class="text-2xl">{{ stats.total }}</b></div>
                    <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Comptes actifs</p><b class="text-2xl text-emerald-600">{{ stats.active }}</b></div>
                    <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Enseignants</p><b class="text-2xl">{{ stats.teachers }}</b></div>
                    <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Employés</p><b class="text-2xl">{{ stats.employees }}</b></div>
                </div>

                <form
                    class="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-2 xl:grid-cols-6"
                    @submit.prevent="applyFilters"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Nom, e-mail, téléphone, matricule, N° SS..."
                        />
                    </div>
                    <select
                        v-model="roleFilter"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Tous les rôles</option>
                        <option value="admin">Administrateurs</option>
                        <option value="teacher">Enseignants</option>
                        <option value="employee">Employés</option>
                    </select>
                    <select v-model="typeFilter" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"><option value="">Toutes les fonctions</option><option v-for="type in employeeTypes" :key="type.id" :value="String(type.id)">{{ type.name }}</option></select>
                    <select v-model="statusFilter" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"><option value="">Tous les statuts RH</option><option value="active">En poste</option><option value="on_leave">En congé</option><option value="inactive">Inactif</option><option value="terminated">Contrat terminé</option></select>
                    <select v-model="accessFilter" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"><option value="">Tous les accès</option><option value="enabled">Connexion autorisée</option><option value="disabled">Sans accès portail</option><option value="inactive">Compte inactif</option></select>
                    <div class="flex gap-2"><Button type="submit" class="flex-1"><Search class="mr-2 size-4" />Filtrer</Button><Button type="button" variant="outline" title="Effacer les filtres" @click="clearFilters"><X class="size-4" /></Button></div>
                </form>

                <div class="flex items-center justify-between"><p class="text-sm text-muted-foreground"><b class="text-foreground">{{ users.total }}</b> résultat(s)</p><p class="hidden text-xs text-muted-foreground sm:block">Cliquez sur « Dossier RH » pour accéder aux congés, documents et informations.</p></div>

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="user in users.data" :key="user.id" class="group overflow-hidden rounded-2xl border bg-card shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                        <div class="h-1.5" :class="user.is_active ? 'bg-emerald-500' : 'bg-slate-300'"></div>
                        <div class="p-5">
                            <div class="flex items-start gap-4">
                                <img v-if="user.staff?.photo_url" :src="user.staff.photo_url" :alt="user.name" class="size-14 rounded-xl object-cover ring-2 ring-muted" />
                                <div v-else class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg font-bold text-primary">{{ initials(user.name) }}</div>
                                <div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><h2 class="truncate font-semibold">{{ user.name }}</h2><BadgeCheck v-if="user.is_active" class="size-4 shrink-0 text-emerald-500" /></div><p class="truncate text-sm text-muted-foreground">{{ user.staff?.employee_type?.name || user.job_title || roleLabel(user.role) }}</p><p class="mt-1 font-mono text-xs text-muted-foreground">{{ user.staff?.employee_code || `USR-${user.id}` }}</p></div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2"><span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">{{ roleLabel(user.role) }}</span><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">{{ user.is_active ? employmentLabel(user.staff?.employment_status) : 'Compte inactif' }}</span><span v-if="!user.can_login" class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">Sans accès portail</span></div>
                            <div class="mt-4 space-y-2 border-t pt-4 text-sm text-muted-foreground"><p class="flex items-center gap-2 truncate"><Mail class="size-4 shrink-0" />{{ user.email }}</p><p class="flex items-center gap-2"><Phone class="size-4 shrink-0" />{{ user.phone || 'Téléphone non renseigné' }}</p><p v-if="user.staff?.hire_date" class="flex items-center gap-2"><CalendarDays class="size-4 shrink-0" />Embauché(e) le {{ user.staff.hire_date }}</p><p v-else class="flex items-center gap-2"><BriefcaseBusiness class="size-4 shrink-0" />{{ user.staff ? 'Date d’embauche à compléter' : 'Compte administratif' }}</p></div>
                            <div class="mt-5 grid grid-cols-2 gap-2"><Button v-if="user.staff" as-child><Link :href="`/admin/staff/${user.staff.id}`"><Eye class="mr-2 size-4" />Dossier RH</Link></Button><Button variant="outline" :class="{ 'col-span-2': !user.staff }" @click="openEdit(user)"><Pencil class="mr-2 size-4" />Compte</Button></div>
                            <Button class="mt-2 w-full" size="sm" :variant="user.is_active ? 'ghost' : 'outline'" :disabled="user.id === currentUserId" @click="toggleActive(user)"><UserX v-if="user.is_active" class="mr-2 size-4" /><UserCheck v-else class="mr-2 size-4" />{{ user.is_active ? 'Désactiver le compte' : 'Réactiver le compte' }}</Button>
                        </div>
                    </article>
                </div>

                <div
                    v-if="false"
                    class="hidden overflow-hidden rounded-xl border bg-card md:block"
                >
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3 font-medium">Personnel</th>
                                <th class="px-5 py-3 font-medium">Rôle</th>
                                <th class="px-5 py-3 font-medium">Fonction</th>
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
                                    {{
                                        user.staff?.employee_type?.name ||
                                        user.job_title ||
                                        '—'
                                    }}
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
                                            !user.is_active
                                                ? 'Inactif'
                                                : user.can_login
                                                  ? 'Connexion autorisée'
                                                  : 'Sans accès'
                                        }}</span
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="user.staff"
                                            as-child
                                            size="sm"
                                            variant="outline"
                                            title="Ouvrir le dossier"
                                            ><Link
                                                :href="`/admin/staff/${user.staff.id}`"
                                                ><Eye class="size-4" /></Link
                                        ></Button>
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

                <div v-if="false" class="grid gap-3 md:hidden">
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
                            <p v-if="user.job_title">{{ user.job_title }}</p>
                            <p>{{ user.phone || 'Aucun téléphone' }}</p>
                            <p>
                                {{
                                    user.can_login
                                        ? 'Connexion autorisée'
                                        : 'Sans accès au portail'
                                }}
                            </p>
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
                                    ? 'Modifier le membre du personnel'
                                    : 'Ajouter un membre du personnel'
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
                            @change="onRoleChange"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="teacher">Enseignant</option>
                            <option value="employee">Employé</option>
                            <option value="admin">
                                Administrateur
                            </option></select
                        ><InputError :message="form.errors.role" class="mt-1" />
                    </div>
                    <div v-if="form.role === 'employee'">
                        <Label for="job_title">Fonction de l’employé</Label
                        ><Input
                            id="job_title"
                            v-model="form.job_title"
                            class="mt-1"
                            required
                            placeholder="Secrétaire, comptable, agent d’entretien…"
                        /><InputError
                            :message="form.errors.job_title"
                            class="mt-1"
                        />
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
                    <label class="flex items-center gap-3 rounded-lg border p-3"
                        ><input
                            v-model="form.can_login"
                            type="checkbox"
                            class="size-4 rounded border-gray-300 text-primary"
                        /><span
                            ><span class="block text-sm font-medium"
                                >Autoriser la connexion</span
                            ><span class="block text-xs text-muted-foreground"
                                >Les employés sont généralement créés sans accès
                                au portail.</span
                            ></span
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
                                :required="!editingUser && form.can_login"
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
                                :required="!editingUser && form.can_login"
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
