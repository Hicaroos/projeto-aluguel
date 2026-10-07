<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import LeaseController from '@/actions/App/Http/Controllers/LeaseController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import PersonQualificationFields from '@/components/PersonQualificationFields.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import type { SearchableSelectOption } from '@/components/SearchableSelect.vue';
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
import { todayIsoDate } from '@/lib/formatters';
import {
    adjustmentIndexLabels,
    guaranteeTypeLabels,
    leasePurposeLabels,
} from '@/lib/lease-labels';
import type {
    AdjustmentIndex,
    GuaranteeType,
    Lease,
    LeasePurpose,
    LeasePropertyOption,
    LeaseTenantOption,
} from '@/types';

const props = defineProps<{
    lease?: Lease | null;
    properties: LeasePropertyOption[];
    tenants: LeaseTenantOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.lease
        ? LeaseController.update.form(props.lease)
        : LeaseController.store.form(),
);

const selectableProperties = computed(() =>
    props.properties.filter(
        (property) =>
            property.status === 'available' ||
            property.id === props.lease?.property_id,
    ),
);

const propertyOptions = computed<SearchableSelectOption[]>(() =>
    selectableProperties.value.map((property) => ({
        value: String(property.id),
        label: `${property.street}, ${property.number}${property.complement ? ` - ${property.complement}` : ''}`,
        description: `${property.neighborhood} · ${property.city}/${property.state}`,
    })),
);

const tenantOptions = computed<SearchableSelectOption[]>(() =>
    props.tenants.map((tenant) => ({
        value: String(tenant.id),
        label: tenant.name,
    })),
);

const propertyId = ref(props.lease ? String(props.lease.property_id) : '');
const tenantId = ref(props.lease ? String(props.lease.tenant_id) : '');
const amount = ref(props.lease?.amount ?? '');

/** An active lease's rent being edited directly, instead of through an adjustment. */
const isChangingActiveRent = computed(
    () =>
        props.lease?.status === 'active' &&
        amount.value !== '' &&
        Number(amount.value) !== Number(props.lease.amount),
);
const guaranteeType = ref<GuaranteeType>(props.lease?.guarantee_type ?? 'none');
const purpose = ref<LeasePurpose>(props.lease?.purpose ?? 'residential');
const adjustmentIndex = ref<AdjustmentIndex>(
    props.lease?.adjustment_index ?? 'igpm',
);

