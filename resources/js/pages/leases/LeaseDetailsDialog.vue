<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    FileCheck,
    FileText,
    NotebookPen,
    Pencil,
    ReceiptText,
    ShieldCheck,
    Wallet,
} from '@lucide/vue';
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
import { formatCurrency } from '@/lib/currency';
import { formatDate, monthsBetween } from '@/lib/formatters';
import {
    guaranteeTypeLabels,
    leaseDeadlineHint,
    leaseStatusBadgeClasses,
    leaseStatusDotClasses,
    leaseStatusLabels,
} from '@/lib/lease-labels';
import { index as paymentsIndex } from '@/routes/payments';
import type { Lease } from '@/types';

defineProps<{
    lease: Lease | null;
}>();

const emit = defineEmits<{
    close: [];
    edit: [lease: Lease];
    finish: [lease: Lease];
}>();
</script>

<template>
    <Dialog :open="!!lease" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="lease" class="sm:max-w-xl">
            <DialogHeader class="flex-row items-start gap-4 text-left">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <FileText class="size-6" />
                </div>
                <div class="min-w-0 space-y-1.5 pr-6">
                    <DialogTitle class="leading-snug">
                        {{ lease.tenant.name }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ lease.property.street }},
                        {{ lease.property.number }} ·
                        {{ lease.property.city }}/{{ lease.property.state }}
                    </DialogDescription>
                    <Badge
                        variant="outline"
                        :class="leaseStatusBadgeClasses[lease.status]"
                    >
                        <span
                            class="size-1.5 rounded-full"
                            :class="leaseStatusDotClasses[lease.status]"
                        />
                        {{ leaseStatusLabels[lease.status] }}
                    </Badge>
                </div>
            </DialogHeader>

            <div
                class="flex items-center gap-4 rounded-lg border bg-muted/40 p-4"
            >
                <div
                    class="flex size-10 items-center justify-center rounded-full bg-background text-primary shadow-xs"
                >
                    <Wallet class="size-5" />
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        Valor do aluguel
                    </p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ formatCurrency(lease.amount) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >/mês · vence todo dia {{ lease.due_day }}</span
                        >
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <CalendarDays class="size-3.5" />
                    Vigência
                </h3>
                <dl class="grid grid-cols-3 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Início</dt>
                        <dd class="font-medium">
                            {{ formatDate(lease.start_date) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Término</dt>
                        <dd class="font-medium">
                            {{ formatDate(lease.end_date) }}
                        </dd>
                        <dd
                            v-if="leaseDeadlineHint(lease)"
                            class="text-xs font-medium"
                            :class="leaseDeadlineHint(lease)?.class"
                        >
                            {{ leaseDeadlineHint(lease)?.label }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Duração</dt>
                        <dd class="font-medium">
                            {{
                                monthsBetween(lease.start_date, lease.end_date)
                            }}
                            meses
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <ShieldCheck class="size-3.5" />
                    Garantia
                </h3>
                <dl class="grid grid-cols-3 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Tipo</dt>
                        <dd class="font-medium">
                            {{ guaranteeTypeLabels[lease.guarantee_type] }}
                        </dd>
                    </div>
                    <div v-if="lease.deposit_amount">
                        <dt class="text-muted-foreground">Valor da caução</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatCurrency(lease.deposit_amount) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div v-if="lease.notes" class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <NotebookPen class="size-3.5" />
                    Observações
                </h3>
                <p
                    class="rounded-lg border bg-muted/40 p-3 text-sm whitespace-pre-line"
                >
                    {{ lease.notes }}
                </p>
            </div>

            <DialogFooter class="border-t pt-6">
                <Button variant="ghost" class="sm:mr-auto" as-child>
                    <Link :href="paymentsIndex({ query: { lease: lease.id } })">
                        <ReceiptText class="size-4" />
                        Ver cobranças
                    </Link>
                </Button>
                <template v-if="lease.status === 'active'">
                    <Button variant="outline" @click="emit('finish', lease)">
                        <FileCheck class="size-4" />
                        Finalizar contrato
                    </Button>
                    <Button @click="emit('edit', lease)">
                        <Pencil class="size-4" />
                        Editar contrato
                    </Button>
                </template>
                <DialogClose v-else as-child>
                    <Button variant="outline">Fechar</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
