<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarX2,
    CheckCircle2,
    CircleAlert,
    Clock3,
    UserRound,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    portal: any;
    context: any;
    academicYears: any[];
    periods: any[];
    subjects: any[];
    filters: any;
    summary: any;
    absences: any[];
    hasEnrollment: boolean;
}>();
const filters = reactive({ ...props.filters });
const selectedAbsence = ref<any | null>(null);
const attachmentUploading = ref(false);
const justificationOpen = computed({
    get: () => selectedAbsence.value !== null,
    set: (open: boolean) => {
        if (!open) closeJustification();
    },
});
const justificationForm = useForm<{ justification: string; attachment_temp_folders: string[] }>({ justification: '', attachment_temp_folders: [] });
function openJustification(absence: any) {
    selectedAbsence.value = absence;
    attachmentUploading.value = false;
    justificationForm.reset();
    justificationForm.attachment_temp_folders = [];
    justificationForm.clearErrors();
}
function closeJustification() {
    selectedAbsence.value = null;
    attachmentUploading.value = false;
    justificationForm.reset();
    justificationForm.clearErrors();
}
function submitJustification() {
    if (!selectedAbsence.value || attachmentUploading.value) return;
    justificationForm.patch(
        `/parent/absences/${selectedAbsence.value.id}/justification`,
        { preserveScroll: true, forceFormData: true, onSuccess: closeJustification },
    );
}
function apply() {
    router.get(
        '/parent/absences',
        Object.fromEntries(
            Object.entries(filters).filter(
                ([, value]) =>
                    value !== null && value !== '' && value !== 'all',
            ),
        ),
        { preserveState: true, replace: true },
    );
}
const date = (value: string) =>
    new Date(`${value}T12:00:00`).toLocaleDateString('fr-DZ', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
const day = (value: string) =>
    new Date(`${value}T12:00:00`).toLocaleDateString('fr-DZ', {
        day: '2-digit',
    });
const month = (value: string) =>
    new Date(`${value}T12:00:00`)
        .toLocaleDateString('fr-DZ', { month: 'short' })
        .replace('.', '')
        .toUpperCase();
</script>

<template>
    <Head title="Absences" />
    <ParentLayout :portal="portal">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">Absences</h1>
                <p v-if="context" class="mt-1 text-slate-500">
                    {{ context.name }} ·
                    {{
                        [context.level, context.group]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                    · {{ context.academic_year }}
                </p>
            </div>

            <section
                v-if="!context"
                class="rounded-2xl border border-dashed bg-white p-12 text-center text-slate-500"
            >
                Aucun enfant associé à ce compte.
            </section>
            <section
                v-else-if="!hasEnrollment"
                class="rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <b>Aucune information disponible pour cette année scolaire.</b>
            </section>
            <template v-else>
                <section class="grid gap-3 sm:grid-cols-3">
                    <article class="rounded-2xl border bg-white p-5">
                        <p class="text-sm text-slate-500">Total des absences</p>
                        <p class="mt-2 text-3xl font-bold">
                            {{ summary.total }}
                        </p>
                    </article>
                    <article class="rounded-2xl border bg-white p-5">
                        <p class="text-sm text-slate-500">Ce trimestre</p>
                        <p class="mt-2 text-3xl font-bold">
                            {{ summary.period }}
                        </p>
                    </article>
                    <article class="rounded-2xl border bg-white p-5">
                        <p class="text-sm text-slate-500">Non justifiées</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">
                            {{ summary.unjustified }}
                        </p>
                    </article>
                </section>

                <section
                    class="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <label class="text-xs font-medium text-slate-500"
                        >Année scolaire<select
                            v-model="filters.academic_year_id"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                            @change="apply"
                        >
                            <option
                                v-for="year in academicYears"
                                :key="year.id"
                                :value="year.id"
                            >
                                {{ year.name }}
                            </option>
                        </select></label
                    >
                    <label class="text-xs font-medium text-slate-500"
                        >Période<select
                            v-model="filters.period"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                            @change="apply"
                        >
                            <option value="all">Toutes</option>
                            <option value="week">Cette semaine</option>
                            <option value="month">Ce mois</option>
                            <option
                                v-for="period in periods"
                                :key="period.id"
                                :value="`period:${period.id}`"
                            >
                                {{ period.name }}
                            </option>
                        </select></label
                    >
                    <label class="text-xs font-medium text-slate-500"
                        >Matière<select
                            v-model="filters.subject_id"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                            @change="apply"
                        >
                            <option :value="null">Toutes les matières</option>
                            <option
                                v-for="subject in subjects"
                                :key="subject.id"
                                :value="subject.id"
                            >
                                {{ subject.title }}
                            </option>
                        </select></label
                    >
                    <label class="text-xs font-medium text-slate-500"
                        >Justification<select
                            v-model="filters.justification"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm text-slate-900"
                            @change="apply"
                        >
                            <option :value="null">Toutes</option>
                            <option value="justified">Justifiées</option>
                            <option value="pending">En attente de validation</option>
                            <option value="unjustified">Non justifiées</option>
                        </select></label
                    >
                </section>

                <section class="rounded-2xl border bg-white p-4 sm:p-6">
                    <h2 class="mb-4 font-bold">Historique</h2>
                    <div v-if="absences.length" class="space-y-1">
                        <article
                            v-for="absence in absences"
                            :key="absence.id"
                            class="flex gap-4 border-b py-5 last:border-0"
                        >
                            <div class="w-14 shrink-0 text-center">
                                <p class="text-2xl font-bold text-primary">
                                    {{ day(absence.date) }}
                                </p>
                                <p class="text-xs font-semibold text-slate-500">
                                    {{ month(absence.date) }}
                                </p>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <h3 class="font-bold">
                                            {{
                                                absence.subject ||
                                                'Absence sur la journée'
                                            }}
                                        </h3>
                                        <p class="text-sm text-slate-500">
                                            {{ date(absence.date) }}
                                        </p>
                                    </div>
                                    <span
                                        class="inline-flex w-fit items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            absence.justification === 'approved'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : absence.justification === 'pending'
                                                  ? 'bg-blue-50 text-blue-700'
                                                  : 'bg-amber-50 text-amber-700'
                                        "
                                        ><CheckCircle2
                                            v-if="
                                                absence.justification === 'approved'
                                            "
                                            class="size-3.5"
                                        /><CircleAlert
                                            v-else
                                            class="size-3.5"
                                        />{{
                                            absence.justification === 'approved'
                                                ? 'Justifiée'
                                                : absence.justification === 'pending'
                                                  ? 'En attente de validation'
                                                  : 'Non justifiée'
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600"
                                >
                                    <span
                                        v-if="absence.start_time"
                                        class="flex items-center gap-1"
                                        ><Clock3 class="size-4" />{{
                                            absence.start_time
                                        }}
                                        – {{ absence.end_time }}</span
                                    ><span
                                        v-if="absence.teacher"
                                        class="flex items-center gap-1"
                                        ><UserRound class="size-4" />{{
                                            absence.teacher
                                        }}</span
                                    >
                                </div>
                                <div
                                    v-if="absence.justification_text"
                                    class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-700"
                                >
                                    <b class="block text-xs text-slate-500">Justification envoyée</b>
                                    <p class="mt-1 whitespace-pre-line">{{ absence.justification_text }}</p>
                                    <a
                                        v-if="absence.attachment_url"
                                        :href="absence.attachment_url"
                                        class="mt-2 inline-block text-sm font-medium text-primary hover:underline"
                                        target="_blank"
                                    >Voir le justificatif joint<span v-if="absence.attachment_name"> · {{ absence.attachment_name }}</span></a>
                                </div>
                                <Button
                                    v-if="absence.justification === 'unjustified'"
                                    class="mt-3"
                                    size="sm"
                                    variant="outline"
                                    @click="openJustification(absence)"
                                >Ajouter une justification</Button>
                            </div>
                        </article>
                    </div>
                    <div v-else class="py-12 text-center">
                        <CalendarX2 class="mx-auto size-10 text-slate-300" />
                        <p class="mt-3 font-semibold">
                            Aucune absence enregistrée pour cette période.
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            En l’absence d’un enregistrement, l’élève est
                            considéré présent.
                        </p>
                    </div>
                </section>
            </template>
        </div>

        <Dialog v-model:open="justificationOpen">
            <DialogScrollContent
                v-if="selectedAbsence"
                class="max-h-[calc(100dvh-2rem)] overflow-hidden p-0 sm:max-w-lg"
            >
                <form
                    class="flex max-h-[calc(100dvh-2rem)] min-h-0 flex-col"
                    @submit.prevent="submitJustification"
                >
                    <DialogHeader class="shrink-0 border-b px-6 py-5 pr-12 text-left">
                        <DialogTitle>Justifier l’absence</DialogTitle>
                        <DialogDescription>
                            {{ selectedAbsence.subject || 'Journée entière' }} · {{ date(selectedAbsence.date) }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                        <label class="block text-sm font-medium">
                            Motif ou justification
                            <textarea
                                v-model="justificationForm.justification"
                                rows="5"
                                maxlength="5000"
                                required
                                class="mt-1 w-full rounded-xl border px-3 py-2 font-normal"
                                placeholder="Expliquez le motif de l’absence…"
                            />
                        </label>
                        <label class="block text-sm font-medium">
                            Pièce justificative (facultative)
                            <FileUpload
                                v-model="justificationForm.attachment_temp_folders"
                                collection="parent_justifications"
                                :allow-multiple="false"
                                :max-files="1"
                                :max-file-size="10 * 1024 * 1024"
                                :allowed-file-types="['application/pdf', 'image/jpeg', 'image/png', 'image/webp']"
                                @upload-started="attachmentUploading = true"
                                @upload-finished="attachmentUploading = false"
                            />
                            <span class="mt-1 block text-xs font-normal text-muted-foreground">PDF ou image, 10 Mo maximum.</span>
                        </label>
                        <InputError :message="justificationForm.errors.attachment_temp_folders" />
                        <InputError :message="justificationForm.errors.justification" />
                        <p class="text-xs text-muted-foreground">
                            La justification sera envoyée à l’établissement pour validation.
                        </p>
                    </div>

                    <DialogFooter class="shrink-0 border-t bg-background px-6 py-4">
                        <Button type="button" variant="outline" @click="closeJustification">Annuler</Button>
                        <Button :disabled="justificationForm.processing || attachmentUploading">
                            {{ attachmentUploading ? 'Téléversement en cours…' : 'Envoyer la justification' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </ParentLayout>
</template>
