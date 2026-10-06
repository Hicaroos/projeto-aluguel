import type { MaritalStatus, PersonQualification } from '@/types';

export const maritalStatusLabels: Record<MaritalStatus, string> = {
    single: 'Solteiro(a)',
    married: 'Casado(a)',
    stable_union: 'União estável',
    divorced: 'Divorciado(a)',
    separated: 'Separado(a)',
    widowed: 'Viúvo(a)',
};

/**
 * Summarize the filled qualification details, e.g. "brasileira · Casado(a) · Engenheira · RG 12.345.678-9".
 */
export function formatPersonQualification(
    person: PersonQualification,
): string | null {
    const parts = [
        person.nationality,
        person.marital_status
            ? maritalStatusLabels[person.marital_status]
            : null,
        person.profession,
        person.rg ? `RG ${person.rg}` : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' · ') : null;
}

/**
 * Join the filled address parts into a single line, or return null when no address was given.
 */
export function formatPersonAddress(
    person: PersonQualification,
): string | null {
    const street = [person.street, person.number, person.complement]
        .filter(Boolean)
        .join(', ');
    const city = [person.city, person.state].filter(Boolean).join('/');
    const parts = [street, person.neighborhood, city].filter(Boolean);

    return parts.length > 0 ? parts.join(' — ') : null;
}
