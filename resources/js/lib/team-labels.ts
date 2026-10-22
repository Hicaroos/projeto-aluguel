import { badgeTones } from '@/lib/badge-tones';
import type { Role, TeamMemberStatus } from '@/types';

/** What each role may do, shown when choosing a role. */
export const roleDescriptions: Record<Role, string> = {
    admin: 'Tudo, inclusive equipe, unidades, dados da imobiliária e vitrine. Vê todas as unidades.',
    general:
        'O dia a dia: imóveis, inquilinos, contratos, financeiro, comercial e mensagens do WhatsApp. Não mexe na equipe, nas unidades, nos dados da imobiliária nem na vitrine.',
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
