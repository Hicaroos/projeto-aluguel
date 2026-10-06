export type ContractTemplate = {
    id: number;
    name: string;
    body: string;
    is_default: boolean;
    updated_at: string;
};

export type ContractTemplateOption = Pick<
    ContractTemplate,
    'id' | 'name' | 'is_default'
>;

/** A placeholder the template editor can insert, filled with the lease details in the PDF. */
export type ContractVariableOption = {
    key: string;
    label: string;
    group: string;
};
