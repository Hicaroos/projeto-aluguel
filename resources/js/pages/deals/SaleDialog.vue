<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { BadgeCheck, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SaleController from '@/actions/App/Http/Controllers/SaleController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import PersonQualificationFields from '@/components/PersonQualificationFields.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import type { SearchableSelectOption } from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { formatCurrency } from '@/lib/currency';
import { todayIsoDate } from '@/lib/formatters';
import { propertyTypeLabels } from '@/lib/property-labels';
import { paymentPlanLabels } from '@/lib/sales-labels';
import type { Deal, DealPropertyOption, Sale, TeamMemberOption } from '@/types';

const props = withDefaults(
    defineProps<{
        /** The deal to close as a sale. */
        deal?: Deal | null;
        /** The sale to edit, e.g. to complete the buyer details for the contract. */
        sale?: Sale | null;
        /** Whether to register a sale straight for a property, without the funnel. */
        direct?: boolean;
        /** The properties for sale to choose from in a direct sale. */
        properties?: DealPropertyOption[];
        /** The property a direct sale starts with. */
        propertyId?: number | null;
        users: TeamMemberOption[];
        /** The commission the agency usually charges; null for single owners. */
        saleCommissionPercent: string | null;
    }>(),
    {
        deal: null,
        sale: null,
        direct: false,
        properties: () => [],
        propertyId: null,
    },
);

const emit = defineEmits<{
    close: [];
}>();

const NONE = 'none';

const isOpen = computed(() => !!props.deal || !!props.sale || props.direct);
const isDirect = computed(() => props.direct && !props.deal && !props.sale);
const isAgency = computed(() => props.saleCommissionPercent !== null);

const formAction = computed(() =>
    props.sale
        ? SaleController.update.form(props.sale)
        : props.deal
          ? SaleController.store.form(props.deal)
          : props.direct
            ? SaleController.storeDirect.form()
            : null,
);

const price = ref('');
const commissionPercent = ref('');
const paymentPlan = ref<string>(NONE);
const userId = ref<string>(NONE);
const directPropertyId = ref('');

const propertyOptions = computed<SearchableSelectOption[]>(() =>
    props.properties.map((property) => ({
        value: String(property.id),
        label: `${property.street}, ${property.number}${property.complement ? ` - ${property.complement}` : ''}`,
        description: [
            propertyTypeLabels[property.type],
            property.neighborhood,
            property.sale_price ? formatCurrency(property.sale_price) : null,
        ]
            .filter(Boolean)
            .join(' · '),
    })),
);

const directProperty = computed(() =>
    props.properties.find(
        (property) => String(property.id) === directPropertyId.value,
    ),
);

/** The property being sold, once known. */
const soldProperty = computed(() =>
    isDirect.value ? directProperty.value : props.deal?.property,
);

watch(directPropertyId, () => {
    if (isDirect.value) {
        price.value = directProperty.value?.sale_price ?? '';
    }
});

watch(
    () => [props.deal, props.sale, props.direct] as const,
    ([deal, sale, direct]) => {
        if (sale) {
            price.value = sale.price;
            commissionPercent.value = sale.commission_percent ?? '';
            paymentPlan.value = sale.payment_plan ?? NONE;
            userId.value = sale.user_id ? String(sale.user_id) : NONE;

            return;
        }

        if (!deal && direct) {
            directPropertyId.value = props.propertyId
                ? String(props.propertyId)
                : '';
            price.value = directProperty.value?.sale_price ?? '';
            commissionPercent.value = props.saleCommissionPercent ?? '';
            paymentPlan.value = NONE;
            userId.value = NONE;

            return;
        }

        if (!deal) {
            return;
        }

        const accepted = deal.proposals.find(
            (proposal) => proposal.status === 'accepted',
        );

        price.value = accepted?.amount ?? deal.value ?? '';
        commissionPercent.value = props.saleCommissionPercent ?? '';
        paymentPlan.value =
            accepted?.payment_plan ?? deal.lead.payment_plan ?? NONE;
        userId.value = deal.user_id ? String(deal.user_id) : NONE;
    },
    { immediate: true },
);

/** The buyer details the form starts with. */
const buyer = computed(() => {
    const sale = props.sale;

    return {
        name: sale?.buyer_name ?? props.deal?.lead.name ?? '',
        document: sale?.buyer_document ?? null,
        phone: sale?.buyer_phone ?? props.deal?.lead.phone ?? null,
        email: sale?.buyer_email ?? props.deal?.lead.email ?? '',
        qualification: {
            rg: sale?.buyer_rg ?? null,
            nationality: sale?.buyer_nationality ?? null,
            marital_status: sale?.buyer_marital_status ?? null,
            profession: sale?.buyer_profession ?? null,
        },
        address: {
            zip_code: sale?.buyer_zip_code ?? null,
            street: sale?.buyer_street ?? null,
            number: sale?.buyer_number ?? null,
            complement: sale?.buyer_complement ?? null,
            neighborhood: sale?.buyer_neighborhood ?? null,
            city: sale?.buyer_city ?? null,
            state: sale?.buyer_state ?? null,
        },
    };
});

const propertyLine = computed(() => {
    const property = props.sale?.property ?? soldProperty.value;

    return property ? `${property.street}, ${property.number}` : null;
});

/** What can happen to the lease of a rented property when it is sold. */
const rentalOutcomes = [
    {
        value: 'terminate',
        title: 'Encerrar o contrato',
        description:
            'O inquilino sai. O contrato é rescindido, as cobranças futuras são canceladas e o imóvel sai da carteira como vendido.',
    },
    {
        value: 'keep',
        title: 'O contrato continua',
        description:
            'O inquilino fica e o imóvel continua na carteira como alugado. Se o comprador for continuar com vocês, cadastre-o em Proprietários e troque o proprietário no cadastro do imóvel.',
    },
];

const commissionAmount = computed(() => {
    const value = (Number(price.value) * Number(commissionPercent.value)) / 100;

    return Number.isFinite(value) ? value : 0;
});

const textareaClass =
    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Dialog :open="isOpen" @update:open="(open) => !open && emit('close')">
        <DialogScrollContent v-if="isOpen && formAction" class="sm:max-w-2xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <component
                        :is="sale ? Pencil : BadgeCheck"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>
                        {{ sale ? 'Editar venda' : 'Registrar venda' }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="isDirect">
                            Para vendas fechadas sem passar pelo funil.
                        </template>
                        <template v-else-if="propertyLine">
                            {{ propertyLine }} para {{ buyer.name }}.
                        </template>
                        <template v-else>
                            Escolha o imóvel da negociação antes de registrar a
                            venda.
                        </template>
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Form
                v-bind="formAction"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                @success="emit('close')"
            >
                <InputError :message="errors.deal" />

                <div v-if="isDirect" class="grid gap-2">
                    <Label for="property_id">Imóvel</Label>
                    <SearchableSelect
                        id="property_id"
                        v-model="directPropertyId"
                        name="property_id"
                        :options="propertyOptions"
                        placeholder="Busque pela rua ou bairro"
                        empty-text="Nenhum imóvel à venda encontrado."
                    />
                    <InputError :message="errors.property_id" />
                </div>

                <input
                    type="hidden"
                    name="payment_plan"
                    :value="paymentPlan === NONE ? '' : paymentPlan"
                />
                <input
                    v-if="isAgency"
                    type="hidden"
                    name="user_id"
                    :value="userId === NONE ? '' : userId"
                />

                <section class="grid gap-4">
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Comprador
                    </h3>
                    <div class="grid gap-2">
                        <Label for="buyer_name">Nome</Label>
                        <Input
                            id="buyer_name"
                            name="buyer_name"
                            :default-value="buyer.name"
                        />
                        <InputError :message="errors.buyer_name" />
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="buyer_document">CPF/CNPJ</Label>
                            <MaskedInput
                                id="buyer_document"
                                mask="document"
                                name="buyer_document"
                                placeholder="000.000.000-00"
                                :default-value="buyer.document"
                            />
                            <InputError :message="errors.buyer_document" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="buyer_phone">Celular</Label>
                            <MaskedInput
                                id="buyer_phone"
                                mask="phone"
                                name="buyer_phone"
                                type="tel"
                                :default-value="buyer.phone"
                            />
                            <InputError :message="errors.buyer_phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="buyer_email">E-mail</Label>
                            <Input
                                id="buyer_email"
                                name="buyer_email"
                                type="email"
                                :default-value="buyer.email"
                            />
                            <InputError :message="errors.buyer_email" />
                        </div>
                    </div>
                </section>

                <section class="grid gap-4">
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Venda
                    </h3>
                    <div class="grid items-start gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="price">Valor da venda</Label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >R$</span
                                >
                                <Input
                                    id="price"
                                    v-model="price"
                                    name="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="pl-10 tabular-nums"
                                />
                            </div>
                            <InputError :message="errors.price" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sold_on">Data</Label>
                            <Input
                                id="sold_on"
                                name="sold_on"
                                type="date"
                                :max="todayIsoDate()"
                                :default-value="sale?.sold_on ?? todayIsoDate()"
                            />
                            <InputError :message="errors.sold_on" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payment_plan">Pagamento</Label>
                            <Select v-model="paymentPlan">
                                <SelectTrigger id="payment_plan" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">
                                        Não informado
                                    </SelectItem>
                                    <SelectItem
                                        v-for="(
                                            label, key
                                        ) in paymentPlanLabels"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </section>

                <section
                    v-if="!sale && soldProperty?.status === 'rented'"
                    class="grid gap-3"
                >
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Imóvel alugado
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        O que acontece com o contrato de aluguel?
                    </p>
                    <label
                        v-for="option in rentalOutcomes"
                        :key="option.value"
                        class="flex cursor-pointer items-start gap-3 rounded-md border p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            type="radio"
                            name="rental_outcome"
                            :value="option.value"
                            class="mt-1 accent-primary"
                        />
                        <span class="grid gap-1">
                            <span class="text-sm font-medium">
                                {{ option.title }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ option.description }}
                            </span>
                        </span>
                    </label>
                    <InputError :message="errors.rental_outcome" />
                </section>

                <section v-if="isAgency" class="grid gap-4">
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Imobiliária
                    </h3>
                    <div class="grid items-start gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="commission_percent">Comissão</Label>
                            <div class="relative">
                                <Input
                                    id="commission_percent"
                                    v-model="commissionPercent"
                                    name="commission_percent"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="pr-8 tabular-nums"
                                />
                                <span
                                    class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >%</span
                                >
                            </div>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ formatCurrency(commissionAmount) }}
                            </p>
                            <InputError :message="errors.commission_percent" />
                        </div>
                        <div
                            v-if="users.length > 0"
                            class="grid gap-2 sm:col-span-2"
                        >
                            <Label for="user_id">Corretor que vendeu</Label>
                            <Select v-model="userId">
                                <SelectTrigger id="user_id" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">
                                        Não informado
                                    </SelectItem>
                                    <SelectItem
                                        v-for="user in users"
                                        :key="user.id"
                                        :value="String(user.id)"
                                    >
                                        {{ user.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </section>

                <section class="grid gap-4">
                    <div>
                        <h3
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Para o contrato e o recibo
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Opcional. O que ficar em branco sai como uma linha
                            para completar à mão, e pode ser preenchido depois
                            em Editar venda.
                        </p>
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="down_payment">Sinal</Label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >R$</span
                                >
                                <Input
                                    id="down_payment"
                                    name="down_payment"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="pl-10 tabular-nums"
                                    :default-value="sale?.down_payment ?? ''"
                                />
                            </div>
                            <InputError :message="errors.down_payment" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="deed_deadline">
                                Prazo para a escritura
                            </Label>
                            <Input
                                id="deed_deadline"
                                name="deed_deadline"
                                type="date"
                                :default-value="sale?.deed_deadline ?? ''"
                            />
                            <InputError :message="errors.deed_deadline" />
                        </div>
                    </div>
                    <p class="text-sm font-medium">Qualificação do comprador</p>
                    <PersonQualificationFields
                        :value="buyer.qualification"
                        :errors="errors"
                        name-prefix="buyer"
                    />
                    <AddressFields
                        :value="buyer.address"
                        :errors="errors"
                        name-prefix="buyer"
                    />
                </section>

                <div class="grid gap-2">
                    <Label for="notes">Observações</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="2"
                        placeholder="Ex.: escritura marcada para o dia 20, ITBI pago pelo comprador…"
                        :class="textareaClass"
                        :value="sale?.notes ?? ''"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <DialogFooter class="border-t pt-6">
                    <DialogClose as-child>
                        <Button type="button" variant="outline"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || (!sale && !soldProperty)"
                    >
                        <Spinner v-if="processing" />
                        {{ sale ? 'Salvar alterações' : 'Registrar venda' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogScrollContent>
    </Dialog>
</template>
