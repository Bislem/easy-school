<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import locations from '@/data/algeria-locations.json';
import { computed, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        wilayaError?: string;
        communeError?: string;
        required?: boolean;
        compact?: boolean;
    }>(),
    { required: false, compact: false },
);

const wilaya = defineModel<string>('wilaya', { required: true });
const commune = defineModel<string>('commune', { required: true });
const communes = computed(
    () => locations.find((item) => item.name === wilaya.value)?.communes ?? [],
);

watch(wilaya, (value, oldValue) => {
    if (
        oldValue &&
        value !== oldValue &&
        !communes.value.some((item) => item.name === commune.value)
    ) {
        commune.value = '';
    }
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-2 block text-xs font-bold" for="wilaya"
                >Wilaya
                <span v-if="required" class="text-red-500">*</span></label
            >
            <select
                id="wilaya"
                v-model="wilaya"
                name="wilaya"
                :required="required"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 text-sm transition outline-none focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10"
                :class="compact ? 'h-10' : 'h-12'"
            >
                <option value="">Sélectionnez la wilaya</option>
                <option
                    v-for="item in locations"
                    :key="item.code"
                    :value="item.name"
                >
                    {{ String(item.code).padStart(2, '0') }} — {{ item.name }}
                </option>
            </select>
            <InputError :message="wilayaError" class="mt-1" />
        </div>
        <div>
            <label class="mb-2 block text-xs font-bold" for="commune"
                >Commune
                <span v-if="required" class="text-red-500">*</span></label
            >
            <select
                id="commune"
                v-model="commune"
                name="commune"
                :required="required"
                :disabled="!wilaya"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 text-sm transition outline-none focus:border-[#13bba8] focus:ring-3 focus:ring-[#13bba8]/10 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
                :class="compact ? 'h-10' : 'h-12'"
            >
                <option value="">
                    {{
                        wilaya
                            ? 'Sélectionnez la commune'
                            : 'Choisissez d’abord une wilaya'
                    }}
                </option>
                <option
                    v-for="item in communes"
                    :key="item.name"
                    :value="item.name"
                >
                    {{ item.name }}
                </option>
            </select>
            <InputError :message="communeError" class="mt-1" />
        </div>
    </div>
</template>
