import type { AttachedDocument } from './document';
import type { Paginator } from './pagination';
import type { Property } from './property';

/** Who bears an expense of a property an agency manages. */
export type ExpensePayer = 'owner' | 'agency';

export type ExpenseType =
    'property_tax' | 'condo_fee' | 'maintenance' | 'other';

export type ExpenseStatus = 'pending' | 'paid' | 'canceled';

export type ExpenseDisplayStatus = ExpenseStatus | 'overdue';

export type ExpenseDocumentType =
    'signed_receipt' | 'invoice' | 'payment_proof' | 'bill' | 'photo' | 'other';

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
    /** Who did the job, for the receipt they sign. */
    provider_name: string | null;
    /** The provider's CPF or CNPJ, as digits. */
    provider_document: string | null;
    charged_to: ExpensePayer;
    amount: string;
    due_date: string;
    payment_date: string | null;
    status: ExpenseStatus;
    created_at: string;
    updated_at: string;
    property: ExpensePropertyOption & Pick<Property, 'type'>;
    documents?: AttachedDocument[];
};

export type ExpensePaginator = Paginator<Expense>;

export type ExpenseSummary = {
    total: number;
    paid: number;
    pending: number;
    overdue: number;
    overdue_count: number;
};
