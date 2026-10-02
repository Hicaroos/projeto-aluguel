import type { Paginator } from './pagination';
import type { Property } from './property';
import type { Tenant } from './tenant';

export type LeaseStatus = 'active' | 'ended' | 'terminated';

export type GuaranteeType = 'none' | 'deposit' | 'guarantor' | 'surety_bond';

export type LeasePropertyOption = Pick<
    Property,
    | 'id'
    | 'street'
    | 'number'
    | 'complement'
    | 'neighborhood'
    | 'city'
    | 'state'
    | 'rent_amount'
    | 'status'
>;

export type LeaseTenantOption = Pick<Tenant, 'id' | 'name'>;

export type Lease = {
    id: number;
    account_id: number;
    property_id: number;
    tenant_id: number;
    start_date: string;
    end_date: string;
    amount: string;
    due_day: number;
    guarantee_type: GuaranteeType;
    deposit_amount: string | null;
    status: LeaseStatus;
    notes: string | null;
    created_at: string;
    updated_at: string;
    property: LeasePropertyOption & Pick<Property, 'type'>;
    tenant: Pick<Tenant, 'id' | 'name' | 'email' | 'phone'>;
};

export type LeasePaginator = Paginator<Lease>;
