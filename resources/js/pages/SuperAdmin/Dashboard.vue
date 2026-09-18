<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CircleCheck,
    CirclePause,
    GraduationCap,
    MessagesSquare,
    Users,
} from 'lucide-vue-next';

const props = defineProps<{
    stats: Record<string, number>;
    recentSchools: Array<any>;
    parentAccountNotifications: Array<any>;
}>();
const basePath = (usePage().props.superAdmin as any).basePath as string;
const cards = [
    ['Écoles', 'schools', Building2],
    ['Écoles actives', 'activeSchools', CircleCheck],
    ['Suspendues', 'suspendedSchools', CirclePause],
    ['Utilisateurs', 'users', Users],
    ['Étudiants', 'students', GraduationCap],
    ['Nouveaux messages', 'newContacts', MessagesSquare],
] as const;
</script>

<template>
    <SuperAdminLayout
        title="Tableau de bord"
        description="Vue globale de la plateforme"
    >
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <Card v-for="card in cards" :key="card[1]"
                ><CardContent class="flex items-center justify-between p-5"
                    ><div>
                        <p class="text-sm text-muted-foreground">
                            {{ card[0] }}
                        </p>
                        <p class="mt-2 text-3xl font-black">
                            {{ props.stats[card[1]] }}
                        </p>
                    </div>
                    <component
                        :is="card[2]"
                        class="size-6 text-primary" /></CardContent
            ></Card>
        </div>
        <Card class="mt-6"
            ><CardContent class="p-0"
                ><div class="border-b p-5">
                    <h2 class="font-bold">Écoles récentes</h2>
                </div>
                <div
                    v-for="school in recentSchools"
                    :key="school.id"
                    class="flex items-center justify-between border-b p-4 last:border-0"
                >
                    <div>
                        <p class="font-semibold">{{ school.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ school.slug }}
                        </p>
                    </div>
                    <Link
                        :href="`${basePath}/schools/${school.id}`"
                        class="text-sm font-semibold text-primary"
                        >Gérer</Link
                    >
                </div></CardContent
            ></Card
        >
        <Card class="mt-6">
            <CardContent class="p-0">
                <div class="border-b p-5">
                    <h2 class="font-bold">Activité des comptes parents</h2>
                    <p class="text-xs text-muted-foreground">
                        Changements à prendre en compte pour la facturation.
                    </p>
                </div>
                <div
                    v-for="notification in parentAccountNotifications"
                    :key="notification.id"
                    class="flex items-start justify-between gap-4 border-b p-4 last:border-0"
                >
                    <div>
                        <p class="font-semibold">{{ notification.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ notification.tenant?.name }} ·
                            {{ notification.message }}
                        </p>
                    </div>
                    <time class="shrink-0 text-xs text-muted-foreground">
                        {{
                            new Date(notification.created_at).toLocaleString(
                                'fr-DZ',
                            )
                        }}
                    </time>
                </div>
                <p
                    v-if="!parentAccountNotifications.length"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    Aucun changement de compte parent.
                </p>
            </CardContent>
        </Card>
    </SuperAdminLayout>
</template>
