export type Role = 'admin' | 'general' | 'agent' | 'finance';

export type TeamMemberStatus = 'active' | 'pending' | 'expired' | 'inactive';

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: Role;
    /** Empty for administrators, who work in every branch. */
    branches: { id: number; name: string }[];
    status: TeamMemberStatus;
    invited_at: string | null;
    last_login_at: string | null;
    /** Created the account: always an active administrator. */
    is_owner: boolean;
    is_self: boolean;
};

export type RoleOption = {
    value: Role;
    label: string;
    sees_every_branch: boolean;
};

/** The invitation link handed to the page after inviting someone. */
export type InvitationLink = {
    name: string;
    url: string;
    expires_in_days: number;
};
