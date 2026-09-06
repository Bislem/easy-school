<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Archive,
    CheckCircle2,
    Clock3,
    Inbox,
    Mail,
    MessageSquareText,
    Phone,
    Search,
    Send,
    UserRound,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    requests: any;
    filters: any;
    counts: Record<string, number>;
}>();
const base = (usePage().props.superAdmin as any).basePath;
const selected = ref<any>(null);
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const action = useForm({ status: 'in_progress', internal_note: '' });
const applyFilters = () =>
    router.get(
        `${base}/contact-requests`,
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
const open = (item: any) => {
    selected.value = item;
    action.status = item.status === 'new' ? 'in_progress' : item.status;
    action.internal_note = item.internal_note || '';
};
const save = () =>
    action.patch(`${base}/contact-requests/${selected.value.id}`, {
        onSuccess: () => (selected.value = null),
    });
const meta: Record<string, { label: string; style: string }> = {
    new: { label: 'Nouveau', style: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'En cours', style: 'bg-amber-100 text-amber-700' },
    resolved: { label: 'Résolu', style: 'bg-emerald-100 text-emerald-700' },
    archived: { label: 'Archivé', style: 'bg-slate-100 text-slate-600' },
};
const subjects: Record<string, string> = {
    information: 'Informations',
    pricing: 'Tarifs et abonnement',
    partnership: 'Partenariat',
    support: 'Assistance',
    other: 'Autre demande',
};
</script>

<template>
    <SuperAdminLayout
        title="Messages de contact"
        description="Centralisez et suivez les demandes envoyées depuis le site public"
    >
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card
                v-for="item in [
                    [Inbox, 'new', 'Nouveaux', 'text-blue-600'],
                    [Clock3, 'in_progress', 'En cours', 'text-amber-600'],
                    [CheckCircle2, 'resolved', 'Résolus', 'text-emerald-600'],
                    [Archive, 'archived', 'Archivés', 'text-slate-500'],
                ]"
                :key="item[1] as string"
                ><CardContent class="flex items-center justify-between p-5"
                    ><div>
                        <p
                            class="text-xs font-bold text-muted-foreground uppercase"
                        >
                            {{ item[2] }}
                        </p>
                        <p class="mt-2 text-3xl font-black">
                            {{ counts[item[1] as string] || 0 }}
                        </p>
                    </div>
                    <component
                        :is="item[0]"
                        class="size-7"
                        :class="item[3]" /></CardContent
            ></Card>
        </div>
        <div
            class="my-6 flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-sm md:flex-row"
        >
            <div class="relative flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                /><Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Nom, email, organisation ou message…"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-background px-3 text-sm"
                @change="applyFilters"
            >
                <option value="">Tous les statuts</option>
                <option v-for="(value, key) in meta" :key="key" :value="key">
                    {{ value.label }}
                </option>
            </select>
            <Button variant="outline" @click="applyFilters">Filtrer</Button>
        </div>
        <Card
            ><CardContent class="p-0">
                <div
                    v-if="!requests.data.length"
                    class="grid min-h-72 place-items-center p-8 text-center"
                >
                    <div>
                        <Inbox
                            class="mx-auto size-12 text-muted-foreground/40"
                        />
                        <h2 class="mt-4 font-black">Aucun message trouvé</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Les nouvelles demandes apparaîtront ici.
                        </p>
                    </div>
                </div>
                <button
                    v-for="item in requests.data"
                    :key="item.id"
                    class="grid w-full gap-3 border-b p-5 text-left transition last:border-0 hover:bg-muted/30 md:grid-cols-[1fr_1.4fr_auto] md:items-center"
                    @click="open(item)"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl"
                            :class="
                                item.status === 'new'
                                    ? 'bg-blue-100 text-blue-600'
                                    : 'bg-muted text-muted-foreground'
                            "
                            ><Mail class="size-5"
                        /></span>
                        <div class="min-w-0">
                            <p class="truncate font-black">{{ item.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ item.organization || item.email }}
                            </p>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-primary">
                            {{ subjects[item.subject] }}
                        </p>
                        <p class="mt-1 truncate text-sm text-muted-foreground">
                            {{ item.message }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 md:justify-end">
                        <span class="text-xs text-muted-foreground">{{
                            new Date(item.created_at).toLocaleDateString(
                                'fr-DZ',
                            )
                        }}</span
                        ><Badge
                            class="border-0"
                            :class="meta[item.status]?.style"
                            >{{ meta[item.status]?.label }}</Badge
                        >
                    </div>
                </button>
                <div
                    v-if="requests.links?.length > 3"
                    class="flex flex-wrap justify-center gap-1 border-t p-4"
                >
                    <Link
                        v-for="link in requests.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-md border px-3 py-1.5 text-xs"
                        :class="{
                            'bg-primary text-primary-foreground': link.active,
                            'pointer-events-none opacity-40': !link.url,
                        }"
                        v-html="link.label"
                    />
                </div> </CardContent
        ></Card>

        <div
            v-if="selected"
            class="fixed inset-0 z-50 flex justify-end bg-black/45"
            @click.self="selected = null"
        >
            <div
                class="h-full w-full max-w-xl overflow-y-auto bg-background p-6 shadow-2xl sm:p-8"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-black text-primary uppercase">
                            {{ subjects[selected.subject] }}
                        </p>
                        <h2 class="mt-1 text-2xl font-black">
                            {{ selected.name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ selected.organization }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="selected = null"
                        ><X
                    /></Button>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a
                        :href="`mailto:${selected.email}?subject=Re: ${subjects[selected.subject]}`"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-bold text-primary-foreground"
                        ><Send class="size-4" />Répondre par email</a
                    ><a
                        v-if="selected.phone"
                        :href="`tel:${selected.phone}`"
                        class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-xs font-bold"
                        ><Phone class="size-4" />{{ selected.phone }}</a
                    >
                </div>
                <div class="mt-6 rounded-2xl border bg-muted/30 p-5">
                    <MessageSquareText class="size-5 text-primary" />
                    <p class="mt-3 text-sm leading-7 whitespace-pre-wrap">
                        {{ selected.message }}
                    </p>
                </div>
                <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Email</dt>
                        <dd class="mt-1 font-bold">{{ selected.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Reçu le</dt>
                        <dd class="mt-1 font-bold">
                            {{
                                new Date(selected.created_at).toLocaleString(
                                    'fr-DZ',
                                )
                            }}
                        </dd>
                    </div>
                    <div v-if="selected.handler">
                        <dt class="text-xs text-muted-foreground">
                            Pris en charge par
                        </dt>
                        <dd class="mt-1 flex items-center gap-1 font-bold">
                            <UserRound class="size-4" />{{
                                selected.handler.name
                            }}
                        </dd>
                    </div>
                </dl>
                <form class="mt-8 border-t pt-6" @submit.prevent="save">
                    <label class="text-xs font-bold">Statut du dossier</label
                    ><select
                        v-model="action.status"
                        class="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="(value, key) in meta"
                            :key="key"
                            :value="key"
                        >
                            {{ value.label }}
                        </option></select
                    ><label class="mt-5 block text-xs font-bold"
                        >Note interne</label
                    ><textarea
                        v-model="action.internal_note"
                        rows="5"
                        class="mt-2 w-full rounded-lg border bg-background p-3 text-sm"
                        placeholder="Suivi, prochaine action, résumé de l’échange…"
                    /><InputError
                        :message="action.errors.internal_note"
                    /><Button
                        class="mt-4 w-full gap-2"
                        :disabled="action.processing"
                        ><CheckCircle2 class="size-4" />Enregistrer le
                        suivi</Button
                    >
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
