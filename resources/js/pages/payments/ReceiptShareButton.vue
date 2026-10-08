<script setup lang="ts">
import { Download, Share2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useFileSharing } from '@/composables/useFileSharing';
import { formatCurrency } from '@/lib/currency';
import { formatDate } from '@/lib/formatters';
import {
    paymentReferenceLabel,
    receiptTotalAmount,
} from '@/lib/payment-labels';
import { pdf } from '@/routes/receipts';
import type { Payment, Receipt } from '@/types';

const props = withDefaults(
    defineProps<{
        receipt: Pick<
            Receipt,
            'id' | 'date' | 'amount' | 'late_fee_amount' | 'interest_amount'
        >;
        payment: Pick<Payment, 'type' | 'description' | 'reference_month'>;
        /** Show only the icon, e.g. in a compact list. */
        iconOnly?: boolean;
    }>(),
    { iconOnly: false },
);

const { supportsSharing, isPreparing, share, download } = useFileSharing({
    mimeType: 'application/pdf',
    downloadedMessage: () => 'Recibo baixado em PDF.',
});

const label = computed(() =>
    supportsSharing ? 'Compartilhar recibo' : 'Baixar recibo (PDF)',
);

/** What the receipt is for, e.g. "Recibo de aluguel — Outubro de 2026". */
const title = computed(
    () =>
        `${props.payment.type === 'extra' ? 'Recibo' : 'Recibo de aluguel'} — ${paymentReferenceLabel(props.payment)}`,
);

/** Message sent along with the PDF, e.g. to the tenant on WhatsApp. */
const shareText = computed(() =>
    [
        title.value,
        `Valor recebido: ${formatCurrency(receiptTotalAmount(props.receipt))} em ${formatDate(props.receipt.date)}`,
    ].join('\n'),
);

/** File name from the reference, e.g. "recibo-outubro-de-2026". */
const fileName = computed(
    () =>
        `recibo-${paymentReferenceLabel(props.payment)}`
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '') || 'recibo',
);

function shareReceipt() {
    const file = { id: props.receipt.id, url: pdf(props.receipt).url };

    if (!supportsSharing) {
        download([file], fileName.value);

        return;
    }

    share([file], {
        fileName: fileName.value,
        title: title.value,
        text: shareText.value,
    });
}
</script>

<template>
    <Button
        variant="outline"
        :size="iconOnly ? 'icon-sm' : 'default'"
        :disabled="isPreparing"
        :title="iconOnly ? label : undefined"
        @click="shareReceipt"
    >
        <Spinner v-if="isPreparing" />
        <component
            :is="supportsSharing ? Share2 : Download"
            v-else
            class="size-4"
        />
        <span :class="{ 'sr-only': iconOnly }">{{
            supportsSharing ? 'Compartilhar' : 'Baixar PDF'
        }}</span>
    </Button>
</template>
