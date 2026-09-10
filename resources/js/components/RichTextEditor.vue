<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    List,
    ListOrdered,
    Quote,
    Underline,
} from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const editor = ref<HTMLElement | null>(null);

watch(
    () => props.modelValue,
    async (value) => {
        await nextTick();
        if (editor.value && editor.value.innerHTML !== value) {
            editor.value.innerHTML = value || '';
        }
    },
    { immediate: true },
);

const sync = () => emit('update:modelValue', editor.value?.innerHTML || '');
const command = (name: string, value?: string) => {
    editor.value?.focus();
    document.execCommand(name, false, value);
    sync();
};
const tools = [
    [Bold, 'Gras', 'bold'],
    [Italic, 'Italique', 'italic'],
    [Underline, 'Souligné', 'underline'],
    [Heading2, 'Grand titre', 'formatBlock', 'h2'],
    [Heading3, 'Sous-titre', 'formatBlock', 'h3'],
    [List, 'Liste', 'insertUnorderedList'],
    [ListOrdered, 'Liste numérotée', 'insertOrderedList'],
    [Quote, 'Citation', 'formatBlock', 'blockquote'],
] as const;
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border bg-white shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15"
    >
        <div class="flex flex-wrap gap-1 border-b bg-slate-50 p-2">
            <Button
                v-for="tool in tools"
                :key="tool[1]"
                type="button"
                size="icon"
                variant="ghost"
                class="size-8"
                :title="tool[1]"
                @mousedown.prevent="command(tool[2], tool[3])"
            >
                <component :is="tool[0]" class="size-4" />
            </Button>
        </div>
        <div
            ref="editor"
            contenteditable="true"
            class="rich-editor max-h-72 min-h-40 overflow-y-auto px-4 py-3 text-sm outline-none"
            :data-placeholder="
                placeholder ||
                'Présentez votre campagne, les conditions et les informations utiles…'
            "
            @input="sync"
            @blur="sync"
        />
    </div>
</template>

<style scoped>
.rich-editor:empty::before {
    color: #94a3b8;
    content: attr(data-placeholder);
    pointer-events: none;
}
.rich-editor :deep(h2) {
    margin: 0.75rem 0 0.35rem;
    font-size: 1.25rem;
    font-weight: 800;
}
.rich-editor :deep(h3) {
    margin: 0.65rem 0 0.3rem;
    font-size: 1.05rem;
    font-weight: 700;
}
.rich-editor :deep(ul) {
    margin: 0.5rem 0;
    list-style: disc;
    padding-left: 1.5rem;
}
.rich-editor :deep(ol) {
    margin: 0.5rem 0;
    list-style: decimal;
    padding-left: 1.5rem;
}
.rich-editor :deep(blockquote) {
    margin: 0.5rem 0;
    border-left: 3px solid #0f766e;
    padding-left: 0.75rem;
    color: #475569;
}
</style>
