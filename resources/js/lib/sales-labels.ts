import { badgeTones } from '@/lib/badge-tones';
import type {
    DealType,
    LeadInterest,
    LeadSource,
    PaymentPlan,
    ProposalStatus,
    SaleDocumentType,
    VisitStatus,
} from '@/types';

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
    list: 'Anunciar imóvel',
};

export const leadInterestBadgeClasses: Record<LeadInterest, string> = {
    buy: badgeTones.accent,
    rent: badgeTones.info,
    list: badgeTones.warning,
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

export const visitStatusLabels: Record<VisitStatus, string> = {
    scheduled: 'Agendada',
    done: 'Realizada',
    canceled: 'Cancelada',
};

export const visitStatusBadgeClasses: Record<VisitStatus, string> = {
    scheduled: badgeTones.info,
    done: badgeTones.success,
    canceled: badgeTones.neutral,
};

export const proposalStatusLabels: Record<ProposalStatus, string> = {
    pending: 'Em análise',
    accepted: 'Aceita',
    rejected: 'Recusada',
    countered: 'Contraproposta',
};

export const proposalStatusBadgeClasses: Record<ProposalStatus, string> = {
    pending: badgeTones.warning,
    accepted: badgeTones.success,
    rejected: badgeTones.danger,
    countered: badgeTones.info,
};

/** Format a date and time such as "2026-10-12T14:30:00" as "12/10 às 14:30". */
export function formatVisitTime(value: string): string {
    const date = new Date(value);

    return `${new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(date)} às ${new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' }).format(date)}`;
}

export const saleDocumentTypeLabels: Record<SaleDocumentType, string> = {
    signed_contract: 'Contrato assinado',
    down_payment_receipt: 'Recibo do sinal',
    deed: 'Escritura',
    property_registration: 'Matrícula do imóvel',
    buyer_document: 'Documento do comprador',
    other: 'Outro',
};
