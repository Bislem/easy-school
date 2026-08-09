<script setup lang="ts">
import PasswordResetLinkController from '@/actions/App/Http/Controllers/Auth/PasswordResetLinkController';
import HomeLayout from '@/layouts/HomeLayout.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <HomeLayout>
        <Head title="Mot de passe oublié" />

        <div class="flex min-h-[80vh] items-center justify-center px-4 sm:px-6 lg:px-8">
    
            <div class="space-y-6 w-1/3">
                <Form
                    v-bind="PasswordResetLinkController.store.form()"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="email">Adresse e-mail</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="off"
                            autofocus
                            placeholder="email@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>
    
                    <div class="my-6 flex items-center justify-start">
                        <Button
                            class="w-full"
                            :disabled="processing"
                            data-test="email-password-reset-link-button"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="h-4 w-4 animate-spin"
                            />
                            Envoyer le lien de réinitialisation
                        </Button>
                    </div>
                </Form>
    
                <div class="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Ou retourner à la</span>
                    <TextLink :href="login()">connexion</TextLink>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
