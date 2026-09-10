<script setup lang="ts">
import AccessTabs from '@/components/access/AccessTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Copy, Pencil, Plus, RotateCcw, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Role = {
    id: number;
    name: string;
    description?: string | null;
    system_key?: string | null;
    is_active: boolean;
    users_count: number;
    permissions: { key: string }[];
};
const props = defineProps<{ roles: Role[] }>();
const page = usePage();
const canManage = (
    (page.props.auth.permissions as string[] | undefined) ?? []
).includes('roles.manage');
const search = ref('');
const roles = computed(() =>
    props.roles.filter((r) =>
        `${r.name} ${r.description ?? ''}`
            .toLowerCase()
            .includes(search.value.toLowerCase()),
    ),
);
const editing = ref<Role | null>(null);
const modalOpen = ref(false);
const form = useForm({
    name: '',
    description: '',
    permissions: [] as string[],
});
function edit(role?: Role) {
    editing.value = role ?? null;
    form.name = role?.name ?? '';
    form.description = role?.description ?? '';
    form.permissions = role?.permissions.map((p) => p.key) ?? [];
    modalOpen.value = true;
}
function save() {
    const done = () => {
        editing.value = null;
        modalOpen.value = false;
        form.reset();
    };
    editing.value
        ? form.put(`/admin/users/roles/${editing.value.id}`, {
              onSuccess: done,
          })
        : form.post('/admin/users/roles', { onSuccess: done });
}
function duplicate(role: Role) {
    const name = window.prompt('Nom de la copie', `${role.name} — copie`);
    if (name) router.post(`/admin/users/roles/${role.id}/duplicate`, { name });
}
function toggle(role: Role) {
    if (
        window.confirm(
            `${role.is_active ? 'Désactiver' : 'Activer'} le rôle « ${role.name} » ?`,
        )
    )
        router.patch(`/admin/users/roles/${role.id}/toggle`);
}
function restore(role: Role) {
    if (
        window.confirm(
            'Restaurer le nom et toutes les permissions du modèle par défaut ?',
        )
    )
        router.post(`/admin/users/roles/${role.id}/restore`);
}
</script>
<template>
    <AdminLayout
        ><Head title="Rôles" />
        <main class="p-4 sm:p-6 lg:p-8">
            <AccessTabs>
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-between">
                    <div class="relative max-w-md flex-1">
                        <Search
                            class="absolute top-3 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Rechercher un rôle…"
                        />
                    </div>
                    <Button v-if="canManage" @click="edit()"
                        ><Plus class="mr-2 size-4" />Nouveau rôle</Button
                    >
                </div>
                <div
                    v-if="!roles.length"
                    class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                >
                    Aucun rôle ne correspond à votre recherche.
                </div>
                <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="role in roles"
                        :key="role.id"
                        class="rounded-xl border bg-card p-5 shadow-sm"
                    >
                        <div class="flex justify-between gap-3">
                            <div>
                                <h2 class="font-semibold">{{ role.name }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        role.description || 'Aucune description'
                                    }}
                                </p>
                            </div>
                            <span
                                class="h-fit rounded-full px-2 py-1 text-xs"
                                :class="
                                    role.is_active
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >{{
                                    role.is_active ? 'Actif' : 'Inactif'
                                }}</span
                            >
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded bg-muted px-2 py-1">{{
                                role.system_key
                                    ? 'Rôle système'
                                    : 'Rôle personnalisé'
                            }}</span
                            ><span class="rounded bg-muted px-2 py-1"
                                >{{ role.users_count }} utilisateur(s)</span
                            ><span class="rounded bg-muted px-2 py-1"
                                >{{ role.permissions.length }} permissions</span
                            >
                        </div>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="!canManage"
                                :title="
                                    canManage
                                        ? 'Modifier le rôle'
                                        : 'Permission roles.manage requise'
                                "
                                @click="edit(role)"
                                ><Pencil class="mr-1 size-3" />Modifier</Button
                            ><Button
                                size="sm"
                                variant="outline"
                                :disabled="!canManage"
                                @click="duplicate(role)"
                                ><Copy class="mr-1 size-3" />Dupliquer</Button
                            ><Button
                                v-if="role.system_key"
                                size="sm"
                                variant="outline"
                                :disabled="!canManage"
                                @click="restore(role)"
                                ><RotateCcw
                                    class="mr-1 size-3"
                                />Restaurer</Button
                            ><Button
                                size="sm"
                                variant="ghost"
                                :disabled="!canManage || !!role.system_key"
                                :title="
                                    role.system_key
                                        ? 'Les rôles système restent disponibles; modifiez plutôt leurs permissions.'
                                        : ''
                                "
                                @click="toggle(role)"
                                >{{
                                    role.is_active ? 'Désactiver' : 'Activer'
                                }}</Button
                            >
                        </div>
                    </article>
                </div>
                <div
                    v-if="modalOpen"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
                >
                    <form
                        class="w-full max-w-lg space-y-4 rounded-xl bg-background p-6 shadow-xl"
                        @submit.prevent="save"
                    >
                        <h2 class="text-lg font-semibold">
                            {{ editing ? 'Modifier le rôle' : 'Créer un rôle' }}
                        </h2>
                        <Input
                            v-model="form.name"
                            placeholder="Nom du rôle"
                        /><textarea
                            v-model="form.description"
                            class="min-h-24 w-full rounded-md border bg-background p-3 text-sm"
                            placeholder="Description"
                        ></textarea>
                        <p
                            v-if="form.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    modalOpen = false;
                                    editing = null;
                                    form.reset();
                                "
                                >Annuler</Button
                            ><Button :disabled="form.processing"
                                >Enregistrer</Button
                            >
                        </div>
                    </form>
                </div>
            </AccessTabs>
        </main></AdminLayout
    >
</template>
