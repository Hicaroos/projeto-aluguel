<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Criar uma conta',
        description: 'Preencha os dados abaixo para criar sua conta',
    },
});

const accountType = ref<'single_owner' | 'agency'>('single_owner');
const step = ref<1 | 2>(1);
const stepOneEl = ref<HTMLDivElement | null>(null);

function goToStep2() {
    const fields = stepOneEl.value?.querySelectorAll<HTMLInputElement | HTMLSelectElement>('input, select');

    for (const field of fields ?? []) {
        if (!field.checkValidity()) {
            field.reportValidity();

            return;
        }
    }

    step.value = 2;
}

function handleError(errors: Record<string, string>) {
    if (errors.name || errors.email || errors.password || errors.password_confirmation || errors.account_name || errors.account_type) {
        step.value = 1;
    }
}
</script>

<template>

    <Head title="Cadastro" />

    <Form v-bind="store.form()" :reset-on-success="['password', 'password_confirmation']" @error="handleError"
        v-slot="{ errors, processing }" class="flex flex-col gap-6">
        <div ref="stepOneEl" class="grid gap-6" v-show="step === 1">

            <div class="grid gap-2">
                <Label for="account_type">Tipo de conta</Label>
                <Select v-model="accountType" name="account_type">
                    <SelectTrigger id="account_type" class="w-full" :tabindex="3">
                        <SelectValue placeholder="Selecione o tipo de conta" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="single_owner">Dono único</SelectItem>
                        <SelectItem value="agency">Imobiliária</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.account_type" />
            </div>

            <div class="grid gap-2">
                <Label for="account_name">{{
                    accountType === 'agency'
                        ? 'Nome da imobiliária'
                        : 'Nome da conta'
                }}</Label>
                <Input id="account_name" type="text" required :tabindex="4" name="account_name" :placeholder="accountType === 'agency'
                    ? 'Nome da imobiliária'
                    : 'Nome da sua conta'
                    " />
                <InputError :message="errors.account_name" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input id="name" type="text" required autofocus :tabindex="1" autocomplete="name" name="name"
                    placeholder="Nome completo" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input id="email" type="email" required :tabindex="2" autocomplete="email" name="email"
                    placeholder="seuemail@exemplo.com" />
                <InputError :message="errors.email" />
            </div>


            <div class="grid gap-2">
                <Label for="password">Senha</Label>
                <PasswordInput id="password" required :tabindex="5" autocomplete="new-password" name="password"
                    placeholder="Senha" :passwordrules="passwordRules" />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <PasswordInput id="password_confirmation" required :tabindex="6" autocomplete="new-password"
                    name="password_confirmation" placeholder="Confirmar senha" :passwordrules="passwordRules" />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button v-if="accountType === 'single_owner'" type="button" class="mt-2 w-full" :tabindex="7"
                @click="goToStep2">
                Próximo
            </Button>

            <Button v-else type="submit" class="mt-2 w-full" :tabindex="7" :disabled="processing"
                data-test="register-user-button">
                <Spinner v-if="processing" />
                Criar conta
            </Button>
        </div>

        <div v-if="accountType === 'single_owner'" class="grid gap-6" v-show="step === 2">
            <div class="grid gap-2">
                <Label for="owner_cpf_cnpj">CPF ou CNPJ</Label>
                <Input id="owner_cpf_cnpj" type="text" required :tabindex="8" name="owner_cpf_cnpj"
                    placeholder="000.000.000-00 ou 00.000.000/0000-00" />
                <InputError :message="errors.owner_cpf_cnpj" />
            </div>

            <div class="grid gap-2">
                <Label for="owner_phone">Telefone (opcional)</Label>
                <Input id="owner_phone" type="text" :tabindex="9" name="owner_phone" placeholder="(00) 00000-0000" />
                <InputError :message="errors.owner_phone" />
            </div>

            <div class="flex flex-col gap-3">

                <Button type="submit" class="w-full" :tabindex="11" :disabled="processing"
                    data-test="register-user-button">
                    <Spinner v-if="processing" />
                    Criar conta
                </Button>
                
                <Button type="button" variant="outline" class="w-full" :tabindex="10" @click="step = 1">
                    Voltar
                </Button>
            </div>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Já tem uma conta?
            <TextLink :href="login()" class="underline underline-offset-4" :tabindex="12">Entrar</TextLink>
        </div>
    </Form>
</template>
