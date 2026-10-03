<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Clock,
    Eye,
    HandCoins,
    MoreHorizontal,
    ReceiptText,
    SearchX,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
    isPaymentOpen,
    paymentDisplayStatus,
    paymentDueHint,
    paymentReceivedAmount,
    paymentStatusBadgeClasses,
    paymentStatusDotClasses,
    paymentStatusLabels,
} from '@/lib/payment-labels';
import PaymentDetailsDialog from '@/pages/payments/PaymentDetailsDialog.vue';
import ReceiptFormDialog from '@/pages/payments/ReceiptFormDialog.vue';
import { index } from '@/routes/payments';
import type {
    PaymentDisplayStatus,
    PaymentLeaseFilter,
    PaymentPaginator,
    PaymentSummary,
} from '@/types';

const props = defineProps<{
    payments: PaymentPaginator;
    summary: PaymentSummary;
    filters: {
        month: string;
        search: string;
        status: PaymentDisplayStatus | null;
        lease: number | null;
    };
    lease: PaymentLeaseFilter | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cobranças', href: '/payments' }],
    },
});

const search = ref(props.filters.search);
const status = ref<PaymentDisplayStatus | 'all'>(props.filters.status ?? 'all');

function visit(overrides: Record<string, string | number | undefined> = {}) {
    router.get(
        index().url,
        {
            month: props.filters.lease ? undefined : props.filters.month,
            lease: props.filters.lease ?? undefined,
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, () => visit(), { debounce: 350 });
watch(status, () => visit());

function shiftMonth(months: number) {
    const [year, month] = props.filters.month.split('-').map(Number);
    const target = new Date(year, month - 1 + months, 1);

    visit({
        month: `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`,
    });
}

const currentMonth = (() => {
    const today = new Date();

    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
})();

const isFiltering = computed(
    () => !!props.filters.search || !!props.filters.status,
);

function clearFilters() {
    search.value = '';
    status.value = 'all';
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
        </PageHeader>

        <PageHeader
            v-else
            title="Cobranças"
            description="Acompanhe os aluguéis do mês e registre os pagamentos recebidos."
        >
            <Button
                v-if="filters.month !== currentMonth"
                variant="ghost"
                size="sm"
                @click="visit({ month: currentMonth })"
            >
                Ir para o mês atual
            </Button>
            <div class="flex items-center gap-1 rounded-lg border bg-card p-1">
                <Button variant="ghost" size="icon-sm" @click="shiftMonth(-1)">
                    <ChevronLeft class="size-4" />
                    <span class="sr-only">Mês anterior</span>
                </Button>
                <span class="min-w-36 text-center text-sm font-medium">
                    {{ formatMonthYear(`${filters.month}-01`) }}
                </span>
                <Button variant="ghost" size="icon-sm" @click="shiftMonth(1)">
                    <ChevronRight class="size-4" />
                    <span class="sr-only">Próximo mês</span>
                </Button>
            </div>
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
                        <TableHead
                            class="h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >Inquilino</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase lg:table-cell"
                            >Referência</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase md:table-cell"
                            >Vencimento</TableHead
                        >
                        <TableHead
                            class="h-11 px-4 text-right text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >Valor</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:table-cell"
                            >Situação</TableHead
                        >
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
                                    payment.reference_month,
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
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 sm:table-cell">
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
    />

    <ReceiptFormDialog
        :payment="paymentToRegister"
        @close="registeringPaymentId = null"
    />
</template>
