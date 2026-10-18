<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { index as ownersIndex } from '@/routes/owners';
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
    Object.entries(propertyStatusLabels).filter(
        ([key]) => key !== 'rented' && key !== 'sold',
    ),
) as Record<Exclude<PropertyStatus, 'rented' | 'sold'>, string>;

/** Rented and sold properties follow their lease or sale. */
const lockedStatus = computed(() =>
    props.property?.status === 'rented' || props.property?.status === 'sold'
        ? props.property.status
        : null,
);

const ownerId = ref(props.property ? String(props.property.owner_id) : '');

/** Other people who own part of the property, each with their share. */
const coOwners = ref(
    (props.property?.owners ?? [])
        .filter((owner) => owner.id !== props.property?.owner_id)
        .map((owner) => ({
            ownerId: String(owner.id),
            share: String(Number(owner.pivot.share_percent)),
        })),
);

const mainOwnerShare = computed(() => {
    const coOwnersShare = coOwners.value.reduce(
        (total, coOwner) => total + (Number(coOwner.share) || 0),
        0,
    );

    return Math.round((100 - coOwnersShare) * 100) / 100;
});

function addCoOwner() {
    coOwners.value.push({ ownerId: '', share: '' });
}

function removeCoOwner(index: number) {
    coOwners.value.splice(index, 1);
}

/** The owners a co-owner row may pick: anyone but the main owner and the other rows. */
function coOwnerOptions(index: number) {
    const taken = new Set([
        ownerId.value,
        ...coOwners.value
            .filter((_, other) => other !== index)
            .map((coOwner) => coOwner.ownerId),
    ]);

    return props.owners.filter((owner) => !taken.has(String(owner.id)));
}

const forRent = ref(props.property?.for_rent ?? true);
const forSale = ref(props.property?.for_sale ?? false);

const isPublished = ref(Boolean(props.property?.published_at));
const isFeatured = ref(props.property?.is_featured ?? false);

const roomFields = [
    { name: 'bedrooms', label: 'Quartos' },
    { name: 'suites', label: 'Suítes' },
    { name: 'bathrooms', label: 'Banheiros' },
    { name: 'parking_spaces', label: 'Vagas' },
] as const;

