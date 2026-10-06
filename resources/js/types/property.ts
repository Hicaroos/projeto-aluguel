import type { Paginator } from './pagination';

export type PropertyType = 'house' | 'apartment' | 'commercial' | 'land';

export type PropertyStatus =
    'available' | 'rented' | 'maintenance' | 'inactive';

export type PropertyOwnerOption = {
    id: number;
    name: string;
};

export type Property = {
    id: number;
    account_id: number;
    owner_id: number;
    type: PropertyType;
    zip_code: string;
    street: string;
    number: string;
    complement: string | null;
    neighborhood: string;
    city: string;
    state: string;
    rent_amount: string;
    status: PropertyStatus;
    created_at: string;
    updated_at: string;
    /** Loaded on the properties list: the lease currently renting the property. */
    active_lease?: PropertyActiveLease | null;
};

export type PropertyPaginator = Paginator<Property>;

export type PropertyActiveLease = {
    id: number;
    tenant_id: number;
    start_date: string;
    end_date: string;
    amount: string;
    due_day: number;
    tenant: { id: number; name: string };
};
