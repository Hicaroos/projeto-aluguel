<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarPlus,
    ChevronDown,
    FileCheck,
    FileDown,
    FileText,
    House,
    NotebookPen,
    Pencil,
    PiggyBank,
    ReceiptText,
    Scale,
    ShieldCheck,
    Star,
    TrendingUp,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import {
    formatDate,
    formatDocument,
    formatPhone,
    monthsBetween,
} from '@/lib/formatters';
import {
    adjustmentIndexLabels,
    adjustmentStatusBadgeClasses,
    adjustmentStatusLabels,
    canSettleDeposit,
    depositUsed,
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
import LeaseDocuments from '@/pages/leases/LeaseDocuments.vue';
import { contract } from '@/routes/leases';
import { amendment as renewalAmendment } from '@/routes/leases/renewals';
import { index as paymentsIndex } from '@/routes/payments';
import { index as propertiesIndex } from '@/routes/properties';
import { index as tenantsIndex } from '@/routes/tenants';
import type { ContractTemplateOption, Lease } from '@/types';

withDefaults(
    defineProps<{
        lease: Lease | null;
        contractTemplates?: ContractTemplateOption[];
    }>(),
    { contractTemplates: () => [] },
);

const can = usePermissions();

const emit = defineEmits<{
    close: [];
    edit: [lease: Lease];
    finish: [lease: Lease];
    adjust: [lease: Lease];
    renew: [lease: Lease];
    'settle-deposit': [lease: Lease];
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
                    <div class="flex flex-wrap items-center gap-1.5">
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
                        <Badge
                            v-if="canSettleDeposit(lease)"
                            variant="outline"
                            class="border-attention-border bg-attention text-attention-foreground"
                        >
                            <PiggyBank class="size-3" />
                            Caução em aberto
                        </Badge>
                    </div>
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

                <ul
                    v-if="lease.renewals.length"
                    class="divide-y rounded-lg border"
                >
                    <li
                        v-for="renewal in lease.renewals"
                        :key="renewal.id"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 p-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                Renovado em
                                {{ formatDate(renewal.created_at) }}
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                Término
                                {{ formatDate(renewal.previous_end_date) }} →
                                {{ formatDate(renewal.new_end_date) }}
                                <template v-if="renewal.notes">
                                    · {{ renewal.notes }}
                                </template>
                            </p>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="renewalAmendment([lease, renewal]).url"
                                target="_blank"
                            >
                                <FileDown class="size-4" />
                                Termo aditivo (PDF)
                            </a>
                        </Button>
                    </li>
                </ul>
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
                    v-if="lease.deposit_settled_on"
                    class="flex gap-2 rounded-lg border bg-muted/40 p-3 text-sm"
                >
                    <PiggyBank class="mt-0.5 size-4 shrink-0 text-primary" />
                    <p>
                        Caução acertada em
                        {{ formatDate(lease.deposit_settled_on) }}:
                        <span class="font-medium tabular-nums">{{
                            formatCurrency(lease.deposit_refunded_amount ?? 0)
                        }}</span>
                        devolvidos ao inquilino<template
                            v-if="depositUsed(lease) > 0"
                        >
                            ({{ formatCurrency(depositUsed(lease)) }} abatidos
                            de pendências)</template
                        >.
                    </p>
                </div>
                <div
                    v-else-if="canSettleDeposit(lease)"
                    class="flex flex-col gap-3 rounded-lg border border-attention-border bg-attention p-3 text-sm sm:flex-row sm:items-center"
                >
                    <p class="flex-1 text-attention-foreground">
                        A caução ainda não foi acertada: abata as pendências e
                        registre a devolução ao inquilino.
                    </p>
                    <Button
                        v-if="can.manageFinance"
                        size="sm"
                        class="self-start sm:self-center"
                        @click="emit('settle-deposit', lease)"
                    >
                        <PiggyBank class="size-4" />
                        Acertar caução
                    </Button>
                </div>

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

            <div
                v-if="lease.next_adjustment_date || lease.adjustments.length"
                class="space-y-3"
            >
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <TrendingUp class="size-3.5" />
                    Reajuste anual
                </h3>

                <div
                    v-if="lease.next_adjustment_date"
                    class="flex flex-col gap-3 rounded-lg border bg-muted/40 p-3 sm:flex-row sm:items-center"
                >
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="text-sm">
                            Próximo reajuste
                            {{
                                lease.adjustment_index === 'negotiated'
                                    ? '(livre negociação)'
                                    : `pelo ${adjustmentIndexLabels[lease.adjustment_index]}`
                            }}
                            em
                            <span class="font-medium">{{
                                formatDate(lease.next_adjustment_date)
                            }}</span>
                        </p>
                        <Badge
                            v-if="lease.adjustment_status"
                            variant="outline"
                            :class="
                                adjustmentStatusBadgeClasses[
                                    lease.adjustment_status
                                ]
                            "
                        >
                            {{
                                adjustmentStatusLabels[lease.adjustment_status]
                            }}
                        </Badge>
                    </div>
                    <Button
                        v-if="lease.adjustment_status && can.manageRentals"
                        size="sm"
                        class="self-start sm:self-center"
                        @click="emit('adjust', lease)"
                    >
                        <TrendingUp class="size-4" />
                        Aplicar reajuste
                    </Button>
                </div>

                <ul
                    v-if="lease.adjustments.length"
                    class="divide-y rounded-lg border"
                >
                    <li
                        v-for="adjustment in lease.adjustments"
                        :key="adjustment.id"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 p-3 text-sm"
                    >
                        <div>
                            <p class="font-medium">
                                {{ formatDate(adjustment.effective_on) }} ·
                                {{
                                    adjustmentIndexLabels[
                                        adjustment.adjustment_index
                                    ]
                                }}
                                {{ formatPercent(adjustment.percent) }}
                            </p>
                            <p
                                v-if="adjustment.notes"
                                class="text-xs text-muted-foreground"
                            >
                                {{ adjustment.notes }}
                            </p>
                        </div>
                        <p class="text-muted-foreground tabular-nums">
                            {{ formatCurrency(adjustment.previous_amount) }} →
                            <span class="font-medium text-foreground">{{
                                formatCurrency(adjustment.new_amount)
                            }}</span>
                        </p>
                    </li>
                </ul>
            </div>

            <LeaseDocuments :lease="lease" />

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
                <Button
                    v-if="contractTemplates.length <= 1"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <a :href="contract(lease).url" target="_blank">
                        <FileDown class="size-4" />
                        Gerar contrato (PDF)
                    </a>
                </Button>
                <DropdownMenu v-else>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm">
                            <FileDown class="size-4" />
                            Gerar contrato (PDF)
                            <ChevronDown class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64">
                        <DropdownMenuLabel>Escolha o modelo</DropdownMenuLabel>
                        <DropdownMenuItem
                            v-for="template in contractTemplates"
                            :key="template.id"
                            as-child
                        >
                            <a
                                :href="
                                    contract(lease, {
                                        query: { template: template.id },
                                    }).url
                                "
                                target="_blank"
                            >
                                <span class="min-w-0 flex-1 truncate">{{
                                    template.name
                                }}</span>
                                <Star
                                    v-if="template.is_default"
                                    class="size-3.5 text-primary"
                                />
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <DialogFooter class="border-t pt-6">
                <template v-if="lease.status === 'active' && can.manageRentals">
                    <Button variant="outline" @click="emit('finish', lease)">
                        <FileCheck class="size-4" />
                        Finalizar contrato
                    </Button>
                    <Button variant="outline" @click="emit('renew', lease)">
                        <CalendarPlus class="size-4" />
                        Renovar
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