const areaFields = [
    { name: 'built_area', label: 'Área construída', unit: 'm²' },
    { name: 'land_area', label: 'Área do terreno', unit: 'm²' },
    { name: 'land_front', label: 'Frente', unit: 'm' },
    { name: 'land_depth', label: 'Fundo', unit: 'm' },
] as const;

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

                <div v-if="lockedStatus" class="grid gap-2">
                    <Label>Situação</Label>
                    <div
                        class="flex h-9 items-center gap-2 rounded-md border bg-muted/40 px-3 text-sm"
                    >
                        <span
                            class="size-2 rounded-full"
                            :class="propertyStatusDotClasses[lockedStatus]"
                        />
                        {{ propertyStatusLabels[lockedStatus] }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{
                            lockedStatus === 'rented'
                                ? 'Controlada pelo contrato ativo deste imóvel.'
                                : 'Este imóvel foi vendido.'
                        }}
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
                <Select v-model="ownerId" name="owner_id">
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
                <p
                    v-if="owners.length === 0"
                    class="text-xs text-muted-foreground"
                >
                    Nenhum proprietário cadastrado.
                    <Link
                        :href="ownersIndex()"
                        class="font-medium text-primary hover:underline"
                        >Cadastre o proprietário</Link
                    >
                    antes do imóvel.
                </p>
                <InputError :message="errors.owner_id" />
            </div>

            <div
                v-if="accountType === 'agency' && owners.length > 1"
                class="grid gap-3"
            >
                <div v-if="coOwners.length > 0" class="grid gap-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-medium">Coproprietários</p>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            Proprietário principal: {{ mainOwnerShare }}%
                        </p>
                    </div>
                    <div
                        v-for="(coOwner, index) in coOwners"
                        :key="index"
                        class="grid gap-2"
                    >
                        <div class="flex items-start gap-2">
                            <Select
                                v-model="coOwner.ownerId"
                                :name="`co_owners[${index}][owner_id]`"
                            >
                                <SelectTrigger
                                    :id="`co_owner_${index}`"
                                    class="w-full min-w-0 flex-1"
                                    :aria-label="`Coproprietário ${index + 1}`"
                                >
                                    <SelectValue
                                        placeholder="Selecione o coproprietário"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="owner in coOwnerOptions(index)"
                                        :key="owner.id"
                                        :value="String(owner.id)"
                                    >
                                        {{ owner.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <div class="relative w-28 shrink-0">
                                <Input
                                    v-model="coOwner.share"
                                    :name="`co_owners[${index}][share_percent]`"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="0"
                                    class="pr-8 tabular-nums"
                                    :aria-label="`Participação do coproprietário ${index + 1}`"
                                />
                                <span
                                    class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >%</span
                                >
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remover coproprietário ${index + 1}`"
                                @click="removeCoOwner(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <InputError
                            :message="
                                errors[`co_owners.${index}.owner_id`] ??
                                errors[`co_owners.${index}.share_percent`]
                            "
                        />
                    </div>
                </div>
                <InputError :message="errors.co_owners" />
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addCoOwner"
                    >
                        <Plus class="size-4" />
                        Adicionar coproprietário
                    </Button>
                </div>
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
                        <MaskedInput
                            id="zip_code"
                            v-model="zipCode"
                            mask="zip"
                            name="zip_code"
                            autocomplete="postal-code"
                            placeholder="00000-000"
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
                Finalidade e valores
            </h3>

            <input type="hidden" name="for_rent" :value="forRent ? 1 : 0" />
            <input type="hidden" name="for_sale" :value="forSale ? 1 : 0" />

            <div class="flex flex-wrap gap-3">
                <Label
                    class="flex items-center gap-3 rounded-md border p-3 font-normal"
                >
                    <Checkbox v-model="forRent" />
                    Para alugar
                </Label>
                <Label
                    class="flex items-center gap-3 rounded-md border p-3 font-normal"
                >
                    <Checkbox v-model="forSale" />
                    À venda
                </Label>
            </div>
            <InputError :message="errors.for_rent" />

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div v-if="forRent" class="grid gap-2">
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
                            :default-value="property?.rent_amount ?? ''"
                        />
                    </div>
                    <InputError :message="errors.rent_amount" />
                </div>

                <div v-if="forSale" class="grid gap-2">
                    <Label for="sale_price">Preço de venda</Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >R$</span
                        >
                        <Input
                            id="sale_price"
                            name="sale_price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0,00"
                            class="pl-10 tabular-nums"
                            :default-value="property?.sale_price ?? ''"
                        />
                    </div>
                    <InputError :message="errors.sale_price" />
                </div>
            </div>
        </section>

        <section class="grid gap-4">
            <div>
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Características
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Opcional. Ajuda a apresentar o imóvel a quem quer comprar ou
                    alugar.
                </p>
            </div>

            <div class="grid grid-cols-2 items-start gap-4 sm:grid-cols-4">
                <div
                    v-for="field in roomFields"
                    :key="field.name"
                    class="grid gap-2"
                >
                    <Label :for="field.name">{{ field.label }}</Label>
                    <Input
                        :id="field.name"
                        :name="field.name"
                        type="number"
                        min="0"
                        max="99"
                        inputmode="numeric"
                        class="tabular-nums"
                        :default-value="property?.[field.name] ?? ''"
                    />
                    <InputError :message="errors[field.name]" />
                </div>
            </div>

            <div class="grid grid-cols-2 items-start gap-4 sm:grid-cols-4">
                <div
                    v-for="field in areaFields"
                    :key="field.name"
                    class="grid gap-2"
                >
                    <Label :for="field.name">{{ field.label }}</Label>
                    <div class="relative">
                        <Input
                            :id="field.name"
                            :name="field.name"
                            type="number"
                            step="0.01"
                            min="0"
                            class="pr-10 tabular-nums"
                            :default-value="property?.[field.name] ?? ''"
                        />
                        <span
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >{{ field.unit }}</span
                        >
                    </div>
                    <InputError :message="errors[field.name]" />
                </div>
            </div>

            <div class="grid gap-2 sm:max-w-sm">
                <Label for="registration_number">
                    Matrícula
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    >
                </Label>
                <Input
                    id="registration_number"
                    name="registration_number"
                    placeholder="Ex.: 12.345 do CRI de Ouricuri"
                    :default-value="property?.registration_number ?? ''"
                />
                <p class="text-xs text-muted-foreground">
                    Aparece no contrato de compra e venda.
                </p>
                <InputError :message="errors.registration_number" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Descrição</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="5000"
                    placeholder="Ex.: casa térrea com quintal amplo, cozinha planejada, perto do centro…"
                    class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    :value="property?.description ?? ''"
                />
                <InputError :message="errors.description" />
            </div>
        </section>

        <section class="grid gap-4">
            <div>
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Vitrine
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    O site com os imóveis para mandar aos clientes. Mostra as
                    fotos, o preço, as características e a descrição; do
                    endereço, só o bairro e a cidade.
                </p>
            </div>

            <input
                type="hidden"
                name="is_published"
                :value="isPublished ? 1 : 0"
            />
            <input
                type="hidden"
                name="is_featured"
                :value="isPublished && isFeatured ? 1 : 0"
            />

            <div class="flex flex-wrap gap-3">
                <Label
                    class="flex items-center gap-3 rounded-md border p-3 font-normal"
                >
                    <Checkbox v-model="isPublished" />
                    Publicar na vitrine
                </Label>
                <Label
                    v-if="isPublished"
                    class="flex items-center gap-3 rounded-md border p-3 font-normal"
                >
                    <Checkbox v-model="isFeatured" />
                    Destacar na página inicial
                </Label>
            </div>
            <p v-if="isPublished" class="text-xs text-muted-foreground">
                Sai da vitrine sozinho quando for alugado ou vendido.
            </p>
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
