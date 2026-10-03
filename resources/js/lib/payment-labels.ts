import { daysUntil } from '@/lib/formatters';
import type { Payment, PaymentDisplayStatus, PaymentMethod } from '@/types';

export const paymentStatusLabels: Record<PaymentDisplayStatus, string> = {
    pending: 'Pendente',
    partial: 'Parcial',
    paid: 'Paga',
    overdue: 'Atrasada',
    canceled: 'Cancelada',
};

export const paymentStatusBadgeClasses: Record<PaymentDisplayStatus, string> = {
    pending:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
    partial:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300',
    paid: 'border-primary/25 bg-primary/10 text-primary',
    overdue:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300',
    canceled:
        'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400',
};

export const paymentStatusDotClasses: Record<PaymentDisplayStatus, string> = {
    pending: 'bg-sky-500',
    partial: 'bg-amber-500',
    paid: 'bg-primary',
    overdue: 'bg-rose-500',
    canceled: 'bg-zinc-400',
};

export const paymentMethodLabels: Record<PaymentMethod, string> = {
    pix: 'Pix',
    cash: 'Dinheiro',
    bank_transfer: 'Transferência',
    bank_slip: 'Boleto',
    card: 'Cartão',
};

export function isPaymentOpen(payment: Pick<Payment, 'status'>): boolean {
    return payment.status === 'pending' || payment.status === 'partial';
}

export function paymentDisplayStatus(
    payment: Pick<Payment, 'status' | 'due_date'>,
): PaymentDisplayStatus {
    return isPaymentOpen(payment) && daysUntil(payment.due_date) < 0
        ? 'overdue'
        : payment.status;
}

export function paymentReceivedAmount(
    payment: Pick<Payment, 'received_amount'>,
): number {
    return Number(payment.received_amount ?? 0);
}

export function paymentRemainingAmount(
    payment: Pick<Payment, 'amount' | 'received_amount'>,
): number {
    return Math.max(
        0,
        Math.round(
            (Number(payment.amount) - paymentReceivedAmount(payment)) * 100,
        ) / 100,
    );
}

export function paymentDueHint(
    payment: Pick<Payment, 'status' | 'due_date'>,
): { label: string; class: string } | null {
    if (!isPaymentOpen(payment)) {
        return null;
    }

    const days = daysUntil(payment.due_date);

    if (days < 0) {
        return {
            label: `Atrasada há ${Math.abs(days)} ${Math.abs(days) === 1 ? 'dia' : 'dias'}`,
            class: 'text-rose-600 dark:text-rose-400',
        };
    }

    if (days === 0) {
        return {
            label: 'Vence hoje',
            class: 'text-amber-600 dark:text-amber-400',
        };
    }

    if (days <= 5) {
        return {
            label: `Vence em ${days} ${days === 1 ? 'dia' : 'dias'}`,
            class: 'text-amber-600 dark:text-amber-400',
        };
    }

    return null;
}
