<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    FileText,
    House,
    IdCard,
    Mail,
    MapPin,
    Pencil,
    Phone,
    UserRound,
} from '@lucide/vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatDocument, formatPhone } from '@/lib/formatters';
import { leaseStatusBadgeClasses, leaseStatusLabels } from '@/lib/lease-labels';
import {
    formatPersonAddress,
    formatPersonQualification,
} from '@/lib/person-labels';
import { index as leasesIndex } from '@/routes/leases';
import { index as propertiesIndex } from '@/routes/properties';
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
        <DialogContent
            v-if="tenant"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
        >
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
                        <p class="text-xs text-muted-foreground">Celular</p>
                        <a
                            v-if="tenant.phone"
                            :href="`tel:${tenant.phone}`"
                            class="text-sm font-medium text-primary tabular-nums hover:underline"
                            >{{ formatPhone(tenant.phone) }}</a
                        >
                        <p v-else class="text-sm font-medium">—</p>
                    </div>
                </li>
                <li
                    v-if="formatPersonQualification(tenant)"
                    class="flex items-center gap-3 p-3"
                >
                    <UserRound class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            Qualificação
                        </p>
                        <p class="text-sm font-medium">
                            {{ formatPersonQualification(tenant) }}
                        </p>
                    </div>
                </li>
                <li
                    v-if="formatPersonAddress(tenant)"
                    class="flex items-center gap-3 p-3"
                >
                    <MapPin class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            Endereço atual
                        </p>
                        <p class="text-sm font-medium">
                            {{ formatPersonAddress(tenant) }}
                        </p>
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

            <div class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <FileText class="size-3.5" />
                    Contratos
                </h3>

                <p
                    v-if="!tenant.leases?.length"
                    class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    Este inquilino ainda não tem contratos.
                </p>

                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="lease in tenant.leases"
                        :key="lease.id"
                        class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <span class="min-w-0 truncate">
                                    {{ lease.property.street }},
                                    {{ lease.property.number }}
                                </span>
                                <Badge
                                    variant="outline"
                                    :class="
                                        leaseStatusBadgeClasses[lease.status]
                                    "
                                >
                                    {{ leaseStatusLabels[lease.status] }}
                                </Badge>
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ formatDate(lease.start_date) }} a
                                {{ formatDate(lease.end_date) }} ·
                                {{ formatCurrency(lease.amount) }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="
                                        propertiesIndex({
                                            query: { show: lease.property_id },
                                        })
                                    "
                                >
                                    <House class="size-4" />
                                    Imóvel
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="
                                        leasesIndex({
                                            query: { show: lease.id },
                                        })
                                    "
                                >
                                    <FileText class="size-4" />
                                    Contrato
                                </Link>
                            </Button>
                        </div>
                    </li>
                </ul>
            </div>

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
