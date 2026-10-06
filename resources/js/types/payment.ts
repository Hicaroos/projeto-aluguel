import type { LeaseStatus } from './lease';
import type { Paginator } from './pagination';
import type { Property } from './property';
import type { Tenant } from './tenant';

export type PaymentStatus = 'pending' | 'partial' | 'paid' | 'canceled';

export type PaymentDisplayStatus = PaymentStatus | 'overdue';

export type PaymentType = 'rent' | 'extra';

export type PaymentMethod =
    'pix' | 'cash' | 'bank_transfer' | 'bank_slip' | 'card';

export type Receipt = {
    id: number;
    payment_id: number;
    amount: string;
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
    lease: {
        id: number;
        due_day: number;
        status: LeaseStatus;
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

export type PaymentSummary = {
    expected: number;
    received: number;
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
