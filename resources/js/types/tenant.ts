import type { LeaseStatus } from './lease';
import type { Paginator } from './pagination';
import type { Property } from './property';

export type Tenant = {
    id: number;
    account_id: number;
    name: string;
    cpf_cnpj: string | null;
    email: string | null;
    phone: string | null;
    created_at: string;
    updated_at: string;
    /** Loaded on the tenants list: their leases, the active one first. */
    leases?: TenantLease[];
};

export type TenantPaginator = Paginator<Tenant>;

export type TenantLease = {
    id: number;
    property_id: number;
    start_date: string;
    end_date: string;
    amount: string;
    status: LeaseStatus;
    property: Pick<
        Property,
        'id' | 'type' | 'street' | 'number' | 'neighborhood'
    >;
};
