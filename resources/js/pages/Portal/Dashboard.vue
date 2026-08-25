<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
defineProps({
    kind: String,
    profile: Object,
    today: Array,
    upcoming: Array,
    groups: Array,
    students: Array,
    notifications: Array,
    summary: Object,
});
const time = (v: string) =>
    new Date(v).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
function read(n: any) {
    const visit = () => n.data?.url && router.visit(n.data.url);
    if (!n.read_at)
        router.patch(
            `/portal/notifications/${n.id}/read`,
            {},
            { onSuccess: visit },
        );
    else visit();
}
</script>
<template>
    <Head title="Mon espace" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <header>
                    <h1 class="text-2xl font-semibold">
                        {{
                            kind === 'parent'
                                ? 'Espace parent'
                                : kind === 'student'
                                  ? 'Espace étudiant'
                                  : kind === 'teacher'
                                    ? 'Espace enseignant'
                                    : 'Espace personnel'
                        }}
                    </h1>
                    <p class="text-muted-foreground">
                        Informations personnelles et activités utiles.
                    </p>
                </header>
                <section v-if="kind === 'parent'" class="space-y-5">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border bg-card p-4">
                            <p class="text-sm text-muted-foreground">
                                Enfants associés
                            </p>
                            <p class="mt-1 text-2xl font-semibold">
                                {{ summary.children || 0 }}
                            </p>
                        </div>
                        <div class="rounded-xl border bg-card p-4">
                            <p class="text-sm text-muted-foreground">
                                Assiduité globale
                            </p>
                            <p class="mt-1 text-2xl font-semibold">
                                {{
                                    summary.attendance_rate === null
                                        ? '—'
                                        : `${summary.attendance_rate}%`
                                }}
                            </p>
                        </div>
                        <div class="rounded-xl border bg-card p-4">
                            <p class="text-sm text-muted-foreground">
                                Total réglé
                            </p>
                            <p class="mt-1 text-2xl font-semibold">
                                {{
                                    Number(summary.paid || 0).toLocaleString(
                                        'fr-DZ',
                                    )
                                }}
                                DA
                            </p>
                        </div>
                        <div class="rounded-xl border bg-card p-4">
                            <p class="text-sm text-muted-foreground">
                                Reste à payer
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold"
                                :class="
                                    summary.balance > 0
                                        ? 'text-amber-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{
                                    Number(summary.balance || 0).toLocaleString(
                                        'fr-DZ',
                                    )
                                }}
                                DA
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <article
                            v-for="child in profile.students"
                            :key="child.id"
                            class="overflow-hidden rounded-2xl border bg-card shadow-sm"
                        >
                            <div class="flex items-center gap-4 p-5">
                                <img
                                    v-if="child.photo_url"
                                    :src="child.photo_url"
                                    class="size-16 rounded-xl object-cover"
                                />
                                <div
                                    v-else
                                    class="grid size-16 shrink-0 place-items-center rounded-xl bg-primary/10 text-lg font-semibold text-primary"
                                >
                                    {{ child.first_name?.[0]
                                    }}{{ child.last_name?.[0] }}
                                </div>
                                <div class="min-w-0">
                                    <h2 class="truncate font-semibold">
                                        {{ child.first_name }}
                                        {{ child.last_name }}
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            child.school_level ||
                                            'Niveau non renseigné'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            child.portal_summary?.formations ||
                                            0
                                        }}
                                        formation(s) active(s)
                                    </p>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-3 border-y bg-muted/20 text-center text-sm"
                            >
                                <div class="p-3">
                                    <b>{{
                                        child.portal_summary
                                            ?.attendance_rate === null
                                            ? '—'
                                            : `${child.portal_summary?.attendance_rate}%`
                                    }}</b
                                    ><small class="block text-muted-foreground"
                                        >Assiduité</small
                                    >
                                </div>
                                <div class="border-x p-3">
                                    <b>{{
                                        Number(
                                            child.portal_summary?.paid || 0,
                                        ).toLocaleString('fr-DZ')
                                    }}</b
                                    ><small class="block text-muted-foreground"
                                        >Payé (DA)</small
                                    >
                                </div>
                                <div class="p-3">
                                    <b>{{
                                        Number(
                                            child.portal_summary?.balance || 0,
                                        ).toLocaleString('fr-DZ')
                                    }}</b
                                    ><small class="block text-muted-foreground"
                                        >Reste (DA)</small
                                    >
                                </div>
                            </div>
                            <div class="p-4">
                                <Button
                                    as-child
                                    class="w-full"
                                    variant="outline"
                                    ><Link
                                        :href="`/portal/children/${child.id}`"
                                        >Ouvrir le dossier complet</Link
                                    ></Button
                                >
                            </div>
                        </article>
                        <div
                            v-if="!profile.students?.length"
                            class="col-span-full rounded-xl border border-dashed p-10 text-center text-muted-foreground"
                        >
                            Aucun enfant n’est encore associé à ce compte.
                            Contactez l’administration.
                        </div>
                    </div>
                </section>
                <section v-else class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm text-muted-foreground">Aujourd’hui</p>
                        <p class="text-2xl font-semibold">
                            {{ today.length }} séance(s)
                        </p>
                    </div>
                    <div
                        v-if="kind === 'teacher'"
                        class="rounded-xl border bg-card p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Heures terminées
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ summary.completed_hours || 0 }} h
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm text-muted-foreground">
                            Notifications non lues
                        </p>
                        <p class="text-2xl font-semibold">
                            {{
                                notifications.filter((n: any) => !n.read_at)
                                    .length
                            }}
                        </p>
                    </div>
                </section>
                <div class="grid gap-5 lg:grid-cols-2">
                    <section class="rounded-xl border bg-card p-5">
                        <div class="flex justify-between">
                            <h2 class="font-semibold">Prochaines séances</h2>
                            <Link
                                href="/portal/planning"
                                class="text-sm text-primary"
                                >Planning</Link
                            >
                        </div>
                        <div class="mt-3 divide-y">
                            <div
                                v-for="s in upcoming"
                                :key="s.id"
                                class="py-3 text-sm"
                            >
                                <b>{{ s.title }}</b>
                                <p class="text-muted-foreground">
                                    {{
                                        new Date(
                                            s.starts_at,
                                        ).toLocaleDateString('fr-FR')
                                    }}
                                    · {{ time(s.starts_at) }} ·
                                    {{ s.classroom?.name || 'Salle à définir' }}
                                </p>
                            </div>
                            <p
                                v-if="!upcoming.length"
                                class="py-5 text-muted-foreground"
                            >
                                Aucune séance à venir.
                            </p>
                        </div>
                    </section>
                    <section class="rounded-xl border bg-card p-5">
                        <div class="flex justify-between">
                            <h2 class="font-semibold">Notifications</h2>
                            <Link
                                href="/portal/notifications"
                                class="text-sm text-primary"
                                >Tout voir</Link
                            >
                        </div>
                        <button
                            v-for="n in notifications"
                            :key="n.id"
                            class="block w-full border-b py-3 text-left text-sm"
                            :class="!n.read_at ? 'font-medium' : ''"
                            @click="read(n)"
                        >
                            <span>{{ n.title }}</span>
                            <p
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ n.message }} · {{ n.occurred_at }}
                            </p>
                        </button>
                        <p
                            v-if="!notifications.length"
                            class="py-5 text-sm text-muted-foreground"
                        >
                            Aucune notification.
                        </p>
                    </section>
                </div>
            </div>
        </main></AdminLayout
    >
</template>
