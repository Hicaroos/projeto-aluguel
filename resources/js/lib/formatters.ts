function onlyDigits(value: string): string {
    return value.replace(/\D/g, '');
}

export function formatDocument(value: string | null): string {
    if (!value) {
        return '—';
    }

    const digits = onlyDigits(value);

    if (digits.length === 11) {
        return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }

    if (digits.length === 14) {
        return digits.replace(
            /(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/,
            '$1.$2.$3/$4-$5',
        );
    }

    return value;
}

export function formatPhone(value: string | null): string {
    if (!value) {
        return '—';
    }

    const digits = onlyDigits(value);

    if (digits.length === 11) {
        return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }

    if (digits.length === 10) {
        return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }

    return value;
}

export function formatZipCode(value: string): string {
    const digits = onlyDigits(value);

    return digits.length === 8
        ? digits.replace(/(\d{5})(\d{3})/, '$1-$2')
        : value;
}

export function formatDate(value: string): string {
    return new Intl.DateTimeFormat('pt-BR').format(new Date(value));
}
