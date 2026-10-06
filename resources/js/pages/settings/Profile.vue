<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Account } from '@/types';

const props = defineProps<{
    account?: Account | null;
    owner?: { phone: string | null; cpf_cnpj: string | null } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configurações de perfil',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

/** Single owner accounts edit their name in the owner details, where it is used in contracts. */
const isSingleOwner = computed(() => props.account?.type === 'single_owner');
</script>

<template>
    <Head title="Configurações de perfil" />

    <h1 class="sr-only">Configurações de perfil</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Perfil"
            description="Atualize suas informações pessoais e da sua conta"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div v-if="account" class="grid gap-2">
                <Label for="account_name">Nome da conta</Label>
                <Input
                    id="account_name"
                    class="mt-1 block w-full"
                    name="account_name"
                    :default-value="account.name"
                    required
                    placeholder="Nome da conta"
                />
                <InputError class="mt-2" :message="errors.account_name" />
            </div>

            <div v-if="!isSingleOwner" class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Nome completo"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="E-mail"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="owner" class="grid gap-2">
                <Label for="owner_phone">Telefone</Label>
                <Input
                    id="owner_phone"
                    class="mt-1 block w-full"
                    name="owner_phone"
                    :default-value="owner.phone ?? ''"
                    placeholder="(00) 00000-0000"
                />
                <InputError class="mt-2" :message="errors.owner_phone" />
            </div>

            <div v-if="owner" class="grid gap-2">
                <Label for="owner_cpf_cnpj">CPF/CNPJ</Label>
                <Input
                    id="owner_cpf_cnpj"
                    class="mt-1 block w-full"
                    :default-value="owner.cpf_cnpj ?? ''"
                    disabled
                />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Seu endereço de e-mail não foi verificado.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Clique aqui para reenviar o e-mail de verificação.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Um novo link de verificação foi enviado para o seu endereço
                    de e-mail.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Salvar</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
