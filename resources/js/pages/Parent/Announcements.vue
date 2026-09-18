<script setup lang="ts">
import AnnouncementViewer from '@/components/announcements/AnnouncementViewer.vue';
import ParentLayout from '@/layouts/ParentLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, Eye } from 'lucide-vue-next';
import { ref } from 'vue';
defineProps<{portal:any;announcements:{data:any[];links:any[]}}>();
const selected=ref<any>(null),open=ref(false);
function view(item:any){selected.value=item;open.value=true;if(!item.read_at)router.patch(`/portal/notifications/${item.id}/read`,{},{preserveScroll:true,preserveState:true})}
const date=(v:string)=>new Date(v).toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'});
</script>
<template><Head title="Annonces"/><ParentLayout :portal="portal"><main class="mx-auto w-full max-w-6xl pb-10">
<section class="rounded-3xl bg-gradient-to-r from-slate-950 to-teal-950 px-7 py-9 text-white shadow-xl sm:px-10"><p class="text-xs font-bold uppercase tracking-widest text-teal-300">Informations de l’établissement</p><h1 class="mt-2 text-3xl font-black sm:text-5xl">Annonces de l’école</h1><p class="mt-3 max-w-2xl text-slate-300">Retrouvez ici les communications et informations importantes publiées par l’administration.</p></section>
<section v-if="announcements.data.length" class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><article v-for="a in announcements.data" :key="a.id" class="group overflow-hidden rounded-3xl border bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl"><button class="block h-60 w-full overflow-hidden text-left" @click="view(a)"><img v-if="a.poster_url" :src="a.poster_url" :alt="a.title" class="h-full w-full object-cover transition duration-500 group-hover:scale-105"><div v-else class="grid h-full place-items-center bg-gradient-to-br from-primary via-teal-500 to-cyan-700 p-7 text-center text-white"><div><Bell class="mx-auto mb-3 size-8 opacity-75"/><h2 class="line-clamp-4 text-3xl font-black leading-tight">{{a.title}}</h2></div></div></button><div class="p-5"><time class="text-xs font-medium text-slate-400">{{date(a.published_at)}}</time><h2 class="mt-2 line-clamp-2 text-xl font-bold">{{a.title}}</h2><p class="mt-2 line-clamp-3 min-h-[3.75rem] whitespace-pre-line text-sm leading-5 text-slate-600">{{a.message}}</p><button class="mt-4 inline-flex items-center text-sm font-bold text-primary" @click="view(a)"><Eye class="mr-2 size-4"/>Voir l’annonce</button></div></article></section>
<section v-else class="mt-8 rounded-3xl border border-dashed bg-white p-16 text-center"><Bell class="mx-auto size-12 text-slate-300"/><h2 class="mt-4 text-xl font-bold">Aucune annonce</h2><p class="mt-2 text-sm text-slate-500">Les nouvelles annonces de l’école apparaîtront ici.</p></section>
<nav v-if="announcements.links?.length>3" class="mt-8 flex flex-wrap justify-center gap-2"><Link v-for="link in announcements.links" :key="link.label" :href="link.url||'#'" class="rounded-xl border bg-white px-4 py-2 text-sm" :class="{'bg-primary text-white':link.active,'pointer-events-none opacity-40':!link.url}" v-html="link.label"/></nav>
<AnnouncementViewer :open="open" :announcement="selected" @close="open=false"/>
</main></ParentLayout></template>
