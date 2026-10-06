export type MaritalStatus =
    | 'single'
    | 'married'
    | 'stable_union'
    | 'divorced'
    | 'separated'
    | 'widowed';

/** Personal details a lease contract needs to identify a person. */
export type PersonQualification = {
    rg: string | null;
    nationality: string | null;
    marital_status: MaritalStatus | null;
    profession: string | null;
    zip_code: string | null;
    street: string | null;
    number: string | null;
    complement: string | null;
    neighborhood: string | null;
    city: string | null;
    state: string | null;
};
