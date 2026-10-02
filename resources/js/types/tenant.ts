export type Tenant = {
    id: number;
    account_id: number;
    name: string;
    cpf_cnpj: string | null;
    email: string | null;
    phone: string | null;
    created_at: string;
    updated_at: string;
};

export type TenantPaginator = {
    data: Tenant[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
};
