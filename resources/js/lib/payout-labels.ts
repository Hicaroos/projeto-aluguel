import type { PayoutMode, PayoutRules, PayoutSchedule } from '@/types';

/** Every payout rule, as the columns owners and leases may set otherwise. */
export const payoutRuleColumns: (keyof PayoutRules)[] = [
    'admin_fee_percent',
    'intermediation_fee_percent',
    'renewal_fee_percent',
    'admin_fee_with_intermediation',
    'admin_fee_on_extra_charges',
    'extra_charges_to_owner',
    'late_charges_owner_percent',
    'payout_mode',
    'payout_schedule',
    'payout_days',
    'payout_day',
];

export const payoutModeLabels: Record<PayoutMode, string> = {
    received: 'Repassar só o que foi recebido',
    guaranteed: 'Aluguel garantido',
};

export const payoutModeDescriptions: Record<PayoutMode, string> = {
    received:
        'O proprietário recebe depois que o inquilino paga. Se o inquilino atrasar, o repasse espera.',
    guaranteed:
        'A imobiliária repassa no prazo mesmo que o inquilino atrase, e assume o risco da inadimplência.',
};

export const payoutScheduleLabels: Record<PayoutSchedule, string> = {
    days_after: 'Alguns dias depois',
    monthly_day: 'Em um dia fixo do mês',
};

/** Who keeps the late fee and interest paid by the tenant. */
export type LateChargesDestination = 'owner' | 'agency' | 'split';

export const lateChargesDestinationLabels: Record<
    LateChargesDestination,
    string
> = {
    owner: 'Proprietário',
    agency: 'Imobiliária',
    split: 'Dividir',
};

/**
 * Describe who keeps the late charges, from the share that goes to the owner.
 */
export function describeLateChargesShare(ownerPercent: number): string {
    if (ownerPercent >= 100) {
        return 'Proprietário';
    }

    if (ownerPercent <= 0) {
        return 'Imobiliária';
    }

    return `${ownerPercent}% para o proprietário`;
}
