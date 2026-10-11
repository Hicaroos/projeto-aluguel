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

export const visitStatusLabels: Record<VisitStatus, string> = {
    scheduled: 'Agendada',
    done: 'Realizada',
    canceled: 'Cancelada',
};

export const visitStatusBadgeClasses: Record<VisitStatus, string> = {
    scheduled:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
    done: 'border-primary/25 bg-primary/10 text-primary',
    canceled: 'text-muted-foreground',
};

export const proposalStatusLabels: Record<ProposalStatus, string> = {
    pending: 'Em análise',
    accepted: 'Aceita',
    rejected: 'Recusada',
    countered: 'Contraproposta',
};

export const proposalStatusBadgeClasses: Record<ProposalStatus, string> = {
    pending: 'border-attention-border bg-attention text-attention-foreground',
    accepted: 'border-primary/25 bg-primary/10 text-primary',
    rejected:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300',
    countered:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
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
