<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Mail,
    MapPin,
    Phone,
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
    group_number?: number | null;
    student?: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    } | null;
}
const props = defineProps<{
    enrollmentForm: any;
    enrollments: {
        data: Enrollment[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: {
        confirmed: number;
        pending: number;
        groups: Record<string, number>;
    };
}>();
const confirmed = computed(() =>
    props.enrollments.data.filter((item) => item.confirmed_at),
);
const pending = computed(() =>
    props.enrollments.data.filter((item) => !item.confirmed_at),
);
const groups = computed(() =>
    Array.from(
        { length: props.enrollmentForm.groups_count },
        (_, index) => index + 1,
    ),
);
const groupCount = (group: number) => props.stats.groups[String(group)] ?? 0;
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
const addModalOpen = ref(false);
const addForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    birth_date: '',
    group_number: '',
});
function openAddModal() {
    addForm.reset();
    addForm.clearErrors();
    addModalOpen.value = true;
}
function closeAddModal() {
    addModalOpen.value = false;
    addForm.clearErrors();
}
function addStudent() {
    addForm.post(
        `/admin/enrollment-forms/${props.enrollmentForm.id}/enrollments`,
        { preserveScroll: true, onSuccess: closeAddModal },
    );
}
function removeStudent(enrollment: Enrollment) {
    if (
        window.confirm(
            `Supprimer l'inscription de ${enrollment.first_name} ${enrollment.last_name} ?`,
        )
    ) {
        router.delete(
            `/admin/enrollment-forms/${props.enrollmentForm.id}/enrollments/${enrollment.id}`,
            { preserveScroll: true },
        );
    }
}
function changeGroup(enrollment: Enrollment, event: Event) {
    const group = Number((event.target as HTMLSelectElement).value);
    router.patch(
        `/admin/enrollment-forms/${props.enrollmentForm.id}/enrollments/${enrollment.id}/group`,
        { group_number: group },
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
                        ><ArrowLeft class="size-4" />Retour aux
                        formulaires</Link
                    >
                    <Button @click="openAddModal"
                        ><Plus class="mr-2 size-4" />Ajouter un étudiant</Button
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
                            <p class="flex gap-2">
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

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm text-muted-foreground">Confirmés</p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ stats.confirmed }} /
                            {{ enrollmentForm.max_students }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm text-muted-foreground">En attente</p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ stats.pending }}
                        </p>
                    </div>
                    <div
                        v-for="group in groups"
                        :key="group"
                        class="rounded-xl border bg-card p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Groupe {{ group }}
                        </p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ groupCount(group) }} étudiant(s)
                        </p>
                    </div>
                </div>

                <section>
                    <h2 class="mb-3 text-lg font-semibold">
                        Inscriptions confirmées
                    </h2>
                    <div
                        class="hidden overflow-hidden rounded-xl border bg-card md:block"
                    >
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40 text-left">
                                <tr>
                                    <th class="px-5 py-3">Étudiant</th>
                                    <th class="px-5 py-3">Contact</th>
                                    <th class="px-5 py-3">Groupe affecté</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in confirmed" :key="item.id">
                                    <td class="px-5 py-4">
                                        <p class="font-medium">
                                            {{ item.first_name }}
                                            {{ item.last_name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Dossier étudiant nº
                                            {{ item.student?.id }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p>{{ item.email }}</p>
                                        <p class="text-muted-foreground">
                                            {{ item.phone }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <select
                                            :value="item.group_number"
                                            class="h-9 rounded-md border bg-transparent px-3"
                                            @change="changeGroup(item, $event)"
                                        >
                                            <option
                                                v-for="group in groups"
                                                :key="group"
                                                :value="group"
                                            >
                                                Groupe {{ group }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="text-red-600"
                                            @click="removeStudent(item)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="grid gap-3 md:hidden">
                        <article
                            v-for="item in confirmed"
                            :key="item.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <h3 class="font-semibold">
                                {{ item.first_name }} {{ item.last_name }}
                            </h3>
                            <p
                                class="mt-2 flex gap-2 text-sm text-muted-foreground"
                            >
                                <Mail class="size-4" />{{ item.email }}
                            </p>
                            <p
                                class="mt-1 flex gap-2 text-sm text-muted-foreground"
                            >
                                <Phone class="size-4" />{{ item.phone }}
                            </p>
                            <select
                                :value="item.group_number"
                                class="mt-4 h-10 w-full rounded-md border bg-transparent px-3"
                                @change="changeGroup(item, $event)"
                            >
                                <option
                                    v-for="group in groups"
                                    :key="group"
                                    :value="group"
                                >
                                    Groupe {{ group }}
                                </option>
                            </select>
                            <Button
                                class="mt-2 w-full text-red-600"
                                variant="ghost"
                                @click="removeStudent(item)"
                                ><Trash2 class="mr-2 size-4" />Supprimer
                                l'inscription</Button
                            >
                        </article>
                    </div>
                    <p
                        v-if="confirmed.length === 0"
                        class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
                    >
                        Aucune inscription confirmée.
                    </p>
                </section>

                <section v-if="pending.length">
                    <h2 class="mb-3 text-lg font-semibold">
                        En attente de confirmation
                    </h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <article
                            v-for="item in pending"
                            :key="item.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <h3 class="font-semibold">
                                {{ item.first_name }} {{ item.last_name }}
                            </h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ item.email }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.phone }}
                            </p>
                            <span
                                class="mt-3 inline-block rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700"
                                >E-mail non confirmé</span
                            >
                            <Button
                                class="mt-3 w-full text-red-600"
                                variant="ghost"
                                @click="removeStudent(item)"
                                ><Trash2 class="mr-2 size-4" />Supprimer</Button
                            >
                        </article>
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
            @click.self="closeAddModal"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 shadow-2xl sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            Ajouter un étudiant
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            L'étudiant sera confirmé immédiatement et ajouté à
                            un groupe.
                        </p>
                    </div>
                    <Button size="icon" variant="ghost" @click="closeAddModal"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="addStudent">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="add_first_name">Prénom</Label
                            ><Input
                                id="add_first_name"
                                v-model="addForm.first_name"
                                class="mt-1"
                                required
                                autofocus
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
                    <div>
                        <Label for="add_email">Adresse e-mail</Label
                        ><Input
                            id="add_email"
                            v-model="addForm.email"
                            type="email"
                            class="mt-1"
                            required
                        /><InputError
                            :message="addForm.errors.email"
                            class="mt-1"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Un dossier étudiant existant avec cet e-mail sera
                            automatiquement réutilisé.
                        </p>
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
                            <Label for="add_birth_date">Date de naissance</Label
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
                        <Label for="add_group">Groupe</Label
                        ><select
                            id="add_group"
                            v-model="addForm.group_number"
                            class="mt-1 h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Affectation automatique</option>
                            <option
                                v-for="group in groups"
                                :key="group"
                                :value="String(group)"
                            >
                                Groupe {{ group }} —
                                {{ groupCount(group) }} étudiant(s)
                            </option></select
                        ><InputError
                            :message="addForm.errors.group_number"
                            class="mt-1"
                        />
                    </div>
                    <div
                        class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeAddModal"
                            >Annuler</Button
                        ><Button type="submit" :disabled="addForm.processing">{{
                            addForm.processing ? 'Ajout…' : "Ajouter l'étudiant"
                        }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
