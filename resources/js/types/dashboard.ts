import type { Lease } from './lease';
import type { Property } from './property';
import type { Tenant } from './tenant';

export type DashboardStats = {
    expected: number;
    received: number;
    overdue: number;
    overdueCount: number;
    expensesPaid: number;
    expensesPending: number;
    /** Late fees and interest received this month, included in the net income. */
    charges: number;
    netIncome: number;
    properties: number;
    rentedProperties: number;
    activeLeases: number;
    tenants: number;
};

export type MonthlyRevenue = {
    month: string;
    expected: number;
    received: number;
};

export type DashboardEndingLease = Pick<
    Lease,
    'id' | 'end_date' | 'amount' | 'status'
> & {
    tenant: Pick<Tenant, 'id' | 'name'>;
    property: Pick<Property, 'id' | 'street' | 'number'>;
};

export type DashboardAdjustmentLease = Pick<
    Lease,
    | 'id'
    | 'amount'
    | 'adjustment_index'
    | 'next_adjustment_date'
    | 'adjustment_status'
> & {
    tenant: Pick<Tenant, 'id' | 'name'>;
    property: Pick<Property, 'id' | 'street' | 'number'>;
};

export type DashboardVacantProperty = Pick<
    Property,
    | 'id'
    | 'type'
    | 'street'
    | 'number'
    | 'neighborhood'
    | 'city'
    | 'state'
    | 'rent_amount'
>;