watch(propertyId, (selectedId) => {
    const property = props.properties.find(
        (option) => String(option.id) === selectedId,
    );

    if (property) {
        amount.value = property.rent_amount;
    }
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
                Partes
            </h3>

            <div class="grid gap-2">
                <Label for="property_id">Imóvel</Label>
                <SearchableSelect
                    id="property_id"
                    v-model="propertyId"
                    name="property_id"
                    :options="propertyOptions"
                    placeholder="Digite a rua, bairro ou cidade…"
                    empty-text="Nenhum imóvel disponível encontrado."
                />
                <p
                    v-if="selectableProperties.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhum imóvel disponível. Apenas imóveis com situação
                    “Disponível” podem receber um contrato.
                </p>
                <InputError :message="errors.property_id" />
            </div>

            <div class="grid gap-2">
                <Label for="tenant_id">Inquilino</Label>
                <SearchableSelect
                    id="tenant_id"
                    v-model="tenantId"
                    name="tenant_id"
                    :options="tenantOptions"
                    placeholder="Digite o nome do inquilino…"
                    empty-text="Nenhum inquilino encontrado."
                />
                <p
                    v-if="tenants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhum inquilino cadastrado ainda.
                </p>
                <InputError :message="errors.tenant_id" />
            </div>
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Vigência e valores
            </h3>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="start_date">Início</Label>
                    <Input
                        id="start_date"
                        name="start_date"
                        type="date"
                        :default-value="lease?.start_date ?? todayIsoDate()"
                    />
                    <InputError :message="errors.start_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="end_date">Término</Label>
                    <Input
                        id="end_date"
                        name="end_date"
                        type="date"
                        :default-value="lease?.end_date"
                    />
                    <InputError :message="errors.end_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Valor do aluguel</Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >R$</span
                        >
                        <Input
                            id="amount"
                            v-model="amount"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0,00"
                            class="pl-10 tabular-nums"
                        />
                    </div>
                    <InputError :message="errors.amount" />
                    <p
                        v-if="isChangingActiveRent"
                        class="text-xs text-attention-foreground"
                    >
                        Para reajustar o aluguel, use "Aplicar reajuste" nos
                        detalhes do contrato: assim fica registrado no
                        histórico. Altere aqui apenas para corrigir o valor.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="due_day">Dia do vencimento</Label>
                    <Input
                        id="due_day"
                        name="due_day"
                        type="number"
                        min="1"
                        max="31"
                        placeholder="Ex.: 10"
                        :default-value="lease?.due_day"
                    />
                    <InputError :message="errors.due_day" />
                </div>
            </div>
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Termos do contrato
            </h3>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="purpose">Finalidade</Label>
                    <Select v-model="purpose" name="purpose">
                        <SelectTrigger id="purpose" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in leasePurposeLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.purpose" />
                </div>

                <div class="grid gap-2">
                    <Label for="adjustment_index">Índice de reajuste</Label>
                    <Select v-model="adjustmentIndex" name="adjustment_index">
                        <SelectTrigger id="adjustment_index" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in adjustmentIndexLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.adjustment_index" />
                </div>
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="late_fee_percent">Multa por atraso</Label>
                    <div class="relative">
                        <Input
                            id="late_fee_percent"
                            name="late_fee_percent"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="pr-8 tabular-nums"
                            :default-value="lease?.late_fee_percent ?? '10'"
                        />
                        <span
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >%</span
                        >
                    </div>
                    <InputError :message="errors.late_fee_percent" />
                </div>

                <div class="grid gap-2">
                    <Label for="monthly_interest_percent">Juros ao mês</Label>
                    <div class="relative">
                        <Input
                            id="monthly_interest_percent"
                            name="monthly_interest_percent"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="pr-8 tabular-nums"
                            :default-value="
                                lease?.monthly_interest_percent ?? '1'
                            "
                        />
                        <span
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >%</span
                        >
                    </div>
                    <InputError :message="errors.monthly_interest_percent" />
                </div>

                <div class="grid gap-2">
                    <Label for="termination_fee_months">Multa rescisória</Label>
                    <div class="relative">
                        <Input
                            id="termination_fee_months"
                            name="termination_fee_months"
                            type="number"
                            step="1"
                            min="0"
                            max="12"
                            class="pr-20 tabular-nums"
                            :default-value="lease?.termination_fee_months ?? 3"
                        />
                        <span
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >aluguéis</span
                        >
                    </div>
                    <InputError :message="errors.termination_fee_months" />
                </div>
            </div>
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Garantia
            </h3>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="guarantee_type">Tipo de garantia</Label>
                    <Select v-model="guaranteeType" name="guarantee_type">
                        <SelectTrigger id="guarantee_type" class="w-full">
                            <SelectValue placeholder="Selecione a garantia" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in guaranteeTypeLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.guarantee_type" />
                </div>

                <div v-if="guaranteeType === 'deposit'" class="grid gap-2">
                    <Label for="deposit_amount">Valor da caução</Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >R$</span
                        >
                        <Input
                            id="deposit_amount"
                            name="deposit_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0,00"
                            class="pl-10 tabular-nums"
                            :default-value="lease?.deposit_amount ?? ''"
                        />
                    </div>
                    <InputError :message="errors.deposit_amount" />
                </div>

                <template v-if="guaranteeType === 'surety_bond'">
                    <div class="grid gap-2">
                        <Label for="surety_insurer">Seguradora</Label>
                        <Input
                            id="surety_insurer"
                            name="surety_insurer"
                            placeholder="Ex.: Porto Seguro"
                            :default-value="lease?.surety_insurer ?? ''"
                        />
                        <InputError :message="errors.surety_insurer" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="surety_policy_number">
                            Número da apólice
                        </Label>
                        <Input
                            id="surety_policy_number"
                            name="surety_policy_number"
                            :default-value="lease?.surety_policy_number ?? ''"
                        />
                        <InputError :message="errors.surety_policy_number" />
                    </div>
                </template>
            </div>

            <div
                v-if="guaranteeType === 'guarantor'"
                class="grid gap-4 rounded-lg border bg-muted/30 p-4"
            >
                <p class="text-sm font-medium">Dados do fiador</p>

                <div class="grid gap-2">
                    <Label for="guarantor_name">Nome completo</Label>
                    <Input
                        id="guarantor_name"
                        name="guarantor[name]"
                        :default-value="lease?.guarantor?.name ?? ''"
                    />
                    <InputError :message="errors['guarantor.name']" />
                </div>

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="guarantor_cpf_cnpj">CPF/CNPJ</Label>
                        <MaskedInput
                            id="guarantor_cpf_cnpj"
                            mask="document"
                            name="guarantor[cpf_cnpj]"
                            placeholder="000.000.000-00"
                            :default-value="lease?.guarantor?.cpf_cnpj"
                        />
                        <InputError :message="errors['guarantor.cpf_cnpj']" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="guarantor_phone">Telefone</Label>
                        <MaskedInput
                            id="guarantor_phone"
                            mask="phone"
                            name="guarantor[phone]"
                            type="tel"
                            placeholder="(00) 00000-0000"
                            :default-value="lease?.guarantor?.phone"
                        />
                        <InputError :message="errors['guarantor.phone']" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="guarantor_email">E-mail</Label>
                    <Input
                        id="guarantor_email"
                        name="guarantor[email]"
                        type="email"
                        placeholder="nome@exemplo.com"
                        :default-value="lease?.guarantor?.email ?? ''"
                    />
                    <InputError :message="errors['guarantor.email']" />
                </div>

                <PersonQualificationFields
                    name-prefix="guarantor"
                    :value="lease?.guarantor"
                    :errors="errors"
                />

                <AddressFields
                    name-prefix="guarantor"
                    :value="lease?.guarantor"
                    :errors="errors"
                />

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="guarantor_spouse_name"
                            >Nome do cônjuge</Label
                        >
                        <Input
                            id="guarantor_spouse_name"
                            name="guarantor[spouse_name]"
                            :default-value="lease?.guarantor?.spouse_name ?? ''"
                        />
                        <InputError
                            :message="errors['guarantor.spouse_name']"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="guarantor_spouse_cpf">CPF do cônjuge</Label>
                        <MaskedInput
                            id="guarantor_spouse_cpf"
                            mask="cpf"
                            name="guarantor[spouse_cpf]"
                            placeholder="000.000.000-00"
                            :default-value="lease?.guarantor?.spouse_cpf"
                        />
                        <InputError :message="errors['guarantor.spouse_cpf']" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="guarantor_property_registration">
                        Matrícula do imóvel em garantia
                    </Label>
                    <Input
                        id="guarantor_property_registration"
                        name="guarantor[property_registration]"
                        placeholder="Ex.: Matrícula 12.345 do 1º Registro de Imóveis"
                        :default-value="
                            lease?.guarantor?.property_registration ?? ''
                        "
                    />
                    <InputError
                        :message="errors['guarantor.property_registration']"
                    />
                </div>
            </div>
        </section>

        <section class="grid gap-2">
            <Label for="notes">
                Observações
                <span class="font-normal text-muted-foreground"
                    >(opcional)</span
                >
            </Label>
            <textarea
                id="notes"
                name="notes"
                rows="3"
                placeholder="Anotações sobre o contrato, combinados com o inquilino…"
                class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                :value="lease?.notes ?? ''"
            />
            <InputError :message="errors.notes" />
        </section>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ lease ? 'Salvar alterações' : 'Cadastrar contrato' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
