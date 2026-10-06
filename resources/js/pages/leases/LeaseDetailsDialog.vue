<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    FileCheck,
    FileText,
    House,
    NotebookPen,
    Pencil,
    ReceiptText,
    Scale,
    ShieldCheck,
    UserRound,
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
import {
    formatDate,
    formatDocument,
    formatPhone,
    monthsBetween,
} from '@/lib/formatters';
import {
    adjustmentIndexLabels,
    guaranteeTypeLabels,
    leaseDeadlineHint,
    leaseStatusBadgeClasses,
    leaseStatusDotClasses,
    leasePurposeLabels,
    leaseStatusLabels,
} from '@/lib/lease-labels';
import {
    formatPersonAddress,
    formatPersonQualification,
} from '@/lib/person-labels';
import { index as paymentsIndex } from '@/routes/payments';
import { index as propertiesIndex } from '@/routes/properties';
import { index as tenantsIndex } from '@/routes/tenants';
import type { Lease } from '@/types';

defineProps<{
    lease: Lease | null;
}>();

const emit = defineEmits<{
    close: [];
    edit: [lease: Lease];
    finish: [lease: Lease];
}>();

const formatPercent = (value: string): string =>
    `${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;
</script>

<template>
    <Dialog :open="!!lease" @update:open="(open) => !open && emit('close')">
        <DialogContent
            v-if="lease"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
        >
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
                    <template v-if="lease.guarantee_type === 'surety_bond'">
                        <div>
                            <dt class="text-muted-foreground">Seguradora</dt>
                            <dd class="font-medium">
                                {{ lease.surety_insurer || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Apólice</dt>
                            <dd class="font-medium">
                                {{ lease.surety_policy_number || '—' }}
                            </dd>
                        </div>
                    </template>
                </dl>

                <div
                    v-if="lease.guarantor"
                    class="space-y-1 rounded-lg border bg-muted/40 p-3 text-sm"
                >
                    <p class="font-medium">
                        {{ lease.guarantor.name }}
                        <span
                            v-if="lease.guarantor.cpf_cnpj"
                            class="font-normal text-muted-foreground tabular-nums"
                        >
                            · {{ formatDocument(lease.guarantor.cpf_cnpj) }}
                        </span>
                    </p>
                    <p
                        v-if="formatPersonQualification(lease.guarantor)"
                        class="text-muted-foreground"
                    >
                        {{ formatPersonQualification(lease.guarantor) }}
                    </p>
                    <p
                        v-if="formatPersonAddress(lease.guarantor)"
                        class="text-muted-foreground"
                    >
                        {{ formatPersonAddress(lease.guarantor) }}
                    </p>
                    <p
                        v-if="lease.guarantor.phone"
                        class="text-muted-foreground tabular-nums"
                    >
                        {{ formatPhone(lease.guarantor.phone) }}
                    </p>
                    <p
                        v-if="lease.guarantor.spouse_name"
                        class="text-muted-foreground"
                    >
                        Cônjuge: {{ lease.guarantor.spouse_name }}
                    </p>
                    <p
                        v-if="lease.guarantor.property_registration"
                        class="text-muted-foreground"
                    >
                        Imóvel em garantia:
                        {{ lease.guarantor.property_registration }}
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <Scale class="size-3.5" />
                    Termos do contrato
                </h3>
                <dl class="grid grid-cols-3 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Finalidade</dt>
                        <dd class="font-medium">
                            {{ leasePurposeLabels[lease.purpose] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Reajuste</dt>
                        <dd class="font-medium">
                            {{ adjustmentIndexLabels[lease.adjustment_index] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Multa rescisória</dt>
                        <dd class="font-medium tabular-nums">
                            {{ lease.termination_fee_months }}
                            {{
                                lease.termination_fee_months === 1
                                    ? 'aluguel'
                                    : 'aluguéis'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Multa por atraso</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatPercent(lease.late_fee_percent) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Juros ao mês</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatPercent(lease.monthly_interest_percent) }}
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

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            propertiesIndex({
                                query: { show: lease.property_id },
                            })
                        "
                    >
                        <House class="size-4" />
                        Ver imóvel
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            tenantsIndex({ query: { show: lease.tenant_id } })
                        "
                    >
                        <UserRound class="size-4" />
                        Ver inquilino
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="paymentsIndex({ query: { lease: lease.id } })">
                        <ReceiptText class="size-4" />
                        Ver cobranças
                    </Link>
                </Button>
            </div>

            <DialogFooter class="border-t pt-6">
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
