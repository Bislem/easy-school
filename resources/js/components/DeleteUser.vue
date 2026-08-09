<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = ref<InstanceType<typeof Input> | null>(null);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Supprimer le compte"
description="Supprimez votre compte ainsi que toutes les ressources qui y sont associées."  />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Avertissement</p>
                <p class="text-sm">
                    Veuillez procéder avec prudence, cela ne peut être annulé.
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive" data-test="delete-user-button">Supprimer le compte</Button>
                </DialogTrigger>
                <DialogContent>
                    <Form v-bind="ProfileController.destroy.form()" reset-on-success
                        @error="() => passwordInput?.$el?.focus()" :options="{
                            preserveScroll: true,
                        }" class="space-y-6" v-slot="{ errors, processing, reset, clearErrors }">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>
                                Êtes-vous sûr de vouloir supprimer votre compte ?
                            </DialogTitle>

                            <DialogDescription>
                                Une fois votre compte supprimé, toutes les ressources et toutes les données qui y sont
                                associées seront définitivement supprimées. Veuillez saisir votre mot de passe pour
                                confirmer que vous souhaitez supprimer définitivement votre compte.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">Mot de passe</Label>
                            <Input id="password" type="password" name="password" ref="passwordInput"
                                placeholder="Mot de passe" />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                ">
                                    Annuler
                                </Button>
                            </DialogClose>

                            <Button type="submit" variant="destructive" :disabled="processing"
                                data-test="confirm-delete-user-button">
                                Supprimer le compte
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
