<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({
    certificates: { type: Object, required: true },
    students: { type: Array, required: true },
    types: { type: Array, required: true },
    filters: { type: Object, required: true },
});
const open = ref(false),
    search = ref((props.filters as any).search ?? ''),
    type = ref((props.filters as any).type ?? '');
const form = useForm({
    student_id: '',
    course_enrollment_id: '',
    type: 'enrollment_attestation',
    issue_date: new Date().toISOString().slice(0, 10),
    result: '',
    signature_name: '',
    notes: '',
});
const student = computed(() =>
    props.students.find((s: any) => String(s.id) === form.student_id),
);
function filter() {
    router.get(
        '/admin/certificates',
        { search: search.value || undefined, type: type.value || undefined },
        { preserveState: true, replace: true },
    );
}
function issue() {
    form.post('/admin/certificates', {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>
<template>
    <Head title="Certificats" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header class="flex justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Certificats et attestations
                        </h1>
                        <p class="text-muted-foreground">
                            Documents administratifs vérifiables et historique.
                        </p>
                    </div>
                    <Button @click="open = true">Générer</Button>
                </header>
                <section class="flex gap-2 rounded-xl border bg-card p-4">
                    <Input
                        v-model="search"
                        placeholder="Nº ou étudiant"
                        @keyup.enter="filter"
                    /><select
                        v-model="type"
                        class="rounded-md border bg-background px-3"
                        @change="filter"
                    >
                        <option value="">Tous les documents</option>
                        <option
                            v-for="t in types"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </section>
                <section class="overflow-x-auto rounded-xl border bg-card">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="p-3">Numéro</th>
                                <th>Étudiant</th>
                                <th>Formation</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="c in certificates.data"
                                :key="c.id"
                                class="border-b"
                            >
                                <td class="p-3 font-mono">
                                    {{ c.certificate_number }}
                                </td>
                                <td>{{ c.student_name }}</td>
                                <td>{{ c.formation_name }}</td>
                                <td>
                                    {{
                                        types.find(
                                            (t: any) => t.value === c.type,
                                        )?.label || c.type
                                    }}
                                </td>
                                <td>{{ c.issue_date }}</td>
                                <td>
                                    <Button as-child size="sm" variant="outline"
                                        ><a
                                            :href="`/admin/certificates/${c.id}/print`"
                                            >PDF</a
                                        ></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <div
                    v-if="open"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
                >
                    <form
                        class="w-full max-w-lg space-y-3 rounded-xl bg-background p-6"
                        @submit.prevent="issue"
                    >
                        <h2 class="text-lg font-semibold">Nouveau document</h2>
                        <select
                            v-model="form.student_id"
                            class="h-9 w-full rounded-md border bg-background px-3"
                            required
                        >
                            <option value="">Étudiant</option>
                            <option
                                v-for="s in students"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.first_name }} {{ s.last_name }}
                            </option></select
                        ><select
                            v-model="form.course_enrollment_id"
                            class="h-9 w-full rounded-md border bg-background px-3"
                            required
                        >
                            <option value="">Inscription / formation</option>
                            <option
                                v-for="e in student?.enrollments || []"
                                :key="e.id"
                                :value="e.id"
                            >
                                {{ e.form.course.title }} · Groupe
                                {{ e.group_number || '—' }}
                            </option></select
                        ><select
                            v-model="form.type"
                            class="h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="t in types"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option></select
                        ><Input
                            v-model="form.issue_date"
                            type="date"
                            required
                        /><Input
                            v-model="form.result"
                            placeholder="Résultat / mention facultative"
                        /><Input
                            v-model="form.signature_name"
                            placeholder="Signataire facultatif"
                        /><textarea
                            v-model="form.notes"
                            class="w-full rounded-md border p-2"
                            placeholder="Notes internes"
                        ></textarea
                        ><Button class="w-full">Générer le document</Button
                        ><Button
                            class="w-full"
                            type="button"
                            variant="ghost"
                            @click="open = false"
                            >Fermer</Button
                        >
                    </form>
                </div>
            </div>
        </main></AdminLayout
    >
</template>
