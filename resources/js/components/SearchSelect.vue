<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Check, ChevronsUpDown, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: any[];
        placeholder?: string;
        searchPlaceholder?: string;
        emptyLabel?: string;
        allowEmpty?: boolean;
    }>(),
    {
        placeholder: 'Sélectionner',
        searchPlaceholder: 'Rechercher…',
        emptyLabel: 'Aucun résultat',
        allowEmpty: false,
    },
);
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const root = ref<HTMLElement | null>(null),
    open = ref(false),
    search = ref('');
const label = (option: any) =>
    option?.search_label ??
    option?.label ??
    option?.name ??
    option?.title ??
    '';
const selected = computed(() =>
    props.options.find(
        (option) => String(option.id) === String(props.modelValue ?? ''),
    ),
);
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.options.filter(
        (option) =>
            !q ||
            `${label(option)} ${option.search_text ?? ''}`
                .toLowerCase()
                .includes(q),
    );
});
function choose(value: string) {
    emit('update:modelValue', value);
    open.value = false;
    search.value = '';
}
function outside(event: MouseEvent) {
    if (root.value && !root.value.contains(event.target as Node))
        open.value = false;
}
onMounted(() => document.addEventListener('mousedown', outside));
onBeforeUnmount(() => document.removeEventListener('mousedown', outside));
</script>
<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex h-9 w-full items-center justify-between rounded-md border bg-background px-3 text-left text-sm"
            @click="open = !open"
        >
            <span :class="selected ? '' : 'text-muted-foreground'">{{
                selected ? label(selected) : placeholder
            }}</span
            ><ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
        </button>
        <div
            v-if="open"
            class="absolute z-40 mt-1 w-full min-w-64 rounded-md border bg-popover p-2 shadow-lg"
        >
            <div class="relative mb-2">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                /><Input
                    v-model="search"
                    autofocus
                    class="pl-9"
                    :placeholder="searchPlaceholder"
                />
            </div>
            <div class="max-h-60 overflow-y-auto">
                <button
                    v-if="allowEmpty"
                    type="button"
                    class="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                    @click="choose('')"
                >
                    <Check
                        class="size-4"
                        :class="
                            String(modelValue ?? '') === ''
                                ? 'opacity-100'
                                : 'opacity-0'
                        "
                    />{{ placeholder }}</button
                ><button
                    v-for="option in filtered"
                    :key="option.id"
                    type="button"
                    class="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                    @click="choose(String(option.id))"
                >
                    <Check
                        class="size-4 shrink-0"
                        :class="
                            String(modelValue) === String(option.id)
                                ? 'opacity-100'
                                : 'opacity-0'
                        "
                    /><span>{{ label(option) }}</span>
                </button>
                <p
                    v-if="!filtered.length"
                    class="p-3 text-center text-sm text-muted-foreground"
                >
                    {{ emptyLabel }}
                </p>
            </div>
        </div>
    </div>
</template>
