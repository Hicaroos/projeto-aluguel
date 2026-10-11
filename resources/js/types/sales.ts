import type { Paginator } from './pagination';
import type { Property, PropertyType } from './property';

export type LeadSource =
    | 'referral'
    | 'sign'
    | 'instagram'
    | 'facebook'
    | 'portal'
    | 'whatsapp'
    | 'website'
    | 'walk_in'
    | 'other';

export type LeadInterest = 'buy' | 'rent';

export type PaymentPlan =
    'cash' | 'financing' | 'fgts' | 'exchange' | 'installments';

export type DealType = 'sale' | 'rent';

export type DealOutcome = 'won' | 'lost';

export type VisitStatus = 'scheduled' | 'done' | 'canceled';

export type ProposalStatus = 'pending' | 'accepted' | 'rejected' | 'countered';

export type TeamMemberOption = { id: number; name: string };

export type Lead = {
    id: number;
    account_id: number;
    branch_id: number | null;
    user_id: number | null;
    name: string;
    phone: string | null;
    email: string | null;
    source: LeadSource | null;
    interest: LeadInterest;
    property_type: PropertyType | null;
    max_price: string | null;
    neighborhoods: string | null;
    payment_plan: PaymentPlan | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    /** Loaded on the leads list. */
    user?: TeamMemberOption | null;
    deals_count?: number;
    deals?: LeadDeal[];
};

export type LeadPaginator = Paginator<Lead>;

export type LeadDeal = Pick<
    Deal,
    'id' | 'type' | 'value' | 'closed_at' | 'deal_stage_id'
> & {
    stage: Pick<DealStage, 'id' | 'name' | 'outcome'>;
    property: Pick<
        Property,
        'id' | 'type' | 'street' | 'number' | 'neighborhood'
    > | null;
};

export type DealStage = {
    id: number;
    name: string;
    outcome: DealOutcome | null;
    sort_order: number;
};

export type DealVisit = {
    id: number;
    deal_id: number;
    user_id: number | null;
    scheduled_at: string;
    status: VisitStatus;
    feedback: string | null;
    user: TeamMemberOption | null;
};

export type DealProposal = {
    id: number;
    deal_id: number;
    amount: string;
    payment_plan: PaymentPlan | null;
    conditions: string | null;
    status: ProposalStatus;
    proposed_on: string;
};

export type Deal = {
    id: number;
    lead_id: number;
    property_id: number | null;
    deal_stage_id: number;
    user_id: number | null;
    type: DealType;
    value: string | null;
    position: number;
    lost_reason: string | null;
    closed_at: string | null;
    created_at: string;
    lead: Pick<
        Lead,
        | 'id'
        | 'name'
        | 'phone'
        | 'email'
        | 'interest'
        | 'payment_plan'
        | 'max_price'
        | 'branch_id'
    >;
    property: Pick<
        Property,
        | 'id'
        | 'type'
        | 'street'
        | 'number'
        | 'neighborhood'
        | 'city'
        | 'state'
        | 'sale_price'
        | 'rent_amount'
        | 'status'
    > | null;
    user: TeamMemberOption | null;
    visits: DealVisit[];
    proposals: DealProposal[];
};

export type FunnelStage = DealStage & {
    deals: Deal[];
};

/** A property a deal may be about. */
export type DealPropertyOption = Pick<
    Property,
    | 'id'
    | 'type'
    | 'street'
    | 'number'
    | 'complement'
    | 'neighborhood'
    | 'city'
    | 'for_rent'
    | 'for_sale'
    | 'rent_amount'
    | 'sale_price'
    | 'status'
>;

export type DealLeadOption = Pick<Lead, 'id' | 'name' | 'interest'>;
