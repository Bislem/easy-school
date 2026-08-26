<script setup lang="ts">
import BadgeCard from '@/components/BadgeCard.vue';
import AnnualLeaveTab from '@/components/hr/AnnualLeaveTab.vue';
import SickLeaveTab from '@/components/hr/SickLeaveTab.vue';
import HrRecordsTab from '@/components/hr/HrRecordsTab.vue';
import EmployeeTimeline from '@/components/hr/EmployeeTimeline.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BriefcaseBusiness,
    CalendarDays,
    ClipboardCheck,
    Clock3,
    CreditCard,
    FileText,
    GraduationCap,
    History,
    NotebookPen,
    Pencil,
    Stethoscope,
    TriangleAlert,
    UserRound,
    UserRoundX,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    employee: any;
    documentTypes: Record<string, string>;
    annualLeaveSummary: any;
    sickLeaveSummary: any;
    hrRecordCategories: Record<string, any>;
    employeeTimeline: any[];
}>();
const page = usePage();
const activeTab = ref('general');
const statuses: Record<string, string> = {
    active: 'Actif',
    inactive: 'Inactif',
    on_leave: 'En congé',
    terminated: 'Fin de contrat',
};
const salaryTypes: Record<string, string> = {
    monthly: 'Mensuel fixe',
    hourly: 'Horaire',
    per_session: 'Par séance',
    daily: 'Journalier',
    custom: 'Manuel',
};
const tabs = computed(() => [
    { id: 'general', label: 'Général', icon: UserRound },
    { id: 'documents', label: 'Documents', icon: FileText },
    { id: 'annual-leave', label: 'Congés annuels', icon: CalendarDays },
    { id: 'sick-leave', label: 'Congés maladie', icon: Stethoscope },
    { id: 'absences', label: 'Absences', icon: UserRoundX },
    { id: 'attendance', label: 'Pointage', icon: Clock3 },
    { id: 'payroll', label: 'Paie', icon: WalletCards },
    { id: 'contracts', label: 'Contrats', icon: BriefcaseBusiness },
    { id: 'training', label: 'Formations RH', icon: GraduationCap },
    { id: 'evaluations', label: 'Évaluations', icon: ClipboardCheck },
    { id: 'discipline', label: 'Discipline', icon: TriangleAlert },
    { id: 'notes', label: 'Notes RH', icon: NotebookPen },
    { id: 'badge', label: 'Badge', icon: CreditCard },
    { id: 'history', label: 'Historique', icon: History },
    ...(props.employee.is_teacher
        ? [
              {
                  id: 'teaching',
                  label: 'Activité pédagogique',
                  icon: CalendarDays,
              },
          ]
        : []),
]);
const activeSection = computed(
    () => tabs.value.find((tab) => tab.id === activeTab.value) ?? tabs.value[0],
);
const recordTabCategories: Record<string, string> = { absences: 'absence', contracts: 'contract', training: 'training', evaluations: 'evaluation', discipline: 'discipline', notes: 'note' };
const currentSalary = computed(
    () => props.employee.salary_configurations?.[0] ?? null,
);
const money = (value: string | number) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));
const uploadKey = ref(0);
const documentForm = useForm({
    temp_folders: [] as string[],
    type: 'administrative',
    title: '',
    reference: '',
    issued_at: '',
    expires_at: '',
    notes: '',
});
function storeDocuments() {
    documentForm.post(`/admin/staff/${props.employee.id}/documents`, {
        preserveScroll: true,
        onSuccess: () => {
            documentForm.reset();
            documentForm.type = 'administrative';
            uploadKey.value++;
        },
    });
}
function deleteDocument(document: any) {
    if (
        !confirm(
            `Supprimer « ${document.title || document.file?.original_name} » ?`,
        )
    )
        return;
    router.delete(
        `/admin/staff/${props.employee.id}/documents/${document.id}`,
        {
            preserveScroll: true,
        },
    );
}
const fileSize = (bytes: number) =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(
        Number(bytes || 0) / 1024 / 1024,
    ) + ' Mo';
</script>

