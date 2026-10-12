<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarClock,
    ChevronRight,
    FileClock,
    FileText,
    HandCoins,
    PartyPopper,
    Receipt,
    TrendingUp,
    TriangleAlert,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import DashboardPanel from '@/components/DashboardPanel.vue';
import { formatCurrency } from '@/lib/currency';
import { formatDate } from '@/lib/formatters';
import { index as dealsIndex } from '@/routes/deals';
import { index as expensesIndex } from '@/routes/expenses';
import { index as payoutsIndex } from '@/routes/payouts';
import type { DashboardAgenda, DashboardAgendaKey } from '@/types';

const props = defineProps<{
    agenda: DashboardAgenda;
    /** Today's date (Y-m-d). */
    today: string;
}>();

type ItemDetails = {
    icon: LucideIcon;
    tone: string;
    title: (count: number) => string;
    detail: (amount: number | null) => string;
    /** A page, or a panel further down the dashboard. */
    href: string;
};

const plural = (count: number, one: string, many: string): string =>
    count === 1 ? `1 ${one}` : `${count} ${many}`;

const details: Record<DashboardAgendaKey, ItemDetails> = {
    overdue_payments: {
        icon: TriangleAlert,
        tone: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
        title: (count) =>
            plural(count, 'cobrança atrasada', 'cobranças atrasadas'),
        detail: (amount) => `${formatCurrency(amount ?? 0)} a receber`,
        href: '#atencao',
    },
    payments_due_today: {
        icon: CalendarClock,
        tone: 'bg-attention text-attention-foreground',
        title: (count) =>
            count === 1
                ? '1 cobrança vence hoje'
                : `${count} cobranças vencem hoje`,
        detail: (amount) => `${formatCurrency(amount ?? 0)} a receber`,
        href: '#atencao',
    },
    payouts_available: {
        icon: HandCoins,
        tone: 'bg-primary/10 text-primary',
        title: (count) =>
            plural(
                count,
                'proprietário com repasse liberado',
                'proprietários com repasse liberado',
            ),
        detail: (amount) => `${formatCurrency(amount ?? 0)} a repassar`,
        href: payoutsIndex().url,
    },
    expenses_due: {
        icon: Receipt,
        tone: 'bg-attention text-attention-foreground',
        title: (count) =>
            plural(
                count,
                'despesa vencida ou vencendo hoje',
                'despesas vencidas ou vencendo hoje',
            ),
        detail: (amount) => `${formatCurrency(amount ?? 0)} a pagar`,
        href: expensesIndex().url,
    },
    leases_ending: {
        icon: FileClock,
        tone: 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
        title: (count) =>
            count === 1
                ? '1 contrato termina nos próximos 30 dias'
                : `${count} contratos terminam nos próximos 30 dias`,
        detail: () => 'Renove ou combine a entrega das chaves',
        href: '#contratos-terminando',
    },
    adjustments_due: {
        icon: TrendingUp,
        tone: 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
        title: (count) =>
            plural(
                count,
                'contrato com reajuste para aplicar',
                'contratos com reajuste para aplicar',
            ),
        detail: () => 'Completaram mais um ano',
        href: '#reajustes',
    },
    proposals_pending: {
        icon: FileText,
        tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
        title: (count) =>
            plural(
                count,
                'proposta aguardando resposta',
                'propostas aguardando resposta',
            ),
        detail: () => 'Veja no funil',
        href: dealsIndex().url,
    },
};

const items = computed(() =>
    props.agenda.items.map((item) => ({
        ...item,
        ...details[item.key],
    })),
);

const isEmpty = computed(
    () => props.agenda.items.length === 0 && props.agenda.visits.length === 0,
);

function visitTime(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

const rowClass =
    'flex h-full items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none';
</script>

<template>
    <DashboardPanel
        title="Agenda de hoje"
        :description="`O que precisa de atenção hoje, ${formatDate(today)}.`"
    >
        <div
            v-if="isEmpty"
            class="flex flex-col items-center gap-2 px-5 py-8 text-center"
        >
            <PartyPopper class="size-6 text-primary" />
            <p class="text-sm text-muted-foreground">
                Nada pendente para hoje. Tudo em dia!
            </p>
        </div>

        <ul v-else class="grid gap-2 p-4 sm:grid-cols-2 xl:grid-cols-3">
            <li v-for="visit in agenda.visits" :key="`visit-${visit.id}`">
                <Link
                    :href="dealsIndex({ query: { show: visit.deal_id } }).url"
                    :class="rowClass"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300"
                    >
                        <CalendarCheck class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            Visita às {{ visitTime(visit.scheduled_at) }} ·
                            {{ visit.deal.lead.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            <template v-if="visit.deal.property">
                                {{ visit.deal.property.street }},
                                {{ visit.deal.property.number }}
                            </template>
                            <template v-if="visit.user">
                                · {{ visit.user.name }}
                            </template>
                        </p>
                    </div>
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </Link>
            </li>

            <li v-for="item in items" :key="item.key">
                <component
                    :is="item.href.startsWith('#') ? 'a' : Link"
                    :href="item.href"
                    :class="rowClass"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                        :class="item.tone"
                    >
                        <component :is="item.icon" class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">
                            {{ item.title(item.count) }}
                        </p>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ item.detail(item.amount) }}
                        </p>
                    </div>
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </component>
            </li>
        </ul>
    </DashboardPanel>
</template>
