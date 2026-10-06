<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import type { SearchableSelectOption } from '@/components/SearchableSelect.vue';
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
import { expenseTypeIcons, expenseTypeLabels } from '@/lib/expense-labels';
import { formatDate, todayIsoDate } from '@/lib/formatters';
import { leaseStatusLabels } from '@/lib/lease-labels';
import type {
    Expense,
    ExpenseLeaseOption,
    ExpensePropertyOption,
    ExpenseType,
} from '@/types';

const props = defineProps<{
    expense?: Expense | null;
    properties: ExpensePropertyOption[];
    leases: ExpenseLeaseOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.expense
        ? ExpenseController.update.form(props.expense)
        : ExpenseController.store.form(),
);

const propertyId = ref(props.expense ? String(props.expense.property_id) : '');
const type = ref<ExpenseType>(props.expense?.type ?? 'condo_fee');

const chargeTenant = ref(false);
const chargeLeaseId = ref('');

const chargeableLeaseOptions = computed<SearchableSelectOption[]>(() =>
    props.leases
        .filter((lease) => String(lease.property_id) === propertyId.value)
        .map((lease) => ({
            value: String(lease.id),
            label: lease.tenant.name,
            description: `${formatDate(lease.start_date)} a ${formatDate(lease.end_date)} · ${leaseStatusLabels[lease.status]}`,
        })),
);

watch(propertyId, () => {
    chargeLeaseId.value = '';
});

const propertyOptions = computed<SearchableSelectOption[]>(() =>
    props.properties.map((property) => ({
        value: String(property.id),
        label: `${property.street}, ${property.number}${property.complement ? ` - ${property.complement}` : ''}`,
        description: `${property.neighborhood} · ${property.city}/${property.state}`,
    })),
);
</script>

<template>
    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        :options="{ preserveScroll: true }"
        @success="emit('success')"
    >
        <div class="grid gap-2">
            <Label for="property_id">Imóvel</Label>
            <SearchableSelect
                id="property_id"
                v-model="propertyId"
                name="property_id"
                :options="propertyOptions"
                placeholder="Digite a rua, bairro ou cidade…"
                empty-text="Nenhum imóvel encontrado."
            />
            <InputError :message="errors.property_id" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="type">Tipo</Label>
                <Select v-model="type" name="type">
                    <SelectTrigger id="type" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, key) in expenseTypeLabels"
                            :key="key"
                            :value="key"
                        >
                            <component
                                :is="expenseTypeIcons[key as ExpenseType]"
                                class="size-4 text-muted-foreground"
                            />
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="amount">Valor</Label>
                <div class="relative">
                    <span
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                        >R$</span
                    >
                    <Input
                        id="amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0,00"
                        class="pl-10 tabular-nums"
                        :default-value="expense?.amount"
                    />
                </div>
                <InputError :message="errors.amount" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="description">
                Descrição
                <span
                    v-if="type !== 'other'"
                    class="font-normal text-muted-foreground"
                    >(opcional)</span
                >
            </Label>
            <Input
                id="description"
                name="description"
                placeholder="Ex.: IPTU 2026 – parcela 3/10, troca do chuveiro…"
                :default-value="expense?.description ?? ''"
            />
            <InputError :message="errors.description" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="due_date">Vencimento</Label>
                <Input
                    id="due_date"
                    name="due_date"
                    type="date"
                    :default-value="expense?.due_date ?? todayIsoDate()"
                />
                <InputError :message="errors.due_date" />
            </div>

            <div class="grid gap-2">
                <Label for="payment_date">
                    Data de pagamento
                    <span class="font-normal text-muted-foreground"
                        >(se já foi paga)</span
                    >
                </Label>
                <Input
                    id="payment_date"
                    name="payment_date"
                    type="date"
                    :max="todayIsoDate()"
                    :default-value="expense?.payment_date ?? ''"
                />
                <InputError :message="errors.payment_date" />
            </div>
        </div>

        <section v-if="!expense" class="grid gap-3 rounded-lg border p-4">
            <Label for="charge_tenant" class="flex items-start gap-3">
                <Checkbox
                    id="charge_tenant"
                    v-model="chargeTenant"
                    class="mt-0.5"
                />
                <span class="grid gap-0.5">
                    <span>Cobrar do inquilino</span>
                    <span class="text-sm font-normal text-muted-foreground">
                        Gera uma cobrança avulsa com o mesmo valor, para o
                        inquilino (ou ex-inquilino) reembolsar.
                    </span>
                </span>
            </Label>
            <input
                type="hidden"
                name="charge_tenant"
                :value="chargeTenant ? '1' : '0'"
            />

            <div v-if="chargeTenant" class="grid gap-2">
                <Label for="charge_lease_id">Contrato do inquilino</Label>
                <SearchableSelect
                    id="charge_lease_id"
                    v-model="chargeLeaseId"
                    name="charge_lease_id"
                    :options="chargeableLeaseOptions"
                    placeholder="Digite o nome do inquilino…"
                    :empty-text="
                        propertyId
                            ? 'Nenhum contrato neste imóvel.'
                            : 'Escolha o imóvel primeiro.'
                    "
                />
                <InputError :message="errors.charge_lease_id" />
            </div>
        </section>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ expense ? 'Salvar alterações' : 'Cadastrar despesa' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
