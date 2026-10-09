export type Branch = {
    id: number;
    account_id: number;
    name: string;
    document: string | null;
    creci: string | null;
    phone: string | null;
    zip_code: string | null;
    street: string | null;
    number: string | null;
    complement: string | null;
    neighborhood: string | null;
    city: string | null;
    state: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    /** Loaded on the branches list. */
    properties_count?: number;
    active_leases_count?: number;
};

export type BranchOption = Pick<Branch, 'id' | 'name' | 'is_active'>;

export type BranchSelector = {
    branches: Pick<Branch, 'id' | 'name'>[];
    /** The branch the whole app shows, or null for every branch. */
    selectedId: number | null;
};
