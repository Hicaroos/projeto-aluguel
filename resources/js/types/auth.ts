export type Account = {
    id: number;
    name: string;
    legal_name: string | null;
    type: string;
    document: string | null;
    creci: string | null;
    phone: string | null;
    email: string | null;
    zip_code: string | null;
    street: string | null;
    number: string | null;
    complement: string | null;
    neighborhood: string | null;
    city: string | null;
    state: string | null;
    logo_url: string | null;
    plan: string | null;
    status: string;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    account?: Account | null;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    can: {
        /** Manage the agency: its details, branches and team. */
        manageAgency: boolean;
    };
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
