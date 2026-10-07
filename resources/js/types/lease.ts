import type { Paginator } from './pagination';
import type { Payment } from './payment';
import type { PersonQualification } from './person';
import type { Property } from './property';
import type { Tenant } from './tenant';

export type LeaseStatus = 'active' | 'ended' | 'terminated';

export type GuaranteeType = 'none' | 'deposit' | 'guarantor' | 'surety_bond';

export type LeasePurpose = 'residential' | 'commercial';

export type AdjustmentIndex = 'igpm' | 'ipca' | 'inpc' | 'ivar' | 'negotiated';

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
    /** When the deposit was settled, after the lease ended. */
    deposit_settled_on: string | null;
    /** What was left of the deposit and given back to the tenant. */
    deposit_refunded_amount: string | null;
    /** Payments the tenant still owes on the lease, oldest due first. */
    open_payments: Payment[];
    surety_insurer: string | null;
    surety_policy_number: string | null;
    guarantor: Guarantor | null;
    status: LeaseStatus;
    notes: string | null;
    created_at: string;
    updated_at: string;
    property: LeasePropertyOption & Pick<Property, 'type'>;
    tenant: Pick<Tenant, 'id' | 'name' | 'email' | 'phone'>;
    adjustments: LeaseAdjustment[];
    /** The next anniversary the rent can be adjusted on, if any. */
    next_adjustment_date: string | null;
    adjustment_status: LeaseAdjustmentStatus | null;
};

export type LeasePaginator = Paginator<Lease>;

/** 'available' within 30 days of the anniversary, 'overdue' after it. */
export type LeaseAdjustmentStatus = 'available' | 'overdue';

export type LeaseAdjustment = {
    id: number;
    lease_id: number;
    effective_on: string;
    adjustment_index: AdjustmentIndex;
    percent: string;
    previous_amount: string;
    new_amount: string;
    notes: string | null;
    created_at: string;
};
