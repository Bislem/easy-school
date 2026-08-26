<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appDialogState, resolveAppDialog } from '@/composables/useAppDialog';
import { AlertTriangle, CheckCircle2, HelpCircle, Info } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const inputElement = ref<InstanceType<typeof Input> | null>(null);
const icon = computed(() =>
    appDialogState.tone === 'danger' || appDialogState.tone === 'warning'
        ? AlertTriangle
        : appDialogState.tone === 'success'
          ? CheckCircle2
          : appDialogState.showCancel
            ? HelpCircle
            : Info,
);
const iconTone = computed(() =>
    appDialogState.tone === 'danger'
        ? 'bg-red-100 text-red-600'
        : appDialogState.tone === 'warning'
          ? 'bg-amber-100 text-amber-600'
          : appDialogState.tone === 'success'
            ? 'bg-emerald-100 text-emerald-600'
            : 'bg-primary/10 text-primary',
);
watch(
    () => appDialogState.open,
    (open) => {
        if (open && appDialogState.input)
            nextTick(() => inputElement.value?.$el?.focus?.());
    },
);
function confirm() {
    if (appDialogState.inputRequired && !appDialogState.inputValue.trim())
        return;
    resolveAppDialog(true);
}
</script>

<template>
    <Dialog
        :open="appDialogState.open"
        @update:open="
            (open) => {
                if (!open) resolveAppDialog(false);
            }
        "
    >
        <DialogContent class="w-[calc(100vw-1.5rem)] max-w-md rounded-2xl p-0">
            <div class="p-5 sm:p-6">
                <DialogHeader class="text-left">
                    <div class="mb-2 flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-xl"
                            :class="iconTone"
                            ><component :is="icon" class="size-5"
                        /></span>
                        <div>
                            <DialogTitle>{{ appDialogState.title }}</DialogTitle
                            ><DialogDescription
                                class="mt-2 leading-6 whitespace-pre-line"
                                >{{ appDialogState.message }}</DialogDescription
                            >
                        </div>
                    </div>
                </DialogHeader>
                <div v-if="appDialogState.input" class="mt-4">
                    <Label v-if="appDialogState.inputLabel">{{
                        appDialogState.inputLabel
                    }}</Label
                    ><Input
                        ref="inputElement"
                        v-model="appDialogState.inputValue"
                        :type="appDialogState.inputType"
                        class="mt-1"
                        :placeholder="appDialogState.inputPlaceholder"
                        @keyup.enter="confirm"
                    />
                </div>
                <DialogFooter class="mt-6 flex-col-reverse gap-2 sm:flex-row">
                    <Button
                        v-if="appDialogState.showCancel"
                        type="button"
                        variant="outline"
                        @click="resolveAppDialog(false)"
                        >{{ appDialogState.cancelText }}</Button
                    >
                    <Button
                        type="button"
                        :variant="
                            appDialogState.tone === 'danger'
                                ? 'destructive'
                                : 'default'
                        "
                        :disabled="
                            appDialogState.inputRequired &&
                            !appDialogState.inputValue.trim()
                        "
                        @click="confirm"
                        >{{ appDialogState.confirmText }}</Button
                    >
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
