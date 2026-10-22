<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Briefcase,
    CalendarClock,
    CalendarPlus,
    ChevronRight,
    CircleCheck,
    FileText,
    HandCoins,
    House,
    KeyRound,
    PartyPopper,
    Scale,
    TrendingUp,
    TriangleAlert,
    UserRound,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AgencyRevenueDialog from '@/components/AgencyRevenueDialog.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DashboardPanel from '@/components/DashboardPanel.vue';
import DayAgenda from '@/components/DayAgenda.vue';
import OwnersMoneyPanel from '@/components/OwnersMoneyPanel.vue';
import PageHeader from '@/components/PageHeader.vue';
import RevenueChart from '@/components/RevenueChart.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { usePermissions } from '@/composables/usePermissions';
import { iconTones } from '@/lib/badge-tones';
import { formatCurrency } from '@/lib/currency';
import { daysUntil, formatDate, formatMonthYear } from '@/lib/formatters';
import {
    adjustmentIndexLabels,
    adjustmentStatusBadgeClasses,
    adjustmentStatusLabels,
    leaseDeadlineHint,
} from '@/lib/lease-labels';
import {
    paymentDueHint,
    paymentReferenceLabel,
    paymentRemainingAmount,
} from '@/lib/payment-labels';
import { propertyTypeIcons } from '@/lib/property-labels';
import { formatVisitTime } from '@/lib/sales-labels';
import LeaseDetailsDialog from '@/pages/leases/LeaseDetailsDialog.vue';
import PaymentDetailsDialog from '@/pages/payments/PaymentDetailsDialog.vue';
import ReceiptFormDialog from '@/pages/payments/ReceiptFormDialog.vue';
import PropertyDetailsDialog from '@/pages/properties/PropertyDetailsDialog.vue';
import { dashboard } from '@/routes';
import { index as dealsIndex } from '@/routes/deals';
import { index as leasesIndex } from '@/routes/leases';
import { index as ownersIndex } from '@/routes/owners';
import {
    destroy as destroyPayment,
    index as paymentsIndex,
} from '@/routes/payments';
import { index as propertiesIndex } from '@/routes/properties';
import { index as tenantsIndex } from '@/routes/tenants';
import type {
    ContractTemplateOption,
    DashboardAdjustmentLease,
    DashboardAgenda,
    DashboardAgencyFinances,
    DashboardEndingLease,
    DashboardStats,
    DashboardVacantProperty,
    DashboardCommercial,
    Lease,
    MonthlyRevenue,
    Payment,
    Property,
} from '@/types';

