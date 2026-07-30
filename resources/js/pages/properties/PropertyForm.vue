<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import InputError from '@/components/InputError.vue';
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
import {
    propertyStatusLabels,
    propertyTypeLabels,
} from '@/lib/property-labels';
import type { Property, PropertyOwnerOption } from '@/types';

const props = defineProps<{
    property?: Property | null;
    accountType: 'single_owner' | 'agency';
    owners: PropertyOwnerOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.property
        ? PropertyController.update.form(props.property)
        : PropertyController.store.form(),
);
</script>

<template>
    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @success="emit('success')"
    >
        <div class="grid gap-2">
            <Label for="type">Tipo</Label>
            <Select name="type" :default-value="property?.type ?? 'house'">
                <SelectTrigger id="type" class="w-full">
                    <SelectValue placeholder="Selecione o tipo" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="(label, key) in propertyTypeLabels"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.type" />
        </div>

        <div v-if="accountType === 'agency'" class="grid gap-2">
            <Label for="owner_id">Proprietário</Label>
            <Select
                name="owner_id"
                :default-value="
                    property ? String(property.owner_id) : undefined
                "
            >
                <SelectTrigger id="owner_id" class="w-full">
                    <SelectValue placeholder="Selecione o proprietário" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="owner in owners"
                        :key="owner.id"
                        :value="String(owner.id)"
                    >
                        {{ owner.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.owner_id" />
        </div>

        <div class="grid gap-2">
            <Label for="zip_code">CEP</Label>
            <Input
                id="zip_code"
                name="zip_code"
                placeholder="00000-000"
                :default-value="property?.zip_code"
            />
            <InputError :message="errors.zip_code" />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2 grid gap-2">
                <Label for="street">Rua</Label>
                <Input
                    id="street"
                    name="street"
                    :default-value="property?.street"
                />
                <InputError :message="errors.street" />
            </div>
            <div class="grid gap-2">
                <Label for="number">Número</Label>
                <Input
                    id="number"
                    name="number"
                    :default-value="property?.number"
                />
                <InputError :message="errors.number" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="complement">Complemento (opcional)</Label>
            <Input
                id="complement"
                name="complement"
                :default-value="property?.complement ?? ''"
            />
            <InputError :message="errors.complement" />
        </div>

        <div class="grid gap-2">
            <Label for="neighborhood">Bairro</Label>
            <Input
                id="neighborhood"
                name="neighborhood"
                :default-value="property?.neighborhood"
            />
            <InputError :message="errors.neighborhood" />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2 grid gap-2">
                <Label for="city">Cidade</Label>
                <Input id="city" name="city" :default-value="property?.city" />
                <InputError :message="errors.city" />
            </div>
            <div class="grid gap-2">
                <Label for="state">UF</Label>
                <Input
                    id="state"
                    name="state"
                    maxlength="2"
                    :default-value="property?.state"
                />
                <InputError :message="errors.state" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="rent_amount">Valor do aluguel</Label>
            <Input
                id="rent_amount"
                name="rent_amount"
                type="number"
                step="0.01"
                min="0"
                :default-value="property?.rent_amount"
            />
            <InputError :message="errors.rent_amount" />
        </div>

        <div class="grid gap-2">
            <Label for="status">Situação</Label>
            <Select
                name="status"
                :default-value="property?.status ?? 'available'"
            >
                <SelectTrigger id="status" class="w-full">
                    <SelectValue placeholder="Selecione a situação" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="(label, key) in propertyStatusLabels"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.status" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            {{ property ? 'Salvar alterações' : 'Cadastrar imóvel' }}
        </Button>
    </Form>
</template>
