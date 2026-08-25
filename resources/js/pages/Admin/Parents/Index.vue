<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    KeyRound,
    Pencil,
    Plus,
    Search,
    UserRound,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps<{ parents: any; students: any[]; filters: any }>();
const search = ref(props.filters.search ?? '');
const open = ref(false);
const editing = ref<any>(null);
const childSearch = ref('');
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    relationship: '',
    password: '',
    password_confirmation: '',
    student_ids: [] as number[],
});
const filteredStudents = computed(() => {
    const q = childSearch.value.toLowerCase().trim();
    return props.students.filter(
        (s) =>
            !q ||
            `${s.first_name} ${s.last_name} ${s.email ?? ''} ${s.phone ?? ''}`
                .toLowerCase()
                .includes(q),
    );
});
function filter() {
    router.get(
        '/admin/parents',
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
}
function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}
function edit(p: any) {
    editing.value = p;
    form.first_name = p.first_name;
    form.last_name = p.last_name;
    form.email = p.user.email;
    form.phone = p.phone ?? '';
    form.relationship = p.relationship ?? '';
    form.password = '';
    form.password_confirmation = '';
    form.student_ids = p.students.map((s: any) => s.id);
    form.clearErrors();
    open.value = true;
}
function toggleChild(id: number) {
    form.student_ids = form.student_ids.includes(id)
        ? form.student_ids.filter((x) => x !== id)
        : [...form.student_ids, id];
}
function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };
    if (editing.value) {
        form.put(`/admin/parents/${editing.value.id}`, options);
    } else {
        form.post('/admin/parents', options);
    }
}
function toggle(p: any) {
    router.patch(`/admin/parents/${p.id}/toggle`, {}, { preserveScroll: true });
}
</script>
<template>
    <Head title="Parents" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <header
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">Comptes parents</h1>
                        <p class="text-sm text-muted-foreground">
                            Créez les accès sur demande et associez un ou
                            plusieurs enfants.
                        </p>
                    </div>
                    <Button @click="create"
                        ><Plus class="mr-2 size-4" />Nouveau parent</Button
                    >
                </header>
                <form
                    class="flex gap-2 rounded-xl border bg-card p-4"
                    @submit.prevent="filter"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Parent, e-mail, téléphone ou enfant…"
                        />
                    </div>
                    <Button>Rechercher</Button>
                </form>
                <section class="grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="p in parents.data"
                        :key="p.id"
                        class="rounded-2xl border bg-card p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex gap-3">
                                <span
                                    class="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary"
                                    ><UserRound class="size-5"
                                /></span>
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2 class="font-semibold">
                                            {{ p.first_name }} {{ p.last_name }}
                                        </h2>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs"
                                            :class="
                                                p.user.is_active
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-red-100 text-red-700'
                                            "
                                            >{{
                                                p.user.is_active
                                                    ? 'Actif'
                                                    : 'Désactivé'
                                            }}</span
                                        >
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ p.user.email }} ·
                                        {{ p.phone || 'Sans téléphone' }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            p.relationship ||
                                            'Lien non renseigné'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <Button size="icon" variant="ghost" @click="edit(p)"
                                ><Pencil class="size-4"
                            /></Button>
                        </div>
                        <div class="mt-4 border-t pt-4">
                            <p
                                class="mb-2 flex items-center gap-2 text-xs font-semibold text-muted-foreground uppercase"
                            >
                                <Users class="size-4" />Enfants associés ·
                                {{ p.students.length }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="s in p.students"
                                    :key="s.id"
                                    class="rounded-lg border bg-muted/30 px-3 py-2 text-sm"
                                    ><b>{{ s.first_name }} {{ s.last_name }}</b
                                    ><small class="ml-1 text-muted-foreground"
                                        >· {{ s.school_level || '—' }}</small
                                    ></span
                                >
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <Button size="sm" variant="outline" @click="edit(p)"
                                >Gérer les enfants</Button
                            ><Button
                                size="sm"
                                :variant="
                                    p.user.is_active ? 'destructive' : 'default'
                                "
                                @click="toggle(p)"
                                >{{
                                    p.user.is_active ? 'Désactiver' : 'Activer'
                                }}</Button
                            >
                        </div>
                    </article>
                    <div
                        v-if="!parents.data.length"
                        class="col-span-full rounded-xl border border-dashed p-12 text-center text-muted-foreground"
                    >
                        Aucun compte parent.
                    </div>
                </section>
                <nav
                    v-if="parents.links?.length > 3"
                    class="flex max-w-full gap-1 overflow-x-auto"
                >
                    <Link
                        v-for="l in parents.links"
                        :key="l.label"
                        :href="l.url || '#'"
                        class="rounded-md border px-3 py-2 text-sm"
                        :class="{
                            'bg-primary text-primary-foreground': l.active,
                            'pointer-events-none opacity-40': !l.url,
                        }"
                        ><span v-html="l.label"
                    /></Link>
                </nav>
            </div>
        </main>
        <div
            v-if="open"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-3"
            @click.self="open = false"
        >
            <form
                class="mx-auto my-4 w-full max-w-2xl space-y-4 rounded-2xl bg-background p-5 sm:my-10 sm:p-6"
                @submit.prevent="save"
            >
                <div class="flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                editing
                                    ? 'Modifier le parent'
                                    : 'Créer un compte parent'
                            }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            L’accès est créé manuellement et limité aux enfants
                            sélectionnés.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="open = false"
                        ><X class="size-4"
                    /></Button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        ><Label>Prénom</Label
                        ><Input v-model="form.first_name" required /></label
                    ><label
                        ><Label>Nom</Label
                        ><Input v-model="form.last_name" required /></label
                    ><label
                        ><Label>E-mail de connexion</Label
                        ><Input
                            v-model="form.email"
                            type="email"
                            required /></label
                    ><label
                        ><Label>Téléphone</Label
                        ><Input v-model="form.phone" /></label
                    ><label class="sm:col-span-2"
                        ><Label>Lien avec les enfants</Label
                        ><Input
                            v-model="form.relationship"
                            placeholder="Père, mère, tuteur légal…" /></label
                    ><label
                        ><Label>{{
                            editing
                                ? 'Nouveau mot de passe (facultatif)'
                                : 'Mot de passe'
                        }}</Label
                        ><Input
                            v-model="form.password"
                            type="password"
                            :required="!editing" /></label
                    ><label
                        ><Label>Confirmation</Label
                        ><Input
                            v-model="form.password_confirmation"
                            type="password"
                            :required="!editing"
                    /></label>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <Label>Enfants autorisés</Label>
                            <p class="text-xs text-muted-foreground">
                                Plusieurs étudiants peuvent être associés.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-primary/10 px-2 py-1 text-xs text-primary"
                            >{{ form.student_ids.length }} sélectionné(s)</span
                        >
                    </div>
                    <div class="relative mt-3">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="childSearch"
                            class="pl-9"
                            placeholder="Rechercher un étudiant…"
                        />
                    </div>
                    <div
                        class="mt-2 max-h-64 overflow-y-auto rounded-lg border"
                    >
                        <button
                            v-for="s in filteredStudents"
                            :key="s.id"
                            type="button"
                            class="flex w-full items-center gap-3 border-b p-3 text-left hover:bg-muted/40"
                            :class="
                                form.student_ids.includes(s.id)
                                    ? 'bg-primary/5'
                                    : ''
                            "
                            @click="toggleChild(s.id)"
                        >
                            <span
                                class="grid size-5 place-items-center rounded border"
                                :class="
                                    form.student_ids.includes(s.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : ''
                                "
                                ><Check
                                    v-if="form.student_ids.includes(s.id)"
                                    class="size-3" /></span
                            ><span
                                ><b>{{ s.first_name }} {{ s.last_name }}</b
                                ><small class="block text-muted-foreground"
                                    >{{
                                        s.school_level || 'Niveau non renseigné'
                                    }}
                                    ·
                                    {{
                                        s.email || s.phone || 'Sans coordonnées'
                                    }}</small
                                ></span
                            >
                        </button>
                    </div>
                </div>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <Button
                    class="w-full"
                    :disabled="form.processing || !form.student_ids.length"
                    ><KeyRound class="mr-2 size-4" />{{
                        editing
                            ? 'Enregistrer les modifications'
                            : 'Créer le compte et donner accès'
                    }}</Button
                >
            </form>
        </div></AdminLayout
    >
</template>
