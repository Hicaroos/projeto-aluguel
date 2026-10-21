export type PayoutMode = 'received' | 'guaranteed';

export type PayoutSchedule = 'days_after' | 'monthly_day';

/** The rules that decide what an owner is paid, as the agency defaults set them. */
export type PayoutRules = {
    admin_fee_percent: string;
    intermediation_fee_percent: string;
    renewal_fee_percent: string;
    admin_fee_with_intermediation: boolean;
    admin_fee_on_extra_charges: boolean;
    extra_charges_to_owner: boolean;
    late_charges_owner_percent: string;
    payout_mode: PayoutMode;
    payout_schedule: PayoutSchedule;
    payout_days: number;
    payout_day: number;
};

/** The rules an owner or a lease sets otherwise; null follows the level above. */
export type PayoutRuleOverrides = {
    [Rule in keyof PayoutRules]: PayoutRules[Rule] | null;
};

/** An owner on the payouts page, with what the agency owes them. */
export type OwnerBalance = {
    id: number;
    name: string;
    cpf_cnpj: string | null;
    pix_key: string | null;
    /** What may be paid out today; negative when the owner owes the agency. */
    available: number;
    /** What is still waiting for its payout date. */
    pending: number;
    last_payout_on: string | null;
};

export type PayoutTotals = {
    available: number;
    pending: number;
    /** Guaranteed rent credited to owners that the tenants still owe. */
    advanced: number;
    /** The fees the agency earned in the month. */
    fees: number;
};

export type LedgerEntryType =
    | 'rent'
    | 'admin_fee'
    | 'intermediation_fee'
    | 'renewal_fee'
    | 'late_charges'
    | 'extra_charge'
    | 'expense'
    | 'sale_down_payment'
    | 'sale_commission'
    | 'adjustment'
    | 'payout';

export type StatementEntry = {
    id: number;
    type: LedgerEntryType;
    type_label: string;
    description: string;
    /** Positive when it is the owner's, negative when it is taken from them. */
    amount: string;
    /** The balance after the entry. */
    balance: number;
    occurred_on: string;
    available_on: string;
    is_pending: boolean;
    is_guaranteed: boolean;
    share_percent: string | null;
    owner_payout_id: number | null;
    /** The proof of transfer attached to the payout, by its file name. */
    payout_receipt_name: string | null;
    is_adjustment: boolean;
    property: {
        id: number;
        street: string;
        number: string;
        complement: string | null;
    } | null;
};

export type HeldDeposit = {
    lease_id: number;
    amount: string;
    tenant: string;
    property: string;
};

export type OwnerStatement = {
    opening_balance: number;
    closing_balance: number;
    credits: number;
    debits: number;
    available: number;
    pending: number;
    entries: StatementEntry[];
    deposits: HeldDeposit[];
};

export type StatementOwner = {
    id: number;
    name: string;
    cpf_cnpj: string | null;
    pix_key: string | null;
};

export type StatementPropertyOption = {
    id: number;
    street: string;
    number: string;
    complement: string | null;
};
