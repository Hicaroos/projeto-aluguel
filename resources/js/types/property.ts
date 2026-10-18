import type { Paginator } from './pagination';

export type PropertyType =
    | 'house'
    | 'apartment'
    | 'commercial'
    | 'office'
    | 'warehouse'
    | 'land'
    | 'farm';

export type PropertyPurpose = 'rent' | 'sale';

export type PropertyStatus =
    'available' | 'rented' | 'maintenance' | 'inactive' | 'sold';

export type PropertyOwnerOption = {
    id: number;
    name: string;
};

export type Property = {
    id: number;
    account_id: number;
    branch_id: number | null;
    owner_id: number;
    type: PropertyType;
    for_rent: boolean;
    for_sale: boolean;
    zip_code: string;
    street: string;
    number: string;
    complement: string | null;
    neighborhood: string;
    city: string;
    state: string;
    rent_amount: string | null;
    sale_price: string | null;
    bedrooms: number | null;
    suites: number | null;
    bathrooms: number | null;
    parking_spaces: number | null;
    built_area: string | null;
    land_area: string | null;
    land_front: string | null;
    land_depth: string | null;
    description: string | null;
    /** When the property went on the showcase; null while it is off it. */
    published_at: string | null;
    /** Whether the showcase highlights the property on its home page. */
    is_featured: boolean;
    /** The registration at the land registry office (matrícula). */
    registration_number: string | null;
    status: PropertyStatus;
    created_at: string;
    updated_at: string;
    /** Loaded on the properties list: the lease currently renting the property. */
    active_lease?: PropertyActiveLease | null;
    /** Loaded on the properties list, starting with the cover. */
    photos?: PropertyPhoto[];
    /** Loaded on the properties list of agencies. */
    branch?: { id: number; name: string } | null;
    /** Loaded on the properties list. */
    owner?: { id: number; name: string } | null;
    /** Loaded on the properties list: every owner, the main one included, with their share. */
    owners?: PropertyCoOwner[];
};

export type PropertyCoOwner = {
    id: number;
    name: string;
    pivot: { share_percent: string };
};

export type PropertyPhoto = {
    id: number;
    property_id: number;
    sort_order: number;
    url: string;
    thumbnail_url: string;
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
