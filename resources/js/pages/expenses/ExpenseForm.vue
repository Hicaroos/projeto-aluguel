<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
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
import { expenseTypeIcons, expenseTypeLabels } from '@/lib/expense-labels';
import { todayIsoDate } from '@/lib/formatters';
import type { Expense, ExpensePropertyOption, ExpenseType } from '@/types';

const props = defineProps<{
    expense?: Expense | null;
    properties: ExpensePropertyOption[];
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
