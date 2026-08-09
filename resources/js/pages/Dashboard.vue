<script setup lang="ts">
import AppLayout from '@/layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { BookOpen, CalendarDays, ClipboardCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const user = page.props.auth.user;

const roleLabels: Record<string, string> = {
    admin: 'Administrateur',
    teacher: 'Enseignant',
};

const roleLabel = computed(() => roleLabels[user.role] ?? user.role);

const cards = computed(() => {
    if (user.role === 'admin') {
        return [
            {
                title: 'Enseignants',
                description: 'Gérez les enseignants et leurs affectations.',
                icon: Users,
            },
            {
                title: 'Présences',
                description:
                    "Consultez les présences et l'activité quotidienne.",
                icon: ClipboardCheck,
            },
            {
                title: 'Classes',
                description:
                    'Organisez les classes, les matières et les emplois du temps.',
                icon: BookOpen,
            },
            {
                title: 'Calendrier scolaire',
                description:
                    "Planifiez les périodes, les événements et les dates de l'école.",
                icon: CalendarDays,
            },
        ];
    }

    return [
        {
            title: 'Mes classes',
            description: 'Vos classes seront disponibles ici.',
            icon: BookOpen,
        },
        {
            title: 'Emploi du temps',
            description: 'Votre emploi du temps sera disponible ici.',
            icon: CalendarDays,
        },
    ];
});
</script>

<template>
    <AppLayout>
        <Head title="Tableau de bord" />
        <main class="flex flex-1 flex-col gap-6 p-4 md:p-8">
            <div>
                <p class="text-sm font-medium text-muted-foreground">
                    Tableau de bord — {{ roleLabel }}
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">
                    Bienvenue, {{ user.name }}
                </h1>
                <p class="mt-2 text-muted-foreground">
                    Votre espace scolaire est prêt à être configuré.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in cards"
                    :key="card.title"
                    class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm"
                >
                    <component
                        :is="card.icon"
                        class="mb-4 size-8 text-primary"
                    />
                    <h2 class="font-semibold">{{ card.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ card.description }}
                    </p>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
