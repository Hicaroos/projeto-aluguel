<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CircleCheck,
    Clock,
    Eye,
    HandCoins,
    MoreHorizontal,
    Plus,
    ReceiptText,
    SearchX,
    Trash2,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortableTableHead from '@/components/SortableTableHead.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { getInitials } from '@/composables/useInitials';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatMonthYear } from '@/lib/formatters';
import {
    isDeletableCharge,
    isPaymentOpen,
    paymentDisplayStatus,
    paymentDueHint,
    lateChargesTodayTotal as lateChargesTotal,
    paymentReceivedAmount,
    paymentRemainingAmount,
    paymentStatusBadgeClasses,
    paymentStatusDotClasses,
    paymentStatusLabels,
} from '@/lib/payment-labels';
import { nextSort } from '@/lib/table-sort';
import type { TableSort } from '@/lib/table-sort';
import ExtraChargeForm from '@/pages/payments/ExtraChargeForm.vue';
import PaymentDetailsDialog from '@/pages/payments/PaymentDetailsDialog.vue';
import ReceiptFormDialog from '@/pages/payments/ReceiptFormDialog.vue';
import { destroy, index } from '@/routes/payments';
import type {
    Payment,
    PaymentDisplayStatus,
    PaymentLeaseFilter,
    PaymentLeaseOption,
    PaymentPaginator,
    PaymentSummary,
    PaymentType,
} from '@/types';

const props = defineProps<{
    payments: PaymentPaginator;
    summary: PaymentSummary;
    filters: TableSort & {
        month: string;
        search: string;
        status: PaymentDisplayStatus | null;
        type: PaymentType | null;
        lease: number | null;
    };
    lease: PaymentLeaseFilter | null;
    leaseOptions: PaymentLeaseOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cobranças', href: '/payments' }],
    },
});

const search = ref(props.filters.search);
const status = ref<PaymentDisplayStatus | 'all'>(props.filters.status ?? 'all');
const type = ref<PaymentType | 'all'>(props.filters.type ?? 'all');

