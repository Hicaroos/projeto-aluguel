import { daysUntil } from '@/lib/formatters';
import type { GuaranteeType, Lease, LeaseStatus } from '@/types';

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
            class: 'text-amber-600 dark:text-amber-400',
        };
    }

    return null;
}
