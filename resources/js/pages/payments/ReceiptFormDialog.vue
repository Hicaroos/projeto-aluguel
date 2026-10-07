<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { HandCoins, TimerReset } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ReceiptController from '@/actions/App/Http/Controllers/ReceiptController';
import InputError from '@/components/InputError.vue';
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
import {
    calculateLateCharges,
    manualPaymentMethodLabels,
    paymentReferenceLabel,
    paymentRemainingAmount,
} from '@/lib/payment-labels';
import type { Payment } from '@/types';

const props = defineProps<{
    payment: Payment | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const amount = ref('');
const paidOn = ref(todayIsoDate());
const lateFee = ref('0.00');
const interest = ref('0.00');
/** Once the user types a charge, recalculations stop overwriting it. */
const chargesEdited = ref(false);

const lateCharges = computed(() =>
    props.payment
        ? calculateLateCharges(
              props.payment,
              Number(amount.value),
              paidOn.value,
          )
        : null,
);

const isLate = computed(
    () =>
        props.payment?.type === 'rent' &&
        (lateCharges.value?.days_late ?? 0) > 0,
);

const total = computed(
    () =>
        (Number(amount.value) || 0) +
        (isLate.value
            ? (Number(lateFee.value) || 0) + (Number(interest.value) || 0)
            : 0),
);

function applyCalculatedCharges() {
    lateFee.value = (lateCharges.value?.late_fee ?? 0).toFixed(2);
    interest.value = (lateCharges.value?.interest ?? 0).toFixed(2);
}

function recalculateCharges() {
    chargesEdited.value = false;
    applyCalculatedCharges();
}

function waiveCharges() {
    chargesEdited.value = true;
    lateFee.value = '0.00';
    interest.value = '0.00';
}

watch(
    () => props.payment?.id,
    () => {
        if (!props.payment) {
            return;
        }

        amount.value = paymentRemainingAmount(props.payment).toFixed(2);
        paidOn.value = todayIsoDate();
        chargesEdited.value = false;
        applyCalculatedCharges();
    },
    { immediate: true },
);

watch(lateCharges, () => {
    if (!chargesEdited.value) {
        applyCalculatedCharges();
    }
});
</script>

<template>
    <Dialog :open="!!payment" @update:open="(open) => !open && emit('close')">
        <DialogScrollContent v-if="payment" class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <HandCoins class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle>Registrar pagamento</DialogTitle>
                    <DialogDescription class="truncate">
                        {{ payment.lease.tenant.name }} ·
                        {{ paymentReferenceLabel(payment) }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <div
                class="flex items-center justify-between rounded-lg border bg-muted/40 px-4 py-3 text-sm"
            >
                <span class="text-muted-foreground">Em aberto</span>
                <span class="text-base font-semibold tabular-nums">{{
                    formatCurrency(paymentRemainingAmount(payment))
                }}</span>
            </div>

            <Form
                v-bind="ReceiptController.store.form(payment)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
            >
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="receipt_amount">{{
                            payment.type === 'rent'
                                ? 'Valor do aluguel'
                                : 'Valor recebido'
                        }}</Label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >R$</span
                            >
                            <Input
                                id="receipt_amount"
                                v-model="amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="pl-10 tabular-nums"
                            />
                        </div>
                        <InputError :message="errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="receipt_date">Data do pagamento</Label>
                        <Input
                            id="receipt_date"
                            v-model="paidOn"
                            name="date"
                            type="date"
                            :max="todayIsoDate()"
                        />
                        <InputError :message="errors.date" />
                    </div>
                </div>

                <div
                    v-if="isLate"
                    class="grid gap-4 rounded-lg border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900 dark:bg-amber-950/30"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-0.5">
                            <p
                                class="text-sm font-medium text-amber-800 dark:text-amber-300"
                            >
                                Encargos por atraso —
                                {{ lateCharges?.days_late }}
                                {{
                                    lateCharges?.days_late === 1
                                        ? 'dia'
                                        : 'dias'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Multa de
                                {{ Number(payment.lease.late_fee_percent) }}% e
                                juros de
                                {{
                                    Number(
                                        payment.lease.monthly_interest_percent,
                                    )
                                }}% ao mês, conforme o contrato.
                            </p>
                        </div>
                        <Button
                            v-if="chargesEdited"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="shrink-0"
                            @click="recalculateCharges"
                        >
                            <TimerReset class="size-4" />
                            Recalcular
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="shrink-0"
                            @click="waiveCharges"
                        >
                            Dispensar encargos
                        </Button>
                    </div>

                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="receipt_late_fee">Multa</Label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >R$</span
                                >
                                <Input
                                    id="receipt_late_fee"
                                    v-model="lateFee"
                                    name="late_fee_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="bg-background pl-10 tabular-nums"
                                    @input="chargesEdited = true"
                                />
                            </div>
                            <InputError :message="errors.late_fee_amount" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="receipt_interest">Juros</Label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >R$</span
                                >
                                <Input
                                    id="receipt_interest"
                                    v-model="interest"
                                    name="interest_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="bg-background pl-10 tabular-nums"
                                    @input="chargesEdited = true"
                                />
                            </div>
                            <InputError :message="errors.interest_amount" />
                        </div>
                    </div>
                </div>

                <div
                    v-if="isLate"
                    class="flex items-center justify-between border-y py-3 text-sm"
                >
                    <span class="font-medium">Total a receber</span>
                    <span class="text-lg font-semibold tabular-nums">{{
                        formatCurrency(total)
                    }}</span>
                </div>

                <div class="grid gap-2">
                    <Label for="payment_method">Forma de pagamento</Label>
                    <Select name="payment_method" default-value="pix">
                        <SelectTrigger id="payment_method" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(
                                    label, key
                                ) in manualPaymentMethodLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.payment_method" />
                </div>

                <div class="grid gap-2">
                    <Label for="receipt_notes">
                        Observação
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="receipt_notes"
                        name="notes"
                        placeholder="Ex.: comprovante enviado por WhatsApp…"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <DialogFooter class="border-t pt-6">
                    <DialogClose as-child>
                        <Button type="button" variant="outline"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Registrar pagamento
                    </Button>
                </DialogFooter>
            </Form>
        </DialogScrollContent>
    </Dialog>
</template>
