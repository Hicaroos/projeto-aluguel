import { badgeTones } from '@/lib/badge-tones';
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
    active: badgeTones.success,
    pending: badgeTones.warning,
    expired: badgeTones.danger,
    inactive: badgeTones.neutral,
};
