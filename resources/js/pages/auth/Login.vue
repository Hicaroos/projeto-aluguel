<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { badgeTones } from '@/lib/badge-tones';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Entrar',
        description: 'Use o e-mail e a senha da sua conta.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Entrar" />

    <p
        v-if="status"
        role="status"
        :class="[
            'rounded-xl border px-4 py-3 text-sm font-medium',
            badgeTones.success,
        ]"
    >
        {{ status }}
    </p>

    <Form
        v-bind="store.form()"
        :options="{ replace: true }"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="grid gap-4"
    >
        <div class="grid gap-2">
            <Label for="email">E-mail</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                placeholder="seuemail@exemplo.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Senha</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <InputError :message="errors.password" />
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
            <Label for="remember" class="flex items-center gap-2.5 font-normal">
                <Checkbox id="remember" name="remember" />
                Lembrar de mim
            </Label>
            <TextLink v-if="canResetPassword" :href="request()" class="text-sm">
                Esqueceu a senha?
            </TextLink>
        </div>

        <Button
            type="submit"
            class="mt-2 w-full"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Entrando…' : 'Entrar' }}
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Não tem uma conta?
            <TextLink :href="register()">Cadastre-se</TextLink>
        </p>
    </Form>
</template>
