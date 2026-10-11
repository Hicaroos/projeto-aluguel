<script setup lang="ts">
import LeaseDocumentController from '@/actions/App/Http/Controllers/LeaseDocumentController';
import AttachedDocuments from '@/components/AttachedDocuments.vue';
import { usePermissions } from '@/composables/usePermissions';
import { leaseDocumentTypeLabels } from '@/lib/lease-labels';
import type { AttachedDocument, Lease } from '@/types';

defineProps<{
    lease: Lease;
}>();

const can = usePermissions();
</script>

<template>
    <AttachedDocuments
        :documents="lease.documents ?? []"
        :type-labels="leaseDocumentTypeLabels"
        primary-type="signed_contract"
        :upload-url="LeaseDocumentController.store(lease).url"
        :delete-url="
            (document: AttachedDocument) =>
                LeaseDocumentController.destroy(document).url
        "
        :can-manage="can.manageRentals"
        empty-hint="Contrato assinado, vistorias, aditivos…"
    />
</template>
