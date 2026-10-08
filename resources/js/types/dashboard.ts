import type { Lease } from './lease';
import type { Property } from './property';

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

/** Dashboard leases carry everything their details show, so they open right on the dashboard. */
export type DashboardEndingLease = Lease;

export type DashboardAdjustmentLease = Lease;

/** Dashboard properties carry everything their details show, so they open right on the dashboard. */
export type DashboardVacantProperty = Property;
