/**
 * Get the link that opens a WhatsApp chat with sales, with the message already typed. Without a
 * number set, WhatsApp asks who to send the message to.
 */
export function salesWhatsappUrl(
    number: string | null,
    message: string,
): string {
    const recipient = number ? `55${number.replace(/\D/g, '')}` : '';

    return `https://wa.me/${recipient}?text=${encodeURIComponent(message)}`;
}
