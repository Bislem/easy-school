<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({ email: '', password: '', remember: false });
const submit = () =>
    form.post(window.location.pathname, {
        onFinish: () => form.reset('password'),
    });
</script>

<template>
    <AuthLayout
        title="Administration SaaS"
        description="Accès réservé aux super administrateurs"
    >
        <Head title="Administration SaaS" />
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="email">Adresse e-mail</Label
                ><Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                /><InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Mot de passe</Label
                ><Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                /><InputError :message="form.errors.password" />
            </div>
            <label class="flex items-center gap-3 text-sm"
                ><Checkbox v-model="form.remember" /> Se souvenir de moi</label
            >
            <Button :disabled="form.processing"
                ><LoaderCircle v-if="form.processing" class="animate-spin" />Se
                connecter</Button
            >
        </form>
    </AuthLayout>
</template>
