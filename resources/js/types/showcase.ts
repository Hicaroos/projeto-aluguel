import type { PropertyType } from './property';

/** What every showcase page shows about the account behind it. */
export type ShowcaseSite = {
    name: string;
    slug: string;
    creci: string | null;
    /** As digits. */
    phone: string | null;
    /** The number visitors talk to, as digits. */
    whatsapp: string | null;
    email: string | null;
    /** The office address, on one line. */
    office: string | null;
    headline: string;
    about: string;
    color: { accent: string; strong: string; wash: string };
    logo_url: string | null;
    is_agency: boolean;
    /** The active branches, when there is more than one. */
    branches: {
        name: string;
        city: string | null;
        state: string | null;
        phone: string | null;
    }[];
    /** How many properties are on the showcase for each purpose. */
    offers: { sale: number; rent: number };
};

/** A property as the showcase shows it: never the street, the number or the owner. */
export type ShowcaseCard = {
    id: number;
    /** The code visitors quote, e.g. "0042". */
    ref: string;
    type: PropertyType;
    title: string;
    neighborhood: string;
    city: string;
    state: string;
    /** Null when the property is not for sale. */
    sale_price: number | null;
    /** Null when the property is not free to rent. */
    rent: number | null;
    bedrooms: number | null;
    suites: number | null;
    bathrooms: number | null;
    parking_spaces: number | null;
    built_area: number | null;
    land_area: number | null;
    is_new: boolean;
    /** The cover thumbnail. */
    photo: string | null;
};

export type ShowcaseProperty = ShowcaseCard & {
    /** The page headline, written the way people search: "Casa com 3 quartos para alugar no bairro Centro, em Ouricuri, PE". */
    headline: string;
    land_front: number | null;
    land_depth: number | null;
    description: string | null;
    photos: { url: string; thumbnail_url: string }[];
};

export type ShowcasePurpose = 'sale' | 'rent';

export type ShowcaseFilters = {
    purpose: ShowcasePurpose;
    neighborhood: string;
    bedrooms: number;
    /** The highest price; 0 for any. */
    max: number;
    sort: 'recentes' | 'menor-preco' | 'maior-preco' | 'maior-area';
};
