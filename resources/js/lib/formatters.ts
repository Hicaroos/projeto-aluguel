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

/** Format a size in bytes as e.g. "850 KB" or "2,4 MB". */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
}

/** The app's "today" sent by the server, which may be a simulated date (APP_FAKE_TODAY). */
let serverToday: string | null = null;

export function setServerToday(value: string | null | undefined): void {
    serverToday = value ?? null;
}

/**
 * Get today at midnight, following the server's date so a simulated date also applies in the browser.
 */
export function currentDate(): Date {
    const today = serverToday ? parseDate(serverToday) : new Date();
    today.setHours(0, 0, 0, 0);

    return today;
}

export function daysUntil(value: string): number {
    const today = currentDate();

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
    const today = currentDate();
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

/**
 * Mask a CPF while it is typed: 000.000.000-00.
 */
export function maskCpf(value: string): string {
    const digits = onlyDigits(value).slice(0, 11);

    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
}

/**
 * Mask a CPF or CNPJ while it is typed, switching to 00.000.000/0000-00 after 11 digits.
 */
export function maskDocument(value: string): string {
    const digits = onlyDigits(value).slice(0, 14);

    if (digits.length <= 11) {
        return maskCpf(digits);
    }

    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

/**
 * Mask a phone with area code while it is typed: (00) 0000-0000, or (00) 00000-0000 for mobiles.
 */
export function maskPhone(value: string): string {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length <= 2) {
        return digits.length ? `(${digits}` : '';
    }

    const area = digits.slice(0, 2);
    const number = digits.slice(2);
    const split = number.length > 8 ? 5 : 4;

    return number.length > split
        ? `(${area}) ${number.slice(0, split)}-${number.slice(split)}`
        : `(${area}) ${number}`;
}

export function maskZipCode(value: string): string {
    const digits = onlyDigits(value).slice(0, 8);

    return digits.length > 5
        ? `${digits.slice(0, 5)}-${digits.slice(5)}`
        : digits;
}
