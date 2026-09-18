<script setup lang="ts">
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronRight,
    GraduationCap,
    MapPin,
    UserRound,
} from 'lucide-vue-next';
defineProps<{ portal: any; children: any[] }>();
</script>
<template>
    <Head title="Mes enfants" /><ParentLayout
        :portal="portal"
        :show-child-selector="false"
        ><div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">Mes enfants</h1>
                <p class="mt-1 text-slate-500">
                    Les élèves associés à votre compte parent.
                </p>
            </div>
            <div
                v-if="children.length"
                class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="child in children"
                    :key="child.id"
                    class="overflow-hidden rounded-2xl border bg-white shadow-sm"
                >
                    <div
                        class="h-2 bg-gradient-to-r from-teal-500 to-cyan-500"
                    ></div>
                    <div class="p-5">
                        <div class="flex gap-4">
                            <img
                                v-if="child.photo_url"
                                :src="child.photo_url"
                                class="size-16 rounded-2xl object-cover"
                            />
                            <div
                                v-else
                                class="grid size-16 place-items-center rounded-2xl bg-primary/10 text-xl font-bold text-primary"
                            >
                                {{
                                    child.name
                                        .split(' ')
                                        .map((x: string) => x[0])
                                        .join('')
                                        .slice(0, 2)
                                }}
                            </div>
                            <div>
                                <h2 class="font-bold">{{ child.name }}</h2>
                                <p class="text-sm text-slate-500">
                                    Élève n° {{ child.identifier }}
                                </p>
                                <p
                                    class="mt-1 text-sm font-medium text-primary"
                                >
                                    {{ child.level || 'Niveau non défini'
                                    }}<span v-if="child.group">
                                        · {{ child.group }}</span
                                    >
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 space-y-2 text-sm text-slate-600">
                            <p class="flex gap-2">
                                <GraduationCap class="size-4" />{{
                                    child.academic_year || 'Aucune année active'
                                }}
                            </p>
                            <p v-if="child.main_teacher" class="flex gap-2">
                                <UserRound class="size-4" />{{
                                    child.main_teacher
                                }}
                            </p>
                            <p v-if="child.site" class="flex gap-2">
                                <MapPin class="size-4" />{{ child.site }}
                            </p>
                        </div>
                        <Link
                            :href="`/parent/children/${child.id}`"
                            class="mt-5 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold hover:bg-primary/10 hover:text-primary"
                            >Voir les détails <ChevronRight class="size-4"
                        /></Link>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="rounded-2xl border border-dashed bg-white p-12 text-center"
            >
                <h2 class="font-semibold">Aucun enfant associé</h2>
                <p class="mt-2 text-sm text-slate-500">
                    L’établissement doit associer un élève à votre compte avant
                    qu’il apparaisse ici.
                </p>
            </div>
        </div></ParentLayout
    >
</template>
