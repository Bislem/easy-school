<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    Layers3,
    Pencil,
    Plus,
    Search,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Course {
    id: number;
    title: string;
    code: string;
    category?: string | null;
    duration_hours: number;
    price: string | number;
    description?: string | null;
    objectives?: string | null;
    prerequisites?: string | null;
    is_certified: boolean;
    is_active: boolean;
    levels: CourseLevel[];
}
interface CourseLevel {
    id: number;
    name: string;
    code: string;
    duration_hours: number;
    price: string | number;
    prerequisites?: string | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    courses: { data: Course[]; links: PaginationLink[]; total: number };
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const modalOpen = ref(false);
const editingCourse = ref<Course | null>(null);
const levelModalOpen = ref(false);
const levelFormModalOpen = ref(false);
const levelCourse = ref<Course | null>(null);
const editingLevel = ref<CourseLevel | null>(null);
const form = useForm({
    title: '',
    code: '',
    category: '',
    duration_hours: 1,
    price: 0,
    description: '',
    objectives: '',
    prerequisites: '',
    is_certified: false,
    is_active: true,
});
const levelForm = useForm({
    name: '',
    code: '',
    duration_hours: 1,
    price: 0,
    prerequisites: '',
    is_active: true,
});

function applyFilters() {
    router.get(
        '/admin/courses',
        { search: search.value, status: status.value },
        { preserveState: true, replace: true },
    );
}

function openCreate() {
    editingCourse.value = null;
    form.reset();
    form.clearErrors();
    form.duration_hours = 1;
    form.price = 0;
    form.is_active = true;
    modalOpen.value = true;
}

function openEdit(course: Course) {
    editingCourse.value = course;
    form.clearErrors();
    form.title = course.title;
    form.code = course.code;
    form.category = course.category ?? '';
    form.duration_hours = course.duration_hours;
    form.price = Number(course.price);
    form.description = course.description ?? '';
    form.objectives = course.objectives ?? '';
    form.prerequisites = course.prerequisites ?? '';
    form.is_certified = course.is_certified;
    form.is_active = course.is_active;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    form.clearErrors();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editingCourse.value) {
        form.put(`/admin/courses/${editingCourse.value.id}`, options);
    } else {
        form.post('/admin/courses', options);
    }
}

function toggleActive(course: Course) {
    router.patch(
        `/admin/courses/${course.id}/toggle-active`,
        {},
        { preserveScroll: true },
    );
}
function openLevels(course: Course) {
    levelCourse.value = course;
    levelModalOpen.value = true;
}
function openLevelForm(level: CourseLevel | null = null) {
    if (!levelCourse.value) return;
    editingLevel.value = level;
    levelForm.clearErrors();
    levelForm.name = level?.name ?? '';
    levelForm.code = level?.code ?? '';
    levelForm.duration_hours = level?.duration_hours ?? 1;
    levelForm.price = Number(level?.price ?? 0);
    levelForm.prerequisites = level?.prerequisites ?? '';
    levelForm.is_active = level?.is_active ?? true;
    levelFormModalOpen.value = true;
}
function submitLevel() {
    if (!levelCourse.value) return;
    const url = `/admin/courses/${levelCourse.value.id}/levels${editingLevel.value ? `/${editingLevel.value.id}` : ''}`;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            levelFormModalOpen.value = false;
            levelCourse.value =
                props.courses.data.find(
                    (course) => course.id === levelCourse.value?.id,
                ) ?? levelCourse.value;
        },
    };
    editingLevel.value
        ? levelForm.put(url, options)
        : levelForm.post(url, options);
}
function toggleLevel(level: CourseLevel) {
    if (!levelCourse.value) return;
    router.patch(
        `/admin/courses/${levelCourse.value.id}/levels/${level.id}/toggle-active`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                levelCourse.value =
                    props.courses.data.find(
                        (course) => course.id === levelCourse.value?.id,
                    ) ?? levelCourse.value;
            },
        },
    );
}

const money = (value: string | number) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
        maximumFractionDigits: 0,
    }).format(Number(value));
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
</script>

