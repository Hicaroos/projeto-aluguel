<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { PiggyBank, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LeaseDepositController from '@/actions/App/Http/Controllers/LeaseDepositController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Spinner } from '@/components/ui/spinner';
import { formatCurrency } from '@/lib/currency';
import { formatDate, todayIsoDate } from '@/lib/formatters';
import {
    paymentReferenceLabel,
    paymentRemainingAmount,
} from '@/lib/payment-labels';
import type { Lease } from '@/types';

type Deduction = { key: number; description: string; amount: string };

const props = defineProps<{
    lease: Lease | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const selectedPaymentIds = ref<number[]>([]);
const deductions = ref<Deduction[]>([]);
const settledOn = ref(todayIsoDate());
let nextDeductionKey = 0;

watch(
    () => props.lease?.id,
    () => {
        selectedPaymentIds.value =
            props.lease?.open_payments.map((payment) => payment.id) ?? [];
        deductions.value = [];
        settledOn.value = todayIsoDate();
    },
    { immediate: true },
);

function togglePayment(id: number, checked: boolean | 'indeterminate') {
    selectedPaymentIds.value =
        checked === true
            ? [...selectedPaymentIds.value, id]
            : selectedPaymentIds.value.filter(
                  (selectedId) => selectedId !== id,
              );
}

function addDeduction() {
    deductions.value.push({
        key: nextDeductionKey++,
        description: '',
        amount: '',
    });
}

function removeDeduction(key: number) {
    deductions.value = deductions.value.filter(
        (deduction) => deduction.key !== key,
    );
}

const round = (value: number) => Math.round(value * 100) / 100;

const summary = computed(() => {
    const deposit = Number(props.lease?.deposit_amount ?? 0);
    const debts = (props.lease?.open_payments ?? [])
        .filter((payment) => selectedPaymentIds.value.includes(payment.id))
        .reduce((total, payment) => total + paymentRemainingAmount(payment), 0);
    const extras = deductions.value.reduce(
        (total, deduction) => total + (Number(deduction.amount) || 0),
        0,
    );
    const owed = round(debts + extras);
    const used = round(Math.min(deposit, owed));

    return {
        deposit,
        debts: round(debts),
        extras: round(extras),
        refund: round(deposit - used),
        stillOwed: round(owed - used),
    };
});
</script>

<template>
    <Dialog :open="!!lease" @update:open="(open) => !open && emit('close')">
        <DialogScrollContent v-if="lease" class="sm:max-w-lg">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <PiggyBank class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle>Acerto da caução</DialogTitle>
                    <DialogDescription class="truncate">
                        {{ lease.tenant.name }} · caução de
                        {{ formatCurrency(lease.deposit_amount ?? 0) }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Form
                v-bind="LeaseDepositController.store.form(lease)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
            >
                <section class="grid gap-3">
                    <div>
                        <h3 class="text-sm font-medium">
                            Pendências do contrato
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            As marcadas são pagas com a caução, da mais antiga
                            para a mais nova.
                        </p>
                    </div>

                    <p
                        v-if="lease.open_payments.length === 0"
                        class="rounded-lg border border-dashed p-3 text-center text-sm text-muted-foreground"
                    >
                        Nenhuma cobrança em aberto neste contrato.
                    </p>

                    <ul v-else class="divide-y rounded-lg border">
                        <li
                            v-for="payment in lease.open_payments"
                            :key="payment.id"
                        >
                            <Label
                                :for="`settle_payment_${payment.id}`"
                                class="flex cursor-pointer items-center gap-3 p-3 font-normal"
                            >
                                <Checkbox
                                    :id="`settle_payment_${payment.id}`"
                                    :model-value="
                                        selectedPaymentIds.includes(payment.id)
                                    "
                                    @update:model-value="
                                        (checked) =>
                                            togglePayment(payment.id, checked)
                                    "
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm">
                                        {{ paymentReferenceLabel(payment) }}
                                    </span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        Vencimento
                                        {{ formatDate(payment.due_date) }}
                                    </span>
                                </span>
                                <span class="text-sm font-medium tabular-nums">
                                    {{
                                        formatCurrency(
                                            paymentRemainingAmount(payment),
                                        )
                                    }}
                                </span>
                            </Label>
                        </li>
                    </ul>
                    <input
                        v-for="id in selectedPaymentIds"
                        :key="id"
                        type="hidden"
                        name="payment_ids[]"
                        :value="id"
                    />
                    <InputError :message="errors['payment_ids.0']" />
                </section>

                <section class="grid gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-medium">
                                Descontos da vistoria de saída
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Ex.: pintura, limpeza, reparos. Viram cobranças
                                avulsas pagas com a caução.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="addDeduction"
                        >
                            <Plus class="size-4" />
                            Adicionar
                        </Button>
                    </div>

                    <div
                        v-for="(deduction, index) in deductions"
                        :key="deduction.key"
                        class="grid gap-1"
                    >
                        <div class="flex items-start gap-2">
                            <Input
                                v-model="deduction.description"
                                :name="`deductions[${index}][description]`"
                                placeholder="Descrição"
                                aria-label="Descrição do desconto"
                                class="flex-1"
                            />
                            <div class="relative w-32 shrink-0">
                                <span
                                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >R$</span
                                >
                                <Input
                                    v-model="deduction.amount"
                                    :name="`deductions[${index}][amount]`"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0,00"
                                    aria-label="Valor do desconto"
                                    class="pl-9 tabular-nums"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="shrink-0 text-muted-foreground hover:text-destructive"
                                @click="removeDeduction(deduction.key)"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">Remover desconto</span>
                            </Button>
                        </div>
                        <InputError
                            :message="
                                errors[`deductions.${index}.description`] ??
                                errors[`deductions.${index}.amount`]
                            "
                        />
                    </div>
                    <InputError :message="errors.deductions" />
                </section>

                <dl
                    class="grid gap-1.5 rounded-lg border bg-muted/40 p-4 text-sm tabular-nums"
                >
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Caução</dt>
                        <dd>{{ formatCurrency(summary.deposit) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Pendências</dt>
                        <dd>− {{ formatCurrency(summary.debts) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Descontos</dt>
                        <dd>− {{ formatCurrency(summary.extras) }}</dd>
                    </div>
                    <div
                        class="mt-1 flex justify-between gap-3 border-t pt-2 text-base font-semibold"
                    >
                        <dt>A devolver ao inquilino</dt>
                        <dd class="text-primary">
                            {{ formatCurrency(summary.refund) }}
                        </dd>
                    </div>
                    <p
                        v-if="summary.stillOwed > 0"
                        class="text-xs text-rose-600 dark:text-rose-400"
                    >
                        A caução não cobre tudo: o inquilino continua devendo
                        {{ formatCurrency(summary.stillOwed) }}.
                    </p>
                </dl>

                <div class="grid gap-2 sm:max-w-48">
                    <Label for="settled_on">Data da devolução</Label>
                    <Input
                        id="settled_on"
                        v-model="settledOn"
                        name="settled_on"
                        type="date"
                        :max="todayIsoDate()"
                    />
                    <InputError :message="errors.settled_on" />
                </div>

                <DialogFooter class="border-t pt-6">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            Fazer depois
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Confirmar acerto
                    </Button>
                </DialogFooter>
            </Form>
        </DialogScrollContent>
    </Dialog>
</template>
