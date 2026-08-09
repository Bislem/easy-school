<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref, watch, computed } from 'vue';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { AlertCircle } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { create } from '@/routes/admin/cars';
import { index } from '@/routes/admin/cars';
import { destroy } from '@/routes/admin/cars';

const props = defineProps<{
  cars: {
    data: Array<{
      id: number
      make: string
      model: string
      year: number
      license_plate: string
      price_per_day: string | number
      status: string
      status_label?: string
      status_color?: string
      image_url?: string
    }>
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: { 
    search?: string
    status?: string 
  }
  statuses: Record<string, {
    label: string
    count: number
    color: string
  }>
  currency: { symbol: string; code: string }
}>()


// Generate status colors based on the colors from the backend
const statusColors = computed(() => {
  const colors: Record<string, { bg: string; text: string; dot: string }> = {};
  
  for (const [status, data] of Object.entries(props.statuses || {})) {
    // Convert hex to RGB for the background with opacity
    const hex = data.color.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    
    colors[status] = {
      bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
      text: `text-[${data.color}]`,
      dot: data.color,
    };
  }
  
  return colors;
});

const getStatusColor = (status: string) => {
  return statusColors.value[status] || { 
    bg: 'rgba(107, 114, 128, 0.1)', 
    text: 'text-gray-500', 
    dot: '#6B7280' 
  };
}

const search = ref(props.filters?.search || '')

const statusFilter = ref(props.filters?.status || 'all')

function doSearch() {
  router.get(index(), { 
    search: search.value,
    status: statusFilter.value === 'all' ? null : statusFilter.value
  }, {
    preserveState: true,
    replace: true,
  })
}

watch(search, (v, ov) => {
  if (v === '' && ov !== '') doSearch()
})

const showDeleteDialog = ref(false);
const carToDelete = ref<number | null>(null);

const openDeleteDialog = (id: number) => {
  carToDelete.value = id;
  showDeleteDialog.value = true;
};

const destroyCar = () => {
  if (!carToDelete.value) return;
  
  router.delete(destroy(carToDelete.value).url, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false;
      carToDelete.value = null;
    },
  });
};
</script>

<template>
    <Head title="Véhicules" />
<AdminLayout>
    <!-- Main -->
    <main class="flex-1 p-8 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Véhicules</h1>
            <Link :href="create()">
                <Button>
                    + Nouveau véhicule
                </Button>
            </Link>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2">
                <Input
                    v-model="search"
                    placeholder="Rechercher par marque, modèle, immatriculation..."
                    class="max-w-md"
                    @keyup.enter="doSearch"
                />
                <Button @click="doSearch">Rechercher</Button>
            </div>

            <!-- Status Filter -->
            <div class="flex flex-wrap items-center gap-2">
                <label class="inline-flex items-center">
                    <input
                        type="radio"
                        class="hidden"
                        v-model="statusFilter"
                        value="all"
                        @change="doSearch"
                    >
                    <span
                        class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors"
                        :class="{
                            'bg-primary text-primary-foreground': statusFilter === 'all',
                            'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== 'all'
                        }"
                    >
                        Tous ({{ Object.values(statuses).reduce((acc, curr) => acc + curr.count, 0) }})
                    </span>
                </label>

                <template v-for="(status, key) in statuses" :key="key">
                    <label class="inline-flex items-center">
                        <input
                            type="radio"
                            class="hidden"
                            v-model="statusFilter"
                            :value="key"
                            @change="doSearch"
                        >
                        <span
                            class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors flex items-center gap-1.5"
                            :class="{
                                'bg-primary text-primary-foreground': statusFilter === key,
                                'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== key
                            }"
                        >
                            <span
                                class="w-2 h-2 rounded-full"
                                :style="{ backgroundColor: status.color }"
                            ></span>
                            {{ status.label }} ({{ status.count }})
                        </span>
                    </label>
                </template>
            </div>
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Véhicule</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix / Jour</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="car in props.cars.data" :key="car.id">
                        <td class="px-4 py-3">
                            <img :src="car.image_url" alt="Véhicule" class="h-12 w-16 object-cover rounded" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ car.year }} {{ car.make }} {{ car.model }}</div>
                        </td>
                        <td class="px-4 py-3">{{ car.license_plate }}</td>
                        <td class="px-4 py-3">{{ currency.symbol }}{{ Number(car.price_per_day).toFixed(2) }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                                :style="{
                                    backgroundColor: getStatusColor(car.status).bg,
                                    color: getStatusColor(car.status).text
                                }"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :style="{ backgroundColor: getStatusColor(car.status).dot }"
                                />
                                {{ car.status_label || car.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <Link :href="`/admin/cars/${car.id}/edit`">
                                <Button variant="outline" size="sm">Modifier</Button>
                            </Link>
                            <Button variant="destructive" size="sm" @click="openDeleteDialog(car.id)">Supprimer</Button>
                        </td>
                    </tr>
                    <tr v-if="props.cars.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                            Aucun véhicule trouvé.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="props.cars.links?.length" class="flex gap-2">
            <Link
                v-for="(link, i) in props.cars.links"
                :key="i"
                :href="link.url || ''"
                :class="[
                    'px-3 py-1 rounded text-sm',
                    link.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700',
                    !link.url && 'pointer-events-none opacity-50'
                ]"
            >
                <span v-html="link.label" />
            </Link>
        </nav>
    </main>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="showDeleteDialog">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <AlertCircle class="h-5 w-5 text-destructive" />
                    Supprimer le véhicule
                </DialogTitle>
                <DialogDescription>
                    Êtes-vous sûr de vouloir supprimer ce véhicule ? Cette action est irréversible.
                </DialogDescription>
            </DialogHeader>

            <Alert variant="destructive" class="mt-4">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    Cette opération supprimera définitivement le véhicule ainsi que toutes les données qui lui sont associées.
                </AlertDescription>
            </Alert>

            <DialogFooter class="mt-4">
                <DialogClose as-child>
                    <Button variant="outline">Annuler</Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    @click="destroyCar"
                    :disabled="!carToDelete"
                >
                    Supprimer le véhicule
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</AdminLayout>
</template>
