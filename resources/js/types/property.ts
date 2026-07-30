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
};

export type PropertyPaginator = {
    data: Property[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
};
