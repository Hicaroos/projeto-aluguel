import type { DealType, LeadInterest, LeadSource, PaymentPlan } from '@/types';

export const leadSourceLabels: Record<LeadSource, string> = {
    referral: 'Indicação',
    sign: 'Placa',
    instagram: 'Instagram',
    facebook: 'Facebook',
    portal: 'Portal de imóveis',
    whatsapp: 'WhatsApp',
    website: 'Site',
    walk_in: 'Visitou o escritório',
    other: 'Outro',
};

export const leadInterestLabels: Record<LeadInterest, string> = {
    buy: 'Comprar',
    rent: 'Alugar',
};

export const leadInterestBadgeClasses: Record<LeadInterest, string> = {
    buy: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/60 dark:text-violet-300',
    rent: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
};

export const paymentPlanLabels: Record<PaymentPlan, string> = {
    cash: 'À vista',
    financing: 'Financiamento',
    fgts: 'FGTS',
    exchange: 'Permuta',
    installments: 'Parcelado direto',
};

export const dealTypeLabels: Record<DealType, string> = {
    sale: 'Venda',
    rent: 'Locação',
};
