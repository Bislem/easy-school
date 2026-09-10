<script setup lang="ts">
import AccessTabs from '@/components/access/AccessTabs.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
type Role = {
    id: number;
    name: string;
    system_key?: string | null;
    permissions: { key: string }[];
};
type Module = {
    key: string;
    label: string;
    permissions: { key: string; label: string; action: string }[];
};
const props = defineProps<{ roles: Role[]; permissionModules: Module[] }>();
const page = usePage();
const canManage = (
    (page.props.auth.permissions as string[] | undefined) ?? []
).includes('roles.manage');
const roleId = ref(props.roles[0]?.id ?? 0);
const selected = ref<string[]>([]);
const baseline = ref<string[]>([]);
const search = ref('');
const saving = ref(false);
const error = ref('');
const actions = [
    'view',
    'create',
    'edit',
    'delete',
    'export',
    'approve',
    'manage',
];
const labels: { [k: string]: string } = {
    view: 'Voir',
    create: 'Créer',
    edit: 'Modifier',
    delete: 'Supprimer',
    export: 'Exporter',
    approve: 'Approuver',
    manage: 'Gérer',
};
const role = computed(() => props.roles.find((r) => r.id === roleId.value));
const modules = computed(() =>
    props.permissionModules.filter((m) =>
        `${m.label} ${m.permissions.map((p) => p.label).join(' ')}`
            .toLowerCase()
            .includes(search.value.toLowerCase()),
    ),
);
const dirty = computed(
    () =>
        [...selected.value].sort().join('|') !==
        [...baseline.value].sort().join('|'),
);
watch(
    roleId,
    () => {
        selected.value = role.value?.permissions.map((p) => p.key) ?? [];
        baseline.value = [...selected.value];
    },
    { immediate: true },
);
const beforeUnload = (e: BeforeUnloadEvent) => {
    if (dirty.value) {
        e.preventDefault();
        e.returnValue = '';
    }
};
window.addEventListener('beforeunload', beforeUnload);
const stopNavigation = router.on('before', (event) => {
    if (
        dirty.value &&
        !window.confirm('Abandonner les modifications non enregistrées ?')
    )
        event.preventDefault();
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    stopNavigation();
});
function permissions(module: Module, action: string) {
    return module.permissions.filter((p) => p.action === action);
}
function checked(key: string) {
    return selected.value.includes(key);
}
function toggle(key: string) {
    selected.value = checked(key)
        ? selected.value.filter((k) => k !== key)
        : [...selected.value, key];
}
function toggleModule(module: Module) {
    const keys = module.permissions.map((p) => p.key);
    const all = keys.every(checked);
    selected.value = all
        ? selected.value.filter((k) => !keys.includes(k))
        : [...new Set([...selected.value, ...keys])];
}
function save() {
    if (!role.value) return;
    saving.value = true;
    error.value = '';
    router.put(
        `/admin/users/roles/${role.value.id}`,
        { name: role.value.name, permissions: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => (baseline.value = [...selected.value]),
            onError: () =>
                (error.value =
                    "Les modifications n'ont pas pu être enregistrées."),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>
<template>
    <AdminLayout
        ><Head title="Matrice des permissions" />
        <main class="p-4 sm:p-6 lg:p-8">
            <AccessTabs>
                <div
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4 lg:flex-row lg:items-end"
                >
                    <label class="flex-1 text-sm font-medium"
                        >Rôle<select
                            v-model="roleId"
                            class="mt-1 h-10 w-full rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="r in roles"
                                :key="r.id"
                                :value="r.id"
                            >
                                {{ r.name }}
                            </option>
                        </select></label
                    >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-3 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Rechercher un module ou une permission…"
                        />
                    </div>
                    <Button
                        :disabled="!canManage || !dirty || saving"
                        :title="
                            canManage ? '' : 'Permission roles.manage requise'
                        "
                        @click="save"
                        >{{
                            saving
                                ? 'Enregistrement…'
                                : `Enregistrer${dirty ? ' les modifications' : ''}`
                        }}</Button
                    >
                </div>
                <div
                    v-if="dirty"
                    class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"
                >
                    Modifications non enregistrées :
                    {{ selected.length }} permissions seront conservées pour «
                    {{ role?.name }} ».
                </div>
                <div
                    v-if="error"
                    class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                >
                    {{ error }}
                </div>
                <div
                    v-if="!modules.length"
                    class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                >
                    Aucune permission trouvée.
                </div>
                <div v-else class="overflow-x-auto rounded-xl border bg-card">
                    <table class="w-full min-w-[780px] text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-3 text-left">Module</th>
                                <th
                                    v-for="a in actions"
                                    :key="a"
                                    class="p-3 text-center"
                                >
                                    {{ labels[a] }}
                                </th>
                                <th class="p-3 text-right">Tout</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="m in modules" :key="m.key">
                                <td class="p-3">
                                    <p class="font-medium">{{ m.label }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ m.permissions.length }} permission(s)
                                    </p>
                                </td>
                                <td
                                    v-for="a in actions"
                                    :key="a"
                                    class="p-3 text-center"
                                >
                                    <div
                                        v-if="permissions(m, a).length"
                                        class="flex flex-col items-center gap-2"
                                    >
                                        <label
                                            v-for="p in permissions(m, a)"
                                            :key="p.key"
                                            class="flex items-center gap-1"
                                            :title="p.label"
                                        >
                                            <Checkbox
                                                :model-value="checked(p.key)"
                                                :disabled="!canManage"
                                                @update:model-value="
                                                    toggle(p.key)
                                                "
                                            />
                                            <span
                                                v-if="
                                                    permissions(m, a).length > 1
                                                "
                                                class="max-w-20 truncate text-[10px]"
                                                >{{
                                                    p.key.split('.').pop()
                                                }}</span
                                            >
                                        </label>
                                    </div>
                                    <span
                                        v-else
                                        class="text-muted-foreground/40"
                                        title="Action non disponible pour ce module"
                                        >—</span
                                    >
                                </td>
                                <td class="p-3 text-right">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        :disabled="!canManage"
                                        @click="toggleModule(m)"
                                        >Tout sélectionner</Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AccessTabs>
        </main></AdminLayout
    >
</template>
