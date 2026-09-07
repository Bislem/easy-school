<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink, FileCheck2 } from 'lucide-vue-next';
const props = defineProps({
    preview: { type: Object, required: true },
    declarations: { type: Array, required: true },
    dasExportSupported: Boolean,
    portals: Object,
    filters: Object,
});
const money = (v: any) =>
    `${Number(v || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} DA`;
const form = useForm({
    declaration_type: 'CNAS_DAS',
    period: String(props.preview.year),
    employee_count: props.preview.employee_count,
    declared_amount: props.preview.declared_amount,
    errors: props.preview.errors,
    warnings: props.preview.warnings,
});
function changeYear(e: any) {
    router.get(
        '/admin/salaries/declarations',
        { year: e.target.value },
        { preserveState: true, replace: true },
    );
}
function save() {
    form.post('/admin/salaries/declarations', { preserveScroll: true });
}
</script>
<template>
    <Head title="Déclarations sociales & fiscales" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <header class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold">
                            Déclarations sociales & fiscales
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            CNAS · DAC/DAS — DGI · G29/G50
                        </p>
                    </div>
                    <label class="text-sm"
                        >Année<Input
                            type="number"
                            :model-value="preview.year"
                            class="w-28"
                            @change="changeYear"
                    /></label>
                </header>
                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="item in [
                            { l: 'Employés', v: preview.employee_count },
                            {
                                l: 'Assiette CNAS déclarée',
                                v: money(preview.declared_amount),
                            },
                            {
                                l: 'CNAS salarié',
                                v: money(preview.employee_cnas),
                            },
                            {
                                l: 'Charges employeur',
                                v: money(preview.employer_charges),
                            },
                        ]"
                        class="rounded-xl border bg-card p-4"
                    >
                        <small class="text-muted-foreground">{{ item.l }}</small
                        ><b class="mt-1 block text-xl">{{ item.v }}</b>
                    </article>
                </section>
                <section class="grid gap-4 lg:grid-cols-2">
                    <article class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">
                            Aperçu DAS {{ preview.year }}
                        </h2>
                        <p class="mt-1 text-sm">
                            {{ preview.employee_count }} employés ·
                            {{ money(preview.declared_amount) }}
                        </p>
                        <div
                            v-if="preview.errors.length"
                            class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"
                        >
                            <b
                                >{{ preview.errors.length }} erreur(s)
                                bloquante(s)</b
                            >
                            <ul class="mt-2 list-disc pl-5">
                                <li v-for="e in preview.errors">{{ e }}</li>
                            </ul>
                        </div>
                        <div
                            v-if="preview.warnings.length"
                            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                        >
                            <b
                                >{{
                                    preview.warnings.length
                                }}
                                avertissement(s)</b
                            >
                            <ul class="mt-2 list-disc pl-5">
                                <li v-for="w in preview.warnings">{{ w }}</li>
                            </ul>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button :disabled="form.processing" @click="save"
                                ><FileCheck2 class="mr-2 size-4" />Valider
                                l’aperçu</Button
                            ><Button
                                disabled
                                title="Spécification fixe officielle non disponible"
                                >Générer les fichiers CNAS</Button
                            ><Button as-child variant="outline"
                                ><a :href="portals.cnas" target="_blank"
                                    >Ouvrir le portail CNAS<ExternalLink
                                        class="ml-2 size-4" /></a
                            ></Button>
                        </div>
                        <p
                            v-if="!dasExportSupported"
                            class="mt-3 flex gap-2 text-xs text-muted-foreground"
                        >
                            <AlertTriangle class="size-4 shrink-0" />Export TXT
                            désactivé : aucun schéma fixe officiel complet n’est
                            présent dans la documentation du projet.
                        </p>
                    </article>
                    <article class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">DGI — G29 / G50</h2>
                        <p class="mt-2 text-sm">
                            IRG calculé pour la période :
                            <b>{{ money(preview.irg) }}</b>
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Les aperçus sont disponibles. Aucun format
                            électronique n’est généré sans spécification
                            officielle.
                        </p>
                        <div class="mt-4 flex gap-2">
                            <Button
                                variant="outline"
                                @click="
                                    form.declaration_type = 'DGI_G29';
                                    save();
                                "
                                >Préparer G29</Button
                            ><Button
                                variant="outline"
                                @click="
                                    form.declaration_type = 'DGI_G50';
                                    save();
                                "
                                >Préparer G50</Button
                            ><Button as-child variant="outline"
                                ><a :href="portals.dgi" target="_blank"
                                    >Ouvrir Jibayatic<ExternalLink
                                        class="ml-2 size-4" /></a
                            ></Button>
                        </div>
                    </article>
                </section>
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="mb-3 font-semibold">Historique</h2>
                    <div
                        v-for="d in declarations"
                        class="grid grid-cols-4 gap-3 border-t py-3 text-sm"
                    >
                        <b>{{ d.declaration_type }}</b
                        ><span>{{ d.period }}</span
                        ><span>{{ d.status }}</span
                        ><span class="text-right">{{
                            money(d.declared_amount)
                        }}</span>
                    </div>
                    <p
                        v-if="!declarations.length"
                        class="text-sm text-muted-foreground"
                    >
                        Aucune déclaration préparée.
                    </p>
                </section>
            </div>
        </main></AdminLayout
    >
</template>
