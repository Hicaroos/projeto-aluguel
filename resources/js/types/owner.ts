import type { Paginator } from './pagination';
import type { PayoutRuleOverrides } from './payout';
import type { PersonQualification } from './person';
import type { Property } from './property';

export type Owner = PersonQualification &
    Partial<PayoutRuleOverrides> & {
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
    | 'for_rent'
    | 'for_sale'
    | 'rent_amount'
    | 'sale_price'
    | 'status'
> & {
    branch: { id: number; name: string } | null;
    /** The share of the property the owner holds. */
    pivot: { share_percent: string };
};

/** The owner whose records the whole app shows, picked in the owner selector. */
export type OwnerSelector = {
    selected: Pick<Owner, 'id' | 'name'> | null;
};

/** An owner offered in the owner selector. */
export type OwnerSelectorOption = Pick<Owner, 'id' | 'name' | 'cpf_cnpj'>;
