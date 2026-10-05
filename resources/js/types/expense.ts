import type { Paginator } from './pagination';
import type { Property } from './property';

export type ExpenseType =
    'property_tax' | 'condo_fee' | 'maintenance' | 'other';

export type ExpenseStatus = 'pending' | 'paid' | 'canceled';

export type ExpenseDisplayStatus = ExpenseStatus | 'overdue';

export type ExpensePropertyOption = Pick<
    Property,
    | 'id'
    | 'street'
    | 'number'
    | 'complement'
    | 'neighborhood'
    | 'city'
    | 'state'
>;

export type Expense = {
    id: number;
    account_id: number;
    property_id: number;
    type: ExpenseType;
    description: string | null;
    amount: string;
    due_date: string;
    payment_date: string | null;
    status: ExpenseStatus;
    created_at: string;
    updated_at: string;
    property: ExpensePropertyOption & Pick<Property, 'type'>;
};

export type ExpensePaginator = Paginator<Expense>;

export type ExpenseSummary = {
    total: number;
    paid: number;
    pending: number;
    overdue: number;
    overdue_count: number;
};
