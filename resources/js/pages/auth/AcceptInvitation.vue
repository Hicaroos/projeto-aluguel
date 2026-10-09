<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { accept } from '@/routes/invitation';

defineOptions({
    layout: {
        title: 'Entrar para a equipe',
        description: 'Crie sua senha para acessar o sistema da imobiliária',
    },
});

defineProps<{
    invitation: {
        token: string;
        name: string;
        email: string;
        agency: string | null;
        role: string;
    } | null;
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Convite" />

    <div v-if="!invitation" class="space-y-6 text-center">
        <p class="text-sm text-muted-foreground">
            Este link de convite expirou ou já foi usado. Peça ao administrador
            da imobiliária um novo link.
        </p>
        <TextLink :href="login()" class="text-sm">Ir para o login</TextLink>
    </div>

    <Form
        v-else
        v-bind="accept.form(invitation.token)"
        :options="{ replace: true }"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <p class="rounded-lg border bg-muted/40 p-3 text-sm">
                Olá, <span class="font-medium">{{ invitation.name }}</span
                >! Você foi convidado para a equipe da
                <span class="font-medium">{{ invitation.agency }}</span> como
                <span class="font-medium">{{ invitation.role }}</span
                >.
            </p>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    autocomplete="email"
                    :model-value="invitation.email"
                    readonly
                />
            </div>

            <div class="grid gap-2">
                <Label for="password">Senha</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    placeholder="Senha"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="Confirmar senha"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button type="submit" class="mt-2 w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Criar senha e entrar
            </Button>
        </div>
    </Form>
</template>
