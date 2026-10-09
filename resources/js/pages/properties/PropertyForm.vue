<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
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
import { useZipCodeLookup } from '@/composables/useZipCodeLookup';
import { maskZipCode } from '@/lib/formatters';
import {
    propertyStatusDotClasses,
    propertyStatusLabels,
    propertyTypeIcons,
    propertyTypeLabels,
} from '@/lib/property-labels';
import type {
    BranchOption,
    Property,
    PropertyOwnerOption,
    PropertyStatus,
    PropertyType,
} from '@/types';

const props = defineProps<{
    property?: Property | null;
    accountType: 'single_owner' | 'agency';
    owners: PropertyOwnerOption[];
    branches: BranchOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.property
        ? PropertyController.update.form(props.property)
        : PropertyController.store.form(),
);

const page = usePage();

/** Active branches, plus the inactive one the property may already be in. */
const selectableBranches = computed(() =>
    props.branches.filter(
        (branch) => branch.is_active || branch.id === props.property?.branch_id,
    ),
);

const defaultBranchId = computed(() => {
    const branchId =
        props.property?.branch_id ??
        page.props.branchSelector?.selectedId ??
        (selectableBranches.value.length === 1
            ? selectableBranches.value[0].id
            : null);

    return branchId ? String(branchId) : undefined;
});

const selectableStatusLabels = Object.fromEntries(
    Object.entries(propertyStatusLabels).filter(([key]) => key !== 'rented'),
) as Record<Exclude<PropertyStatus, 'rented'>, string>;

const zipCode = ref(maskZipCode(props.property?.zip_code ?? ''));
const street = ref(props.property?.street ?? '');
const neighborhood = ref(props.property?.neighborhood ?? '');
const city = ref(props.property?.city ?? '');
const state = ref(props.property?.state ?? '');

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
    document.getElementById(address.street ? 'number' : 'street')?.focus();
});
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
                Informações gerais
            </h3>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="type">Tipo</Label>
                    <Select
                        name="type"
                        :default-value="property?.type ?? 'house'"
                    >
                        <SelectTrigger id="type" class="w-full">
                            <SelectValue placeholder="Selecione o tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in propertyTypeLabels"
                                :key="key"
                                :value="key"
                            >
                                <component
                                    :is="propertyTypeIcons[key as PropertyType]"
                                    class="size-4 text-muted-foreground"
                                />
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.type" />
                </div>

                <div v-if="property?.status === 'rented'" class="grid gap-2">
                    <Label>Situação</Label>
                    <div
                        class="flex h-9 items-center gap-2 rounded-md border bg-muted/40 px-3 text-sm"
                    >
                        <span
                            class="size-2 rounded-full"
                            :class="propertyStatusDotClasses.rented"
                        />
                        {{ propertyStatusLabels.rented }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Controlada pelo contrato ativo deste imóvel.
                    </p>
                </div>

                <div v-else class="grid gap-2">
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
                                v-for="(label, key) in selectableStatusLabels"
                                :key="key"
                                :value="key"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        propertyStatusDotClasses[
                                            key as PropertyStatus
                                        ]
                                    "
                                />
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.status" />
                </div>
            </div>

            <div v-if="accountType === 'agency'" class="grid gap-2">
                <Label for="branch_id">Unidade</Label>
                <Select name="branch_id" :default-value="defaultBranchId">
                    <SelectTrigger id="branch_id" class="w-full">
                        <SelectValue placeholder="Selecione a unidade" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="branch in selectableBranches"
                            :key="branch.id"
                            :value="String(branch.id)"
                        >
                            {{ branch.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.branch_id" />
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
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Endereço
            </h3>

            <div class="grid items-start gap-4 sm:grid-cols-6">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="zip_code">CEP</Label>
                    <div class="relative">
                        <Input
                            id="zip_code"
                            v-model="zipCode"
                            name="zip_code"
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
                        class="text-sm text-attention-foreground"
                    >
                        {{ zipCodeLookupError }}
                    </p>
                    <InputError :message="errors.zip_code" />
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <Label for="street">Rua</Label>
                    <Input id="street" v-model="street" name="street" />
                    <InputError :message="errors.street" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="number">Número</Label>
                    <Input
                        id="number"
                        name="number"
                        :default-value="property?.number"
                    />
                    <InputError :message="errors.number" />
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <Label for="complement">
                        Complemento
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="complement"
                        name="complement"
                        placeholder="Apto, bloco, sala…"
                        :default-value="property?.complement ?? ''"
                    />
                    <InputError :message="errors.complement" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <Label for="neighborhood">Bairro</Label>
                    <Input
                        id="neighborhood"
                        v-model="neighborhood"
                        name="neighborhood"
                    />
                    <InputError :message="errors.neighborhood" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="city">Cidade</Label>
                    <Input id="city" v-model="city" name="city" />
                    <InputError :message="errors.city" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <Label for="state">UF</Label>
                    <Input
                        id="state"
                        v-model="state"
                        name="state"
                        maxlength="2"
                        placeholder="SP"
                    />
                    <InputError :message="errors.state" />
                </div>
            </div>
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Valores
            </h3>

            <div class="grid gap-2 sm:max-w-xs">
                <Label for="rent_amount">Valor do aluguel</Label>
                <div class="relative">
                    <span
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                        >R$</span
                    >
                    <Input
                        id="rent_amount"
                        name="rent_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0,00"
                        class="pl-10 tabular-nums"
                        :default-value="property?.rent_amount"
                    />
                </div>
                <InputError :message="errors.rent_amount" />
            </div>
        </section>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ property ? 'Salvar alterações' : 'Cadastrar imóvel' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
