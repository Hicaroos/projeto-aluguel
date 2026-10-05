import { CircleEllipsis, Landmark, Building2, Wrench } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { daysUntil } from '@/lib/formatters';
import type { Expense, ExpenseDisplayStatus, ExpenseType } from '@/types';

export const expenseTypeLabels: Record<ExpenseType, string> = {
    property_tax: 'IPTU',
    condo_fee: 'Condomínio',
    maintenance: 'Manutenção',
    other: 'Outra',
};

export const expenseTypeIcons: Record<ExpenseType, LucideIcon> = {
    property_tax: Landmark,
    condo_fee: Building2,
    maintenance: Wrench,
    other: CircleEllipsis,
};

export const expenseStatusLabels: Record<ExpenseDisplayStatus, string> = {
    pending: 'A pagar',
    paid: 'Paga',
    overdue: 'Atrasada',
    canceled: 'Cancelada',
};

export const expenseStatusBadgeClasses: Record<ExpenseDisplayStatus, string> = {
    pending:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
    paid: 'border-primary/25 bg-primary/10 text-primary',
    overdue:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300',
    canceled:
        'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400',
};

export const expenseStatusDotClasses: Record<ExpenseDisplayStatus, string> = {
    pending: 'bg-sky-500',
    paid: 'bg-primary',
    overdue: 'bg-rose-500',
    canceled: 'bg-zinc-400',
};

export function expenseDisplayStatus(
    expense: Pick<Expense, 'status' | 'due_date'>,
): ExpenseDisplayStatus {
    return expense.status === 'pending' && daysUntil(expense.due_date) < 0
        ? 'overdue'
        : expense.status;
}

export function expenseDueHint(
    expense: Pick<Expense, 'status' | 'due_date'>,
): { label: string; class: string } | null {
    if (expense.status !== 'pending') {
        return null;
    }

    const days = daysUntil(expense.due_date);

    if (days < 0) {
        return {
            label: `Atrasada há ${Math.abs(days)} ${Math.abs(days) === 1 ? 'dia' : 'dias'}`,
            class: 'text-rose-600 dark:text-rose-400',
        };
    }

    if (days <= 5) {
        return {
            label:
                days === 0
                    ? 'Vence hoje'
                    : `Vence em ${days} ${days === 1 ? 'dia' : 'dias'}`,
            class: 'text-amber-600 dark:text-amber-400',
        };
    }

    return null;
}
