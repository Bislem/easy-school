<script setup lang="ts">
import AccessTabs from '@/components/access/AccessTabs.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';
type Role = { id: number; name: string; is_active: boolean };
type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: { id: number; name: string; scope: string }[];
    site_ids: number[];
    effective_permissions: string[];
};
const props = defineProps<{
    users: {
        data: User[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    roles: Role[];
    sites: { id: number; name: string }[];
    dataScopes: string[];
    filters: { search?: string };
}>();
const page = usePage();
const canManage = (
    (page.props.auth.permissions as string[] | undefined) ?? []
).includes('roles.manage');
const search = ref(props.filters.search ?? '');
const editing = ref<User | null>(null);
const assignments = ref<{ id: number; scope: string }[]>([]);
const siteIds = ref<number[]>([]);
const preview = ref(false);
const saving = ref(false);
function find(id: number) {
    return assignments.value.find((r) => r.id === id);
}
function roleChecked(id: number) {
    return !!find(id);
}
function toggleRole(id: number) {
    assignments.value = roleChecked(id)
        ? assignments.value.filter((r) => r.id !== id)
        : [...assignments.value, { id, scope: 'assigned' }];
}
function open(u: User) {
    editing.value = u;
    assignments.value = u.roles.map((r) => ({ id: r.id, scope: r.scope }));
    siteIds.value = [...u.site_ids];
    preview.value = false;
}
function toggleSite(id: number) {
    siteIds.value = siteIds.value.includes(id)
        ? siteIds.value.filter((x) => x !== id)
        : [...siteIds.value, id];
}
function save() {
    if (!editing.value) return;
    saving.value = true;
    router.put(
        `/admin/users/${editing.value.id}/roles`,
        { roles: assignments.value, site_ids: siteIds.value },
        {
            onSuccess: () => (editing.value = null),
            onFinish: () => (saving.value = false),
        },
    );
}
function applySearch() {
    router.get(
        '/admin/settings/access/users',
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <AdminLayout
        ><Head title="Utilisateurs & accès" />
        <main class="p-4 sm:p-6 lg:p-8">
            <AccessTabs>
                <form class="relative max-w-lg" @submit.prevent="applySearch">
                    <Search
                        class="absolute top-3 left-3 size-4 text-muted-foreground"
                    /><Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Rechercher un utilisateur…"
                    />
                </form>
                <div
                    v-if="!users.data.length"
                    class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                >
                    Aucun utilisateur trouvé.
                </div>
                <div v-else class="grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="u in users.data"
                        :key="u.id"
                        class="rounded-xl border bg-card p-5"
                    >
                        <div class="flex justify-between">
                            <div>
                                <h2 class="font-semibold">{{ u.name }}</h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ u.email }}
                                </p>
                            </div>
                            <span
                                class="text-xs"
                                :class="
                                    u.is_active
                                        ? 'text-emerald-600'
                                        : 'text-destructive'
                                "
                                >{{ u.is_active ? 'Actif' : 'Inactif' }}</span
                            >
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="r in u.roles"
                                :key="r.id"
                                class="rounded-full bg-primary/10 px-2 py-1 text-xs text-primary"
                                >{{ r.name }} · {{ r.scope }}</span
                            ><span
                                v-if="!u.roles.length"
                                class="text-sm text-muted-foreground"
                                >Aucun rôle attribué</span
                            >
                        </div>
                        <div class="mt-4 flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="
                                    preview = true;
                                    open(u);
                                    preview = true;
                                "
                                ><ShieldCheck class="mr-1 size-4" />Aperçu ({{
                                    u.effective_permissions.length
                                }})</Button
                            ><Button
                                size="sm"
                                :disabled="!canManage"
                                :title="
                                    canManage
                                        ? ''
                                        : 'Permission roles.manage requise'
                                "
                                @click="open(u)"
                                >Configurer</Button
                            >
                        </div>
                    </article>
                </div>
                <div
                    v-if="editing"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-background p-6 shadow-xl"
                    >
                        <h2 class="text-lg font-semibold">
                            Accès de {{ editing.name }}
                        </h2>
                        <template v-if="preview"
                            ><p class="mt-1 text-sm text-muted-foreground">
                                Permissions effectives héritées de l’ensemble
                                des rôles.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="p in editing.effective_permissions"
                                    :key="p"
                                    class="rounded bg-muted px-2 py-1 text-xs"
                                    >{{ p }}</span
                                >
                                <p
                                    v-if="!editing.effective_permissions.length"
                                    class="text-sm text-muted-foreground"
                                >
                                    Aucune permission effective.
                                </p>
                            </div></template
                        ><template v-else
                            ><div class="mt-5 space-y-3">
                                <div
                                    v-for="r in roles.filter(
                                        (x) => x.is_active,
                                    )"
                                    :key="r.id"
                                    class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center"
                                >
                                    <label
                                        class="flex flex-1 items-center gap-2"
                                        ><Checkbox
                                            :model-value="roleChecked(r.id)"
                                            @update:model-value="
                                                toggleRole(r.id)
                                            "
                                        />{{ r.name }}</label
                                    ><select
                                        v-if="find(r.id)"
                                        v-model="find(r.id)!.scope"
                                        class="h-9 rounded-md border bg-background px-2 text-sm"
                                    >
                                        <option value="tenant">
                                            Tout l’établissement
                                        </option>
                                        <option value="site">
                                            Sites sélectionnés
                                        </option>
                                        <option value="assigned">
                                            Éléments affectés
                                        </option>
                                        <option value="own">
                                            Mes propres données
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div
                                v-if="
                                    assignments.some((r) => r.scope === 'site')
                                "
                                class="mt-5"
                            >
                                <h3 class="font-medium">Sites autorisés</h3>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="s in sites"
                                        :key="s.id"
                                        class="flex items-center gap-2 rounded border p-2 text-sm"
                                        ><Checkbox
                                            :model-value="
                                                siteIds.includes(s.id)
                                            "
                                            @update:model-value="
                                                toggleSite(s.id)
                                            "
                                        />{{ s.name }}</label
                                    >
                                </div>
                            </div>
                            <p class="mt-4 text-xs text-muted-foreground">
                                Les périmètres non pris en charge par un module
                                sont bloqués par défaut. Le dernier
                                administrateur actif ne peut pas perdre son
                                rôle.
                            </p></template
                        >
                        <div class="mt-6 flex justify-end gap-2">
                            <Button variant="outline" @click="editing = null"
                                >Fermer</Button
                            ><Button
                                v-if="!preview"
                                :disabled="saving"
                                @click="save"
                                >{{
                                    saving
                                        ? 'Enregistrement…'
                                        : 'Enregistrer les accès'
                                }}</Button
                            >
                        </div>
                    </div>
                </div>
            </AccessTabs>
        </main></AdminLayout
    >
</template>
