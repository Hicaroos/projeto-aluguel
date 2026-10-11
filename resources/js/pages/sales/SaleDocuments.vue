<script setup lang="ts">
import SaleDocumentController from '@/actions/App/Http/Controllers/SaleDocumentController';
import AttachedDocuments from '@/components/AttachedDocuments.vue';
import { usePermissions } from '@/composables/usePermissions';
import { saleDocumentTypeLabels } from '@/lib/sales-labels';
import type { AttachedDocument, Sale } from '@/types';

defineProps<{
    sale: Sale;
}>();

const can = usePermissions();
</script>

<template>
    <AttachedDocuments
        :documents="sale.documents ?? []"
        :type-labels="saleDocumentTypeLabels"
        primary-type="signed_contract"
        :upload-url="SaleDocumentController.store(sale).url"
        :delete-url="
            (document: AttachedDocument) =>
                SaleDocumentController.destroy(document).url
        "
        :can-manage="can.manageSales"
        empty-hint="Contrato assinado, recibo do sinal, escritura…"
    />
</template>
