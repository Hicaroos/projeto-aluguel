<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useZipCodeLookup } from '@/composables/useZipCodeLookup';
import { maskZipCode } from '@/lib/formatters';
import type { PersonQualification } from '@/types';

type AddressField =
    | 'zip_code'
    | 'street'
    | 'number'
    | 'complement'
    | 'neighborhood'
    | 'city'
    | 'state';

const props = defineProps<{
    /** Initial values, e.g. the record being edited. */
    value?: Partial<Pick<PersonQualification, AddressField>> | null;
    errors: Record<string, string | undefined>;
    /** Nests the inputs under this key, e.g. "guarantor" posts guarantor[street]. */
    namePrefix?: string;
}>();

const inputName = (field: AddressField): string =>
    props.namePrefix ? `${props.namePrefix}[${field}]` : field;
const inputId = (field: AddressField): string =>
    props.namePrefix ? `${props.namePrefix}_${field}` : field;
const errorFor = (field: AddressField): string | undefined =>
    props.errors[props.namePrefix ? `${props.namePrefix}.${field}` : field];

const zipCode = ref(maskZipCode(props.value?.zip_code ?? ''));
const street = ref(props.value?.street ?? '');
const neighborhood = ref(props.value?.neighborhood ?? '');
const city = ref(props.value?.city ?? '');
const state = ref(props.value?.state ?? '');

const {
    isLoading: isLookingUpZipCode,
    error: zipCodeLookupError,
    lookup: lookupZipCode,
} = useZipCodeLookup();

watch(zipCode, async (value) => {
    const masked = maskZipCode(value);

    if (masked !== value) {
        zipCode.value = masked;

        return;
    }

    const address = await lookupZipCode(masked);

    if (!address || zipCode.value !== masked) {
        return;
    }

    street.value = address.street || street.value;
    neighborhood.value = address.neighborhood || neighborhood.value;
    city.value = address.city;
    state.value = address.state;

    await nextTick();
    document
        .getElementById(inputId(address.street ? 'number' : 'street'))
        ?.focus();
});
</script>

<template>
    <div class="grid items-start gap-4 sm:grid-cols-6">
        <div class="grid gap-2 sm:col-span-2">
            <Label :for="inputId('zip_code')">CEP</Label>
            <div class="relative">
                <Input
                    :id="inputId('zip_code')"
                    v-model="zipCode"
                    :name="inputName('zip_code')"
                    inputmode="numeric"
                    autocomplete="postal-code"
                    placeholder="00000-000"
                    maxlength="9"
                    class="pr-9"
                />
                <Spinner
                    v-if="isLookingUpZipCode"
                    class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>
            <p
                v-if="zipCodeLookupError"
                class="text-sm text-amber-600 dark:text-amber-400"
            >
                {{ zipCodeLookupError }}
            </p>
            <InputError :message="errorFor('zip_code')" />
        </div>

        <div class="grid gap-2 sm:col-span-4">
            <Label :for="inputId('street')">Rua</Label>
            <Input
                :id="inputId('street')"
                v-model="street"
                :name="inputName('street')"
            />
            <InputError :message="errorFor('street')" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label :for="inputId('number')">Número</Label>
            <Input
                :id="inputId('number')"
                :name="inputName('number')"
                :default-value="value?.number ?? ''"
            />
            <InputError :message="errorFor('number')" />
        </div>

        <div class="grid gap-2 sm:col-span-4">
            <Label :for="inputId('complement')">Complemento</Label>
            <Input
                :id="inputId('complement')"
                :name="inputName('complement')"
                placeholder="Apto, bloco, sala…"
                :default-value="value?.complement ?? ''"
            />
            <InputError :message="errorFor('complement')" />
        </div>

        <div class="grid gap-2 sm:col-span-3">
            <Label :for="inputId('neighborhood')">Bairro</Label>
            <Input
                :id="inputId('neighborhood')"
                v-model="neighborhood"
                :name="inputName('neighborhood')"
            />
            <InputError :message="errorFor('neighborhood')" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label :for="inputId('city')">Cidade</Label>
            <Input
                :id="inputId('city')"
                v-model="city"
                :name="inputName('city')"
            />
            <InputError :message="errorFor('city')" />
        </div>

        <div class="grid gap-2 sm:col-span-1">
            <Label :for="inputId('state')">UF</Label>
            <Input
                :id="inputId('state')"
                v-model="state"
                :name="inputName('state')"
                maxlength="2"
                placeholder="SP"
            />
            <InputError :message="errorFor('state')" />
        </div>
    </div>
</template>
