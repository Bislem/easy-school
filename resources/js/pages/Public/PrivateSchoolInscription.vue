<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Baby,
    CalendarDays,
    CheckCircle2,
    GraduationCap,
    MapPin,
    Plus,
    ShieldCheck,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{ campaign: any; isAvailable: boolean }>();
const page = usePage();
const submittedCount = computed(() =>
    Number(
        (page.props.flash as any)?.private_school_inscription_submitted || 0,
    ),
);
const blankChild = () => ({
    first_name: '',
    last_name: '',
    birth_date: '',
    campaign_level_id: '',
    email: '',
    phone: '',
    address: '',
});
const children = ref([blankChild()]);
const addChild = () =>
    children.value.length < 10 && children.value.push(blankChild());
const removeChild = (index: number) =>
    children.value.length > 1 && children.value.splice(index, 1);
const error = (errors: Record<string, string>, index: number, field: string) =>
    errors[`children.${index}.${field}`];
</script>

<template>
    <Head :title="campaign.title" />
    <main class="min-h-screen bg-[#f5f8f7] text-slate-900">
        <div
            class="h-2 bg-gradient-to-r from-teal-500 via-emerald-400 to-amber-300"
        />
        <div
            class="mx-auto grid max-w-7xl gap-8 px-4 py-8 lg:grid-cols-[22rem_1fr] lg:px-8 lg:py-12"
        >
            <aside class="space-y-5 lg:sticky lg:top-8 lg:self-start">
                <section
                    class="overflow-hidden rounded-3xl bg-[#082f36] p-6 text-white shadow-xl"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="grid size-14 place-items-center overflow-hidden rounded-2xl bg-white/10"
                        >
                            <img
                                v-if="campaign.tenant?.logo_url"
                                :src="campaign.tenant.logo_url"
                                class="size-full object-cover"
                            />
                            <GraduationCap
                                v-else
                                class="size-7 text-emerald-300"
                            />
                        </div>
                        <div>
                            <p
                                class="text-xs font-bold tracking-widest text-emerald-300 uppercase"
                            >
                                Admissions
                            </p>
                            <p class="font-bold">{{ campaign.tenant?.name }}</p>
                        </div>
                    </div>
                    <h1 class="mt-7 text-3xl leading-tight font-black">
                        {{ campaign.title }}
                    </h1>
                    <p class="mt-3 text-sm text-teal-50/75">
                        Année scolaire {{ campaign.academic_year.name }}
                    </p>
                    <div
                        v-if="
                            campaign.tenant?.commune || campaign.tenant?.wilaya
                        "
                        class="mt-5 flex items-center gap-2 text-sm text-teal-50/75"
                    >
                        <MapPin class="size-4" />{{
                            [campaign.tenant.commune, campaign.tenant.wilaya]
                                .filter(Boolean)
                                .join(', ')
                        }}
                    </div>
                </section>
                <section
                    class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm"
                >
                    <p class="flex items-center gap-2 font-bold">
                        <ShieldCheck class="size-5 text-emerald-600" />Une seule
                        démarche
                    </p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Ajoutez tous vos enfants à la même demande. Vos
                        coordonnées ne sont saisies qu’une seule fois.
                    </p>
                </section>
            </aside>

            <div class="min-w-0 space-y-6">
                <section
                    class="rounded-3xl border bg-white p-6 shadow-sm sm:p-8"
                >
                    <div
                        v-if="campaign.description"
                        class="max-w-none text-sm leading-7 break-words [&_blockquote]:border-l-4 [&_blockquote]:border-emerald-500 [&_blockquote]:pl-4 [&_h2]:text-xl [&_h2]:font-black [&_h3]:font-bold [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:pl-6"
                        v-html="campaign.description"
                    />
                    <div
                        class="mt-5 flex flex-wrap items-center gap-2 border-t pt-5"
                    >
                        <span
                            v-if="campaign.deadline"
                            class="mr-auto flex items-center gap-2 text-sm font-medium text-slate-600"
                            ><CalendarDays
                                class="size-4 text-emerald-600"
                            />Avant le
                            {{
                                new Date(campaign.deadline).toLocaleString(
                                    'fr-FR',
                                )
                            }}</span
                        >
                        <span
                            v-for="level in campaign.levels"
                            :key="level.id"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800"
                            >{{ level.level.name }}</span
                        >
                    </div>
                </section>

                <section
                    v-if="submittedCount"
                    class="rounded-3xl border border-emerald-200 bg-white p-12 text-center shadow-sm"
                >
                    <span
                        class="mx-auto grid size-20 place-items-center rounded-full bg-emerald-50"
                        ><CheckCircle2 class="size-11 text-emerald-600"
                    /></span>
                    <h2 class="mt-5 text-2xl font-black">
                        Demande enregistrée
                    </h2>
                    <p class="mt-2 text-slate-600">
                        {{ submittedCount }}
                        {{
                            submittedCount > 1
                                ? 'demandes ont été transmises'
                                : 'demande a été transmise'
                        }}
                        à l’école.
                    </p>
                </section>
                <section
                    v-else-if="!isAvailable"
                    class="rounded-3xl border bg-white p-12 text-center shadow-sm"
                >
                    <h2 class="text-2xl font-black">Inscriptions fermées</h2>
                    <p class="mt-2 text-slate-600">
                        Cette campagne n’accepte plus de demandes.
                    </p>
                </section>

                <Form
                    v-else
                    :action="`/ecole/inscription/${campaign.public_token}`"
                    method="post"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <section
                        class="rounded-3xl border bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div class="mb-6 flex items-center gap-4">
                            <span
                                class="grid size-11 place-items-center rounded-2xl bg-teal-50 text-teal-700"
                                ><Users class="size-5"
                            /></span>
                            <div>
                                <p
                                    class="text-xs font-bold text-teal-700 uppercase"
                                >
                                    Étape 1
                                </p>
                                <h2 class="text-xl font-black">
                                    Parent ou tuteur
                                </h2>
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label
                                ><Label>Prénom *</Label
                                ><Input
                                    name="parent_first_name"
                                    required /><InputError
                                    :message="errors.parent_first_name"
                            /></label>
                            <label
                                ><Label>Nom *</Label
                                ><Input
                                    name="parent_last_name"
                                    required /><InputError
                                    :message="errors.parent_last_name"
                            /></label>
                            <label
                                ><Label>E-mail *</Label
                                ><Input
                                    name="parent_email"
                                    type="email"
                                    required /><InputError
                                    :message="errors.parent_email"
                            /></label>
                            <label
                                ><Label>Téléphone *</Label
                                ><Input
                                    name="parent_phone"
                                    type="tel"
                                    required /><InputError
                                    :message="errors.parent_phone"
                            /></label>
                            <label class="sm:col-span-2"
                                ><Label>Lien avec les enfants</Label
                                ><Input
                                    name="relationship"
                                    placeholder="Père, mère, tuteur…" /><InputError
                                    :message="errors.relationship"
                            /></label>
                        </div>
                    </section>

                    <section
                        class="rounded-3xl border bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div class="mb-6 flex items-center gap-4">
                            <span
                                class="grid size-11 place-items-center rounded-2xl bg-amber-50 text-amber-700"
                                ><Baby class="size-5"
                            /></span>
                            <div class="flex-1">
                                <p
                                    class="text-xs font-bold text-amber-700 uppercase"
                                >
                                    Étape 2
                                </p>
                                <h2 class="text-xl font-black">
                                    Enfants à inscrire
                                </h2>
                            </div>
                            <span
                                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold"
                                >{{ children.length }}/10</span
                            >
                        </div>
                        <InputError :message="errors.children" class="mb-4" />
                        <div class="space-y-5">
                            <fieldset
                                v-for="(child, index) in children"
                                :key="index"
                                class="relative rounded-2xl border border-slate-200 bg-slate-50/70 p-5 sm:p-6"
                            >
                                <legend class="px-2 font-black text-slate-700">
                                    Enfant {{ index + 1 }}
                                </legend>
                                <Button
                                    v-if="children.length > 1"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="absolute top-3 right-3 text-red-500"
                                    title="Retirer cet enfant"
                                    @click="removeChild(index)"
                                    ><Trash2 class="size-4"
                                /></Button>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label
                                        ><Label>Prénom *</Label
                                        ><Input
                                            v-model="child.first_name"
                                            :name="`children[${index}][first_name]`"
                                            required /><InputError
                                            :message="
                                                error(
                                                    errors,
                                                    index,
                                                    'first_name',
                                                )
                                            "
                                    /></label>
                                    <label
                                        ><Label>Nom *</Label
                                        ><Input
                                            v-model="child.last_name"
                                            :name="`children[${index}][last_name]`"
                                            required /><InputError
                                            :message="
                                                error(
                                                    errors,
                                                    index,
                                                    'last_name',
                                                )
                                            "
                                    /></label>
                                    <label
                                        ><Label>Date de naissance *</Label
                                        ><Input
                                            v-model="child.birth_date"
                                            :name="`children[${index}][birth_date]`"
                                            type="date"
                                            required /><InputError
                                            :message="
                                                error(
                                                    errors,
                                                    index,
                                                    'birth_date',
                                                )
                                            "
                                    /></label>
                                    <label
                                        ><Label>Niveau demandé *</Label
                                        ><select
                                            v-model="child.campaign_level_id"
                                            :name="`children[${index}][campaign_level_id]`"
                                            required
                                            class="h-10 w-full rounded-md border bg-white px-3 text-sm"
                                        >
                                            <option value="">
                                                Sélectionner
                                            </option>
                                            <option
                                                v-for="level in campaign.levels"
                                                :key="level.id"
                                                :value="level.id"
                                            >
                                                {{ level.level.name
                                                }}{{
                                                    level.level.cycle?.name
                                                        ? ` · ${level.level.cycle.name}`
                                                        : ''
                                                }}
                                            </option></select
                                        ><InputError
                                            :message="
                                                error(
                                                    errors,
                                                    index,
                                                    'campaign_level_id',
                                                )
                                            "
                                    /></label>
                                    <label
                                        ><Label
                                            >E-mail
                                            <span
                                                class="font-normal text-slate-400"
                                                >(facultatif)</span
                                            ></Label
                                        ><Input
                                            v-model="child.email"
                                            :name="`children[${index}][email]`"
                                            type="email" /><InputError
                                            :message="
                                                error(errors, index, 'email')
                                            "
                                    /></label>
                                    <label
                                        ><Label
                                            >Téléphone
                                            <span
                                                class="font-normal text-slate-400"
                                                >(facultatif)</span
                                            ></Label
                                        ><Input
                                            v-model="child.phone"
                                            :name="`children[${index}][phone]`"
                                            type="tel" /><InputError
                                            :message="
                                                error(errors, index, 'phone')
                                            "
                                    /></label>
                                    <label class="sm:col-span-2"
                                        ><Label
                                            >Adresse
                                            <span
                                                class="font-normal text-slate-400"
                                                >(facultative)</span
                                            ></Label
                                        ><Input
                                            v-model="child.address"
                                            :name="`children[${index}][address]`" /><InputError
                                            :message="
                                                error(errors, index, 'address')
                                            "
                                    /></label>
                                </div>
                            </fieldset>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-5 w-full border-dashed py-6 text-teal-700"
                            :disabled="children.length >= 10"
                            @click="addChild"
                            ><Plus class="size-5" />Ajouter un autre
                            enfant</Button
                        >
                    </section>
                    <Button
                        class="h-14 w-full rounded-2xl text-base font-black shadow-lg shadow-primary/20"
                        :disabled="processing"
                        >{{
                            processing
                                ? 'Transmission en cours…'
                                : `Envoyer ${children.length > 1 ? 'les demandes' : 'la demande'}`
                        }}<ArrowRight v-if="!processing" class="ml-2 size-5"
                    /></Button>
                </Form>
            </div>
        </div>
    </main>
</template>
