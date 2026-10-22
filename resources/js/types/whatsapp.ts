/** A WhatsApp message written from the account's text, opened on the user's own phone. */
export type WhatsAppMessage = {
    template: MessageTemplateKey;
    /** Label of the button that opens it, e.g. "Cobrar atraso". */
    action_label: string;
    /** Who receives it, e.g. "inquilino". */
    recipient: string;
    /** Null when the recipient has no phone WhatsApp can call. */
    phone: string | null;
    text: string;
    url: string | null;
    /** The last message opened about the same record, by anyone of the team. */
    last_sent: {
        sent_at: string;
        user: string | null;
        label: string;
    } | null;
};

export type MessageTemplateKey =
    | 'payment_reminder'
    | 'payment_overdue'
    | 'payout_notice'
    | 'adjustment_notice';

/** A message as the settings show it, to be rewritten. */
export type MessageTemplateSetting = {
    key: MessageTemplateKey;
    label: string;
    description: string;
    text: string;
    default: string;
    /** The variables the message may use, with the label shown on each tag. */
    variables: { key: string; label: string }[];
    /** Example values of the variables, to preview the message. */
    sample: Record<string, string>;
};
