<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, BellRing } from 'lucide-vue-next';
import { reactive } from 'vue';

const props = defineProps<{ attendanceSettings: { monthly_absence_threshold: number; consecutive_days_threshold: number } }>();
const form = reactive({ ...props.attendanceSettings });
function save() {
    router.put('/admin/school-absence/settings', form, { preserveScroll: true });
}
</script>

<template>
    <Head title="Seuils d’alerte" />
    <AdminLayout>
        <main class="min-h-full flex-1 bg-slate-50/60 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-3xl space-y-6">
                <a href="/admin/school-absence" class="inline-flex items-center gap-1 text-sm text-blue-600"><ArrowLeft class="size-4" />Retour aux absences</a>
                <header class="rounded-2xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-xl bg-amber-100 text-amber-700"><BellRing class="size-5" /></span><div><h1 class="text-2xl font-semibold">Seuils d’alerte</h1><p class="mt-1 text-sm text-muted-foreground">Configurez quand une absence répétée doit attirer l’attention de l’administration.</p></div></div>
                </header>
                <form class="grid gap-4 rounded-2xl border bg-white p-6 shadow-sm sm:grid-cols-2" @submit.prevent="save">
                    <label class="text-sm font-medium text-slate-700">Absences par mois<Input v-model="form.monthly_absence_threshold" type="number" min="1" max="100" class="mt-2" required /><span class="mt-1 block text-xs font-normal text-muted-foreground">Alerter après ce nombre d’absences sur un même mois.</span></label>
                    <label class="text-sm font-medium text-slate-700">Jours consécutifs<Input v-model="form.consecutive_days_threshold" type="number" min="1" max="30" class="mt-2" required /><span class="mt-1 block text-xs font-normal text-muted-foreground">Alerter après ce nombre de jours consécutifs.</span></label>
                    <div class="flex justify-end sm:col-span-2"><Button type="submit">Enregistrer les seuils</Button></div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
