<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, MapPin, Pencil, Plus, Search, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Site {
    id: number;
    name: string;
    code: string;
    wilaya: string;
    commune?: string | null;
    address?: string | null;
    phone?: string | null;
    is_active: boolean;
    classrooms_count: number;
}
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}
const props = defineProps<{
    sites: { data: Site[]; links: PaginationLink[] };
    filters: { search?: string; status?: string };
}>();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const modalOpen = ref(false);
const editing = ref<Site | null>(null);
const form = useForm({
    name: '',
    code: '',
    wilaya: '',
    commune: '',
    address: '',
    phone: '',
    is_active: true,
});
function filter() {
    router.get(
        '/admin/sites',
        { search: search.value, status: status.value },
        { preserveState: true, replace: true },
    );
}
function open(site: Site | null = null) {
    editing.value = site;
    form.clearErrors();
    form.name = site?.name ?? '';
    form.code = site?.code ?? '';
    form.wilaya = site?.wilaya ?? '';
    form.commune = site?.commune ?? '';
    form.address = site?.address ?? '';
    form.phone = site?.phone ?? '';
    form.is_active = site?.is_active ?? true;
    modalOpen.value = true;
}
function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (modalOpen.value = false),
    };
    editing.value
        ? form.put(`/admin/sites/${editing.value.id}`, options)
        : form.post('/admin/sites', options);
}
function toggle(site: Site) {
    router.patch(
        `/admin/sites/${site.id}/toggle-active`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout
        ><Head title="Gestion des sites" />
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <header
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">Sites de l'école</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Gérez les établissements et annexes dans les
                            différentes wilayas.
                        </p>
                    </div>
                    <Button @click="open()"
                        ><Plus class="mr-2 size-4" />Ajouter un site</Button
                    >
                </header>
                <form
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_180px_auto]"
                    @submit.prevent="filter"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Nom, code, wilaya ou commune"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="1">Sites actifs</option>
                        <option value="0">Sites inactifs</option></select
                    ><Button variant="outline">Rechercher</Button>
                </form>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-4">Site</th>
                                <th>Localisation</th>
                                <th>Salles</th>
                                <th>Statut</th>
                                <th class="pr-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="site in sites.data" :key="site.id">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="grid size-9 place-items-center rounded-md bg-primary/10 text-primary"
                                            ><Building2 class="size-4"
                                        /></span>
                                        <div>
                                            <strong>{{ site.name }}</strong>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ site.code }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="flex items-center gap-2"
                                        ><MapPin
                                            class="size-4 text-muted-foreground"
                                        />{{ site.wilaya
                                        }}<template v-if="site.commune">
                                            · {{ site.commune }}</template
                                        ></span
                                    >
                                    <p
                                        v-if="site.address"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ site.address }}
                                    </p>
                                </td>
                                <td>{{ site.classrooms_count }}</td>
                                <td>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs"
                                        :class="
                                            site.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-red-100 text-red-700'
                                        "
                                        >{{
                                            site.is_active ? 'Actif' : 'Inactif'
                                        }}</span
                                    >
                                </td>
                                <td class="pr-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="icon"
                                            variant="outline"
                                            title="Modifier"
                                            @click="open(site)"
                                            ><Pencil class="size-4" /></Button
                                        ><Button
                                            size="sm"
                                            variant="outline"
                                            @click="toggle(site)"
                                            >{{
                                                site.is_active
                                                    ? 'Désactiver'
                                                    : 'Activer'
                                            }}</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-if="!sites.data.length"
                        class="p-10 text-center text-muted-foreground"
                    >
                        Aucun site trouvé.
                    </div>
                </div>
                <nav
                    v-if="sites.links.length > 3"
                    class="flex justify-center gap-1"
                >
                    <Link
                        v-for="link in sites.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-md border px-3 py-2 text-sm"
                        :class="{
                            'bg-primary text-primary-foreground': link.active,
                            'pointer-events-none opacity-40': !link.url,
                        }"
                        ><span v-html="link.label"
                    /></Link>
                </nav>
            </div>
        </main>
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="modalOpen = false"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-xl bg-background p-5 sm:max-w-xl sm:rounded-lg sm:p-6"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editing ? 'Modifier le site' : 'Ajouter un site'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Identifiez l'établissement et sa localisation.
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="modalOpen = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Nom</Label
                            ><Input
                                v-model="form.name"
                                class="mt-1"
                                placeholder="Annexe Béjaïa"
                                required
                            /><InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <Label>Code</Label
                            ><Input
                                v-model="form.code"
                                class="mt-1 uppercase"
                                placeholder="BEJ-01"
                                required
                            /><InputError :message="form.errors.code" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Wilaya</Label
                            ><Input
                                v-model="form.wilaya"
                                class="mt-1"
                                required
                            /><InputError :message="form.errors.wilaya" />
                        </div>
                        <div>
                            <Label>Commune</Label
                            ><Input v-model="form.commune" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <Label>Adresse</Label
                        ><Input v-model="form.address" class="mt-1" />
                    </div>
                    <div>
                        <Label>Téléphone</Label
                        ><Input v-model="form.phone" class="mt-1" />
                    </div>
                    <label class="flex items-center gap-3 rounded-lg border p-3"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4"
                        /><span class="text-sm font-medium"
                            >Site actif</span
                        ></label
                    >
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="modalOpen = false"
                            >Annuler</Button
                        ><Button :disabled="form.processing"
                            >Enregistrer</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
