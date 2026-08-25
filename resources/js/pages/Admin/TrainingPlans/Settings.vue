<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { appConfirm } from '@/composables/useAppDialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Search, ShieldCheck, Trash2, UserPlus, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Teacher { id: number; name: string; email?: string | null }
interface Access {
    id: number; teacher_id: number; is_main: boolean;
    can_manage_groups: boolean; can_add_sessions: boolean; can_record_attendance: boolean;
    teacher: Teacher;
}
const props = defineProps<{
    plan: { id: number; title: string; teacher: Teacher; level: { name: string; course: { title: string } }; teacher_accesses: Access[] };
    availableTeachers: Teacher[];
}>();

const permissions = [
    { key: 'can_manage_groups', title: 'Gérer les groupes', text: 'Ajouter, modifier et supprimer les groupes.' },
    { key: 'can_add_sessions', title: 'Ajouter des séances', text: 'Programmer de nouvelles séances.' },
    { key: 'can_record_attendance', title: 'Saisir les présences', text: 'Enregistrer les présences des étudiants.' },
] as const;
const main = computed(() => props.plan.teacher_accesses.find((item) => item.is_main));
const extras = computed(() => props.plan.teacher_accesses.filter((item) => !item.is_main));
const search = ref('');
const selectedTeacher = ref<number | null>(null);
const matches = computed(() => {
    const needle = search.value.toLowerCase().trim();
    return props.availableTeachers.filter((teacher) => !needle || `${teacher.name} ${teacher.email ?? ''}`.toLowerCase().includes(needle));
});
const addForm = useForm({ teacher_id: null as number | null, can_manage_groups: false, can_add_sessions: false, can_record_attendance: false });

function save(access: Access) {
    const url = access.is_main
        ? `/admin/planifications/${props.plan.id}/parametres/principal`
        : `/admin/planifications/${props.plan.id}/parametres/enseignants/${access.id}`;
    router.put(url, {
        can_manage_groups: access.can_manage_groups,
        can_add_sessions: access.can_add_sessions,
        can_record_attendance: access.can_record_attendance,
    }, { preserveScroll: true });
}
function addTeacher() {
    addForm.teacher_id = selectedTeacher.value;
    addForm.post(`/admin/planifications/${props.plan.id}/parametres/enseignants`, {
        preserveScroll: true,
        onSuccess: () => { addForm.reset(); selectedTeacher.value = null; search.value = ''; },
    });
}
async function remove(access: Access) {
    if (await appConfirm(`Retirer l’accès de ${access.teacher.name} ?`, { title: 'Retirer l’accès', tone: 'danger', confirmText: 'Retirer' })) {
        router.delete(`/admin/planifications/${props.plan.id}/parametres/enseignants/${access.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Accès · ${plan.title}`" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
            <div class="flex items-start gap-3">
                <Button variant="outline" size="icon" as-child><Link :href="`/admin/planifications/${plan.id}`"><ArrowLeft class="size-4" /></Link></Button>
                <div><p class="text-sm text-muted-foreground">{{ plan.level.course.title }} · {{ plan.level.name }}</p><h1 class="text-2xl font-bold">Accès à {{ plan.title }}</h1><p class="mt-1 text-sm text-muted-foreground">La consultation est toujours autorisée. Activez uniquement les actions nécessaires.</p></div>
            </div>

            <section v-if="main" class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-3"><span class="rounded-xl bg-primary/10 p-3 text-primary"><ShieldCheck class="size-6" /></span><div><p class="font-semibold">Formateur principal</p><p class="text-sm text-muted-foreground">{{ main.teacher.name }} · {{ main.teacher.email }}</p></div></div>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <label v-for="permission in permissions" :key="permission.key" class="cursor-pointer rounded-xl border p-4 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                        <div class="flex gap-3"><input v-model="main[permission.key]" type="checkbox" class="mt-1 size-4 rounded border-gray-300 text-primary" /><span><span class="block font-medium">{{ permission.title }}</span><span class="mt-1 block text-xs text-muted-foreground">{{ permission.text }}</span></span></div>
                    </label>
                </div>
                <div class="mt-4 flex justify-end"><Button @click="save(main)">Enregistrer les accès</Button></div>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-3"><span class="rounded-xl bg-muted p-3"><UserPlus class="size-6" /></span><div><h2 class="font-semibold">Ajouter un formateur</h2><p class="text-sm text-muted-foreground">Donnez un accès supplémentaire à cette planification.</p></div></div>
                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_auto]">
                    <div><div class="relative"><Search class="absolute left-3 top-3 size-4 text-muted-foreground" /><Input v-model="search" class="pl-9" placeholder="Rechercher par nom ou e-mail…" /></div>
                        <div v-if="search" class="mt-2 max-h-48 overflow-auto rounded-xl border p-1"><button v-for="teacher in matches" :key="teacher.id" type="button" class="flex w-full rounded-lg px-3 py-2 text-left hover:bg-muted" :class="selectedTeacher === teacher.id ? 'bg-primary/10' : ''" @click="selectedTeacher = teacher.id; search = teacher.name"><span><span class="block text-sm font-medium">{{ teacher.name }}</span><span class="block text-xs text-muted-foreground">{{ teacher.email }}</span></span></button><p v-if="!matches.length" class="p-3 text-sm text-muted-foreground">Aucun formateur disponible.</p></div>
                        <InputError :message="addForm.errors.teacher_id" class="mt-2" />
                    </div><Button :disabled="!selectedTeacher || addForm.processing" @click="addTeacher"><UserPlus class="mr-2 size-4" />Ajouter en lecture seule</Button>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center gap-2"><Users class="size-5" /><h2 class="text-lg font-semibold">Formateurs supplémentaires</h2><span class="rounded-full bg-muted px-2 py-0.5 text-xs">{{ extras.length }}</span></div>
                <div v-if="extras.length" class="grid gap-4 xl:grid-cols-2">
                    <article v-for="access in extras" :key="access.id" class="rounded-2xl border bg-card p-5 shadow-sm">
                        <div class="flex justify-between gap-3"><div><p class="font-semibold">{{ access.teacher.name }}</p><p class="text-sm text-muted-foreground">{{ access.teacher.email }}</p></div><Button variant="ghost" size="icon" class="text-destructive" @click="remove(access)"><Trash2 class="size-4" /></Button></div>
                        <div class="mt-4 space-y-2"><label v-for="permission in permissions" :key="permission.key" class="flex cursor-pointer items-start gap-3 rounded-lg border p-3"><input v-model="access[permission.key]" type="checkbox" class="mt-1 size-4 rounded border-gray-300 text-primary" /><span><span class="block text-sm font-medium">{{ permission.title }}</span><span class="block text-xs text-muted-foreground">{{ permission.text }}</span></span></label></div>
                        <div class="mt-4 flex justify-end"><Button size="sm" @click="save(access)">Enregistrer</Button></div>
                    </article>
                </div><div v-else class="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">Aucun accès supplémentaire. Seul le formateur principal peut consulter cette planification.</div>
            </section>
        </div>
    </AdminLayout>
</template>
