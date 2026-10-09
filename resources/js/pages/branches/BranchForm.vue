<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import BranchController from '@/actions/App/Http/Controllers/BranchController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Branch } from '@/types';

const props = defineProps<{
    branch?: Branch | null;
}>();

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.branch
        ? BranchController.update.form(props.branch)
        : BranchController.store.form(),
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
                Identificação
            </h3>

            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    name="name"
                    autofocus
                    placeholder="Ex.: Ouricuri, Centro, Filial Araripina"
                    :default-value="branch?.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="phone">
                        Telefone
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <MaskedInput
                        id="phone"
                        mask="phone"
                        name="phone"
                        type="tel"
                        placeholder="(00) 0000-0000"
                        :default-value="branch?.phone"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="document">
                        CNPJ
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <MaskedInput
                        id="document"
                        mask="document"
                        name="document"
                        placeholder="00.000.000/0000-00"
                        :default-value="branch?.document"
                    />
                    <InputError :message="errors.document" />
                </div>

                <div class="grid gap-2">
                    <Label for="creci">
                        CRECI
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="creci"
                        name="creci"
                        maxlength="20"
                        placeholder="Ex.: 1234-J"
                        :default-value="branch?.creci ?? ''"
                    />
                    <InputError :message="errors.creci" />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                Deixe o CNPJ e o CRECI em branco quando a unidade usa os mesmos
                da imobiliária.
            </p>
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Endereço
            </h3>

            <AddressFields :value="branch" :errors="errors" />
        </section>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ branch ? 'Salvar alterações' : 'Cadastrar unidade' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
