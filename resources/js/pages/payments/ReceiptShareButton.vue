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

const number = computed(() => String(props.receipt.id).padStart(6, '0'));
const label = computed(() =>
    supportsSharing ? 'Compartilhar recibo' : 'Baixar recibo (PDF)',
);

/** Message sent along with the PDF, e.g. to the tenant on WhatsApp. */
const shareText = computed(() =>
    [
        `Recibo de aluguel nº ${number.value}`,
        `Referência: ${paymentReferenceLabel(props.payment)}`,
        `Valor recebido: ${formatCurrency(receiptTotalAmount(props.receipt))} em ${formatDate(props.receipt.date)}`,
    ].join('\n'),
);

function shareReceipt() {
    const file = { id: props.receipt.id, url: pdf(props.receipt).url };
    const fileName = `recibo-${number.value}`;

    if (!supportsSharing) {
        download([file], fileName);

        return;
    }

    share([file], {
        fileName,
        title: `Recibo ${number.value}`,
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
