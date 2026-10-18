import type { Lease } from './lease';
import type { Property } from './property';

export type DashboardSalesOverview = {
    count: number;
    total: number;
    commission: number;
    openDeals: number;
    upcomingVisits: {
        id: number;
        deal_id: number;
        scheduled_at: string;
        deal: {
            id: number;
            lead: { id: number; name: string };
            property: {
                id: number;
                street: string;
                number: string;
                neighborhood: string;
            } | null;
        };
        user: { id: number; name: string } | null;
    }[];
};

export type DashboardStats = {
    expected: number;
    received: number;
    overdue: number;
    overdueCount: number;
    /** Only for people who handle the money. */
    expensesPaid?: number;
    expensesPending?: number;
    /** Late fees and interest received this month, included in the net income. */
    charges: number;
    netIncome?: number;
    properties: number;
    rentedProperties: number;
    activeLeases: number;
    tenants: number;
    /** Agencies register the owners before their properties. */
    owners: number;
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

/** What the agency earned itself in the month and the owners' money it holds. */
export type DashboardAgencyFinances = {
    revenue: {
        adminFees: number;
        intermediationFees: number;
        renewalFees: number;
        saleCommissions: number;
        /** The late fees and interest the agency kept, after the owners' part. */
        lateCharges: number;
        /** The extra charges the agency kept instead of passing them on. */
        extraCharges: number;
        /** The expenses the agency bore itself. */
        expenses: number;
        result: number;
    };
    owners: {
        /** Received from the tenants in the month, late charges included. */
        received: number;
        available: number;
        pending: number;
        /** Guaranteed rent paid to owners that the tenants still owe the agency. */
        advanced: number;
        paidOut: number;
    };
};

/** What may need attention today, each kind listed only when the user may handle it. */
export type DashboardAgendaKey =
    | 'overdue_payments'
    | 'payments_due_today'
    | 'payouts_available'
    | 'expenses_due'
    | 'leases_ending'
    | 'adjustments_due'
    | 'proposals_pending'
    | 'showcase_requests';

export type DashboardAgenda = {
    items: { key: DashboardAgendaKey; count: number; amount: number | null }[];
    visits: DashboardSalesOverview['upcomingVisits'];
};
