<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appConfirm } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    MapPin,
    Plus,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Enrollment {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    confirmed_at?: string | null;
    status: string;
    training_plan_group_id?: number | null;
    training_plan_group?: {
        id: number;
        name: string;
        plan?: { title: string };
    } | null;
    student?: { id: number; first_name: string; last_name: string } | null;
}
interface PlanGroup {
    id: number;
    name: string;
    capacity: number | null;
    enrollments_count: number;
    plan: { id: number; title: string };
}
const props = defineProps<{
    enrollmentForm: any;
    enrollments: {
        data: Enrollment[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: { confirmed: number; pending: number; unplaced: number };
    groups: PlanGroup[];
    students: any[];
    applicationStatuses: string[];
}>();
const registered = computed(() =>
    props.enrollments.data.filter((item) => item.status === 'registered'),
);
const applications = computed(() =>
    props.enrollments.data.filter((item) =>
        ['new', 'contacted', 'waiting', 'approved'].includes(item.status),
    ),
);
const closed = computed(() =>
    props.enrollments.data.filter((item) =>
        ['rejected', 'cancelled', 'completed'].includes(item.status),
    ),
);
const statusLabels: Record<string, string> = {
    new: 'Nouveau',
    contacted: 'Contacté',
    waiting: 'En attente',
    approved: 'Approuvé',
    registered: 'Inscrit',
    rejected: 'Rejeté',
    cancelled: 'Annulé',
    completed: 'Terminé',
};
const groupLabel = (group: PlanGroup) =>
    `${group.plan.title} — ${group.name} (${group.enrollments_count}${group.capacity ? ` / ${group.capacity}` : ''})`;
const studentOptions = computed(() =>
    props.students.map((s) => ({
        id: s.id,
        label: `${s.last_name} ${s.first_name}${s.birth_date ? ` · ${s.birth_date}` : ''}${s.phone ? ` · ${s.phone}` : ''}`,
    })),
);
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
const base = computed(
    () => `/admin/enrollment-forms/${props.enrollmentForm.id}/enrollments`,
);

// Direct registration: pick an existing student file or create a new one.
const addModalOpen = ref(false);
const addMode = ref<'existing' | 'new'>('existing');
const addForm = useForm({
    student_id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    birth_date: '',
    training_plan_group_id: '',
});
function openAddModal() {
    addForm.reset();
    addForm.clearErrors();
    addMode.value = props.students.length ? 'existing' : 'new';
    addModalOpen.value = true;
}
function addStudent() {
    addForm
        .transform((data) =>
            addMode.value === 'existing'
                ? {
                      student_id: data.student_id,
                      training_plan_group_id:
                          data.training_plan_group_id || null,
                  }
                : {
                      ...data,
                      student_id: null,
                      training_plan_group_id:
                          data.training_plan_group_id || null,
                  },
        )
        .post(base.value, {
            preserveScroll: true,
            onSuccess: () => (addModalOpen.value = false),
        });
}

// Registering an approved application: link it to an existing student or create one.
const registering = ref<Enrollment | null>(null);
const registerForm = useForm({ student_id: '', training_plan_group_id: '' });
function openRegister(enrollment: Enrollment) {
    registering.value = enrollment;
    registerForm.reset();
    registerForm.clearErrors();
}
function register() {
    if (!registering.value) return;
    registerForm
        .transform((data) => ({
            student_id: data.student_id || null,
            training_plan_group_id: data.training_plan_group_id || null,
        }))
        .post(`${base.value}/${registering.value.id}/register`, {
            preserveScroll: true,
            onSuccess: () => (registering.value = null),
        });
}

async function cancel(enrollment: Enrollment) {
    if (
        await appConfirm(
            `Annuler l'inscription de ${enrollment.first_name} ${enrollment.last_name} ?`,
            {
                title: 'Annuler l’inscription',
                tone: 'danger',
                confirmText: 'Annuler l’inscription',
            },
        )
    ) {
        router.delete(`${base.value}/${enrollment.id}`, {
            preserveScroll: true,
        });
    }
}
function changeGroup(enrollment: Enrollment, event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    router.patch(
        `${base.value}/${enrollment.id}/group`,
        { training_plan_group_id: value || null },
        { preserveScroll: true },
    );
}
function changeStatus(enrollment: Enrollment, event: Event) {
    router.patch(
        `${base.value}/${enrollment.id}/status`,
        { status: (event.target as HTMLSelectElement).value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout>
        <Head :title="enrollmentForm.title" />
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Link
                        href="/admin/enrollment-forms"
                        class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        ><ArrowLeft class="size-4" />Retour aux sessions</Link
                    >
                    <Button @click="openAddModal"
                        ><Plus class="mr-2 size-4" />Inscrire un
                        étudiant</Button
                    >
                </div>
                <div class="overflow-hidden rounded-xl border bg-card">
                    <img
                        v-if="enrollmentForm.cover_url"
                        :src="enrollmentForm.cover_url"
                        :alt="enrollmentForm.title"
                        class="h-48 w-full object-cover sm:h-64"
                    />
                    <div class="p-5 sm:p-6">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <h1 class="text-2xl font-semibold">
                                    {{ enrollmentForm.title }}
                                </h1>
                                <p class="mt-1 text-muted-foreground">
                                    {{ enrollmentForm.course.title }} ·
                                    {{ enrollmentForm.course.code }}
                                </p>
                            </div>
                            <span
                                class="w-fit rounded-full px-3 py-1 text-sm font-medium"
                                :class="
                                    enrollmentForm.is_active
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-slate-100 text-slate-600'
                                "
                                >{{
                                    enrollmentForm.is_active
                                        ? 'Inscriptions ouvertes'
                                        : 'Inscriptions fermées'
                                }}</span
                            >
                        </div>
                        <div class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                            <p class="flex gap-2">
                                <CalendarDays class="size-4 text-primary" />{{
                                    enrollmentForm.start_date
                                }}
                                — {{ enrollmentForm.end_date }}
                            </p>
                            <p v-if="enrollmentForm.teacher" class="flex gap-2">
                                <Users class="size-4 text-primary" />{{
                                    enrollmentForm.teacher.name
                                }}
                            </p>
                            <p
                                v-if="enrollmentForm.classroom"
                                class="flex gap-2"
                            >
                                <MapPin class="size-4 text-primary" />{{
                                    enrollmentForm.classroom.name
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm text-muted-foreground">Inscrits</p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ stats.confirmed }} /
                            {{ enrollmentForm.max_students }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm text-muted-foreground">
                            Candidatures à traiter
                        </p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ stats.pending }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm text-muted-foreground">
                            Inscrits sans groupe
                        </p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ stats.unplaced }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="!groups.length"
                    class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                >
                    Aucune planification n’est encore liée à cette session : les
                    étudiants sont inscrits sans groupe. Créez une planification
                    depuis cette session pour pouvoir les affecter.
                </p>

                <section>
                    <h2 class="mb-3 text-lg font-semibold">
                        Étudiants inscrits
                    </h2>
                    <div class="overflow-x-auto rounded-xl border bg-card">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40 text-left">
                                <tr>
                                    <th class="px-5 py-3">Étudiant</th>
                                    <th class="px-5 py-3">Contact</th>
                                    <th class="px-5 py-3">Groupe</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in registered" :key="item.id">
                                    <td class="px-5 py-4">
                                        <Link
                                            v-if="item.student"
                                            :href="`/admin/students/${item.student.id}`"
                                            class="font-medium text-primary"
                                            >{{ item.first_name }}
                                            {{ item.last_name }}</Link
                                        >
                                    </td>
                                    <td class="px-5 py-4">
                                        <p>{{ item.phone }}</p>
                                        <p class="text-muted-foreground">
                                            {{ item.email }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <select
                                            :value="
                                                item.training_plan_group_id ??
                                                ''
                                            "
                                            class="h-9 w-full max-w-72 rounded-md border bg-background px-3"
                                            :disabled="!groups.length"
                                            @change="changeGroup(item, $event)"
                                        >
                                            <option value="">
                                                Sans groupe
                                            </option>
                                            <option
                                                v-for="group in groups"
                                                :key="group.id"
                                                :value="group.id"
                                            >
                                                {{ groupLabel(group) }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="text-red-600"
                                            title="Annuler l’inscription"
                                            @click="cancel(item)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p
                            v-if="registered.length === 0"
                            class="p-8 text-center text-muted-foreground"
                        >
                            Aucun étudiant inscrit.
                        </p>
                    </div>
                </section>

                <section v-if="applications.length">
                    <h2 class="mb-3 text-lg font-semibold">Candidatures</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <article
                            v-for="item in applications"
                            :key="item.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <h3 class="font-semibold">
                                {{ item.first_name }} {{ item.last_name }}
                            </h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ item.email }} · {{ item.phone }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{
                                    item.confirmed_at
                                        ? 'E-mail confirmé'
                                        : 'E-mail non confirmé'
                                }}
                            </p>
                            <select
                                :value="item.status"
                                class="mt-3 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                @change="changeStatus(item, $event)"
                            >
                                <option
                                    v-for="value in applicationStatuses"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ statusLabels[value] }}
                                </option>
                            </select>
                            <Button
                                v-if="item.status === 'approved'"
                                class="mt-2 w-full"
                                @click="openRegister(item)"
                                >Inscrire comme étudiant</Button
                            >
                        </article>
                    </div>
                </section>

                <section v-if="closed.length">
                    <h2 class="mb-3 text-lg font-semibold">Historique</h2>
                    <div class="divide-y rounded-xl border bg-card text-sm">
                        <div
                            v-for="item in closed"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 px-5 py-3"
                        >
                            <span
                                >{{ item.first_name }}
                                {{ item.last_name }}</span
                            >
                            <span class="flex items-center gap-3">
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                    >{{ statusLabels[item.status] }}</span
                                >
                                <select
                                    v-if="item.status !== 'completed'"
                                    :value="item.status"
                                    class="h-8 rounded-md border bg-background px-2 text-xs"
                                    title="Rouvrir la candidature"
                                    @change="changeStatus(item, $event)"
                                >
                                    <option
                                        v-for="value in applicationStatuses"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ statusLabels[value] }}
                                    </option>
                                </select>
                            </span>
                        </div>
                    </div>
                </section>
                <nav
                    v-if="enrollments.links.length > 3"
                    class="flex flex-wrap justify-center gap-1"
                >
                    <Link
                        v-for="link in enrollments.links"
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
            v-if="addModalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="addModalOpen = false"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 shadow-2xl sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-semibold">Inscrire un étudiant</h2>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="addModalOpen = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        :variant="
                            addMode === 'existing' ? 'default' : 'outline'
                        "
                        @click="addMode = 'existing'"
                        >Étudiant existant</Button
                    >
                    <Button
                        type="button"
                        :variant="addMode === 'new' ? 'default' : 'outline'"
                        @click="addMode = 'new'"
                        >Nouvel étudiant</Button
                    >
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="addStudent">
                    <div v-if="addMode === 'existing'">
                        <Label>Dossier étudiant</Label>
                        <SearchSelect
                            v-model="addForm.student_id"
                            :options="studentOptions"
                            placeholder="Rechercher par nom, date de naissance, téléphone"
                            class="mt-1"
                        />
                        <InputError
                            :message="addForm.errors.student_id"
                            class="mt-1"
                        />
                    </div>
                    <template v-else>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="add_first_name">Prénom</Label
                                ><Input
                                    id="add_first_name"
                                    v-model="addForm.first_name"
                                    class="mt-1"
                                    required
                                /><InputError
                                    :message="addForm.errors.first_name"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="add_last_name">Nom</Label
                                ><Input
                                    id="add_last_name"
                                    v-model="addForm.last_name"
                                    class="mt-1"
                                    required
                                /><InputError
                                    :message="addForm.errors.last_name"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="add_phone">Téléphone</Label
                                ><Input
                                    id="add_phone"
                                    v-model="addForm.phone"
                                    type="tel"
                                    class="mt-1"
                                    required
                                /><InputError
                                    :message="addForm.errors.phone"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="add_birth_date"
                                    >Date de naissance</Label
                                ><Input
                                    id="add_birth_date"
                                    v-model="addForm.birth_date"
                                    type="date"
                                    class="mt-1"
                                /><InputError
                                    :message="addForm.errors.birth_date"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                        <div>
                            <Label for="add_email">E-mail (facultatif)</Label
                            ><Input
                                id="add_email"
                                v-model="addForm.email"
                                type="email"
                                class="mt-1"
                            /><InputError
                                :message="addForm.errors.email"
                                class="mt-1"
                            />
                        </div>
                    </template>
                    <div>
                        <Label for="add_group">Groupe</Label
                        ><select
                            id="add_group"
                            v-model="addForm.training_plan_group_id"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">
                                Sans groupe (à affecter plus tard)
                            </option>
                            <option
                                v-for="group in groups"
                                :key="group.id"
                                :value="String(group.id)"
                            >
                                {{ groupLabel(group) }}
                            </option></select
                        ><InputError
                            :message="addForm.errors.training_plan_group_id"
                            class="mt-1"
                        />
                    </div>
                    <div
                        class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="addModalOpen = false"
                            >Annuler</Button
                        ><Button type="submit" :disabled="addForm.processing"
                            >Inscrire</Button
                        >
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="registering"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="registering = null"
        >
            <form
                class="w-full space-y-4 rounded-t-2xl bg-background p-5 shadow-2xl sm:max-w-xl sm:rounded-2xl sm:p-6"
                @submit.prevent="register"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            Inscrire {{ registering.first_name }}
                            {{ registering.last_name }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Si cette personne a déjà un dossier étudiant,
                            choisissez-le ; sinon laissez vide pour créer un
                            nouveau dossier.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="registering = null"
                        ><X class="size-5"
                    /></Button>
                </div>
                <div>
                    <Label>Dossier étudiant</Label>
                    <SearchSelect
                        v-model="registerForm.student_id"
                        :options="studentOptions"
                        allow-empty
                        placeholder="Nouveau dossier étudiant"
                        class="mt-1"
                    />
                    <InputError
                        :message="registerForm.errors.student_id"
                        class="mt-1"
                    />
                </div>
                <div>
                    <Label for="register_group">Groupe</Label
                    ><select
                        id="register_group"
                        v-model="registerForm.training_plan_group_id"
                        class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">
                            Sans groupe (à affecter plus tard)
                        </option>
                        <option
                            v-for="group in groups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ groupLabel(group) }}
                        </option></select
                    ><InputError
                        :message="registerForm.errors.training_plan_group_id"
                        class="mt-1"
                    />
                </div>
                <Button class="w-full" :disabled="registerForm.processing"
                    >Confirmer l’inscription</Button
                >
            </form>
        </div>
    </AdminLayout>
</template>
