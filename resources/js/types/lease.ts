import type { Paginator } from './pagination';
import type { PersonQualification } from './person';
import type { Property } from './property';
import type { Tenant } from './tenant';

export type LeaseStatus = 'active' | 'ended' | 'terminated';

export type GuaranteeType = 'none' | 'deposit' | 'guarantor' | 'surety_bond';

export type LeasePurpose = 'residential' | 'commercial';

export type AdjustmentIndex = 'igpm' | 'ipca' | 'inpc' | 'ivar';

export type Guarantor = PersonQualification & {
    id: number;
    lease_id: number;
    name: string;
    cpf_cnpj: string | null;
    email: string | null;
    phone: string | null;
    spouse_name: string | null;
    spouse_cpf: string | null;
    property_registration: string | null;
};

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
    purpose: LeasePurpose;
    adjustment_index: AdjustmentIndex;
    late_fee_percent: string;
    monthly_interest_percent: string;
    termination_fee_months: number;
    guarantee_type: GuaranteeType;
    deposit_amount: string | null;
    surety_insurer: string | null;
    surety_policy_number: string | null;
    guarantor: Guarantor | null;
    status: LeaseStatus;
    notes: string | null;
    created_at: string;
    updated_at: string;
    property: LeasePropertyOption & Pick<Property, 'type'>;
    tenant: Pick<Tenant, 'id' | 'name' | 'email' | 'phone'>;
};

export type LeasePaginator = Paginator<Lease>;