function visit(overrides: Record<string, string | number | undefined> = {}) {
    router.get(
        index().url,
        {
            month: props.filters.lease ? undefined : props.filters.month,
            lease: props.filters.lease ?? undefined,
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            type: type.value === 'all' ? undefined : type.value,
            sort: props.filters.sort,
            direction: props.filters.direction,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, () => visit(), { debounce: 350 });
watch([status, type], () => visit());

function sortBy(column: string) {
    visit(nextSort(props.filters, column));
}

const isFiltering = computed(
    () =>
        !!props.filters.search ||
        !!props.filters.status ||
        !!props.filters.type,
);

function clearFilters() {
    search.value = '';
    status.value = 'all';
    type.value = 'all';
}

const summaryCards = computed(() => [
    {
        label: 'Previsto',
        value: props.summary.expected,
        icon: Wallet,
        class: 'bg-muted text-foreground',
    },
    {
        label: 'Recebido',
        value: props.summary.received,
        hint:
            props.summary.charges > 0
                ? `+ ${formatCurrency(props.summary.charges)} de multa e juros`
                : null,
        icon: CircleCheck,
        class: 'bg-primary/10 text-primary',
    },
    {
        label: 'Em aberto',
        value: props.summary.open,
        icon: Clock,
        class: 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
    },
    {
        label: 'Em atraso',
        value: props.summary.overdue,
        icon: TriangleAlert,
        class: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
    },
]);

const selectedPaymentId = ref<number | null>(null);
const paymentToShow = computed(
    () =>
        props.payments.data.find(
            (payment) => payment.id === selectedPaymentId.value,
        ) ?? null,
);

const isExtraChargeDialogOpen = ref(false);

const chargeToDelete = ref<Payment | null>(null);
const isDeletingCharge = ref(false);

function openDeleteDialog(payment: Payment) {
    selectedPaymentId.value = null;
    chargeToDelete.value = payment;
}

function confirmDeleteCharge() {
    if (!chargeToDelete.value) {
        return;
    }

    isDeletingCharge.value = true;

    router.delete(destroy(chargeToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeletingCharge.value = false;
            chargeToDelete.value = null;
        },
    });
}

const registeringPaymentId = ref<number | null>(null);
const paymentToRegister = computed(
    () =>
        props.payments.data.find(
            (payment) => payment.id === registeringPaymentId.value,
        ) ?? null,
);

function openRegisterDialog(paymentId: number) {
    selectedPaymentId.value = null;
    registeringPaymentId.value = paymentId;
}
</script>

<template>
    <Head title="Cobranças" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            v-if="lease"
            title="Cobranças do contrato"
            :description="`${lease.tenant.name} · ${lease.property.street}, ${lease.property.number}`"
        >
            <Button variant="outline" as-child>
                <Link :href="index()">
                    <ArrowLeft class="size-4" />
                    Todas as cobranças
                </Link>
            </Button>
            <Button @click="isExtraChargeDialogOpen = true">
                <Plus class="size-4" />
                Cobrança avulsa
            </Button>
        </PageHeader>

        <PageHeader
            v-else
            title="Cobranças"
            description="Acompanhe os aluguéis do mês e registre os pagamentos recebidos."
        >
            <MonthNavigator
                :month="filters.month"
                @change="(month) => visit({ month })"
            />
            <Button @click="isExtraChargeDialogOpen = true">
                <Plus class="size-4" />
                Cobrança avulsa
            </Button>
        </PageHeader>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                    :class="card.class"
                >
                    <component :is="card.icon" class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p class="truncate text-lg font-semibold tabular-nums">
                        {{ formatCurrency(card.value) }}
                    </p>
                    <p
                        v-if="card.hint"
                        class="truncate text-xs text-muted-foreground tabular-nums"
                    >
                        {{ card.hint }}
                    </p>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Buscar por inquilino, rua ou bairro"
                />
                <Select v-model="status">
                    <SelectTrigger class="w-full sm:w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas as situações</SelectItem>
                        <SelectItem
                            v-for="(label, key) in paymentStatusLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="type">
                    <SelectTrigger class="w-full sm:w-36">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos os tipos</SelectItem>
                        <SelectItem value="rent">Aluguel</SelectItem>
                        <SelectItem value="extra">Avulsa</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <EmptyState
                v-if="payments.data.length === 0 && isFiltering"
                :icon="SearchX"
                title="Nenhuma cobrança encontrada"
                description="Não encontramos cobranças com esses filtros."
            >
                <Button variant="outline" size="sm" @click="clearFilters">
                    Limpar filtros
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="payments.data.length === 0"
                :icon="ReceiptText"
                :title="
                    lease
                        ? 'Nenhuma cobrança gerada para este contrato'
                        : 'Nenhuma cobrança neste mês'
                "
                description="As cobranças são geradas automaticamente a partir dos contratos ativos."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <SortableTableHead
                            column="tenant"
                            label="Inquilino"
                            :current="filters"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="reference"
                            label="Referência"
                            :current="filters"
                            class="hidden lg:table-cell"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="due_date"
                            label="Vencimento"
                            :current="filters"
                            class="hidden md:table-cell"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="amount"
                            label="Valor"
                            :current="filters"
                            align="right"
                            class="text-right"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="status"
                            label="Situação"
                            :current="filters"
                            class="hidden sm:table-cell"
                            @sort="sortBy"
                        />
                        <TableHead class="h-11 w-0 px-4"
                            ><span class="sr-only">Ações</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="payment in payments.data"
                        :key="payment.id"
                        class="cursor-pointer"
                        @click="selectedPaymentId = payment.id"
                    >
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <Avatar class="size-10">
                                    <AvatarFallback
                                        class="bg-primary/10 text-sm font-medium text-primary"
                                    >
                                        {{
                                            getInitials(
                                                payment.lease.tenant.name,
                                            )
                                        }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ payment.lease.tenant.name }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ payment.lease.property.street }},
                                        {{ payment.lease.property.number }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-muted-foreground lg:table-cell"
                        >
                            {{
                                formatMonthYear(
                                    payment.reference_month ??
                                        payment.created_at,
                                    'short',
                                )
                            }}
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 md:table-cell">
                            <p class="tabular-nums">
                                {{ formatDate(payment.due_date) }}
                            </p>
                            <p
                                v-if="paymentDueHint(payment)"
                                class="text-sm font-medium"
                                :class="paymentDueHint(payment)?.class"
                            >
                                {{ paymentDueHint(payment)?.label }}
                            </p>
                        </TableCell>
                        <TableCell class="px-4 py-3 text-right">
                            <p class="font-medium tabular-nums">
                                {{ formatCurrency(payment.amount) }}
                            </p>
                            <p
                                v-if="payment.status === 'partial'"
                                class="text-sm text-muted-foreground tabular-nums"
                            >
                                Recebido
                                {{
                                    formatCurrency(
                                        paymentReceivedAmount(payment),
                                    )
                                }}
                            </p>
                            <p
                                v-if="
                                    isPaymentOpen(payment) &&
                                    lateChargesTotal(payment) > 0
                                "
                                class="text-sm text-attention-foreground tabular-nums"
                                :title="`Saldo em aberto com multa e juros de ${payment.late_charges_today?.days_late} dias de atraso, se pago hoje`"
                            >
                                {{
                                    formatCurrency(
                                        paymentRemainingAmount(payment) +
                                            lateChargesTotal(payment),
                                    )
                                }}
                                com encargos
                            </p>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 sm:table-cell">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <Badge
                                    variant="outline"
                                    :class="
                                        paymentStatusBadgeClasses[
                                            paymentDisplayStatus(payment)
                                        ]
                                    "
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            paymentStatusDotClasses[
                                                paymentDisplayStatus(payment)
                                            ]
                                        "
                                    />
                                    {{
                                        paymentStatusLabels[
                                            paymentDisplayStatus(payment)
                                        ]
                                    }}
                                </Badge>
                                <Badge
                                    v-if="payment.type === 'extra'"
                                    variant="outline"
                                    class="border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/60 dark:text-violet-300"
                                >
                                    Avulsa
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell class="px-4 py-3" @click.stop>
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="isPaymentOpen(payment)"
                                    variant="outline"
                                    size="sm"
                                    class="hidden xl:inline-flex"
                                    @click="openRegisterDialog(payment.id)"
                                >
                                    <HandCoins class="size-4" />
                                    Receber
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <MoreHorizontal class="size-4" />
                                            <span class="sr-only">Ações</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-52"
                                    >
                                        <DropdownMenuItem
                                            @click="
                                                selectedPaymentId = payment.id
                                            "
                                        >
                                            <Eye class="size-4" />
                                            Ver detalhes
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="isPaymentOpen(payment)"
                                            @click="
                                                openRegisterDialog(payment.id)
                                            "
                                        >
                                            <HandCoins class="size-4" />
                                            Registrar pagamento
                                        </DropdownMenuItem>
                                        <template
                                            v-if="isDeletableCharge(payment)"
                                        >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @click="
                                                    chargeToDelete = payment
                                                "
                                            >
                                                <Trash2 class="size-4" />
                                                Excluir
                                            </DropdownMenuItem>
                                        </template>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <TablePagination
                v-if="payments.data.length > 0"
                :paginator="payments"
                item-label="cobranças"
                item-label-singular="cobrança"
                class="border-t"
            />
        </div>
    </div>

    <PaymentDetailsDialog
        :payment="paymentToShow"
        @close="selectedPaymentId = null"
        @register="(payment) => openRegisterDialog(payment.id)"
        @delete="openDeleteDialog"
    />

    <ReceiptFormDialog
        :payment="paymentToRegister"
        @close="registeringPaymentId = null"
    />

    <ConfirmDeleteDialog
        :open="!!chargeToDelete"
        title="Excluir cobrança avulsa?"
        :processing="isDeletingCharge"
        @close="chargeToDelete = null"
        @confirm="confirmDeleteCharge"
    >
        A cobrança
        <span class="font-medium text-foreground">{{
            chargeToDelete?.description
        }}</span>
        de {{ chargeToDelete?.lease.tenant.name }} será removida. Esta ação não
        pode ser desfeita.
    </ConfirmDeleteDialog>

    <Dialog v-model:open="isExtraChargeDialogOpen">
        <DialogScrollContent class="sm:max-w-xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <ReceiptText class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>Cobrança avulsa</DialogTitle>
                    <DialogDescription>
                        Cobre do inquilino um valor fora do aluguel, como um
                        reparo ou uma multa.
                    </DialogDescription>
                </div>
            </DialogHeader>
            <ExtraChargeForm
                :leases="leaseOptions"
                :initial-lease-id="filters.lease"
                @success="isExtraChargeDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>
</template>
