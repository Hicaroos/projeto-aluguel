<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import OwnerController from '@/actions/App/Http/Controllers/Settings/OwnerController';
import AddressFields from '@/components/AddressFields.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PersonQualificationFields from '@/components/PersonQualificationFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/owner';
import type { Owner } from '@/types';

defineProps<{
    owner: Owner;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dados do proprietário',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dados do proprietário" />

    <h1 class="sr-only">Dados do proprietário</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Dados do proprietário"
            description="Informações do locador usadas nos contratos. CPF e telefone ficam no seu perfil."
        />

        <Form
            v-bind="OwnerController.update.form()"
            class="space-y-8"
            v-slot="{ errors, processing }"
        >
            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Qualificação
                </h3>

                <div class="grid gap-2">
                    <Label for="name">Nome completo</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="name"
                        placeholder="Nome completo"
                        :default-value="owner.name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <PersonQualificationFields :value="owner" :errors="errors" />
            </section>

            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Endereço
                </h3>

                <AddressFields :value="owner" :errors="errors" />
            </section>

            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Recebimento
                </h3>

                <div class="grid gap-2">
                    <Label for="pix_key">Chave PIX</Label>
                    <Input
                        id="pix_key"
                        name="pix_key"
                        placeholder="CPF, e-mail, telefone ou chave aleatória"
                        :default-value="owner.pix_key ?? ''"
                    />
                    <InputError :message="errors.pix_key" />
                </div>
            </section>

            <Button :disabled="processing">Salvar</Button>
        </Form>
    </div>
</template>
