<script setup lang="ts">
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    FileText,
    GraduationCap,
    Mail,
    MapPin,
    Phone,
    Save,
    UserRound,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{ portal: any; child: any }>();
const detailsForm = useForm({
    email: props.child.email ?? '',
    phone: props.child.phone ?? '',
    birth_date: props.child.birth_date ?? '',
    address: props.child.address ?? '',
});
const documentTempFolders = ref<string[]>([]);
const documentRemovedFiles = ref<number[]>([]);
const documentForm = useForm({
    document_temp_folders: [] as string[],
    document_removed_files: [] as number[],
});
watch(
    documentTempFolders,
    (folders) => (documentForm.document_temp_folders = [...folders]),
    { deep: true },
);

function saveDetails() {
    detailsForm.patch(`/parent/children/${props.child.id}/details`, {
        preserveScroll: true,
    });
}
function removeDocument(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        documentRemovedFiles.value.push(data.fileId);
        documentForm.document_removed_files = [...documentRemovedFiles.value];
    }
}
function saveDocuments() {
    documentForm.put(`/parent/children/${props.child.id}/documents`, {
        preserveScroll: true,
        onSuccess: () => {
            documentTempFolders.value = [];
            documentRemovedFiles.value = [];
            documentForm.reset();
        },
    });
}
</script>
<template>
    <Head :title="child.name" /><ParentLayout :portal="portal"
        ><div class="space-y-6">
            <Link
                href="/parent/children"
                class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-primary"
                ><ArrowLeft class="size-4" />Mes enfants</Link
            >
            <section class="rounded-3xl border bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <img
                        v-if="child.photo_url"
                        :src="child.photo_url"
                        class="size-24 rounded-2xl object-cover"
                    />
                    <div
                        v-else
                        class="grid size-24 place-items-center rounded-2xl bg-primary/10 text-3xl font-bold text-primary"
                    >
                        {{
                            child.name
                                .split(' ')
                                .map((x: string) => x[0])
                                .join('')
                                .slice(0, 2)
                        }}
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">
                            Élève n° {{ child.identifier }}
                        </p>
                        <h1 class="text-2xl font-bold">{{ child.name }}</h1>
                        <p class="mt-1 text-primary">
                            {{
                                [child.level, child.group]
                                    .filter(Boolean)
                                    .join(' · ') || 'Affectation non définie'
                            }}
                        </p>
                    </div>
                </div>
            </section>
            <div class="grid gap-5 md:grid-cols-2">
                <section class="rounded-2xl border bg-white p-5">
                    <h2 class="font-bold">Scolarité actuelle</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        <p class="flex gap-3">
                            <CalendarDays class="size-5 text-primary" />{{
                                child.academic_year || 'Aucune année active'
                            }}
                        </p>
                        <p class="flex gap-3">
                            <GraduationCap class="size-5 text-primary" />{{
                                child.level || 'Niveau non défini'
                            }}<span v-if="child.stream"
                                >· {{ child.stream }}</span
                            >
                        </p>
                        <p v-if="child.main_teacher" class="flex gap-3">
                            <UserRound class="size-5 text-primary" />Enseignant
                            principal : {{ child.main_teacher }}
                        </p>
                        <p v-if="child.site" class="flex gap-3">
                            <MapPin class="size-5 text-primary" />{{
                                child.site
                            }}
                        </p>
                    </div>
                </section>
                <section class="rounded-2xl border bg-white p-5">
                    <h2 class="font-bold">Informations personnelles</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Complétez ou corrigez les coordonnées de votre enfant.
                    </p>
                    <form class="mt-4 grid gap-4" @submit.prevent="saveDetails">
                        <div class="grid gap-2">
                            <Label
                                for="child-email"
                                class="flex items-center gap-2"
                                ><Mail
                                    class="size-4 text-primary"
                                />E-mail</Label
                            ><Input
                                id="child-email"
                                v-model="detailsForm.email"
                                type="email"
                            />
                            <p
                                v-if="detailsForm.errors.email"
                                class="text-xs text-red-600"
                            >
                                {{ detailsForm.errors.email }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="child-phone"
                                class="flex items-center gap-2"
                                ><Phone
                                    class="size-4 text-primary"
                                />Téléphone</Label
                            ><Input
                                id="child-phone"
                                v-model="detailsForm.phone"
                            />
                            <p
                                v-if="detailsForm.errors.phone"
                                class="text-xs text-red-600"
                            >
                                {{ detailsForm.errors.phone }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="child-birth-date"
                                class="flex items-center gap-2"
                                ><CalendarDays
                                    class="size-4 text-primary"
                                />Date de naissance</Label
                            ><Input
                                id="child-birth-date"
                                v-model="detailsForm.birth_date"
                                type="date"
                            />
                            <p
                                v-if="detailsForm.errors.birth_date"
                                class="text-xs text-red-600"
                            >
                                {{ detailsForm.errors.birth_date }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="child-address"
                                class="flex items-center gap-2"
                                ><MapPin
                                    class="size-4 text-primary"
                                />Adresse</Label
                            ><textarea
                                id="child-address"
                                v-model="detailsForm.address"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <p
                                v-if="detailsForm.errors.address"
                                class="text-xs text-red-600"
                            >
                                {{ detailsForm.errors.address }}
                            </p>
                        </div>
                        <Button
                            type="submit"
                            class="justify-self-end"
                            :disabled="detailsForm.processing"
                            ><Save class="mr-2 size-4" />Enregistrer les
                            informations</Button
                        >
                    </form>
                </section>
            </div>
            <section class="rounded-2xl border bg-white p-5">
                <div class="mb-4">
                    <h2 class="flex items-center gap-2 font-bold">
                        <FileText class="size-5 text-primary" />Documents du
                        dossier
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Ajoutez des documents PDF ou des images. Taille maximale
                        : 10 Mo par fichier.
                    </p>
                </div>
                <FileUpload
                    v-model="documentTempFolders"
                    :initial-files="child.documents"
                    :allow-multiple="true"
                    :max-files="10"
                    :max-file-size="10 * 1024 * 1024"
                    :allowed-file-types="[
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ]"
                    collection="documents"
                    width="100%"
                    @file-removed="removeDocument"
                />
                <p
                    v-if="documentForm.errors.document_temp_folders"
                    class="mt-2 text-xs text-red-600"
                >
                    {{ documentForm.errors.document_temp_folders }}
                </p>
                <div class="mt-4 flex justify-end">
                    <Button
                        :disabled="documentForm.processing"
                        @click="saveDocuments"
                        ><Save class="mr-2 size-4" />Enregistrer les
                        documents</Button
                    >
                </div>
            </section>
        </div></ParentLayout
    >
</template>
