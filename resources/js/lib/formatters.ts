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

function parseDate(value: string): Date {
    const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (dateOnly) {
        return new Date(
            Number(dateOnly[1]),
            Number(dateOnly[2]) - 1,
            Number(dateOnly[3]),
        );
    }

    return new Date(value);
}

export function formatDate(value: string): string {
    return new Intl.DateTimeFormat('pt-BR').format(parseDate(value));
}

export function daysUntil(value: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round(
        (parseDate(value).getTime() - today.getTime()) / 86_400_000,
    );
}

export function monthsBetween(start: string, end: string): number {
    const startDate = parseDate(start);
    const endDate = parseDate(end);

    return (
        (endDate.getFullYear() - startDate.getFullYear()) * 12 +
        endDate.getMonth() -
        startDate.getMonth()
    );
}

export function todayIsoDate(): string {
    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${today.getFullYear()}-${month}-${day}`;
}

export function formatMonthYear(
    value: string,
    style: 'long' | 'short' = 'long',
): string {
    const formatted = new Intl.DateTimeFormat('pt-BR', {
        month: style,
        year: 'numeric',
    }).format(parseDate(value));

    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
}
