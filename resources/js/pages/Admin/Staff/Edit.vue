<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
const props = defineProps<{
    employee: any;
    employeeTypes: any[];
    statuses: any[];
}>();
const form = useForm({
    first_name: props.employee.first_name,
    last_name: props.employee.last_name,
    employee_type_id: String(props.employee.employee_type_id),
    employee_code: props.employee.employee_code,
    phone: props.employee.phone ?? '',
    email: props.employee.email ?? '',
    address: props.employee.address ?? '',
    birth_date: props.employee.birth_date ?? '',
    hire_date: props.employee.hire_date ?? '',
    employment_status: props.employee.employment_status,
    notes: props.employee.notes ?? '',
    identification_type: props.employee.identification_type ?? '',
    identification_number: props.employee.identification_number ?? '',
    identification_expires_at: props.employee.identification_expires_at ?? '',
    identification_notes: props.employee.identification_notes ?? '',
    can_login: props.employee.user?.can_login ?? false,
    can_view_student_folders: props.employee.can_view_student_folders ?? true,
    password: '',
    password_confirmation: '',
    photo: null as File | null,
});
function submit() {
    form.post(`/admin/staff/${props.employee.id}`, { forceFormData: true });
}
function photo(e: Event) {
    form.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}
</script>
<template>
    <Head :title="`Modifier ${employee.name}`" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-3xl space-y-5">
                <Button as-child variant="ghost"
                    ><Link :href="`/admin/staff/${employee.id}`"
                        ><ArrowLeft class="mr-2 size-4" />Profil</Link
                    ></Button
                >
                <section class="rounded-xl border bg-card p-6">
                    <h1 class="text-2xl font-semibold">
                        Modifier {{ employee.name }}
                    </h1>
                    <form
                        class="mt-6 grid gap-4 sm:grid-cols-2"
                        @submit.prevent="submit"
                    >
                        <div>
                            <Label>Prénom</Label
                            ><Input
                                v-model="form.first_name"
                                required
                            /><InputError :message="form.errors.first_name" />
                        </div>
                        <div>
                            <Label>Nom</Label
                            ><Input v-model="form.last_name" required />
                        </div>
                        <div>
                            <Label>Type</Label
                            ><select
                                v-model="form.employee_type_id"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                            >
                                <option
                                    v-for="t in employeeTypes"
                                    :key="t.id"
                                    :value="String(t.id)"
                                >
                                    {{ t.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label>Référence</Label
                            ><Input
                                v-model="form.employee_code"
                                required
                            /><InputError
                                :message="form.errors.employee_code"
                            />
                        </div>
                        <div>
                            <Label>E-mail</Label
                            ><Input
                                v-model="form.email"
                                type="email"
                            /><InputError :message="form.errors.email" />
                        </div>
                        <div>
                            <Label>Téléphone</Label
                            ><Input v-model="form.phone" />
                        </div>
                        <div>
                            <Label>Date de naissance</Label
                            ><Input v-model="form.birth_date" type="date" />
                        </div>
                        <div>
                            <Label>Date d’embauche</Label
                            ><Input v-model="form.hire_date" type="date" />
                        </div>
                        <div>
                            <Label>Statut</Label
                            ><select
                                v-model="form.employment_status"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label>Nouvelle photo</Label
                            ><Input
                                type="file"
                                accept="image/*"
                                @change="photo"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label>Adresse</Label
                            ><textarea
                                v-model="form.address"
                                class="mt-1 min-h-20 w-full rounded-md border bg-background p-3"
                            />
                        </div>
                        <div>
                            <Label>Type de document</Label
                            ><Input v-model="form.identification_type" />
                        </div>
                        <div>
                            <Label>Numéro de document</Label
                            ><Input v-model="form.identification_number" />
                        </div>
                        <div>
                            <Label>Expiration du document</Label
                            ><Input
                                v-model="form.identification_expires_at"
                                type="date"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label>Notes</Label
                            ><textarea
                                v-model="form.notes"
                                class="mt-1 min-h-24 w-full rounded-md border bg-background p-3"
                            />
                        </div>
                        <label class="flex items-center gap-2 sm:col-span-2"
                            ><input v-model="form.can_login" type="checkbox" />
                            Autoriser la connexion</label
                        ><label v-if="employee.employee_type?.is_teacher" class="flex items-start gap-3 rounded-lg border p-4 sm:col-span-2"><input v-model="form.can_view_student_folders" type="checkbox" class="mt-1"/><span><b class="block">Avoir accès aux dossiers étudiants</b><small class="text-muted-foreground">Autorise la consultation en lecture seule des étudiants appartenant à ses planifications actives. Les paiements et documents restent masqués.</small></span></label
                        ><template v-if="form.can_login"
                            ><div>
                                <Label>Nouveau mot de passe (facultatif)</Label
                                ><Input
                                    v-model="form.password"
                                    type="password"
                                /><InputError :message="form.errors.password" />
                            </div>
                            <div>
                                <Label>Confirmation</Label
                                ><Input
                                    v-model="form.password_confirmation"
                                    type="password"
                                /></div
                        ></template>
                        <div class="flex justify-end gap-2 sm:col-span-2">
                            <Button as-child variant="outline"
                                ><Link :href="`/admin/staff/${employee.id}`"
                                    >Annuler</Link
                                ></Button
                            ><Button :disabled="form.processing"
                                >Enregistrer</Button
                            >
                        </div>
                    </form>
                </section>
            </div>
        </main></AdminLayout
    >
</template>
