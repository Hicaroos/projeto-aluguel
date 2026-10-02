import type { Paginator } from './pagination';

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

export type TenantPaginator = Paginator<Tenant>;
