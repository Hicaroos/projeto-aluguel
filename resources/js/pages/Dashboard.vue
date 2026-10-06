<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleCheck,
    FileText,
    HandCoins,
    House,
    KeyRound,
    PartyPopper,
    Scale,
    TriangleAlert,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardPanel from '@/components/DashboardPanel.vue';
import PageHeader from '@/components/PageHeader.vue';
import RevenueChart from '@/components/RevenueChart.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatMonthYear } from '@/lib/formatters';
import { leaseDeadlineHint } from '@/lib/lease-labels';
import {
    paymentDueHint,
    paymentReferenceLabel,
    paymentRemainingAmount,
} from '@/lib/payment-labels';
import { propertyTypeIcons } from '@/lib/property-labels';
import ReceiptFormDialog from '@/pages/payments/ReceiptFormDialog.vue';
import { dashboard } from '@/routes';
import { index as leasesIndex } from '@/routes/leases';
import { index as paymentsIndex } from '@/routes/payments';
import { index as propertiesIndex } from '@/routes/properties';
import { index as tenantsIndex } from '@/routes/tenants';
import type {
    DashboardEndingLease,
    DashboardStats,
    DashboardVacantProperty,
    MonthlyRevenue,
    Payment,
} from '@/types';

const props = defineProps<{
    month: string;
    stats: DashboardStats;
    monthlyRevenue: MonthlyRevenue[];
    attentionPayments: Payment[];
    formerTenantDebts: Payment[];
    endingLeases: DashboardEndingLease[];
    vacantProperties: DashboardVacantProperty[];
    vacantPropertiesCount: number;
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
const firstName = computed(
    () => page.props.auth.user.name.trim().split(/\s+/)[0],
);

function percentage(part: number, total: number): number {
    return total > 0 ? Math.min(100, Math.round((part / total) * 100)) : 0;
}

const onboardingSteps = computed(() => [
    {
        title: 'Cadastre seus imóveis',
        done: props.stats.properties > 0,
        href: propertiesIndex(),
        icon: House,
    },
    {
        title: 'Cadastre seus inquilinos',
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

const showOnboarding = computed(() =>
    onboardingSteps.value.some((step) => !step.done),
);

const registeringPaymentId = ref<number | null>(null);
const paymentToRegister = computed(
    () =>
        [...props.attentionPayments, ...props.formerTenantDebts].find(
            (payment) => payment.id === registeringPaymentId.value,
        ) ?? null,
);
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
            <ol class="grid gap-3 p-5 md:grid-cols-3">
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

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
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
                        class="flex size-8 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300"
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
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-xs">
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
                        stats.netIncome < 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : ''
                    "
                >
                    {{ formatCurrency(stats.netIncome) }}
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
                                v-if="stats.expensesPending > 0"
                                class="text-muted-foreground/70"
                                :title="`${formatCurrency(stats.expensesPending)} em despesas ainda a pagar neste mês`"
                            >
                                ({{ formatCurrency(stats.expensesPending) }} a
                                pagar)
                            </span>
                        </dt>
                        <dd
                            class="font-medium text-rose-600 dark:text-rose-400"
                        >
                            − {{ formatCurrency(stats.expensesPaid) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

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
                        class="flex items-center gap-3 px-5 py-3"
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
                                @click="registeringPaymentId = payment.id"
                            >
                                Receber
                            </button>
                        </div>
                    </li>
                </ul>
            </DashboardPanel>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <DashboardPanel
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
                        class="flex items-center gap-3 px-5 py-3"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
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
                    </li>
                </ul>
            </DashboardPanel>

            <DashboardPanel
                title="Imóveis vagos"
                :description="
                    vacantPropertiesCount === 1
                        ? '1 imóvel disponível para locação.'
                        : `${vacantPropertiesCount} imóveis disponíveis para locação.`
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
                        class="flex items-center gap-3 px-5 py-3"
                    >
                        <div
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
                                {{ property.neighborhood }} ·
                                {{ property.city }}/{{ property.state }}
                            </p>
                        </div>
                        <p class="text-sm font-medium tabular-nums">
                            {{ formatCurrency(property.rent_amount) }}
                        </p>
                    </li>
                </ul>
            </DashboardPanel>
        </div>

        <DashboardPanel
            v-if="formerTenantDebts.length > 0"
            title="Pendências de ex-inquilinos"
            description="Cobranças atrasadas de contratos já encerrados."
        >
            <ul class="divide-y">
                <li
                    v-for="payment in formerTenantDebts"
                    :key="payment.id"
                    class="flex items-center gap-3 px-5 py-3"
                >
                    <Avatar class="size-9">
                        <AvatarFallback
                            class="bg-rose-100 text-xs font-medium text-rose-700 dark:bg-rose-950/60 dark:text-rose-300"
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
                            @click="registeringPaymentId = payment.id"
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
</template>
