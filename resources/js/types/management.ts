export type BranchSummary = {
    expected: number;
    received: number;
    overdue: number;
    overdueCount: number;
    properties: number;
    rentedProperties: number;
    vacantProperties: number;
    activeLeases: number;
    /** Active leases ending within 60 days. */
    endingLeases: number;
};

export type ManagedBranch = {
    id: number;
    name: string;
    city: string | null;
    state: string | null;
    is_active: boolean;
    summary: BranchSummary;
};

export type TeamOverview = {
    active: number;
    pending: number;
    inactive: number;
    /** Active members, the most recently signed in first. */
    recent: {
        id: number;
        name: string;
        role: string;
        last_login_at: string | null;
    }[];
};
