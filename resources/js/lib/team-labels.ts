import type { Role, TeamMemberStatus } from '@/types';

/** What each role may do, shown when choosing a role. */
export const roleDescriptions: Record<Role, string> = {
    admin: 'Tudo, inclusive equipe, unidades e dados da imobiliária. Vê todas as unidades.',
    general:
        'Imóveis, inquilinos, contratos e todo o financeiro. Não mexe na equipe nem nas unidades.',
    agent: 'Imóveis, inquilinos e contratos. Registra recebimentos, mas não vê despesas nem resultados.',
    finance:
        'Cobranças, recibos, despesas e resultados. Consulta imóveis e contratos sem alterá-los.',
};

export const teamMemberStatusLabels: Record<TeamMemberStatus, string> = {
    active: 'Ativo',
    pending: 'Convite pendente',
    expired: 'Convite expirado',
    inactive: 'Desativado',
};

export const teamMemberStatusClasses: Record<TeamMemberStatus, string> = {
    active: 'border-primary/25 bg-primary/10 text-primary',
    pending:
        'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    expired:
        'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-400',
    inactive: 'text-muted-foreground',
};
