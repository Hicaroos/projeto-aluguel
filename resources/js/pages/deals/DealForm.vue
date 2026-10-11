<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DealController from '@/actions/App/Http/Controllers/DealController';
import InputError from '@/components/InputError.vue';
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
import { formatCurrency } from '@/lib/currency';
import { propertyTypeLabels } from '@/lib/property-labels';
import { dealTypeLabels } from '@/lib/sales-labels';
import type {
    Deal,
    DealLeadOption,
    DealPropertyOption,
    DealType,
    TeamMemberOption,
} from '@/types';

const props = defineProps<{
    deal?: Deal | null;
    /** The leads to choose from, or the only lead of the deal. */
    leads: DealLeadOption[];
    properties: DealPropertyOption[];
    users: TeamMemberOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const NONE = 'none';

const formAction = computed(() =>
    props.deal
        ? DealController.update.form(props.deal)
        : DealController.store.form(),
);

const leadId = ref(
    props.deal
        ? String(props.deal.lead_id)
        : props.leads.length === 1
          ? String(props.leads[0].id)
          : '',
);

const selectedLead = computed(() =>
    props.leads.find((lead) => String(lead.id) === leadId.value),
);

const type = ref<DealType>(
    props.deal?.type ??
        (selectedLead.value?.interest === 'rent' ? 'rent' : 'sale'),
);

watch(selectedLead, (lead) => {
    if (!props.deal && lead) {
        type.value = lead.interest === 'rent' ? 'rent' : 'sale';
    }
});

const propertyId = ref(
    props.deal?.property_id ? String(props.deal.property_id) : '',
);
const value = ref(props.deal?.value ?? '');
const userId = ref(props.deal?.user_id ? String(props.deal.user_id) : NONE);

const leadOptions = computed<SearchableSelectOption[]>(() =>
    props.leads.map((lead) => ({ value: String(lead.id), label: lead.name })),
);

/** The properties offered for what the deal is about. */
const propertyOptions = computed<SearchableSelectOption[]>(() =>
    props.properties
        .filter((property) =>
            type.value === 'sale' ? property.for_sale : property.for_rent,
        )
        .map((property) => {
            const price =
                type.value === 'sale'
                    ? property.sale_price
                    : property.rent_amount;

            return {
                value: String(property.id),
                label: `${property.street}, ${property.number}${property.complement ? ` - ${property.complement}` : ''}`,
                description: [
                    propertyTypeLabels[property.type],
                    property.neighborhood,
                    price ? formatCurrency(price) : null,
                ]
                    .filter(Boolean)
                    .join(' · '),
            };
        }),
);

watch(type, () => {
    if (
        !propertyOptions.value.some(
            (option) => option.value === propertyId.value,
        )
    ) {
        propertyId.value = '';
    }
});

watch(propertyId, (id) => {
    const property = props.properties.find(
        (option) => String(option.id) === id,
    );
    const price =
        type.value === 'sale' ? property?.sale_price : property?.rent_amount;

    if (price) {
        value.value = price;
    }
});
</script>

<template>
    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @success="emit('success')"
    >
        <input type="hidden" name="type" :value="type" />
        <input
            type="hidden"
            name="user_id"
            :value="userId === NONE ? '' : userId"
        />

        <div v-if="leads.length > 1 || deal" class="grid gap-2">
            <Label for="lead_id">Interessado</Label>
            <SearchableSelect
                id="lead_id"
                v-model="leadId"
                name="lead_id"
                :options="leadOptions"
                placeholder="Busque pelo nome"
                empty-text="Nenhum interessado encontrado."
            />
            <InputError :message="errors.lead_id" />
        </div>
        <input v-else type="hidden" name="lead_id" :value="leadId" />

        <div class="grid items-start gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="type">Negociação de</Label>
                <Select v-model="type">
                    <SelectTrigger id="type" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, key) in dealTypeLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.type" />
            </div>

            <div v-if="users.length > 0" class="grid gap-2">
                <Label for="user_id">Responsável</Label>
                <Select v-model="userId">
                    <SelectTrigger id="user_id" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">
                            {{ deal ? 'Ninguém' : 'Eu mesmo' }}
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
                <InputError :message="errors.user_id" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="property_id">
                Imóvel
                <span class="font-normal text-muted-foreground"
                    >(opcional)</span
                >
            </Label>
            <SearchableSelect
                id="property_id"
                v-model="propertyId"
                name="property_id"
                :options="propertyOptions"
                placeholder="Busque pela rua ou bairro"
                :empty-text="
                    type === 'sale'
                        ? 'Nenhum imóvel à venda encontrado.'
                        : 'Nenhum imóvel para alugar encontrado.'
                "
            />
            <p class="text-xs text-muted-foreground">
                Pode ficar em branco enquanto a pessoa ainda escolhe.
            </p>
            <InputError :message="errors.property_id" />
        </div>

        <div class="grid gap-2 sm:max-w-xs">
            <Label for="value">
                {{ type === 'sale' ? 'Valor da venda' : 'Valor do aluguel' }}
            </Label>
            <div class="relative">
                <span
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                    >R$</span
                >
                <Input
                    id="value"
                    v-model="value"
                    name="value"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0,00"
                    class="pl-10 tabular-nums"
                />
            </div>
            <InputError :message="errors.value" />
        </div>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ deal ? 'Salvar alterações' : 'Criar negociação' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
