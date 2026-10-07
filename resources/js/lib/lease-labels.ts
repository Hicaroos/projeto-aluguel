import { daysUntil } from '@/lib/formatters';
import type {
    AdjustmentIndex,
    GuaranteeType,
    Lease,
    LeaseAdjustmentStatus,
    LeasePurpose,
    LeaseStatus,
} from '@/types';

export const adjustmentStatusLabels: Record<LeaseAdjustmentStatus, string> = {
    available: 'Reajuste disponível',
    overdue: 'Reajuste atrasado',
};

export const adjustmentStatusBadgeClasses: Record<
    LeaseAdjustmentStatus,
    string
> = {
    available: 'border-attention-border bg-attention text-attention-foreground',
    overdue:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300',
};

/** Where to look up the accumulated 12-month variation of each index. */
export const adjustmentIndexSources: Record<AdjustmentIndex, string> = {
    igpm: 'site da FGV (IGP-M acumulado em 12 meses)',
    ipca: 'site do IBGE (IPCA acumulado em 12 meses)',
    inpc: 'site do IBGE (INPC acumulado em 12 meses)',
    ivar: 'site da FGV (IVAR acumulado em 12 meses)',
    negotiated: '',
};

/**
 * Determine whether the deposit of a finished lease can still be settled.
 */
export function canSettleDeposit(
    lease: Pick<
        Lease,
        'guarantee_type' | 'deposit_amount' | 'status' | 'deposit_settled_on'
    >,
): boolean {
    return (
        lease.guarantee_type === 'deposit' &&
        Number(lease.deposit_amount) > 0 &&
        lease.status !== 'active' &&
        !lease.deposit_settled_on
    );
}

/**
 * Get how much of a settled deposit paid the tenant's debts.
 */
export function depositUsed(
    lease: Pick<Lease, 'deposit_amount' | 'deposit_refunded_amount'>,
): number {
    return (
        Math.round(
            (Number(lease.deposit_amount) -
                Number(lease.deposit_refunded_amount)) *
                100,
        ) / 100
    );
}

/**
 * Calculate the adjusted rent for the given percent, rounded to cents like the server does.
 */
export function adjustedAmount(
    amount: string | number,
    percent: number,
): number {
    return Math.round(Number(amount) * (1 + percent / 100) * 100) / 100;
}

export const leaseStatusLabels: Record<LeaseStatus, string> = {
    active: 'Ativo',
    ended: 'Encerrado',
    terminated: 'Rescindido',
};

export const leaseStatusBadgeClasses: Record<LeaseStatus, string> = {
    active: 'border-primary/25 bg-primary/10 text-primary',
    ended: 'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400',
    terminated:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300',
};

export const leaseStatusDotClasses: Record<LeaseStatus, string> = {
    active: 'bg-primary',
    ended: 'bg-zinc-400',
    terminated: 'bg-rose-500',
};

export const guaranteeTypeLabels: Record<GuaranteeType, string> = {
    none: 'Sem garantia',
    deposit: 'Caução',
    guarantor: 'Fiador',
    surety_bond: 'Seguro-fiança',
};

export const leasePurposeLabels: Record<LeasePurpose, string> = {
    residential: 'Residencial',
    commercial: 'Comercial',
};

export const adjustmentIndexLabels: Record<AdjustmentIndex, string> = {
    igpm: 'IGP-M',
    ipca: 'IPCA',
    inpc: 'INPC',
    ivar: 'IVAR',
    negotiated: 'Livre negociação',
};

export function leaseDeadlineHint(
    lease: Pick<Lease, 'status' | 'end_date'>,
): { label: string; class: string } | null {
    if (lease.status !== 'active') {
        return null;
    }

    const days = daysUntil(lease.end_date);

    if (days < 0) {
        return { label: 'Prazo vencido', class: 'text-destructive' };
    }

    if (days <= 60) {
        return {
            label: days === 0 ? 'Termina hoje' : `Termina em ${days} dias`,
            class: 'text-attention-foreground',
        };
    }

    return null;
}
