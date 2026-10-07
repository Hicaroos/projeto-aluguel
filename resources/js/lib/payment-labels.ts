import { daysUntil, formatMonthYear } from '@/lib/formatters';
import type {
    LateCharges,
    Payment,
    PaymentDisplayStatus,
    PaymentMethod,
    Receipt,
} from '@/types';

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
    deposit: 'Caução',
};

/** The payment methods offered when registering a payment by hand: the deposit is only used when settling it. */
export const manualPaymentMethodLabels: Record<
    Exclude<PaymentMethod, 'deposit'>,
    string
> = {
    pix: paymentMethodLabels.pix,
    cash: paymentMethodLabels.cash,
    bank_transfer: paymentMethodLabels.bank_transfer,
    bank_slip: paymentMethodLabels.bank_slip,
    card: paymentMethodLabels.card,
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

/**
 * Calculate the late fee and interest the lease charges on the rent amount paid on the given date.
 * Mirrors App\Actions\Payments\CalculateLateCharges: the fee is charged once from the first day late
 * and interest is simple, counting each day as 1/30 of the monthly rate. Extra charges carry none.
 */
export function calculateLateCharges(
    payment: Pick<Payment, 'type' | 'due_date' | 'lease'>,
    amount: number,
    paidOn: string,
): LateCharges {
    const daysLate = Math.max(
        0,
        Math.round(
            (Date.parse(`${paidOn}T00:00:00`) -
                Date.parse(`${payment.due_date}T00:00:00`)) /
                86_400_000,
        ),
    );

    if (
        payment.type === 'extra' ||
        daysLate === 0 ||
        !(amount > 0) ||
        Number.isNaN(daysLate)
    ) {
        return { days_late: daysLate || 0, late_fee: 0, interest: 0 };
    }

    const round = (value: number) => Math.round(value * 100) / 100;

    return {
        days_late: daysLate,
        late_fee: round(
            (amount * Number(payment.lease.late_fee_percent)) / 100,
        ),
        interest: round(
            ((amount * Number(payment.lease.monthly_interest_percent)) /
                100 /
                30) *
                daysLate,
        ),
    };
}

/**
 * Get the late fee plus interest on the open rent if it were paid today, as sent on the payments list.
 */
export function lateChargesTodayTotal(
    payment: Pick<Payment, 'late_charges_today'>,
): number {
    return (
        (payment.late_charges_today?.late_fee ?? 0) +
        (payment.late_charges_today?.interest ?? 0)
    );
}

/**
 * Get the total received in a receipt: the rent part plus the late fee and interest.
 */
export function receiptTotalAmount(
    receipt: Pick<Receipt, 'amount' | 'late_fee_amount' | 'interest_amount'>,
): number {
    return (
        Math.round(
            (Number(receipt.amount) +
                Number(receipt.late_fee_amount) +
                Number(receipt.interest_amount)) *
                100,
        ) / 100
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

export function paymentReferenceLabel(
    payment: Pick<Payment, 'type' | 'description' | 'reference_month'>,
    style: 'long' | 'short' = 'long',
): string {
    if (payment.type === 'extra' || !payment.reference_month) {
        return payment.description ?? 'Cobrança avulsa';
    }

    return formatMonthYear(payment.reference_month, style);
}

/**
 * Extra charges can be deleted while no amount was received for them.
 */
export function isDeletableCharge(
    payment: Pick<Payment, 'type' | 'receipts'>,
): boolean {
    return payment.type === 'extra' && payment.receipts.length === 0;
}