const props = defineProps<{
    month: string;
    stats: DashboardStats;
    monthlyRevenue: MonthlyRevenue[];
    attentionPayments: Payment[];
    formerTenantDebts: Payment[];
    endingLeases: DashboardEndingLease[];
    adjustmentLeases: DashboardAdjustmentLease[];
    vacantProperties: DashboardVacantProperty[];
    vacantPropertiesCount: number;
    contractTemplates: ContractTemplateOption[];
    /** Only for those who handle sales, once the account uses the funnel. */
    commercial: DashboardCommercial | null;
    /** Only for agencies: what needs attention today, as far as the user's permissions go. */
    agenda: DashboardAgenda | null;
    /** Only for agency members who handle the money. */
    agencyFinances: DashboardAgencyFinances | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const can = usePermissions();
const firstName = computed(
    () => page.props.auth.user.name.trim().split(/\s+/)[0],
);

const isRevenueOpen = ref(false);

function percentage(part: number, total: number): number {
    return total > 0 ? Math.min(100, Math.round((part / total) * 100)) : 0;
}

function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

const leaseMovement = computed(() => {
    const { leasesStarted, leasesFinished } = props.stats;

    if (leasesStarted === 0 && leasesFinished === 0) {
        return 'Nenhum contrato novo ou encerrado no mês';
    }

    return [
        leasesStarted > 0
            ? plural(leasesStarted, 'contrato novo', 'contratos novos')
            : null,
        leasesFinished > 0
            ? plural(leasesFinished, 'encerrado', 'encerrados')
            : null,
    ]
        .filter(Boolean)
        .join(' · ')
        .concat(' no mês');
});

/** How long the property has been waiting for a tenant; two months or more stands out. */
function vacancy(
    property: DashboardVacantProperty,
): { label: string; isLong: boolean } | null {
    if (!property.vacant_since) {
        return null;
    }

    const days = Math.max(0, -daysUntil(property.vacant_since));

    return {
        label:
            days === 0
                ? 'Vago desde hoje'
                : `Vago há ${plural(days, 'dia', 'dias')}`,
        isLong: days >= 60,
    };
}

const commercialFigures = computed(() => {
    const commercial = props.commercial;

    if (!commercial) {
        return [];
    }

    return [
        { label: 'Leads novos', value: commercial.newLeads, hint: null },
        { label: 'Visitas feitas', value: commercial.visitsDone, hint: null },
        {
            label: 'Propostas pendentes',
            value: commercial.pendingProposals,
            hint: null,
        },
        {
            label: 'Negócios ganhos',
            value: commercial.wonDeals,
            hint: plural(commercial.openDeals, 'em aberto', 'em aberto'),
        },
        {
            label: 'Vendas',
            value: commercial.sales.count,
            hint:
                commercial.sales.count > 0
                    ? formatCurrency(commercial.sales.total)
                    : null,
        },
        {
            label: 'Comissões',
            value: formatCurrency(commercial.sales.commission),
            hint: null,
        },
    ];
});

const teamColumns: {
    key: 'leads' | 'visits' | 'proposals' | 'won';
    label: string;
}[] = [
    { key: 'leads', label: 'Leads' },
    { key: 'visits', label: 'Visitas' },
    { key: 'proposals', label: 'Propostas' },
    { key: 'won', label: 'Ganhos' },
];

const isAgency = computed(
    () => page.props.auth.user.account?.type === 'agency',
);

/** Agencies register the owners first, as every property needs one. */
const onboardingSteps = computed(() => [
    ...(isAgency.value
        ? [
              {
                  title: 'Cadastre os proprietários',
                  done: props.stats.owners > 0,
                  href: ownersIndex(),
                  icon: UserRound,
              },
          ]
        : []),
    {
        title: isAgency.value ? 'Cadastre os imóveis' : 'Cadastre seus imóveis',
        done: props.stats.properties > 0,
        href: propertiesIndex(),
        icon: House,
    },
    {
        title: isAgency.value
            ? 'Cadastre os inquilinos'
            : 'Cadastre seus inquilinos',
        done: props.stats.tenants > 0,
        href: tenantsIndex(),
        icon: Users,
    },
    {
        title: 'Crie o primeiro contrato',
        done: props.stats.activeLeases > 0,
        href: leasesIndex(),
        icon: FileText,
    },
]);

const showOnboarding = computed(
    () =>
        can.value.manageRentals &&
        onboardingSteps.value.some((step) => !step.done),
);

const payments = computed(() => [
    ...props.attentionPayments,
    ...props.formerTenantDebts,
]);
const leases = computed(() => [
    ...props.endingLeases,
    ...props.adjustmentLeases,
]);

/*
 * The details open right on the dashboard, from the panel items. They are looked up by id
 * so they show the reloaded data after a change (a photo, a document, a receipt…), and
 * close by themselves when the item leaves its panel.
 */
const shownPaymentId = ref<number | null>(null);
const paymentToShow = computed(
    () =>
        payments.value.find((payment) => payment.id === shownPaymentId.value) ??
        null,
);
const shownLeaseId = ref<number | null>(null);
const leaseToShow = computed(
    () => leases.value.find((lease) => lease.id === shownLeaseId.value) ?? null,
);
const shownPropertyId = ref<number | null>(null);
const propertyToShow = computed(
    () =>
        props.vacantProperties.find(
            (property) => property.id === shownPropertyId.value,
        ) ?? null,
);

const registeringPaymentId = ref<number | null>(null);
const paymentToRegister = computed(
    () =>
        payments.value.find(
            (payment) => payment.id === registeringPaymentId.value,
        ) ?? null,
);

function registerPayment(payment: Payment) {
    shownPaymentId.value = null;
    registeringPaymentId.value = payment.id;
}

const chargeToDelete = ref<Payment | null>(null);
const isDeletingCharge = ref(false);

function openDeleteCharge(payment: Payment) {
    shownPaymentId.value = null;
    chargeToDelete.value = payment;
}

function confirmDeleteCharge() {
    if (!chargeToDelete.value) {
        return;
    }

    isDeletingCharge.value = true;

    router.delete(destroyPayment(chargeToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeletingCharge.value = false;
            chargeToDelete.value = null;
        },
    });
}

