import type { PersonQualification } from './person';

export type Owner = PersonQualification & {
    id: number;
    account_id: number;
    name: string;
    cpf_cnpj: string | null;
    email: string | null;
    phone: string | null;
    pix_key: string | null;
};
