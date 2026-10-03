<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { HandCoins, ReceiptText, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatMonthYear } from '@/lib/formatters';
import {
    isPaymentOpen,
    paymentDisplayStatus,
    paymentDueHint,
    paymentMethodLabels,
    paymentReceivedAmount,
    paymentRemainingAmount,
    paymentStatusBadgeClasses,
    paymentStatusDotClasses,
    paymentStatusLabels,
} from '@/lib/payment-labels';
import { destroy } from '@/routes/receipts';
import type { Payment, Receipt } from '@/types';

defineProps<{
    payment: Payment | null;
}>();

const emit = defineEmits<{
    close: [];
    register: [payment: Payment];
}>();

const receiptToDelete = ref<Receipt | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!receiptToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(receiptToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            receiptToDelete.value = null;
        },
    });
}
</script>

<template>
    <Dialog :open="!!payment" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="payment" class="sm:max-w-xl">
            <DialogHeader class="flex-row items-start gap-4 text-left">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <ReceiptText class="size-6" />
                </div>
                <div class="min-w-0 space-y-1.5 pr-6">
                    <DialogTitle class="leading-snug">
                        {{ payment.lease.tenant.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Referência
                        {{ formatMonthYear(payment.reference_month) }} ·
                        {{ payment.lease.property.street }},
                        {{ payment.lease.property.number }}
                    </DialogDescription>
                    <Badge
                        variant="outline"
                        :class="
                            paymentStatusBadgeClasses[
                                paymentDisplayStatus(payment)
                            ]
                        "
                    >
                        <span
                            class="size-1.5 rounded-full"
                            :class="
                                paymentStatusDotClasses[
                                    paymentDisplayStatus(payment)
                                ]
                            "
                        />
                        {{ paymentStatusLabels[paymentDisplayStatus(payment)] }}
                    </Badge>
                </div>
            </DialogHeader>

            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border bg-muted/40 p-3">
                    <dt class="text-xs text-muted-foreground">Vencimento</dt>
                    <dd class="font-semibold tabular-nums">
                        {{ formatDate(payment.due_date) }}
                    </dd>
                    <dd
                        v-if="paymentDueHint(payment)"
                        class="text-xs font-medium"
                        :class="paymentDueHint(payment)?.class"
                    >
                        {{ paymentDueHint(payment)?.label }}
                    </dd>
                </div>
                <div class="rounded-lg border bg-muted/40 p-3">
                    <dt class="text-xs text-muted-foreground">Valor</dt>
                    <dd class="font-semibold tabular-nums">
                        {{ formatCurrency(payment.amount) }}
                    </dd>
                </div>
                <div class="rounded-lg border bg-muted/40 p-3">
                    <dt class="text-xs text-muted-foreground">Recebido</dt>
                    <dd class="font-semibold text-primary tabular-nums">
                        {{ formatCurrency(paymentReceivedAmount(payment)) }}
                    </dd>
                </div>
                <div class="rounded-lg border bg-muted/40 p-3">
                    <dt class="text-xs text-muted-foreground">Em aberto</dt>
                    <dd class="font-semibold tabular-nums">
                        {{
                            formatCurrency(
                                payment.status === 'canceled'
                                    ? 0
                                    : paymentRemainingAmount(payment),
                            )
                        }}
                    </dd>
                </div>
            </dl>

            <div class="space-y-3">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Pagamentos recebidos
                </h3>

                <p
                    v-if="payment.receipts.length === 0"
                    class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    Nenhum pagamento registrado para esta cobrança.
                </p>

                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="receipt in payment.receipts"
                        :key="receipt.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium tabular-nums">
                                {{ formatCurrency(receipt.amount) }}
                                <span class="font-normal text-muted-foreground">
                                    · {{ formatDate(receipt.date) }}
                                    <template v-if="receipt.payment_method">
                                        ·
                                        {{
                                            paymentMethodLabels[
                                                receipt.payment_method
                                            ]
                                        }}
                                    </template>
                                </span>
                            </p>
                            <p
                                v-if="receipt.notes"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ receipt.notes }}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:text-destructive"
                            @click="receiptToDelete = receipt"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only">Remover pagamento</span>
                        </Button>
                    </li>
                </ul>
            </div>

            <DialogFooter class="border-t pt-6">
                <DialogClose as-child>
                    <Button variant="outline">Fechar</Button>
                </DialogClose>
                <Button
                    v-if="isPaymentOpen(payment)"
                    @click="emit('register', payment)"
                >
                    <HandCoins class="size-4" />
                    Registrar pagamento
                </Button>
            </DialogFooter>

            <ConfirmDeleteDialog
                :open="!!receiptToDelete"
                title="Remover pagamento?"
                :processing="isDeleting"
                @close="receiptToDelete = null"
                @confirm="confirmDelete"
            >
                O pagamento de
                <span class="font-medium text-foreground">{{
                    receiptToDelete
                        ? formatCurrency(receiptToDelete.amount)
                        : ''
                }}</span>
                será removido e a cobrança voltará a ficar em aberto.
            </ConfirmDeleteDialog>
        </DialogContent>
    </Dialog>
</template>
