<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appAlert } from '@/composables/useAppDialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Clipboard,
    Link2,
    Pencil,
    Plus,
    Search,
    Users,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Option {
    id: number;
    title?: string;
    name?: string;
    code?: string;
    capacity?: number;
}
interface EnrollmentForm {
    id: number;
    public_token: string;
    title: string;
    start_date: string;
    end_date: string;
    min_students: number;
    max_students: number;
    groups_count: number;
    students_per_group?: number | null;
    is_active: boolean;
    course_id: number;
    teacher_id: number;
    classroom_id?: number | null;
    course: Option;
    teacher: Option;
    classroom?: Option | null;
    enrollments_count: number;
    confirmed_enrollments_count: number;
    cover_url?: string | null;
    files: Array<{ id: number; url: string; collection: string }>;
}
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    forms: { data: EnrollmentForm[]; links: PaginationLink[] };
    courses: Option[];
    teachers: Option[];
    classrooms: Option[];
    filters: { search?: string };
}>();
const search = ref(props.filters.search ?? '');
const modalOpen = ref(false);
const copiedId = ref<number | null>(null);
const editing = ref<EnrollmentForm | null>(null);
const coverFiles = ref<Array<{ id: number; url: string }>>([]);
const coverTempFolders = ref<string[]>([]);
const coverRemovedFiles = ref<number[]>([]);
const form = useForm({
    course_id: '',
    teacher_id: '',
    classroom_id: '',
    title: '',
    start_date: '',
    end_date: '',
    min_students: 1,
    max_students: 20,
    groups_count: 1,
    students_per_group: 20,
    is_active: true,
    cover_temp_folders: [] as string[],
    cover_removed_files: [] as number[],
});

