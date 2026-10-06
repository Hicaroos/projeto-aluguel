<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { HandCoins } from '@lucide/vue';
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
    paymentMethodLabels,
    paymentReferenceLabel,
    paymentRemainingAmount,
} from '@/lib/payment-labels';
import type { Payment } from '@/types';

defineProps<{
    payment: Payment | null;
}>();

const emit = defineEmits<{
    close: [];
}>();
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
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="receipt_amount">Valor recebido</Label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >R$</span
                            >
                            <Input
                                id="receipt_amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="pl-10 tabular-nums"
                                :default-value="
                                    paymentRemainingAmount(payment).toFixed(2)
                                "
                            />
                        </div>
                        <InputError :message="errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="receipt_date">Data do pagamento</Label>
                        <Input
                            id="receipt_date"
                            name="date"
                            type="date"
                            :max="todayIsoDate()"
                            :default-value="todayIsoDate()"
                        />
                        <InputError :message="errors.date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="payment_method">Forma de pagamento</Label>
                    <Select name="payment_method" default-value="pix">
                        <SelectTrigger id="payment_method" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in paymentMethodLabels"
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
                        placeholder="Ex.: pago com atraso, comprovante enviado por WhatsApp…"
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