<template>
    <Head :title="employee.name" />
    <AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <div class="flex items-center justify-between gap-3">
                    <Button as-child variant="ghost"
                        ><Link href="/admin/staff"
                            ><ArrowLeft class="mr-2 size-4" />Personnel</Link
                        ></Button
                    >
                    <Button as-child
                        ><Link :href="`/admin/staff/${employee.id}/edit`"
                            ><Pencil class="mr-2 size-4" />Modifier</Link
                        ></Button
                    >
                </div>

                <section class="rounded-xl border bg-card p-6">
                    <div
                        class="flex flex-col gap-5 sm:flex-row sm:items-center"
                    >
                        <img
                            v-if="employee.photo_url"
                            :src="employee.photo_url"
                            class="size-24 rounded-xl object-cover"
                        />
                        <div
                            v-else
                            class="grid size-24 shrink-0 place-items-center rounded-xl bg-muted text-2xl font-semibold"
                        >
                            {{ employee.first_name?.[0]
                            }}{{ employee.last_name?.[0] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h1 class="text-2xl font-semibold">
                                        {{ employee.name }}
                                    </h1>
                                    <p class="text-muted-foreground">
                                        Dossier RH nº {{ employee.id }} ·
                                        {{ employee.employee_type?.name }} ·
                                        {{ employee.employee_code }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full px-3 py-1 text-sm"
                                    :class="
                                        employee.employment_status === 'active'
                                            ? 'bg-green-100 text-green-700'
                                            : employee.employment_status ===
                                                'on_leave'
                                              ? 'bg-amber-100 text-amber-700'
                                              : 'bg-muted text-muted-foreground'
                                    "
                                    >{{
                                        statuses[employee.employment_status]
                                    }}</span
                                >
                            </div>
                            <div
                                class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
                            >
                                <span>{{
                                    employee.email || 'E-mail non renseigné'
                                }}</span
                                ><span>{{
                                    employee.phone || 'Téléphone non renseigné'
                                }}</span
                                ><span
                                    >Embauche :
                                    {{ employee.hire_date || '—' }}</span
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

                <section
                    v-if="activeTab === 'general'"
                    class="grid gap-6 lg:grid-cols-3"
                >
                    <div class="rounded-xl border bg-card p-5 lg:col-span-2">
                        <h2 class="font-semibold">Informations générales</h2>
                        <dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground">Prénom</dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.first_name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Nom</dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.last_name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Type d’employé
                                </dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.employee_type?.name || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Matricule</dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.employee_code || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Date de naissance
                                </dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.birth_date || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Date d’embauche
                                </dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.hire_date || '—' }}
                                </dd>
                            </div>
                            <div><dt class="text-muted-foreground">Lieu de naissance</dt><dd class="mt-1 font-medium">{{ employee.place_of_birth || '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Nationalité</dt><dd class="mt-1 font-medium">{{ employee.nationality || '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Sexe</dt><dd class="mt-1 font-medium">{{ { male: 'Homme', female: 'Femme', other: 'Autre' }[employee.gender] || '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Situation familiale</dt><dd class="mt-1 font-medium">{{ { single: 'Célibataire', married: 'Marié(e)', divorced: 'Divorcé(e)', widowed: 'Veuf / Veuve' }[employee.marital_status] || '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">N° sécurité sociale</dt><dd class="mt-1 font-medium">{{ employee.social_security_number || '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Compte bancaire / RIP</dt><dd class="mt-1 font-medium">{{ employee.bank_account || '—' }}</dd></div>
                            <div>
                                <dt class="text-muted-foreground">E-mail</dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.email || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Téléphone</dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.phone || '—' }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-muted-foreground">Adresse</dt>
                                <dd class="mt-1 font-medium">
                                    {{ employee.address || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="space-y-6">
                        <div class="rounded-xl border bg-card p-5">
                            <h2 class="font-semibold">Accès au système</h2>
                            <div class="mt-4 space-y-3 text-sm">
                                <p class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Connexion</span
                                    ><b>{{
                                        employee.user?.can_login
                                            ? 'Autorisée'
                                            : 'Désactivée'
                                    }}</b>
                                </p>
                                <p class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Compte</span
                                    ><b>{{
                                        employee.user?.is_active
                                            ? 'Actif'
                                            : 'Inactif'
                                    }}</b>
                                </p>
                                <p class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Dossiers étudiants</span
                                    ><b>{{
                                        employee.can_view_student_folders
                                            ? 'Autorisé'
                                            : 'Non autorisé'
                                    }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="rounded-xl border bg-card p-5"><h2 class="font-semibold">Contact d’urgence</h2><div class="mt-4 space-y-2 text-sm"><p><span class="text-muted-foreground">Nom :</span> {{ employee.emergency_contact_name || '—' }}</p><p><span class="text-muted-foreground">Lien :</span> {{ employee.emergency_contact_relationship || '—' }}</p><p><span class="text-muted-foreground">Téléphone :</span> {{ employee.emergency_contact_phone || '—' }}</p></div></div>
                        <div class="rounded-xl border bg-card p-5">
                            <h2 class="font-semibold">Identification</h2>
                            <div class="mt-4 space-y-3 text-sm">
                                <p>
                                    <span class="text-muted-foreground"
                                        >Type :</span
                                    >
                                    {{ employee.identification_type || '—' }}
                                </p>
                                <p>
                                    <span class="text-muted-foreground"
                                        >Numéro :</span
                                    >
                                    {{ employee.identification_number || '—' }}
                                </p>
                                <p>
                                    <span class="text-muted-foreground"
                                        >Expiration :</span
                                    >
                                    {{
                                        employee.identification_expires_at ||
                                        '—'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="employee.notes"
                        class="rounded-xl border bg-card p-5 lg:col-span-3"
                    >
                        <h2 class="font-semibold">Notes générales</h2>
                        <p
                            class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ employee.notes }}
                        </p>
                    </div>
                </section>

                <section
                    v-else-if="activeTab === 'documents'"
                    class="space-y-6"
                >
                    <form
                        class="rounded-xl border bg-card p-5"
                        @submit.prevent="storeDocuments"
                    >
                        <div>
                            <h2 class="font-semibold">Ajouter des documents</h2>
                            <p class="text-sm text-muted-foreground">
                                Sélectionnez une catégorie puis ajoutez autant
                                de fichiers que nécessaire. Les fichiers
                                volumineux sont envoyés par morceaux.
                            </p>
                        </div>
                        <div
                            class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div>
                                <Label>Type de document *</Label
                                ><select
                                    v-model="documentForm.type"
                                    class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                                    required
                                >
                                    <option
                                        v-for="(label, value) in documentTypes"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <p
                                    v-if="documentForm.errors.type"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ documentForm.errors.type }}
                                </p>
                            </div>
                            <div>
                                <Label>Titre</Label
                                ><Input
                                    v-model="documentForm.title"
                                    placeholder="Ex. Contrat CDI signé"
                                />
                            </div>
                            <div>
                                <Label>Référence</Label
                                ><Input
                                    v-model="documentForm.reference"
                                    placeholder="Numéro facultatif"
                                />
                            </div>
                            <div>
                                <Label>Date d’émission</Label
                                ><Input
                                    v-model="documentForm.issued_at"
                                    type="date"
                                />
                            </div>
                            <div>
                                <Label>Date d’expiration</Label
                                ><Input
                                    v-model="documentForm.expires_at"
                                    type="date"
                                />
                            </div>
                            <div class="sm:col-span-2 lg:col-span-3">
                                <Label>Notes</Label
                                ><Input
                                    v-model="documentForm.notes"
                                    placeholder="Informations complémentaires"
                                />
                            </div>
                            <div class="sm:col-span-2 lg:col-span-4">
                                <Label>Fichiers *</Label
                                ><FileUpload
                                    :key="uploadKey"
                                    v-model="documentForm.temp_folders"
                                    collection="hr_documents"
                                    allow-multiple
                                    unlimited
                                    :max-file-size="100 * 1024 * 1024"
                                    :allowed-file-types="[
                                        'application/pdf',
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                        'text/plain',
                                        'text/csv',
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'application/zip',
                                    ]"
                                />
                                <p class="mt-2 text-xs text-muted-foreground">
                                    PDF, images, Word, Excel, texte, CSV ou ZIP
                                    — jusqu’à 100 Mo par fichier.
                                </p>
                                <p
                                    v-if="documentForm.errors.temp_folders"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ documentForm.errors.temp_folders }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <Button
                                :disabled="
                                    documentForm.processing ||
                                    !documentForm.temp_folders.length
                                "
                                >Enregistrer
                                {{ documentForm.temp_folders.length || '' }}
                                document(s)</Button
                            >
                        </div>
                    </form>
                    <div class="rounded-xl border bg-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="font-semibold">
                                    Registre documentaire
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ employee.documents?.length || 0 }}
                                    document(s) dans le dossier.
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="employee.documents?.length"
                            class="mt-5 grid gap-3 lg:grid-cols-2"
                        >
                            <article
                                v-for="document in employee.documents"
                                :key="document.id"
                                class="rounded-lg border p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                                                >{{ document.type_label }}</span
                                            ><span
                                                v-if="
                                                    document.expiry_status ===
                                                    'expired'
                                                "
                                                class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-700"
                                                >Expiré</span
                                            ><span
                                                v-else-if="
                                                    document.expiry_status ===
                                                    'expiring'
                                                "
                                                class="rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-700"
                                                >Expire bientôt</span
                                            >
                                        </div>
                                        <h3 class="mt-2 truncate font-medium">
                                            {{
                                                document.title ||
                                                document.file?.original_name
                                            }}
                                        </h3>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ document.file?.original_name }} ·
                                            {{ fileSize(document.file?.size) }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-xs text-destructive hover:underline"
                                        @click="deleteDocument(document)"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <dt class="text-muted-foreground">
                                            Référence
                                        </dt>
                                        <dd>{{ document.reference || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">
                                            Émis le
                                        </dt>
                                        <dd>{{ document.issued_at || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">
                                            Expire le
                                        </dt>
                                        <dd>
                                            {{ document.expires_at || '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">
                                            Ajouté par
                                        </dt>
                                        <dd>
                                            {{
                                                document.uploader?.name ||
                                                'Système'
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <p
                                    v-if="document.notes"
                                    class="mt-3 text-xs text-muted-foreground"
                                >
                                    {{ document.notes }}
                                </p>
                                <a
                                    :href="document.file?.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-3 inline-flex text-sm font-medium text-primary hover:underline"
                                    >Ouvrir / télécharger</a
                                >
                            </article>
                        </div>
                        <p
                            v-else
                            class="mt-5 rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                        >
                            Aucun document dans ce dossier RH.
                        </p>
                    </div>
                </section>

                <AnnualLeaveTab
                    v-else-if="activeTab === 'annual-leave'"
                    :employee="employee"
                    :summary="annualLeaveSummary"
                />
                <SickLeaveTab v-else-if="activeTab === 'sick-leave'" :employee="employee" :summary="sickLeaveSummary" />
                <HrRecordsTab v-else-if="recordTabCategories[activeTab]" :key="activeTab" :employee="employee" :category="recordTabCategories[activeTab]" :definition="hrRecordCategories[recordTabCategories[activeTab]]" />
                <EmployeeTimeline v-else-if="activeTab === 'history'" :items="employeeTimeline" />

                <section
                    v-else-if="activeTab === 'attendance'"
                    class="rounded-xl border bg-card p-5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">
                                Pointage et temps de travail
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Derniers enregistrements disponibles.
                            </p>
                        </div>
                        <Button as-child size="sm" variant="outline"
                            ><Link href="/admin/attendance"
                                >Ouvrir le pointage</Link
                            ></Button
                        >
                    </div>
                    <div
                        v-if="employee.is_teacher && employee.teaching_stats"
                        class="mt-5 grid gap-3 sm:grid-cols-4 lg:grid-cols-7"
                    >
                        <div
                            v-for="item in [
                                {
                                    label: 'Planifiées',
                                    value: employee.teaching_stats.planned,
                                },
                                {
                                    label: 'Terminées',
                                    value: employee.teaching_stats.completed,
                                },
                                {
                                    label: 'Absences',
                                    value: employee.teaching_stats.absent,
                                },
                                {
                                    label: 'Retards',
                                    value: employee.teaching_stats.late,
                                },
                                {
                                    label: 'Remplacements',
                                    value: employee.teaching_stats.replacements,
                                },
                                {
                                    label: 'Heures prévues',
                                    value:
                                        employee.teaching_stats.planned_hours +
                                        'h',
                                },
                                {
                                    label: 'Heures validées',
                                    value:
                                        employee.teaching_stats.worked_hours +
                                        'h',
                                },
                            ]"
                            :key="item.label"
                            class="rounded-lg bg-muted/40 p-3"
                        >
                            <b class="block text-lg">{{ item.value }}</b
                            ><span class="text-xs text-muted-foreground">{{
                                item.label
                            }}</span>
                        </div>
                    </div>
                    <div v-else class="mt-5 divide-y rounded-lg border px-4">
                        <div
                            v-for="attendance in employee.attendances"
                            :key="attendance.id"
                            class="flex justify-between gap-4 py-3 text-sm"
                        >
                            <span
                                >{{ attendance.attendance_date }} ·
                                {{ attendance.status }}</span
                            ><span>{{ attendance.worked_minutes }} min</span>
                        </div>
                        <p
                            v-if="!employee.attendances?.length"
                            class="py-5 text-sm text-muted-foreground"
                        >
                            Aucun pointage enregistré.
                        </p>
                    </div>
                </section>

                <section
                    v-else-if="activeTab === 'payroll'"
                    class="grid gap-6 lg:grid-cols-2"
                >
                    <div class="rounded-xl border bg-card p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold">
                                Configuration et dernier bulletin
                            </h2>
                            <Button as-child size="sm" variant="outline"
                                ><Link
                                    :href="`/admin/salaries?staff_id=${employee.id}`"
                                    >Gérer</Link
                                ></Button
                            >
                        </div>
                        <div
                            v-if="currentSalary"
                            class="mt-4 space-y-2 text-sm"
                        >
                            <p>
                                <span class="text-muted-foreground"
                                    >Configuration utilisée :</span
                                >
                                {{
                                    currentSalary.name ||
                                    salaryTypes[currentSalary.salary_type] ||
                                    currentSalary.salary_type
                                }}
                            </p>
                            <p>
                                <span class="text-muted-foreground"
                                    >Taux :</span
                                >
                                {{ money(currentSalary.base_rate) }}
                            </p>
                            <p>
                                <span class="text-muted-foreground"
                                    >Effective depuis :</span
                                >
                                {{ currentSalary.effective_from }}
                            </p>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">
                            Aucune configuration n’a encore été utilisée pour
                            cet employé.
                        </p>
                        <div
                            v-if="employee.salary_statements?.[0]"
                            class="mt-5 border-t pt-4 text-sm"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                DERNIER BULLETIN
                            </p>
                            <div class="mt-2 flex justify-between">
                                <span>{{
                                    employee.salary_statements[0].reference
                                }}</span
                                ><strong>{{
                                    money(
                                        employee.salary_statements[0]
                                            .net_salary,
                                    )
                                }}</strong>
                            </div>
                            <p class="mt-1 text-muted-foreground">
                                Reste :
                                {{
                                    money(
                                        employee.salary_statements[0]
                                            .remaining_amount,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">Paiements récents</h2>
                        <div
                            v-if="employee.salary_payments?.length"
                            class="mt-4 divide-y"
                        >
                            <div
                                v-for="payment in employee.salary_payments.slice(
                                    0,
                                    8,
                                )"
                                :key="payment.id"
                                class="flex items-center justify-between gap-3 py-3 text-sm"
                            >
                                <div>
                                    <p>{{ payment.reference }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ payment.paid_at }} ·
                                        {{ payment.payment_method }}
                                    </p>
                                </div>
                                <strong>{{ money(payment.amount) }}</strong>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">
                            Aucun paiement enregistré.
                        </p>
                    </div>
                </section>

                <section
                    v-else-if="activeTab === 'badge'"
                    class="rounded-xl border bg-card p-5"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold">Carte professionnelle</h2>
                            <p class="text-sm text-muted-foreground">
                                Badge actuel et historique des cartes.
                            </p>
                        </div>
                        <Button as-child size="sm" variant="outline"
                            ><Link href="/admin/badges">Gérer</Link></Button
                        >
                    </div>
                    <BadgeCard
                        v-if="employee.badges?.[0]"
                        :badge="employee.badges[0]"
                        :school="page.props.school"
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        Aucune carte professionnelle générée.
                    </p>
                </section>

                <section
                    v-else-if="activeTab === 'teaching'"
                    class="rounded-xl border bg-card p-6"
                >
                    <h2 class="font-semibold">Activité pédagogique</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Les affectations, séances, groupes et la charge
                        pédagogique seront regroupés ici.
                    </p>
                    <div
                        v-if="employee.teaching_stats"
                        class="mt-5 grid gap-3 sm:grid-cols-3"
                    >
                        <div class="rounded-lg bg-muted/40 p-4">
                            <b class="text-xl">{{
                                employee.teaching_stats.planned
                            }}</b>
                            <p class="text-sm text-muted-foreground">
                                Séances planifiées
                            </p>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-4">
                            <b class="text-xl">{{
                                employee.teaching_stats.completed
                            }}</b>
                            <p class="text-sm text-muted-foreground">
                                Séances terminées
                            </p>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-4">
                            <b class="text-xl"
                                >{{ employee.teaching_stats.worked_hours }}h</b
                            >
                            <p class="text-sm text-muted-foreground">
                                Heures validées
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    v-else
                    class="rounded-xl border border-dashed bg-card p-10 text-center"
                >
                    <component
                        :is="activeSection.icon"
                        class="mx-auto size-10 text-primary"
                    />
                    <h2 class="mt-4 text-lg font-semibold">
                        {{ activeSection.label }}
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-xl text-sm text-muted-foreground"
                    >
                        Cette section du dossier RH est prête dans la
                        navigation. Les données, règles métier, formulaires et
                        validations seront implémentés dans une prochaine étape.
                    </p>
                    <span
                        class="mt-4 inline-flex rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                        >Module à venir</span
                    >
                </section>
            </div>
        </main></AdminLayout
    >
</template>
