<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Eye,
    Pencil,
    Plus,
    Search,
    Settings2,
    UserCheck,
    UserX,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Type {
    id: number;
    name: string;
    slug: string;
    is_teacher: boolean;
}
interface Employee {
    id: number;
    name: string;
    first_name: string;
    last_name: string;
    email?: string;
    phone?: string;
    employee_code: string;
    employment_status: string;
    hire_date?: string;
    photo_url?: string;
    employee_type: Type;
    user?: { can_login: boolean } | null;
}
interface LinkItem {
    url: string | null;
    label: string;
    active: boolean;
}
const props = defineProps<{
    staff: { data: Employee[]; links: LinkItem[]; total: number };
    employeeTypes: Type[];
    statuses: { value: string; label: string }[];
    filters: { search?: string; type?: string; status?: string };
}>();
const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? '');
const formOpen = ref(false);
const typeFormOpen = ref(false);
const typeForm = useForm({ name: '', is_teacher: false });
const form = useForm({
    first_name: '',
    last_name: '',
    employee_type_id: '',
    employee_code: '',
    phone: '',
    email: '',
    address: '',
    birth_date: '',
    hire_date: '',
    employment_status: 'active',
    notes: '',
    identification_type: '',
    identification_number: '',
    identification_expires_at: '',
    identification_notes: '',
    can_login: false,
    password: '',
    password_confirmation: '',
    photo: null as File | null,
});
function filter() {
    router.get(
        '/admin/staff',
        { search: search.value, type: type.value, status: status.value },
        { preserveState: true, replace: true },
    );
}
function submit() {
    form.post('/admin/staff', {
        forceFormData: true,
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    });
}
function submitType() {
    typeForm.post('/admin/staff/types', {
        preserveScroll: true,
        onSuccess: () => {
            typeFormOpen.value = false;
            typeForm.reset();
        },
    });
}
function photo(event: Event) {
    form.photo = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function toggle(employee: Employee) {
    router.patch(
        `/admin/staff/${employee.id}/toggle-active`,
        {},
        { preserveScroll: true },
    );
}
function statusLabel(value: string) {
    return props.statuses.find((s) => s.value === value)?.label ?? value;
}
function pagination(label: string) {
    return label
        .replace('&laquo; Previous', 'Précédent')
        .replace('Next &raquo;', 'Suivant');
}
</script>

<template>
    <Head title="Personnel" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">Personnel</h1>
                        <p class="text-sm text-muted-foreground">
                            Profils de tous les employés de l’établissement.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" @click="typeFormOpen = true">
                            <Settings2 class="mr-2 size-4" />Ajouter un type
                        </Button>
                        <Button @click="formOpen = true">
                            <Plus class="mr-2 size-4" />Ajouter un employé
                        </Button>
                    </div>
                </header>
                <form
                    class="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-[1fr_220px_180px_auto]"
                    @submit.prevent="filter"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Nom, code, e-mail, téléphone…"
                        />
                    </div>
                    <select
                        v-model="type"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tous les types</option>
                        <option
                            v-for="item in employeeTypes"
                            :key="item.id"
                            :value="String(item.id)"
                        >
                            {{ item.name }}
                        </option></select
                    ><select
                        v-model="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tous les statuts</option>
                        <option
                            v-for="item in statuses"
                            :key="item.value"
                            :value="item.value"
                        >
                            {{ item.label }}
                        </option></select
                    ><Button variant="outline">Filtrer</Button>
                </form>
                <section class="overflow-hidden rounded-xl border bg-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40 text-left">
                                <tr>
                                    <th class="p-4">Employé</th>
                                    <th class="p-4">Type</th>
                                    <th class="p-4">Référence</th>
                                    <th class="p-4">Statut</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="employee in staff.data"
                                    :key="employee.id"
                                >
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <img
                                                v-if="employee.photo_url"
                                                :src="employee.photo_url"
                                                class="size-10 rounded-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="grid size-10 place-items-center rounded-full bg-muted font-medium"
                                            >
                                                {{ employee.first_name[0]
                                                }}{{ employee.last_name[0] }}
                                            </div>
                                            <div>
                                                <p class="font-medium">
                                                    {{ employee.name }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        employee.email ||
                                                        employee.phone ||
                                                        'Sans coordonnées'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        {{ employee.employee_type.name }}
                                    </td>
                                    <td class="p-4 font-mono text-xs">
                                        {{ employee.employee_code }}
                                    </td>
                                    <td class="p-4">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs"
                                            :class="
                                                employee.employment_status ===
                                                'active'
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                            >{{
                                                statusLabel(
                                                    employee.employment_status,
                                                )
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-4">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                as-child
                                                size="sm"
                                                variant="outline"
                                                ><Link
                                                    :href="`/admin/staff/${employee.id}`"
                                                    ><Eye
                                                        class="size-4" /></Link></Button
                                            ><Button
                                                as-child
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    :href="`/admin/staff/${employee.id}/edit`"
                                                >
                                                    <Pencil class="size-4" />
                                                </Link> </Button
                                            ><Button
                                                size="sm"
                                                :variant="
                                                    employee.employment_status ===
                                                    'active'
                                                        ? 'destructive'
                                                        : 'outline'
                                                "
                                                @click="toggle(employee)"
                                                ><UserX
                                                    v-if="
                                                        employee.employment_status ===
                                                        'active'
                                                    "
                                                    class="size-4" /><UserCheck
                                                    v-else
                                                    class="size-4"
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!staff.data.length">
                                    <td
                                        colspan="5"
                                        class="p-10 text-center text-muted-foreground"
                                    >
                                        Aucun employé trouvé.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <nav class="flex flex-wrap gap-2">
                    <Link
                        v-for="item in staff.links"
                        :key="item.label"
                        :href="item.url || '#'"
                        class="rounded-md border px-3 py-1.5 text-sm"
                        :class="{
                            'bg-primary text-primary-foreground': item.active,
                            'pointer-events-none opacity-40': !item.url,
                        }"
                        v-html="pagination(item.label)"
                    />
                </nav>
            </div>
        </main>
        <div v-if="typeFormOpen" class="fixed inset-0 z-50 bg-black/50 p-4">
            <div
                class="mx-auto mt-24 max-w-md rounded-xl bg-background p-6 shadow-xl"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            Nouveau type d’employé
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Ce type sera immédiatement disponible dans les
                            formulaires.
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="typeFormOpen = false"
                        ><X
                    /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitType">
                    <div>
                        <Label>Nom</Label
                        ><Input v-model="typeForm.name" required /><InputError
                            :message="typeForm.errors.name"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="typeForm.is_teacher"
                            type="checkbox"
                        />Ce type possède les capacités enseignant</label
                    >
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="typeFormOpen = false"
                            >Annuler</Button
                        ><Button :disabled="typeForm.processing"
                            >Ajouter</Button
                        >
                    </div>
                </form>
            </div>
        </div>
        <div
            v-if="formOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4"
        >
            <div
                class="mx-auto my-6 max-w-3xl rounded-xl bg-background p-6 shadow-xl"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">Nouvel employé</h2>
                        <p class="text-sm text-muted-foreground">
                            Le compte utilisateur est facultatif.
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="formOpen = false"
                        ><X
                    /></Button>
                </div>
                <form
                    class="mt-6 grid gap-4 sm:grid-cols-2"
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
                        ><Input v-model="form.last_name" required /><InputError
                            :message="form.errors.last_name"
                        />
                    </div>
                    <div>
                        <Label>Type d’employé</Label
                        ><select
                            v-model="form.employee_type_id"
                            required
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option
                                v-for="item in employeeTypes"
                                :key="item.id"
                                :value="String(item.id)"
                            >
                                {{ item.name }}
                            </option></select
                        ><InputError :message="form.errors.employee_type_id" />
                    </div>
                    <div>
                        <Label>Référence interne</Label
                        ><Input
                            v-model="form.employee_code"
                            placeholder="EMP-000123"
                            required
                        /><InputError :message="form.errors.employee_code" />
                    </div>
                    <div>
                        <Label>E-mail</Label
                        ><Input v-model="form.email" type="email" /><InputError
                            :message="form.errors.email"
                        />
                    </div>
                    <div>
                        <Label>Téléphone</Label><Input v-model="form.phone" />
                    </div>
                    <div>
                        <Label>Date de naissance</Label
                        ><Input v-model="form.birth_date" type="date" />
                    </div>
                    <div>
                        <Label>Date d’embauche</Label
                        ><Input v-model="form.hire_date" type="date" />
                    </div>
                    <div>
                        <Label>Statut</Label
                        ><select
                            v-model="form.employment_status"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option
                                v-for="item in statuses"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label>Photo</Label
                        ><Input type="file" accept="image/*" @change="photo" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label>Adresse</Label
                        ><textarea
                            v-model="form.address"
                            class="mt-1 min-h-20 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <div>
                        <Label>Type de document</Label
                        ><Input v-model="form.identification_type" />
                    </div>
                    <div>
                        <Label>Numéro du document</Label
                        ><Input v-model="form.identification_number" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label>Notes</Label
                        ><textarea
                            v-model="form.notes"
                            class="mt-1 min-h-24 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <label class="flex items-center gap-2 sm:col-span-2"
                        ><input v-model="form.can_login" type="checkbox" />
                        Autoriser la connexion</label
                    ><template v-if="form.can_login"
                        ><div>
                            <Label>Mot de passe</Label
                            ><Input
                                v-model="form.password"
                                type="password"
                            /><InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <Label>Confirmation</Label
                            ><Input
                                v-model="form.password_confirmation"
                                type="password"
                            /></div
                    ></template>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="formOpen = false"
                            >Annuler</Button
                        ><Button :disabled="form.processing"
                            >Créer l’employé</Button
                        >
                    </div>
                </form>
            </div>
        </div></AdminLayout
    >
</template>
