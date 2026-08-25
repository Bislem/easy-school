<script setup lang="ts">
import BadgeCard from '@/components/BadgeCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    CalendarCheck,
    CreditCard,
    FileText,
    History,
    NotebookPen,
    ReceiptText,
    Send,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        student: any;
        statuses: string[];
        readOnly?: boolean;
        teacherView?: boolean;
        parentView?: boolean;
    }>(),
    { readOnly: false, teacherView: false, parentView: false },
);
const page = usePage();
const activeTab = ref('general');
const statusForm = useForm({ status: props.student.status, observation: '' });
const profileForm = useForm({
    _method: 'put',
    first_name: props.student.first_name,
    last_name: props.student.last_name,
    email: props.student.email ?? '',
    phone: props.student.phone,
    parent_phone: props.student.parent_phone ?? '',
    birth_date: props.student.birth_date ?? '',
    registration_date: props.student.registration_date ?? '',
    school_level: props.student.school_level ?? '',
    address: props.student.address ?? '',
    notes: props.student.notes ?? '',
    status: props.student.status,
    is_active: props.student.is_active,
    photo: null as File | null,
});
const documentTempFolders = ref<string[]>([]);
const documentFiles = ref(
    (props.student.files ?? [])
        .filter((file: any) => file.collection === 'documents')
        .map((file: any) => ({ id: file.id, url: file.url })),
);
const documentRemovedFiles = ref<number[]>([]);
const documentForm = useForm({
    document_temp_folders: [] as string[],
    document_removed_files: [] as number[],
});
const observationForm = useForm({
    message: '',
    parent_id: null as number | null,
});
const replyingTo = ref<number | null>(null);
function sendObservation(parentId: number | null = null) {
    observationForm.parent_id = parentId;
    observationForm.post(`/portal/students/${props.student.id}/observations`, {
        preserveScroll: true,
        onSuccess: () => {
            observationForm.reset();
            replyingTo.value = null;
        },
    });
}
watch(
    documentTempFolders,
    (value) => {
        documentForm.document_temp_folders = [...value];
    },
    { deep: true },
);
const labels: Record<string, string> = {
    active: 'Actif',
    enrolled: 'Présent / inscrit',
    waiting: 'En attente',
    stopped: 'Arrêté',
    suspended: 'Suspendu',
    completed: 'Terminé',
    cancelled: 'Annulé',
};
const paymentLabels: Record<string, string> = {
    unpaid: 'Non payé',
    partially_paid: 'Partiellement payé',
    paid: 'Payé',
    overdue: 'En retard',
};
const money = (value: string | number) =>
    `${Number(value ?? 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2 })} DA`;