/** Lease actions that need the leases page forms open there, right on the chosen dialog. */
function openLeaseAction(lease: Lease, action: string) {
    router.visit(leasesIndex({ query: { show: lease.id, action } }).url);
}

function editProperty(property: Property) {
    router.visit(
        propertiesIndex({ query: { show: property.id, action: 'edit' } }).url,
    );
}

/** Panel items open their details when clicked or activated with the keyboard. */
const clickableRow =
    'cursor-pointer transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none';
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Olá, ${firstName}`"
            :description="`Este é o resumo de ${formatMonthYear(month).toLowerCase()}.`"
        >
            <Button variant="outline" as-child>
                <Link :href="paymentsIndex()">
                    <Wallet class="size-4" />
                    Ver cobranças
                </Link>
            </Button>
        </PageHeader>

        <DashboardPanel
            v-if="showOnboarding"
            title="Primeiros passos"
            description="Complete estas etapas para o sistema começar a gerar as cobranças."
        >
            <ol
                class="grid gap-3 p-5"
                :class="
                    onboardingSteps.length === 4
                        ? 'md:grid-cols-2 xl:grid-cols-4'
                        : 'md:grid-cols-3'
                "
            >
                <li
                    v-for="(step, stepIndex) in onboardingSteps"
                    :key="step.title"
                >
                    <Link
                        :href="step.href"
                        class="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-accent"
                        :class="step.done ? 'bg-muted/40' : ''"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full"
                            :class="
                                step.done
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-primary/10 text-primary'
                            "
                        >
                            <CircleCheck v-if="step.done" class="size-5" />
                            <component :is="step.icon" v-else class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Etapa {{ stepIndex + 1 }}
                            </p>
                            <p
                                class="font-medium"
                                :class="
                                    step.done
                                        ? 'text-muted-foreground line-through'
                                        : ''
                                "
                            >
                                {{ step.title }}
                            </p>
                        </div>
                    </Link>
                </li>
            </ol>
        </DashboardPanel>

        <DayAgenda v-if="agenda" :agenda="agenda" :today="page.props.today" />

        <div
            class="grid gap-4 sm:grid-cols-2"
            :class="can.manageFinance ? 'xl:grid-cols-4' : 'xl:grid-cols-3'"
        >
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Recebido no mês</p>
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <HandCoins class="size-4" />
                    </div>
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(stats.received) }}
                </p>
                <p class="text-sm text-muted-foreground tabular-nums">
                    de {{ formatCurrency(stats.expected) }} previstos
                </p>
                <div
                    class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
                    role="meter"
                    :aria-valuenow="percentage(stats.received, stats.expected)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Percentual recebido no mês"
                >
                    <div
                        class="h-full rounded-full bg-primary"
                        :style="{
                            width: `${percentage(stats.received, stats.expected)}%`,
                        }"
                    />
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Em atraso</p>
                    <div
                        class="flex size-8 items-center justify-center rounded-lg"
                        :class="
                            stats.overdueCount > 0
                                ? iconTones.danger
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <TriangleAlert class="size-4" />
                    </div>
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(stats.overdue) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        stats.overdueCount === 0
                            ? 'Nenhuma cobrança atrasada'
                            : stats.overdueCount === 1
                              ? '1 cobrança atrasada'
                              : `${stats.overdueCount} cobranças atrasadas`
                    }}
                </p>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Ocupação</p>
                    <div
                        class="flex size-8 items-center justify-center rounded-lg"
                        :class="iconTones.info"
                    >
                        <KeyRound class="size-4" />
                    </div>
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ percentage(stats.rentedProperties, stats.properties) }}%
                </p>
                <p class="text-sm text-muted-foreground tabular-nums">
                    {{ stats.rentedProperties }} de {{ stats.properties }}
                    imóveis alugados
                </p>
                <div
                    class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
                    role="meter"
                    :aria-valuenow="
                        percentage(stats.rentedProperties, stats.properties)
                    "
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Taxa de ocupação"
                >
                    <div
                        class="h-full rounded-full bg-sky-500"
                        :style="{
                            width: `${percentage(stats.rentedProperties, stats.properties)}%`,
                        }"
                    />
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ leaseMovement }}
                </p>
            </div>

            <button
                v-if="agencyFinances"
                type="button"
                class="rounded-xl border bg-card p-5 text-left shadow-xs transition-colors hover:border-primary/40 hover:bg-accent/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                @click="isRevenueOpen = true"
            >
                <span class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">
                        Receita da imobiliária
                    </span>
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Briefcase class="size-4" />
                    </span>
                </span>
                <span
                    class="mt-2 block text-2xl font-semibold tabular-nums"
                    :class="
                        agencyFinances.revenue.result < 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : ''
                    "
                >
                    {{ formatCurrency(agencyFinances.revenue.result) }}
                </span>
                <span class="block text-sm text-muted-foreground">
                    Taxas e comissões, menos as despesas da imobiliária
                </span>
                <span
                    class="mt-3 flex items-center gap-1 border-t pt-3 text-xs font-medium text-primary"
                >
                    Ver detalhes
                    <ChevronRight class="size-3.5" />
                </span>
            </button>

            <div
                v-else-if="can.manageFinance"
                class="rounded-xl border bg-card p-5 shadow-xs"
            >
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        Resultado do mês
                    </p>
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                    >
                        <Scale class="size-4" />
                    </div>
                </div>
                <p
                    class="mt-2 text-2xl font-semibold tabular-nums"
                    :class="
                        (stats.netIncome ?? 0) < 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : ''
                    "
                >
                    {{ formatCurrency(stats.netIncome ?? 0) }}
                </p>
                <dl class="mt-3 space-y-1 border-t pt-3 text-xs tabular-nums">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Aluguéis</dt>
                        <dd class="font-medium text-primary">
                            + {{ formatCurrency(stats.received) }}
                        </dd>
                    </div>
                    <div
                        v-if="stats.charges > 0"
                        class="flex items-center justify-between gap-2"
                    >
                        <dt class="text-muted-foreground">Multa e juros</dt>
                        <dd class="font-medium text-primary">
                            + {{ formatCurrency(stats.charges) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">
                            Despesas pagas
                            <span
                                v-if="(stats.expensesPending ?? 0) > 0"
                                class="text-muted-foreground/70"
                                :title="`${formatCurrency(stats.expensesPending ?? 0)} em despesas ainda a pagar neste mês`"
                            >
                                ({{
                                    formatCurrency(stats.expensesPending ?? 0)
                                }}
                                a pagar)
                            </span>
                        </dt>
                        <dd
                            class="font-medium text-rose-600 dark:text-rose-400"
                        >
                            − {{ formatCurrency(stats.expensesPaid ?? 0) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <OwnersMoneyPanel
            v-if="agencyFinances"
            :owners="agencyFinances.owners"
        />

        <AgencyRevenueDialog
            v-if="agencyFinances"
            :open="isRevenueOpen"
            :revenue="agencyFinances.revenue"
            :month-label="formatMonthYear(month).toLowerCase()"
            @close="isRevenueOpen = false"
        />

        <div class="grid gap-6 lg:grid-cols-5">
            <DashboardPanel
                title="Recebimentos"
                description="Previsto e recebido nos últimos 6 meses."
                class="lg:col-span-3"
            >
                <div class="p-5">
                    <RevenueChart :data="monthlyRevenue" />
                </div>
            </DashboardPanel>

            <DashboardPanel
                id="atencao"
                title="Precisam de atenção"
                description="Cobranças atrasadas ou que vencem em até 7 dias."
                class="lg:col-span-2"
            >
                <template #action>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="paymentsIndex()">
                            Ver todas
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </template>

                <div
                    v-if="attentionPayments.length === 0"
                    class="flex flex-col items-center gap-2 px-5 py-12 text-center"
                >
                    <PartyPopper class="size-6 text-primary" />
                    <p class="text-sm text-muted-foreground">
                        Tudo em dia! Nenhuma cobrança pendente para os próximos
                        dias.
                    </p>
                </div>

                <ul v-else class="divide-y">
                    <li
                        v-for="payment in attentionPayments"
                        :key="payment.id"
                        role="button"
                        tabindex="0"
                        class="flex items-center gap-3 px-5 py-3"
                        :class="clickableRow"
                        @click="shownPaymentId = payment.id"
                        @keydown.enter="shownPaymentId = payment.id"
                    >
                        <Avatar class="size-9">
                            <AvatarFallback
                                class="bg-primary/10 text-xs font-medium text-primary"
                            >
                                {{ getInitials(payment.lease.tenant.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ payment.lease.tenant.name }}
                            </p>
                            <p
                                class="truncate text-xs font-medium"
                                :class="
                                    paymentDueHint(payment)?.class ??
                                    'text-muted-foreground'
                                "
                            >
                                {{
                                    paymentDueHint(payment)?.label ??
                                    `Vence em ${formatDate(payment.due_date)}`
                                }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium tabular-nums">
                                {{
                                    formatCurrency(
                                        paymentRemainingAmount(payment),
                                    )
                                }}
                            </p>
                            <button
                                type="button"
                                class="text-xs font-medium text-primary hover:underline"
                                @click.stop="registerPayment(payment)"
                                @keydown.enter.stop
                            >
                                Receber
                            </button>
                        </div>
                    </li>
                </ul>
            </DashboardPanel>
        </div>

        <DashboardPanel
            v-if="adjustmentLeases.length > 0"
            id="reajustes"
            title="Reajustes disponíveis"
            description="Contratos que completam (ou já completaram) mais um ano e podem ter o aluguel reajustado."
        >
            <ul class="divide-y">
                <li
                    v-for="lease in adjustmentLeases"
                    :key="lease.id"
                    role="button"
                    tabindex="0"
                    class="flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center"
                    :class="clickableRow"
                    @click="shownLeaseId = lease.id"
                    @keydown.enter="shownLeaseId = lease.id"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <TrendingUp class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ lease.tenant.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ lease.property.street }},
                                {{ lease.property.number }} ·
                                {{ formatCurrency(lease.amount) }} ·
                                {{
                                    adjustmentIndexLabels[
                                        lease.adjustment_index
                                    ]
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="text-sm tabular-nums">
                                {{
                                    lease.next_adjustment_date
                                        ? formatDate(lease.next_adjustment_date)
                                        : '—'
                                }}
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
                                    adjustmentStatusLabels[
                                        lease.adjustment_status
                                    ]
                                }}
                            </Badge>
                        </div>
                        <Button
                            v-if="can.manageRentals"
                            variant="outline"
                            size="sm"
                            @click.stop="openLeaseAction(lease, 'adjust')"
                            @keydown.enter.stop
                        >
                            <TrendingUp class="size-4" />
                            Reajustar
                        </Button>
                    </div>
                </li>
            </ul>
        </DashboardPanel>

        <div class="grid gap-6 lg:grid-cols-2">
            <DashboardPanel
                id="contratos-terminando"
                title="Contratos terminando"
                description="Contratos ativos que terminam nos próximos 60 dias."
            >
                <template #action>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="leasesIndex()">
                            Ver contratos
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </template>

                <p
                    v-if="endingLeases.length === 0"
                    class="px-5 py-12 text-center text-sm text-muted-foreground"
                >
                    Nenhum contrato terminando nos próximos 60 dias.
                </p>

                <ul v-else class="divide-y">
                    <li
                        v-for="lease in endingLeases"
                        :key="lease.id"
                        role="button"
                        tabindex="0"
                        class="flex items-center gap-3 px-5 py-3"
                        :class="clickableRow"
                        @click="shownLeaseId = lease.id"
                        @keydown.enter="shownLeaseId = lease.id"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                            :class="iconTones.warning"
                        >
                            <FileText class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ lease.tenant.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ lease.property.street }},
                                {{ lease.property.number }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm tabular-nums">
                                {{ formatDate(lease.end_date) }}
                            </p>
                            <p
                                class="text-xs font-medium"
                                :class="leaseDeadlineHint(lease)?.class"
                            >
                                {{ leaseDeadlineHint(lease)?.label }}
                            </p>
                        </div>
                        <Button
                            v-if="can.manageRentals"
                            variant="outline"
                            size="sm"
                            @click.stop="openLeaseAction(lease, 'renew')"
                            @keydown.enter.stop
                        >
                            <CalendarPlus class="size-4" />
                            <span class="max-sm:sr-only">Renovar</span>
                        </Button>
                    </li>
                </ul>
            </DashboardPanel>

            <DashboardPanel
                title="Imóveis vagos"
                :description="
                    vacantPropertiesCount === 1
                        ? '1 imóvel disponível para locação.'
                        : `${vacantPropertiesCount} imóveis disponíveis, os vagos há mais tempo primeiro.`
                "
            >
                <template #action>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="propertiesIndex()">
                            Ver imóveis
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </template>

                <p
                    v-if="vacantProperties.length === 0"
                    class="px-5 py-12 text-center text-sm text-muted-foreground"
                >
                    Todos os imóveis estão ocupados.
                </p>

                <ul v-else class="divide-y">
                    <li
                        v-for="property in vacantProperties"
                        :key="property.id"
                        role="button"
                        tabindex="0"
                        class="flex items-center gap-3 px-5 py-3"
                        :class="clickableRow"
                        @click="shownPropertyId = property.id"
                        @keydown.enter="shownPropertyId = property.id"
                    >
                        <img
                            v-if="property.photos?.length"
                            :src="property.photos[0].thumbnail_url"
                            alt=""
                            loading="lazy"
                            class="size-9 shrink-0 rounded-lg border object-cover"
                        />
                        <div
                            v-else
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <component
                                :is="propertyTypeIcons[property.type]"
                                class="size-4"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ property.street }}, {{ property.number }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ property.neighborhood }}
                                <template v-if="vacancy(property)">
                                    ·
                                    <span
                                        :class="{
                                            'font-medium text-amber-700 dark:text-amber-400':
                                                vacancy(property)?.isLong,
                                        }"
                                        >{{ vacancy(property)?.label }}</span
                                    >
                                </template>
                            </p>
                        </div>
                        <p
                            v-if="property.rent_amount"
                            class="text-sm font-medium tabular-nums"
                        >
                            {{ formatCurrency(property.rent_amount) }}
                        </p>
                    </li>
                </ul>
            </DashboardPanel>
        </div>

        <DashboardPanel
            v-if="commercial"
            title="Comercial"
            :description="`Equipe · ${formatMonthYear(month).toLowerCase()}`"
        >
            <template #action>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="dealsIndex()">
                        Ver funil
                        <ChevronRight class="size-4" />
                    </Link>
                </Button>
            </template>

            <dl
                class="grid grid-cols-2 gap-px border-b bg-border sm:grid-cols-3 xl:grid-cols-6"
            >
                <div
                    v-for="figure in commercialFigures"
                    :key="figure.label"
                    class="bg-card p-5"
                >
                    <dt class="text-xs text-muted-foreground">
                        {{ figure.label }}
                    </dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ figure.value }}
                    </dd>
                    <dd
                        v-if="figure.hint"
                        class="truncate text-xs text-muted-foreground tabular-nums"
                    >
                        {{ figure.hint }}
                    </dd>
                </div>
            </dl>

            <div class="grid lg:grid-cols-2 lg:divide-x">
                <div>
                    <p
                        class="px-5 pt-4 text-xs font-medium text-muted-foreground"
                    >
                        Próximas visitas
                    </p>
                    <p
                        v-if="commercial.upcomingVisits.length === 0"
                        class="px-5 pt-2 pb-5 text-sm text-muted-foreground"
                    >
                        Nenhuma visita agendada para os próximos 7 dias.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="visit in commercial.upcomingVisits"
                            :key="visit.id"
                        >
                            <Link
                                :href="
                                    dealsIndex({
                                        query: { show: visit.deal_id },
                                    })
                                "
                                class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-muted/50"
                            >
                                <CalendarClock
                                    class="size-4 shrink-0 text-sky-600"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ visit.deal.lead.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{
                                            visit.deal.property
                                                ? `${visit.deal.property.street}, ${visit.deal.property.number}`
                                                : 'Imóvel a definir'
                                        }}
                                        <template v-if="visit.user">
                                            · {{ visit.user.name }}
                                        </template>
                                    </p>
                                </div>
                                <p class="text-xs font-medium tabular-nums">
                                    {{ formatVisitTime(visit.scheduled_at) }}
                                </p>
                            </Link>
                        </li>
                    </ul>
                </div>

                <div class="border-t lg:border-t-0">
                    <p
                        class="px-5 pt-4 text-xs font-medium text-muted-foreground"
                    >
                        Equipe no mês
                    </p>
                    <p
                        v-if="commercial.team.length === 0"
                        class="px-5 pt-2 pb-5 text-sm text-muted-foreground"
                    >
                        Ninguém da equipe mexeu no funil este mês.
                    </p>
                    <div v-else class="overflow-x-auto px-2 pb-2">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-muted-foreground">
                                    <th class="px-3 py-2 text-left font-normal">
                                        Corretor
                                    </th>
                                    <th
                                        v-for="column in teamColumns"
                                        :key="column.key"
                                        class="px-3 py-2 text-right font-normal"
                                    >
                                        {{ column.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="member in commercial.team"
                                    :key="member.id"
                                >
                                    <td
                                        class="max-w-40 truncate px-3 py-2.5 font-medium"
                                    >
                                        {{ member.name }}
                                    </td>
                                    <td
                                        v-for="column in teamColumns"
                                        :key="column.key"
                                        class="px-3 py-2.5 text-right tabular-nums"
                                        :class="
                                            member[column.key] === 0
                                                ? 'text-muted-foreground'
                                                : column.key === 'won'
                                                  ? 'font-semibold text-primary'
                                                  : ''
                                        "
                                    >
                                        {{ member[column.key] }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </DashboardPanel>

        <DashboardPanel
            v-if="formerTenantDebts.length > 0"
            title="Pendências de ex-inquilinos"
            description="Cobranças atrasadas de contratos já encerrados."
        >
            <ul class="divide-y">
                <li
                    v-for="payment in formerTenantDebts"
                    :key="payment.id"
                    role="button"
                    tabindex="0"
                    class="flex items-center gap-3 px-5 py-3"
                    :class="clickableRow"
                    @click="shownPaymentId = payment.id"
                    @keydown.enter="shownPaymentId = payment.id"
                >
                    <Avatar class="size-9">
                        <AvatarFallback
                            class="text-xs font-medium"
                            :class="iconTones.danger"
                        >
                            {{ getInitials(payment.lease.tenant.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ payment.lease.tenant.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ paymentReferenceLabel(payment) }} ·
                            <span :class="paymentDueHint(payment)?.class">{{
                                paymentDueHint(payment)?.label
                            }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium tabular-nums">
                            {{
                                formatCurrency(paymentRemainingAmount(payment))
                            }}
                        </p>
                        <button
                            type="button"
                            class="text-xs font-medium text-primary hover:underline"
                            @click.stop="registerPayment(payment)"
                            @keydown.enter.stop
                        >
                            Receber
                        </button>
                    </div>
                </li>
            </ul>
        </DashboardPanel>
    </div>

    <ReceiptFormDialog
        :payment="paymentToRegister"
        @close="registeringPaymentId = null"
    />

    <PaymentDetailsDialog
        :payment="paymentToShow"
        @close="shownPaymentId = null"
        @register="registerPayment"
        @delete="openDeleteCharge"
    />

    <LeaseDetailsDialog
        :lease="leaseToShow"
        :contract-templates="contractTemplates"
        @close="shownLeaseId = null"
        @edit="(lease) => openLeaseAction(lease, 'edit')"
        @finish="(lease) => openLeaseAction(lease, 'finish')"
        @adjust="(lease) => openLeaseAction(lease, 'adjust')"
        @renew="(lease) => openLeaseAction(lease, 'renew')"
        @settle-deposit="(lease) => openLeaseAction(lease, 'settle-deposit')"
    />

    <PropertyDetailsDialog
        :property="propertyToShow"
        @close="shownPropertyId = null"
        @edit="editProperty"
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
</template>