watch(coverTempFolders, (value) => (form.cover_temp_folders = [...value]), {
    deep: true,
});
function onCoverRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        coverRemovedFiles.value.push(data.fileId);
        form.cover_removed_files = [...coverRemovedFiles.value];
    }
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    coverFiles.value = [];
    coverTempFolders.value = [];
    coverRemovedFiles.value = [];
    form.min_students = 1;
    form.max_students = 20;
    form.groups_count = 1;
    form.students_per_group = 20;
    form.is_active = true;
    modalOpen.value = true;
}
function openEdit(item: EnrollmentForm) {
    editing.value = item;
    form.clearErrors();
    form.course_id = String(item.course_id);
    form.teacher_id = String(item.teacher_id);
    form.classroom_id = item.classroom_id ? String(item.classroom_id) : '';
    form.title = item.title;
    form.start_date = item.start_date;
    form.end_date = item.end_date;
    form.min_students = item.min_students;
    form.max_students = item.max_students;
    form.groups_count = item.groups_count;
    form.students_per_group = item.students_per_group ?? 20;
    form.is_active = item.is_active;
    coverFiles.value = item.files
        .filter((file) => file.collection === 'cover')
        .map((file) => ({ id: file.id, url: file.url }));
    coverTempFolders.value = [];
    coverRemovedFiles.value = [];
    form.cover_temp_folders = [];
    form.cover_removed_files = [];
    modalOpen.value = true;
}
function closeModal() {
    modalOpen.value = false;
    form.clearErrors();
}
function submit() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editing.value)
        form.put(`/admin/enrollment-forms/${editing.value.id}`, options);
    else form.post('/admin/enrollment-forms', options);
}
function applyFilters() {
    router.get(
        '/admin/enrollment-forms',
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
function toggle(item: EnrollmentForm) {
    router.patch(
        `/admin/enrollment-forms/${item.id}/toggle-active`,
        {},
        { preserveScroll: true },
    );
}
function publicUrl(item: EnrollmentForm) {
    const origin = typeof window === 'undefined' ? '' : window.location.origin;
    return `${origin}/inscription/${item.public_token}`;
}
async function copyLink(item: EnrollmentForm) {
    try {
        if (!navigator.clipboard?.writeText)
            throw new Error('Clipboard API unavailable');
        await navigator.clipboard.writeText(publicUrl(item));
        copiedId.value = item.id;
        setTimeout(() => (copiedId.value = null), 1800);
    } catch {
        await appAlert(
            'Le lien n’a pas pu être copié automatiquement. Vérifiez l’autorisation du presse-papiers dans les paramètres du site, puis réessayez.',
            {
                title: 'Copie impossible',
                confirmText: 'Compris',
                tone: 'warning',
            },
        );
    }
}
const paginationLabel = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant');
</script>

<template>
    <AdminLayout>
        <Head title="Formulaires d'inscription" />
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Formulaires d'inscription
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Créez des campagnes publiques avec confirmation par
                            e-mail.
                        </p>
                    </div>
                    <Button class="w-full sm:w-auto" @click="openCreate"
                        ><Plus class="mr-2 size-4" />Créer un formulaire</Button
                    >
                </div>
                <form
                    class="flex gap-3 rounded-xl border bg-card p-4"
                    @submit.prevent="applyFilters"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Rechercher une campagne ou une formation"
                        />
                    </div>
                    <Button variant="outline">Rechercher</Button>
                </form>
                <div class="grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="item in forms.data"
                        :key="item.id"
                        class="rounded-xl border bg-card p-5 shadow-sm"
                    >
                        <img
                            v-if="item.cover_url"
                            :src="item.cover_url"
                            :alt="item.title"
                            class="mb-5 h-40 w-full rounded-lg object-cover"
                        />
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold">
                                        {{ item.title }}
                                    </h2>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="
                                            item.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-slate-100 text-slate-600'
                                        "
                                        >{{
                                            item.is_active ? 'Ouvert' : 'Fermé'
                                        }}</span
                                    >
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ item.course.title }} ·
                                    {{ item.course.code }}
                                </p>
                            </div>
                            <Button
                                size="icon"
                                variant="outline"
                                @click="openEdit(item)"
                                ><Pencil class="size-4"
                            /></Button>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-muted/50 p-3">
                                <p class="text-muted-foreground">Confirmés</p>
                                <p class="mt-1 font-semibold">
                                    {{ item.confirmed_enrollments_count }} /
                                    {{ item.max_students }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted/50 p-3">
                                <p class="text-muted-foreground">Groupes</p>
                                <p class="mt-1 font-semibold">
                                    {{ item.groups_count }} groupe(s) ·
                                    {{
                                        item.classroom?.capacity ??
                                        item.students_per_group
                                    }}
                                    / groupe
                                </p>
                            </div>
                        </div>
                        <div
                            class="mt-4 space-y-2 text-sm text-muted-foreground"
                        >
                            <p class="flex gap-2">
                                <CalendarDays class="size-4" />Du
                                {{ item.start_date }} au {{ item.end_date }}
                            </p>
                            <p class="flex gap-2">
                                <Users class="size-4" />{{ item.teacher.name
                                }}<template v-if="item.classroom">
                                    · {{ item.classroom.name }} ({{
                                        item.classroom.capacity
                                    }}
                                    places)</template
                                ><template v-else>
                                    · Sans salle attribuée</template
                                >
                            </p>
                        </div>
                        <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                            <Link
                                :href="`/admin/enrollment-forms/${item.id}`"
                                class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"
                                >Détails</Link
                            >
                            <Button
                                class="flex-1"
                                variant="outline"
                                @click="copyLink(item)"
                                ><Check
                                    v-if="copiedId === item.id"
                                    class="mr-2 size-4"
                                /><Clipboard v-else class="mr-2 size-4" />{{
                                    copiedId === item.id
                                        ? 'Lien copié'
                                        : 'Copier le lien public'
                                }}</Button
                            ><a
                                :href="publicUrl(item)"
                                target="_blank"
                                class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-sm font-medium"
                                ><Link2 class="mr-2 size-4" />Ouvrir</a
                            ><Button
                                :variant="
                                    item.is_active ? 'destructive' : 'outline'
                                "
                                @click="toggle(item)"
                                >{{
                                    item.is_active ? 'Fermer' : 'Ouvrir'
                                }}</Button
                            >
                        </div>
                    </article>
                </div>
                <div
                    v-if="forms.data.length === 0"
                    class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                >
                    Aucun formulaire d'inscription.
                </div>
                <nav
                    v-if="forms.links.length > 3"
                    class="flex flex-wrap justify-center gap-1"
                >
                    <Link
                        v-for="link in forms.links"
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
                class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-background p-5 sm:max-w-2xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editing
                                    ? 'Modifier le formulaire'
                                    : "Créer un formulaire d'inscription"
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Configurez la formation, la capacité et la
                            répartition.
                        </p>
                    </div>
                    <Button size="icon" variant="ghost" @click="closeModal"
                        ><X class="size-5"
                    /></Button>
                </div>
                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <Label>Image de couverture</Label>
                        <FileUpload
                            :key="editing?.id ?? 'new'"
                            v-model="coverTempFolders"
                            :initial-files="coverFiles"
                            :allow-multiple="false"
                            :max-files="1"
                            collection="cover"
                            theme="light"
                            width="100%"
                            :required="coverFiles.length === 0"
                            @file-removed="onCoverRemoved"
                        />
                        <InputError
                            :message="form.errors.cover_temp_folders"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="title">Titre public</Label
                        ><Input
                            id="title"
                            v-model="form.title"
                            class="mt-1"
                            placeholder="Inscriptions — Développement Web"
                            required
                        /><InputError
                            :message="form.errors.title"
                            class="mt-1"
                        />
                    </div>
                    <div v-if="!form.classroom_id">
                        <Label for="students_per_group"
                            >Étudiants par groupe</Label
                        ><Input
                            id="students_per_group"
                            v-model="form.students_per_group"
                            type="number"
                            min="1"
                            class="mt-1"
                            required
                        /><InputError
                            :message="form.errors.students_per_group"
                            class="mt-1"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Utilisé comme capacité de chaque groupe
                            lorsqu’aucune salle n’est sélectionnée.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="course">Formation</Label
                            ><select
                                id="course"
                                v-model="form.course_id"
                                class="mt-1 h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                required
                            >
                                <option value="" disabled>Sélectionner</option>
                                <option
                                    v-for="course in courses"
                                    :key="course.id"
                                    :value="String(course.id)"
                                >
                                    {{ course.title }} ({{ course.code }})
                                </option></select
                            ><InputError
                                :message="form.errors.course_id"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="teacher">Enseignant</Label
                            ><select
                                id="teacher"
                                v-model="form.teacher_id"
                                class="mt-1 h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                required
                            >
                                <option value="" disabled>Sélectionner</option>
                                <option
                                    v-for="teacher in teachers"
                                    :key="teacher.id"
                                    :value="String(teacher.id)"
                                >
                                    {{ teacher.name }}
                                </option></select
                            ><InputError
                                :message="form.errors.teacher_id"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label for="classroom">Salle (facultative)</Label
                        ><select
                            id="classroom"
                            v-model="form.classroom_id"
                            class="mt-1 h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Aucune salle</option>
                            <option
                                v-for="room in classrooms"
                                :key="room.id"
                                :value="String(room.id)"
                            >
                                {{ room.name }} — {{ room.capacity }} places
                            </option></select
                        ><InputError
                            :message="form.errors.classroom_id"
                            class="mt-1"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="start">Date de début</Label
                            ><Input
                                id="start"
                                v-model="form.start_date"
                                type="date"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.start_date"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="end">Date de fin</Label
                            ><Input
                                id="end"
                                v-model="form.end_date"
                                type="date"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.end_date"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <Label for="min">Minimum</Label
                            ><Input
                                id="min"
                                v-model="form.min_students"
                                type="number"
                                min="1"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.min_students"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="max">Maximum</Label
                            ><Input
                                id="max"
                                v-model="form.max_students"
                                type="number"
                                min="1"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.max_students"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="groups">Groupes</Label
                            ><Input
                                id="groups"
                                v-model="form.groups_count"
                                type="number"
                                min="1"
                                class="mt-1"
                                required
                            /><InputError
                                :message="form.errors.groups_count"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <label class="flex items-center gap-3 rounded-lg border p-3"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 rounded"
                        /><span class="text-sm font-medium"
                            >Ouvrir immédiatement les inscriptions</span
                        ></label
                    >
                    <div
                        class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModal"
                            >Annuler</Button
                        ><Button :disabled="form.processing">{{
                            form.processing ? 'Enregistrement…' : 'Enregistrer'
                        }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