<template>
    <AdminLayout>
        <Head title="Gestion des formations" />
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Gestion des formations
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Gérez le catalogue des formations, cours et modules
                            proposés.
                        </p>
                    </div>
                    <Button class="w-full sm:w-auto" @click="openCreate"
                        ><Plus class="mr-2 size-4" />Ajouter une
                        formation</Button
                    >
                </div>

                <form
                    class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-[1fr_180px_auto]"
                    @submit.prevent="applyFilters"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Rechercher par intitulé, code ou catégorie"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="1">Formations actives</option>
                        <option value="0">Formations inactives</option>
                    </select>
                    <Button type="submit" variant="outline">Rechercher</Button>
                </form>

                <div
                    class="hidden overflow-hidden rounded-xl border bg-card md:block"
                >
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3 font-medium">Formation</th>
                                <th class="px-5 py-3 font-medium">Niveaux</th>
                                <th class="px-5 py-3 font-medium">Statut</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="course in courses.data" :key="course.id">
                                <td class="px-5 py-4">
                                    <div class="flex items-start gap-3">
                                        <span
                                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"
                                            ><BookOpen class="size-4"
                                        /></span>
                                        <div>
                                            <p class="font-medium">
                                                {{ course.title }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ course.code
                                                }}<template
                                                    v-if="course.category"
                                                >
                                                    ·
                                                    {{
                                                        course.category
                                                    }}</template
                                                >
                                            </p>
                                            <span
                                                v-if="course.is_certified"
                                                class="mt-1 inline-flex items-center gap-1 text-xs text-amber-600"
                                                ><Award
                                                    class="size-3"
                                                />Certifiante</span
                                            >
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <strong>{{ course.levels.length }}</strong>
                                    niveau(x)
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            course.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-red-100 text-red-700'
                                        "
                                        >{{
                                            course.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}</span
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openLevels(course)"
                                            ><Layers3
                                                class="mr-2 size-4"
                                            />Niveaux</Button
                                        >
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openEdit(course)"
                                            ><Pencil class="size-4" /><span
                                                class="sr-only"
                                                >Modifier</span
                                            ></Button
                                        ><Button
                                            size="sm"
                                            :variant="
                                                course.is_active
                                                    ? 'destructive'
                                                    : 'outline'
                                            "
                                            @click="toggleActive(course)"
                                            >{{
                                                course.is_active
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

                <div class="grid gap-3 md:hidden">
                    <article
                        v-for="course in courses.data"
                        :key="course.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="font-semibold">
                                    {{ course.title }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ course.code
                                    }}<template v-if="course.category">
                                        · {{ course.category }}</template
                                    >
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    course.is_active
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'
                                "
                                >{{
                                    course.is_active ? 'Active' : 'Inactive'
                                }}</span
                            >
                        </div>
                        <div class="mt-4 flex flex-wrap gap-4 text-sm">
                            <span class="flex items-center gap-1.5 font-medium">
                                <Layers3 class="size-4" />{{
                                    course.levels.length
                                }}
                                niveau(x)</span
                            ><span
                                v-if="course.is_certified"
                                class="flex items-center gap-1 text-amber-600"
                                ><Award class="size-4" />Certifiante</span
                            >
                        </div>
                        <p
                            v-if="course.description"
                            class="mt-3 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ course.description }}
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <Button
                                variant="outline"
                                @click="openLevels(course)"
                                ><Layers3 class="mr-2 size-4" />Niveaux</Button
                            >
                            <Button variant="outline" @click="openEdit(course)"
                                ><Pencil class="mr-2 size-4" />Modifier</Button
                            ><Button
                                :variant="
                                    course.is_active ? 'destructive' : 'outline'
                                "
                                @click="toggleActive(course)"
                                >{{
                                    course.is_active ? 'Désactiver' : 'Activer'
                                }}</Button
                            >
                        </div>
                    </article>
                </div>

                <div
                    v-if="courses.data.length === 0"
                    class="rounded-xl border border-dashed p-10 text-center"
                >
                    <BookOpen class="mx-auto size-10 text-muted-foreground" />
                    <p class="mt-3 text-muted-foreground">
                        Aucune formation trouvée.
                    </p>
                </div>
                <nav
                    v-if="courses.links.length > 3"
                    class="flex flex-wrap justify-center gap-1"
                >
                    <Link
                        v-for="link in courses.links"
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
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="closeModal"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 shadow-2xl sm:max-w-2xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editingCourse
                                    ? 'Modifier la formation'
                                    : 'Ajouter une formation'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Renseignez les informations principales du
                            programme.
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
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="title">Intitulé</Label
                            ><Input
                                id="title"
                                v-model="form.title"
                                class="mt-1"
                                required
                                autofocus
                            /><InputError
                                :message="form.errors.title"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="code">Code</Label
                            ><Input
                                id="code"
                                v-model="form.code"
                                class="mt-1 uppercase"
                                placeholder="DEV-WEB"
                                required
                            /><InputError
                                :message="form.errors.code"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label for="category">Catégorie</Label
                        ><Input
                            id="category"
                            v-model="form.category"
                            class="mt-1"
                            placeholder="Informatique, langues, gestion…"
                        /><InputError
                            :message="form.errors.category"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="description">Description</Label
                        ><textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                        ></textarea
                        ><InputError
                            :message="form.errors.description"
                            class="mt-1"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="objectives">Objectifs</Label
                            ><textarea
                                id="objectives"
                                v-model="form.objectives"
                                rows="3"
                                class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                            ></textarea
                            ><InputError
                                :message="form.errors.objectives"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="prerequisites">Prérequis</Label
                            ><textarea
                                id="prerequisites"
                                v-model="form.prerequisites"
                                rows="3"
                                class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                            ></textarea
                            ><InputError
                                :message="form.errors.prerequisites"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex items-center gap-3 rounded-lg border p-3"
                            ><input
                                v-model="form.is_certified"
                                type="checkbox"
                                class="size-4 rounded border-gray-300 text-primary"
                            /><span class="text-sm font-medium"
                                >Formation certifiante</span
                            ></label
                        ><label
                            class="flex items-center gap-3 rounded-lg border p-3"
                            ><input
                                v-model="form.is_active"
                                type="checkbox"
                                class="size-4 rounded border-gray-300 text-primary"
                            /><span class="text-sm font-medium"
                                >Formation active</span
                            ></label
                        >
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
        <div
            v-if="levelModalOpen && levelCourse"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="levelModalOpen = false"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-2xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            Niveaux · {{ levelCourse.title }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Créez et gérez les niveaux de cette formation.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button @click="openLevelForm()"
                            ><Plus class="mr-2 size-4" />Ajouter un
                            niveau</Button
                        ><Button
                            size="icon"
                            variant="ghost"
                            @click="levelModalOpen = false"
                            ><X class="size-5"
                        /></Button>
                    </div>
                </div>
                <div
                    v-if="levelCourse.levels.length"
                    class="mt-5 divide-y rounded-lg border"
                >
                    <div
                        v-for="level in levelCourse.levels"
                        :key="level.id"
                        class="flex items-center justify-between gap-3 p-3"
                    >
                        <div>
                            <p class="font-medium">
                                {{ level.name }} · {{ level.code }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ level.duration_hours }} h ·
                                {{ money(level.price) }} ·
                                {{ level.is_active ? 'Actif' : 'Inactif' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                size="icon"
                                variant="outline"
                                title="Modifier"
                                @click="openLevelForm(level)"
                                ><Pencil class="size-4" /></Button
                            ><Button
                                size="sm"
                                variant="outline"
                                @click="toggleLevel(level)"
                                >{{
                                    level.is_active ? 'Désactiver' : 'Activer'
                                }}</Button
                            >
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="mt-5 rounded-lg border border-dashed p-8 text-center"
                >
                    <Layers3 class="mx-auto size-8 text-muted-foreground" />
                    <p class="mt-2 font-medium">Aucun niveau</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Ajoutez le premier niveau de cette formation.
                    </p>
                </div>
            </div>
        </div>
        <div
            v-if="levelFormModalOpen && levelCourse"
            class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="levelFormModalOpen = false"
        >
            <div
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editingLevel
                                    ? 'Modifier le niveau'
                                    : 'Nouveau niveau'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ levelCourse.title }}
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        @click="levelFormModalOpen = false"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="submitLevel">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Nom</Label
                            ><Input
                                v-model="levelForm.name"
                                class="mt-1"
                                placeholder="Débutant"
                                required
                            /><InputError :message="levelForm.errors.name" />
                        </div>
                        <div>
                            <Label>Code</Label
                            ><Input
                                v-model="levelForm.code"
                                class="mt-1 uppercase"
                                placeholder="N1"
                                required
                            /><InputError :message="levelForm.errors.code" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Volume horaire</Label
                            ><Input
                                v-model="levelForm.duration_hours"
                                class="mt-1"
                                type="number"
                                min="1"
                                required
                            />
                        </div>
                        <div>
                            <Label>Tarif (DZD)</Label
                            ><Input
                                v-model="levelForm.price"
                                class="mt-1"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <Label>Prérequis</Label
                        ><textarea
                            v-model="levelForm.prerequisites"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                    <label class="flex items-center gap-3"
                        ><input
                            v-model="levelForm.is_active"
                            type="checkbox"
                            class="size-4"
                        /><span class="text-sm">Niveau actif</span></label
                    >
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="levelFormModalOpen = false"
                            >Annuler</Button
                        ><Button :disabled="levelForm.processing"
                            >Enregistrer le niveau</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
