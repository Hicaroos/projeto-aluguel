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
