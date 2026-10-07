import type { LeaseStatus } from './lease';
import type { Paginator } from './pagination';
import type { Property } from './property';
import type { Tenant } from './tenant';

export type PaymentStatus = 'pending' | 'partial' | 'paid' | 'canceled';

export type PaymentDisplayStatus = PaymentStatus | 'overdue';

export type PaymentType = 'rent' | 'extra';

export type PaymentMethod =
    'pix' | 'cash' | 'bank_transfer' | 'bank_slip' | 'card' | 'deposit';

export type Receipt = {
    id: number;
    payment_id: number;
    /** The part of the rent paid, which is what reduces the payment balance. */
    amount: string;
    late_fee_amount: string;
    interest_amount: string;
    date: string;
    payment_method: PaymentMethod | null;
    notes: string | null;
    created_at: string;
};

export type Payment = {
    id: number;
    lease_id: number;
    type: PaymentType;
    description: string | null;
    /** Null for extra charges. */
    reference_month: string | null;
    due_date: string;
    created_at: string;
    amount: string;
    status: PaymentStatus;
    received_amount: string | null;
    receipts: Receipt[];
    /** Late fee and interest on the open rent if it were paid today. Sent on the payments list. */
    late_charges_today?: LateCharges;
    lease: {
        id: number;
        due_day: number;
        status: LeaseStatus;
        late_fee_percent: string;
        monthly_interest_percent: string;
        tenant: Pick<Tenant, 'id' | 'name'>;
        property: Pick<
            Property,
            | 'id'
            | 'type'
            | 'street'
            | 'number'
            | 'complement'
            | 'neighborhood'
            | 'city'
            | 'state'
        >;
    };
};

export type PaymentPaginator = Paginator<Payment>;

export type LateCharges = {
    days_late: number;
    late_fee: number;
    interest: number;
};

export type PaymentSummary = {
    expected: number;
    received: number;
    /** Late fees and interest received on top of the rent. */
    charges: number;
    open: number;
    overdue: number;
    overdue_count: number;
};

export type PaymentLeaseFilter = {
    id: number;
    tenant: Pick<Tenant, 'id' | 'name'>;
    property: Pick<Property, 'id' | 'street' | 'number'>;
};

export type PaymentLeaseOption = {
    id: number;
    status: LeaseStatus;
    tenant: Pick<Tenant, 'id' | 'name'>;
    property: Pick<Property, 'id' | 'street' | 'number' | 'neighborhood'>;
};
