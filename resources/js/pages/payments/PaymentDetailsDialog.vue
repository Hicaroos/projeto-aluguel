<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    FileText,
    HandCoins,
    NotebookPen,
    ReceiptText,
    Trash2,
} from '@lucide/vue';
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
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import { formatDate } from '@/lib/formatters';
import {
    isDeletableCharge,
    isPaymentOpen,
    lateChargesTodayTotal as lateChargesTotal,
    paymentDisplayStatus,
    paymentDueHint,
    paymentMethodLabels,
    paymentReceivedAmount,
    paymentReferenceLabel,
    paymentRemainingAmount,
    paymentStatusBadgeClasses,
    paymentStatusDotClasses,
    paymentStatusLabels,
    receiptTotalAmount,
} from '@/lib/payment-labels';
import ReceiptShareButton from '@/pages/payments/ReceiptShareButton.vue';
import { index as leasesIndex } from '@/routes/leases';
import { destroy, show } from '@/routes/receipts';
import type { Payment, Receipt } from '@/types';

defineProps<{
    payment: Payment | null;
}>();

const can = usePermissions();

const emit = defineEmits<{
    close: [];
    register: [payment: Payment];
    delete: [payment: Payment];
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
                        <template v-if="payment.type === 'extra'"
                            >Cobrança avulsa:
                        </template>
                        <template v-else>Referência </template>
                        {{ paymentReferenceLabel(payment) }} ·
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

            <p
                v-if="isPaymentOpen(payment) && lateChargesTotal(payment) > 0"
                class="-mt-2 rounded-lg border border-attention-border bg-attention px-3 py-2 text-sm text-attention-foreground"
            >
                Pago hoje:
                <span class="font-semibold tabular-nums">{{
                    formatCurrency(
                        paymentRemainingAmount(payment) +
                            lateChargesTotal(payment),
                    )
                }}</span>
                com multa e juros de
                {{ payment.late_charges_today?.days_late }} dias de atraso.
            </p>

            <div v-if="payment.type === 'extra'" class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <NotebookPen class="size-3.5" />
                    Descrição
                </h3>
                <p
                    class="rounded-lg border bg-muted/40 p-3 text-sm whitespace-pre-line"
                >
                    {{ payment.description ?? 'Sem descrição.' }}
                </p>
            </div>

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
                                {{
                                    formatCurrency(receiptTotalAmount(receipt))
                                }}
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
                                v-if="
                                    receiptTotalAmount(receipt) >
                                    Number(receipt.amount)
                                "
                                class="text-xs text-attention-foreground tabular-nums"
                            >
                                {{ formatCurrency(receipt.amount) }} de aluguel
                                + {{ formatCurrency(receipt.late_fee_amount) }}
                                de multa +
                                {{ formatCurrency(receipt.interest_amount) }}
                                de juros
                            </p>
                            <p
                                v-if="receipt.notes"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ receipt.notes }}
                            </p>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <a :href="show(receipt).url" target="_blank">
                                <FileText class="size-4" />
                                Recibo
                            </a>
                        </Button>
                        <ReceiptShareButton
                            :receipt="receipt"
                            :payment="payment"
                            icon-only
                        />
                        <Button
                            v-if="can.manageFinance"
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

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            leasesIndex({ query: { show: payment.lease_id } })
                        "
                    >
                        <FileText class="size-4" />
                        Ver contrato
                    </Link>
                </Button>
            </div>

            <DialogFooter class="border-t pt-6">
                <Button
                    v-if="can.manageFinance && isDeletableCharge(payment)"
                    variant="ghost"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive sm:mr-auto"
                    @click="emit('delete', payment)"
                >
                    <Trash2 class="size-4" />
                    Excluir cobrança
                </Button>
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
                        ? formatCurrency(receiptTotalAmount(receiptToDelete))
                        : ''
                }}</span>
                será removido e a cobrança voltará a ficar em aberto.
            </ConfirmDeleteDialog>
        </DialogContent>
    </Dialog>
</template>
