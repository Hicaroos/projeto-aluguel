import { CircleEllipsis, Landmark, Building2, Wrench } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { badgeDotTones, badgeTones } from '@/lib/badge-tones';
import { daysUntil } from '@/lib/formatters';
import type {
    Expense,
    ExpenseDisplayStatus,
    ExpenseDocumentType,
    ExpensePayer,
    ExpenseType,
} from '@/types';

export const expensePayerLabels: Record<ExpensePayer, string> = {
    owner: 'Proprietário',
    agency: 'Imobiliária',
};

export const expensePayerDescriptions: Record<ExpensePayer, string> = {
    owner: 'Quando for paga, o valor é descontado do repasse do proprietário.',
    agency: 'Custo da própria imobiliária, sem afetar o repasse.',
};

export const expenseTypeLabels: Record<ExpenseType, string> = {
    property_tax: 'IPTU',
    condo_fee: 'Condomínio',
    maintenance: 'Manutenção',
    other: 'Outra',
};

/** Whether the expense pays someone for a job, so the provider signs a receipt for it. */
export function isServiceExpense(type: ExpenseType): boolean {
    return type === 'maintenance' || type === 'other';
}

export const expenseDocumentTypeLabels: Record<ExpenseDocumentType, string> = {
    signed_receipt: 'Recibo assinado',
    invoice: 'Nota fiscal',
    payment_proof: 'Comprovante de pagamento',
    bill: 'Boleto ou conta',
    photo: 'Foto do serviço',
    other: 'Outro',
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
    pending: badgeTones.info,
    paid: badgeTones.success,
    overdue: badgeTones.danger,
    canceled: badgeTones.neutral,
};

export const expenseStatusDotClasses: Record<ExpenseDisplayStatus, string> = {
    pending: badgeDotTones.info,
    paid: badgeDotTones.success,
    overdue: badgeDotTones.danger,
    canceled: badgeDotTones.neutral,
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
            class: 'text-attention-foreground',
        };
    }

    return null;
}
