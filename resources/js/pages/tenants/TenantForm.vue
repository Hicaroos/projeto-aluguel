<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import PersonQualificationFields from '@/components/PersonQualificationFields.vue';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Tenant } from '@/types';

const props = defineProps<{
    tenant?: Tenant | null;
}>();

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.tenant
        ? TenantController.update.form(props.tenant)
        : TenantController.store.form(),
);
</script>

<template>
    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-8"
        @success="emit('success')"
    >
        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Dados pessoais
            </h3>

            <div class="grid gap-2">
                <Label for="name">Nome completo</Label>
                <Input
                    id="name"
                    name="name"
                    autofocus
                    placeholder="Ex.: Maria da Silva"
                    :default-value="tenant?.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="cpf_cnpj">CPF/CNPJ</Label>
                    <MaskedInput
                        id="cpf_cnpj"
                        mask="document"
                        name="cpf_cnpj"
                        placeholder="000.000.000-00"
                        :default-value="tenant?.cpf_cnpj"
                    />
                    <InputError :message="errors.cpf_cnpj" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Celular</Label>
                    <MaskedInput
                        id="phone"
                        mask="phone"
                        name="phone"
                        type="tel"
                        placeholder="(00) 00000-0000"
                        :default-value="tenant?.phone"
                    />
                    <InputError :message="errors.phone" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">
                    E-mail
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    >
                </Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="nome@exemplo.com"
                    :default-value="tenant?.email ?? ''"
                />
                <InputError :message="errors.email" />
            </div>
        </section>

        <section class="grid gap-4">
            <div>
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Qualificação
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Usada no contrato. Pode ser preenchida depois.
                </p>
            </div>

            <PersonQualificationFields :value="tenant" :errors="errors" />
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Endereço atual
            </h3>

            <AddressFields :value="tenant" :errors="errors" />
        </section>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ tenant ? 'Salvar alterações' : 'Cadastrar inquilino' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
