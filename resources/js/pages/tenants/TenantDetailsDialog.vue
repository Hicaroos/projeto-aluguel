<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Tenant } from '@/types';

defineProps<{
    tenant: Tenant | null;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Dialog :open="!!tenant" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="tenant">
            <DialogHeader>
                <DialogTitle>Detalhes do inquilino</DialogTitle>
            </DialogHeader>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div class="col-span-2">
                    <dt class="text-muted-foreground">Nome</dt>
                    <dd>{{ tenant.name }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">CPF/CNPJ</dt>
                    <dd>{{ tenant.cpf_cnpj ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Telefone</dt>
                    <dd>{{ tenant.phone ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-muted-foreground">E-mail</dt>
                    <dd>{{ tenant.email ?? '—' }}</dd>
                </div>
            </dl>
        </DialogContent>
    </Dialog>
</template>
