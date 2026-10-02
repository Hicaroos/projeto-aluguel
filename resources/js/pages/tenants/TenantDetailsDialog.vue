<script setup lang="ts">
import { CalendarDays, IdCard, Mail, Pencil, Phone } from '@lucide/vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { getInitials } from '@/composables/useInitials';
import { formatDate, formatDocument, formatPhone } from '@/lib/formatters';
import type { Tenant } from '@/types';

defineProps<{
    tenant: Tenant | null;
}>();

const emit = defineEmits<{
    close: [];
    edit: [tenant: Tenant];
}>();
</script>

<template>
    <Dialog :open="!!tenant" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="tenant">
            <DialogHeader class="flex-row items-center gap-4 text-left">
                <Avatar class="size-14">
                    <AvatarFallback
                        class="bg-primary/10 text-lg font-semibold text-primary"
                    >
                        {{ getInitials(tenant.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 space-y-1 pr-6">
                    <DialogTitle class="truncate">{{
                        tenant.name
                    }}</DialogTitle>
                    <DialogDescription>Inquilino</DialogDescription>
                </div>
            </DialogHeader>

            <ul class="divide-y rounded-lg border">
                <li class="flex items-center gap-3 p-3">
                    <IdCard class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">CPF/CNPJ</p>
                        <p class="text-sm font-medium tabular-nums">
                            {{ formatDocument(tenant.cpf_cnpj) }}
                        </p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <Mail class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">E-mail</p>
                        <a
                            v-if="tenant.email"
                            :href="`mailto:${tenant.email}`"
                            class="block truncate text-sm font-medium text-primary hover:underline"
                            >{{ tenant.email }}</a
                        >
                        <p v-else class="text-sm font-medium">—</p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <Phone class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Telefone</p>
                        <a
                            v-if="tenant.phone"
                            :href="`tel:${tenant.phone}`"
                            class="text-sm font-medium text-primary tabular-nums hover:underline"
                            >{{ formatPhone(tenant.phone) }}</a
                        >
                        <p v-else class="text-sm font-medium">—</p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <CalendarDays
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            Cadastrado em
                        </p>
                        <p class="text-sm font-medium">
                            {{ formatDate(tenant.created_at) }}
                        </p>
                    </div>
                </li>
            </ul>

            <DialogFooter class="border-t pt-6">
                <DialogClose as-child>
                    <Button variant="outline">Fechar</Button>
                </DialogClose>
                <Button @click="emit('edit', tenant)">
                    <Pencil class="size-4" />
                    Editar inquilino
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
