import type { Paginator } from './pagination';
import type { PersonQualification } from './person';
import type { Property } from './property';

export type Owner = PersonQualification & {
    id: number;
    account_id: number;
    name: string;
    cpf_cnpj: string | null;
    email: string | null;
    phone: string | null;
    pix_key: string | null;
    created_at: string;
    updated_at: string;
    /** Loaded on the owners list. */
    properties_count?: number;
    /** Loaded on the owners list: the properties the agency manages for them. */
    properties?: OwnerProperty[];
};

export type OwnerPaginator = Paginator<Owner>;

export type OwnerProperty = Pick<
    Property,
    | 'id'
    | 'owner_id'
    | 'branch_id'
    | 'type'
    | 'street'
    | 'number'
    | 'neighborhood'
    | 'city'
    | 'state'
    | 'rent_amount'
    | 'status'
> & {
    branch: { id: number; name: string } | null;
};
