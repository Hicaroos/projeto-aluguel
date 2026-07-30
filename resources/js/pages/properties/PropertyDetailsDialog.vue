<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/currency';
import {
    propertyStatusLabels,
    propertyTypeLabels,
} from '@/lib/property-labels';
import type { Property } from '@/types';

defineProps<{
    property: Property | null;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Dialog :open="!!property" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="property">
            <DialogHeader>
                <DialogTitle>Detalhes do imóvel</DialogTitle>
            </DialogHeader>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div class="col-span-2">
                    <dt class="text-muted-foreground">Endereço</dt>
                    <dd>
                        {{ property.street }}, {{ property.number }}
                        <span v-if="property.complement"
                            >- {{ property.complement }}</span
                        >
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Bairro</dt>
                    <dd>{{ property.neighborhood }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Cidade/UF</dt>
                    <dd>{{ property.city }}/{{ property.state }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">CEP</dt>
                    <dd>{{ property.zip_code }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Tipo</dt>
                    <dd>{{ propertyTypeLabels[property.type] }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Valor do aluguel</dt>
                    <dd>{{ formatCurrency(property.rent_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Situação</dt>
                    <dd>
                        <Badge variant="outline">{{
                            propertyStatusLabels[property.status]
                        }}</Badge>
                    </dd>
                </div>
            </dl>
        </DialogContent>
    </Dialog>
</template>
