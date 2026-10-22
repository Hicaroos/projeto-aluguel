import { router } from '@inertiajs/vue3';
import type { JSONContent } from '@tiptap/vue-3';
import { formatDate } from '@/lib/formatters';
import type { WhatsAppMessage } from '@/types';

/**
 * Record in the background that a WhatsApp message was opened, while the link opens it on the
 * user's phone, so the team sees who already sent one and when.
 */
export function recordWhatsAppMessage(url: string): void {
    router.post(url, {}, { preserveScroll: true, preserveState: true });
}

/** Describe the last message sent, e.g. "Cobrança de atraso em 20/10/2026, por Ana". */
export function lastSentLabel(message: WhatsAppMessage): string | null {
    const lastSent = message.last_sent;

    if (!lastSent) {
        return null;
    }

    return `${lastSent.label} em ${formatDate(lastSent.sent_at)}${lastSent.user ? `, por ${lastSent.user}` : ''}`;
}

/**
 * Fill the {variables} of a message, leaving out the lines where one of them is blank, as the
 * server does when it writes the message.
 */
export function renderMessageTemplate(
    text: string,
    values: Record<string, string>,
): string {
    return text
        .split('\n')
        .filter(
            (line) =>
                ![...line.matchAll(/\{(\w+)\}/g)].some(
                    ([, key]) => values[key] === '',
                ),
        )
        .map((line) =>
            line.replace(
                /\{(\w+)\}/g,
                (match, key: string) => values[key] ?? match,
            ),
        )
        .join('\n')
        .trim();
}

/** Split a message into plain and *bold* parts, the way WhatsApp shows it. */
export function whatsAppSegments(
    text: string,
): { text: string; bold: boolean }[] {
    return text
        .split(/(\*[^*\n]+\*)/)
        .filter((part) => part !== '')
        .map((part) =>
            /^\*[^*\n]+\*$/.test(part)
                ? { text: part.slice(1, -1), bold: true }
                : { text: part, bold: false },
        );
}

/**
 * Turn a message into the content of the message editor: a paragraph for each line, with the
 * *bold* parts in bold and each known {variable} as a tag.
 */
export function messageToDocument(
    text: string,
    labels: Record<string, string>,
): JSONContent {
    return {
        type: 'doc',
        content: text.split('\n').map((line) => ({
            type: 'paragraph',
            content: whatsAppSegments(line).flatMap(({ text: part, bold }) =>
                part
                    .split(/(\{\w+\})/)
                    .filter((piece) => piece !== '')
                    .map((piece): JSONContent => {
                        const key = /^\{(\w+)\}$/.exec(piece)?.[1];
                        const marks = bold ? [{ type: 'bold' }] : [];

                        return key !== undefined && key in labels
                            ? {
                                  type: 'contractVariable',
                                  attrs: { key },
                                  marks,
                              }
                            : { type: 'text', text: piece, marks };
                    }),
            ),
        })),
    };
}

/**
 * Turn the content of the message editor back into the message: a line for each paragraph,
 * bold text between asterisks and each tag as its {variable}.
 */
export function documentToMessage(document: JSONContent): string {
    return (document.content ?? [])
        .map((paragraph) => {
            let line = '';
            let isBold = false;

            for (const node of paragraph.content ?? []) {
                const bold =
                    node.marks?.some((mark) => mark.type === 'bold') ?? false;

                if (bold !== isBold) {
                    line += '*';
                    isBold = bold;
                }

                if (node.type === 'text') {
                    line += node.text ?? '';
                } else if (node.type === 'contractVariable') {
                    line += `{${node.attrs?.key}}`;
                } else if (node.type === 'hardBreak') {
                    line += '\n';
                }
            }

            return isBold ? `${line}*` : line;
        })
        .join('\n');
}