const tabs = computed(() =>
    [
        { id: 'general', label: 'Informations', icon: UserRound },
        { id: 'enrollments', label: 'Formations', icon: BookOpen },
        { id: 'attendance', label: 'Présences', icon: CalendarCheck },
        { id: 'payments', label: 'Paiements', icon: ReceiptText },
        { id: 'documents', label: 'Documents', icon: FileText },
        { id: 'certificates', label: 'Certificats', icon: FileText },
        { id: 'badge', label: 'Badge', icon: CreditCard },
        { id: 'observations', label: 'Observations', icon: NotebookPen },
        { id: 'history', label: 'Historique', icon: History },
    ].filter(
        (tab) =>
            !props.teacherView || !['payments', 'documents'].includes(tab.id),
    ),
);
function updateStatus() {
    if (props.readOnly) return;
    statusForm.patch(`/admin/students/${props.student.id}/status`, {
        preserveScroll: true,
    });
}
function updateProfile() {
    if (props.readOnly) return;
    profileForm.post(`/admin/students/${props.student.id}`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
function selectPhoto(event: Event) {
    profileForm.photo = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function removeDocument(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        documentRemovedFiles.value.push(data.fileId);
        documentForm.document_removed_files = [...documentRemovedFiles.value];
    }
}
function saveDocuments() {
    if (props.readOnly) return;
    documentForm.put(`/admin/students/${props.student.id}/documents`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="student.full_name" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div class="flex items-center justify-between">
                    <Button as-child variant="ghost"
                        ><Link
                            :href="
                                parentView
                                    ? '/portal'
                                    : teacherView
                                      ? '/portal/students'
                                      : '/admin/students'
                            "
                            ><ArrowLeft class="mr-2 size-4" />Étudiants</Link
                        ></Button
                    >
                </div>
                <section class="rounded-xl border bg-card p-6">
                    <div class="flex flex-col gap-5 sm:flex-row">
                        <img
                            v-if="student.photo_url"
                            :src="student.photo_url"
                            class="size-24 rounded-xl object-cover"
                        />
                        <div
                            v-else
                            class="grid size-24 place-items-center rounded-xl bg-muted text-2xl font-semibold"
                        >
                            {{ student.first_name[0]
                            }}{{ student.last_name[0] }}
                        </div>
                        <div class="flex-1">
                            <h1 class="text-2xl font-semibold">
                                {{ student.full_name }}
                            </h1>
                            <p class="text-muted-foreground">
                                Dossier étudiant nº {{ student.id }} · Inscrit
                                le {{ student.registration_date || '—' }}
                            </p>
                            <div
                                v-if="!readOnly"
                                class="mt-4 flex flex-wrap items-end gap-2"
                            >
                                <label class="text-sm"
                                    ><span
                                        class="mb-1 block text-muted-foreground"
                                        >Statut actuel</span
                                    ><select
                                        v-model="statusForm.status"
                                        class="h-9 rounded-md border bg-background px-3"
                                    >
                                        <option
                                            v-for="value in statuses"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ labels[value] }}
                                        </option>
                                    </select></label
                                ><label class="min-w-64 flex-1 text-sm"
                                    ><span
                                        class="mb-1 block text-muted-foreground"
                                        >Observation</span
                                    ><input
                                        v-model="statusForm.observation"
                                        class="h-9 w-full rounded-md border bg-background px-3"
                                        placeholder="Motif facultatif" /></label
                                ><Button @click="updateStatus"
                                    >Mettre à jour</Button
                                >
                            </div>
                        </div>
                    </div>
                </section>
                <div
                    class="flex gap-1 overflow-x-auto rounded-xl border bg-card p-2"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm"
                        :class="
                            activeTab === tab.id
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        @click="activeTab = tab.id"
                    >
                        <component :is="tab.icon" class="size-4" />{{
                            tab.label
                        }}
                    </button>
                </div>
                <section class="rounded-xl border bg-card p-6">
                    <form
                        v-if="activeTab === 'general'"
                        class="grid gap-4 sm:grid-cols-2"
                        :inert="readOnly || undefined"
                        @submit.prevent="updateProfile"
                    >
                        <div>
                            <Label>Prénom</Label
                            ><Input v-model="profileForm.first_name" required />
                        </div>
                        <div>
                            <Label>Nom</Label
                            ><Input v-model="profileForm.last_name" required />
                        </div>
                        <div>
                            <Label>Téléphone</Label
                            ><Input v-model="profileForm.phone" required />
                        </div>
                        <div>
                            <Label>Téléphone parent</Label
                            ><Input v-model="profileForm.parent_phone" />
                        </div>
                        <div>
                            <Label>E-mail</Label
                            ><Input v-model="profileForm.email" type="email" />
                        </div>
                        <div>
                            <Label>Date de naissance</Label
                            ><Input
                                v-model="profileForm.birth_date"
                                type="date"
                            />
                        </div>
                        <div>
                            <Label>Date d’inscription</Label
                            ><Input
                                v-model="profileForm.registration_date"
                                type="date"
                            />
                        </div>
                        <div>
                            <Label>Niveau scolaire</Label
                            ><Input v-model="profileForm.school_level" />
                        </div>
                        <div class="sm:col-span-2">
                            <Label>Adresse</Label
                            ><Input v-model="profileForm.address" />
                        </div>
                        <div v-if="!readOnly" class="sm:col-span-2">
                            <Label>Photo</Label
                            ><Input
                                type="file"
                                accept="image/*"
                                @change="selectPhoto"
                            />
                        </div>
                        <div
                            v-if="!readOnly"
                            class="flex justify-end sm:col-span-2"
                        >
                            <Button :disabled="profileForm.processing"
                                >Enregistrer les informations</Button
                            >
                        </div>
                    </form>
                    <div
                        v-else-if="activeTab === 'enrollments'"
                        class="space-y-3"
                    >
                        <article
                            v-for="item in student.enrollments"
                            :key="item.id"
                            class="rounded-lg border p-4"
                        >
                            <div class="flex justify-between">
                                <div>
                                    <p class="font-medium">
                                        {{
                                            item.form?.course?.title ||
                                            item.training_plan_group?.plan
                                                ?.course?.title ||
                                            'Formation'
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            item.form?.title ||
                                            item.training_plan_group?.plan
                                                ?.title ||
                                            'Inscription directe'
                                        }}
                                    </p>
                                </div>
                                <span class="text-sm"
                                    >{{ item.level || 'Niveau non défini' }} ·
                                    Groupe {{ item.group_number || '—' }}</span
                                >
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Statut : {{ item.status }} · Inscription :
                                {{ item.registered_at?.slice(0, 10) || '—' }}
                            </p>
                        </article>
                        <p
                            v-if="!student.enrollments.length"
                            class="text-muted-foreground"
                        >
                            Aucune inscription.
                        </p>
                    </div>
                    <div
                        v-else-if="activeTab === 'attendance'"
                        class="space-y-5"
                    >
                        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            <div
                                v-for="item in [
                                    {
                                        label: 'Attendues',
                                        value: student.attendance_stats
                                            .expected,
                                    },
                                    {
                                        label: 'Présences',
                                        value: student.attendance_stats.present,
                                    },
                                    {
                                        label: 'Absences',
                                        value: student.attendance_stats.absent,
                                    },
                                    {
                                        label: 'Retards',
                                        value: student.attendance_stats.late,
                                    },
                                    {
                                        label: 'Excusées',
                                        value: student.attendance_stats.excused,
                                    },
                                    {
                                        label: 'Taux',
                                        value:
                                            (student.attendance_stats.rate ??
                                                '—') + '%',
                                    },
                                ]"
                                :key="item.label"
                                class="rounded-xl border bg-muted/20 p-3"
                            >
                                <b class="block text-xl">{{ item.value }}</b
                                ><span class="text-xs text-muted-foreground">{{
                                    item.label
                                }}</span>
                            </div>
                        </div>
                        <div
                            v-if="student.attendance_stats.warning"
                            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                        >
                            Attention requise :
                            {{ student.attendance_stats.consecutive_absences }}
                            absence(s) consécutive(s) ou taux de présence
                            inférieur à 75 %.
                        </div>
                        <div class="overflow-x-auto rounded-xl border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/40 text-left">
                                    <tr>
                                        <th class="p-3">Date</th>
                                        <th class="p-3">Formation / groupe</th>
                                        <th class="p-3">Séance</th>
                                        <th class="p-3">Enseignant</th>
                                        <th class="p-3">Statut</th>
                                        <th class="p-3">Justification</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="a in student.attendances"
                                        :key="a.id"
                                        class="border-t"
                                    >
                                        <td class="p-3">
                                            {{ a.session?.starts_at }}
                                        </td>
                                        <td class="p-3">
                                            {{
                                                a.session?.group?.plan?.course
                                                    ?.title
                                            }}
                                            · {{ a.session?.group?.name }}
                                        </td>
                                        <td class="p-3">
                                            {{ a.session?.title }}
                                        </td>
                                        <td class="p-3">
                                            {{ a.session?.teacher?.name }}
                                        </td>
                                        <td class="p-3">{{ a.status }}</td>
                                        <td class="p-3">
                                            {{
                                                a.justification ||
                                                a.notes ||
                                                '—'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else-if="activeTab === 'payments'" class="space-y-5">
                        <article
                            v-for="item in student.enrollments"
                            :key="item.id"
                            class="rounded-lg border p-4"
                        >
                            <div class="flex flex-wrap justify-between gap-3">
                                <div>
                                    <p class="font-medium">
                                        {{
                                            item.form?.course?.title ||
                                            item.training_plan_group?.plan
                                                ?.course?.title ||
                                            'Formation'
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            paymentLabels[
                                                item.payment_status
                                            ] || item.payment_status
                                        }}
                                    </p>
                                </div>
                                <div class="text-right text-sm">
                                    <p>
                                        Prix final :
                                        {{ money(item.final_price) }}
                                    </p>
                                    <p>Payé : {{ money(item.total_paid) }}</p>
                                    <strong
                                        >Reste :
                                        {{
                                            money(item.remaining_balance)
                                        }}</strong
                                    >
                                </div>
                            </div>
                            <div
                                v-if="item.payments.length"
                                class="mt-4 divide-y border-t"
                            >
                                <div
                                    v-for="payment in item.payments"
                                    :key="payment.id"
                                    class="flex items-center justify-between gap-3 py-3 text-sm"
                                >
                                    <div>
                                        <p>{{ payment.reference }}</p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ payment.payment_date }} ·
                                            {{
                                                payment.recorder?.name ||
                                                'Système'
                                            }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <strong>{{
                                            money(payment.amount)
                                        }}</strong>
                                        <p>
                                            <a
                                                class="text-primary underline"
                                                :href="
                                                    parentView
                                                        ? `/portal/payments/${payment.id}/receipt`
                                                        : `/admin/finance/payments/${payment.id}/receipt`
                                                "
                                                >Imprimer le reçu</a
                                            >
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-else
                                class="mt-3 text-sm text-muted-foreground"
                            >
                                Aucun paiement pour cette inscription.
                            </p>
                        </article>
                        <Button v-if="!readOnly" as-child variant="outline"
                            ><Link href="/admin/finance"
                                >Ouvrir la gestion financière</Link
                            ></Button
                        >
                    </div>
                    <div v-else-if="activeTab === 'badge'" class="space-y-5">
                        <BadgeCard
                            v-if="student.badges?.[0]"
                            :badge="student.badges[0]"
                            :school="page.props.school"
                        />
                        <p v-else class="text-muted-foreground">
                            Aucune carte générée.
                        </p>
                        <div
                            v-if="student.badges?.length"
                            class="space-y-2 border-t pt-4"
                        >
                            <div
                                v-for="badge in student.badges"
                                :key="badge.id"
                                class="flex justify-between rounded-lg border p-3 text-sm"
                            >
                                <span
                                    >{{ badge.card_number }} ·
                                    {{ badge.issue_date }}</span
                                ><span>{{ badge.display_status }}</span>
                            </div>
                        </div>
                        <Button v-if="!readOnly" as-child variant="outline"
                            ><Link href="/admin/badges"
                                >Gérer les badges</Link
                            ></Button
                        >
                    </div>
                    <div
                        v-else-if="activeTab === 'certificates'"
                        class="space-y-3"
                    >
                        <div
                            v-for="certificate in student.certificates"
                            :key="certificate.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                        >
                            <div>
                                <p class="font-medium">
                                    {{ certificate.certificate_number }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ certificate.formation_name }} ·
                                    {{ certificate.issue_date }}
                                </p>
                            </div>
                            <Button
                                v-if="!parentView"
                                as-child
                                size="sm"
                                variant="outline"
                                ><a
                                    :href="`/admin/certificates/${certificate.id}/print`"
                                    >Imprimer</a
                                ></Button
                            >
                        </div>
                        <p
                            v-if="!student.certificates?.length"
                            class="text-muted-foreground"
                        >
                            Aucun certificat délivré.
                        </p>
                        <Button v-if="!readOnly" as-child variant="outline"
                            ><Link href="/admin/certificates"
                                >Gérer les certificats</Link
                            ></Button
                        >
                    </div>
                    <div
                        v-else-if="activeTab === 'documents'"
                        class="space-y-4"
                    >
                        <div v-if="readOnly" class="grid gap-3 sm:grid-cols-2">
                            <a
                                v-for="file in documentFiles"
                                :key="file.id"
                                :href="file.url"
                                target="_blank"
                                class="flex items-center gap-3 rounded-lg border p-4 text-sm text-primary hover:bg-muted/40"
                                ><FileText class="size-5" />Consulter le
                                document</a
                            >
                            <p
                                v-if="!documentFiles.length"
                                class="text-muted-foreground"
                            >
                                Aucun document dans le dossier.
                            </p>
                        </div>
                        <FileUpload
                            v-else
                            v-model="documentTempFolders"
                            :initial-files="documentFiles"
                            :allow-multiple="true"
                            :max-files="10"
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
                        <div v-if="!readOnly" class="flex justify-end">
                            <Button
                                :disabled="documentForm.processing"
                                @click="saveDocuments"
                                >Enregistrer les documents</Button
                            >
                        </div>
                    </div>
                    <div
                        v-else-if="activeTab === 'observations'"
                        class="space-y-5"
                    >
                        <form
                            v-if="teacherView || !readOnly"
                            class="rounded-xl border bg-muted/20 p-4"
                            @submit.prevent="sendObservation(null)"
                        >
                            <Label>Nouvelle observation aux parents</Label>
                            <textarea
                                v-model="observationForm.message"
                                required
                                maxlength="5000"
                                class="mt-2 min-h-28 w-full rounded-md border bg-background p-3 text-sm"
                                placeholder="Décrivez précisément l’observation concernant cet étudiant…"
                            />
                            <div class="mt-3 flex justify-end">
                                <Button :disabled="observationForm.processing"
                                    ><Send class="mr-2 size-4" />Envoyer
                                    l’observation</Button
                                >
                            </div>
                        </form>
                        <article
                            v-for="thread in student.observations"
                            :key="thread.id"
                            class="overflow-hidden rounded-xl border"
                        >
                            <div class="bg-muted/25 p-4">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <b>{{
                                        thread.author?.name || 'Enseignant'
                                    }}</b
                                    ><span
                                        class="text-xs text-muted-foreground"
                                        >{{ thread.created_at }}</span
                                    >
                                </div>
                                <p class="mt-2 text-sm whitespace-pre-line">
                                    {{ thread.message }}
                                </p>
                            </div>
                            <div
                                v-if="thread.replies?.length"
                                class="space-y-3 border-t p-4"
                            >
                                <div
                                    v-for="reply in thread.replies"
                                    :key="reply.id"
                                    class="rounded-lg p-3 text-sm"
                                    :class="
                                        reply.author?.role === 'parent'
                                            ? 'ml-4 bg-primary/10'
                                            : 'mr-4 bg-muted'
                                    "
                                >
                                    <div class="flex justify-between gap-3">
                                        <b>{{ reply.author?.name }}</b
                                        ><span
                                            class="text-xs text-muted-foreground"
                                            >{{ reply.created_at }}</span
                                        >
                                    </div>
                                    <p class="mt-1 whitespace-pre-line">
                                        {{ reply.message }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="teacherView || parentView || !readOnly"
                                class="border-t p-4"
                            >
                                <Button
                                    v-if="replyingTo !== thread.id"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        replyingTo = thread.id;
                                        observationForm.message = '';
                                    "
                                    >Répondre</Button
                                >
                                <form
                                    v-else
                                    class="flex gap-2"
                                    @submit.prevent="sendObservation(thread.id)"
                                >
                                    <Input
                                        v-model="observationForm.message"
                                        required
                                        maxlength="5000"
                                        :placeholder="
                                            parentView
                                                ? 'Répondre à l’enseignant…'
                                                : 'Répondre au parent…'
                                        "
                                    /><Button
                                        size="icon"
                                        :disabled="observationForm.processing"
                                        ><Send class="size-4"
                                    /></Button>
                                </form>
                            </div>
                        </article>
                        <p
                            v-if="!student.observations?.length"
                            class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
                        >
                            Aucune conversation dans le dossier.
                        </p>
                    </div>
                    <div v-else-if="activeTab === 'history'" class="space-y-3">
                        <article
                            v-for="entry in student.histories"
                            :key="entry.id"
                            class="border-l-2 border-primary pl-4"
                        >
                            <p class="font-medium">
                                {{ entry.description || entry.event }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.from_status || '—' }} →
                                {{ entry.to_status || '—' }} ·
                                {{ entry.user?.name || 'Système' }} ·
                                {{ entry.created_at }}
                            </p>
                        </article>
                        <p
                            v-if="!student.histories.length"
                            class="text-muted-foreground"
                        >
                            Aucun historique.
                        </p>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">
                        La fondation de ce module est prête pour une prochaine
                        étape.
                    </div>
                </section>
            </div>
        </main></AdminLayout
    >
</template>
