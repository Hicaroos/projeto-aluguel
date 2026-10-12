<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import DashboardPanel from '@/components/DashboardPanel.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { index as payoutsIndex } from '@/routes/payouts';
import type { DashboardAgencyFinances } from '@/types';

const props = defineProps<{
    finances: DashboardAgencyFinances;
    /** The month name, e.g. "outubro de 2026". */
    monthLabel: string;
}>();

const revenueLines = computed(() => [
    {
        label: 'Taxas de administração',
        value: props.finances.revenue.adminFees,
    },
    {
        label: 'Taxas de intermediação',
        value: props.finances.revenue.intermediationFees,
    },
    { label: 'Taxas de renovação', value: props.finances.revenue.renewalFees },
    {
        label: 'Comissões de venda',
        value: props.finances.revenue.saleCommissions,
    },
    {
        label: 'Multa e juros que ficaram com a imobiliária',
        value: props.finances.revenue.lateCharges,
    },
    {
        label: 'Cobranças extras que ficaram com a imobiliária',
        value: props.finances.revenue.extraCharges,
    },
]);

const delinquencyBuckets = computed(() => {
    const { upTo30, upTo60, over60, total } = props.finances.delinquency;

    return [
        { label: 'Até 30 dias', value: upTo30, bar: 'bg-amber-400' },
        { label: '31 a 60 dias', value: upTo60, bar: 'bg-orange-500' },
        { label: 'Mais de 60 dias', value: over60, bar: 'bg-rose-600' },
    ].map((bucket) => ({
        ...bucket,
        width: total > 0 ? Math.round((bucket.value / total) * 100) : 0,
    }));
});

function formatRate(rate: number): string {
    return `${rate.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <DashboardPanel
            title="Receita da imobiliária"
            :description="`O que a imobiliária ganhou em ${monthLabel}.`"
        >
            <dl class="divide-y text-sm tabular-nums">
                <div
                    v-for="line in revenueLines"
                    :key="line.label"
                    class="flex items-center justify-between gap-3 px-5 py-2.5"
                >
                    <dt class="text-muted-foreground">{{ line.label }}</dt>
                    <dd
                        class="shrink-0 font-medium"
                        :class="{
                            'text-muted-foreground/60': line.value === 0,
                        }"
                    >
                        {{ formatCurrency(line.value) }}
                    </dd>
                </div>
                <div
                    class="flex items-center justify-between gap-3 px-5 py-2.5"
                >
                    <dt class="text-muted-foreground">
                        Despesas pagas pela imobiliária
                    </dt>
                    <dd
                        class="shrink-0 font-medium"
                        :class="
                            finances.revenue.expenses > 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-muted-foreground/60'
                        "
                    >
                        − {{ formatCurrency(finances.revenue.expenses) }}
                    </dd>
                </div>
                <div
                    class="flex items-center justify-between gap-3 bg-muted/40 px-5 py-3"
                >
                    <dt class="font-semibold">Resultado</dt>
                    <dd
                        class="text-base font-semibold"
                        :class="
                            finances.revenue.result < 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-primary'
                        "
                    >
                        {{ formatCurrency(finances.revenue.result) }}
                    </dd>
                </div>
            </dl>
        </DashboardPanel>

        <DashboardPanel
            title="Dinheiro dos proprietários"
            description="O que a imobiliária recebeu e deve repassar."
        >
            <template #action>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="payoutsIndex()">
                        Repasses
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </template>

            <dl class="divide-y text-sm tabular-nums">
                <div
                    class="flex items-center justify-between gap-3 px-5 py-2.5"
                >
                    <dt class="text-muted-foreground">
                        Recebido dos inquilinos no mês
                    </dt>
                    <dd class="shrink-0 font-medium">
                        {{ formatCurrency(finances.owners.received) }}
                    </dd>
                </div>
                <div
                    class="flex items-center justify-between gap-3 bg-primary/5 px-5 py-3"
                >
                    <dt class="font-medium">A repassar hoje</dt>
                    <dd class="shrink-0 text-base font-semibold text-primary">
                        {{ formatCurrency(finances.owners.available) }}
                    </dd>
                </div>
                <div
                    class="flex items-center justify-between gap-3 px-5 py-2.5"
                >
                    <dt class="text-muted-foreground">Aguardando o prazo</dt>
                    <dd class="shrink-0 font-medium">
                        {{ formatCurrency(finances.owners.pending) }}
                    </dd>
                </div>
                <div
                    class="flex items-center justify-between gap-3 px-5 py-2.5"
                >
                    <dt class="text-muted-foreground">Repassado no mês</dt>
                    <dd class="shrink-0 font-medium">
                        {{ formatCurrency(finances.owners.paidOut) }}
                    </dd>
                </div>
                <div
                    v-if="finances.owners.advanced > 0"
                    class="flex items-center justify-between gap-3 px-5 py-2.5"
                >
                    <dt class="text-muted-foreground">
                        Aluguel garantido adiantado
                        <span class="block text-xs">
                            A imobiliária pagou e o inquilino ainda deve.
                        </span>
                    </dt>
                    <dd class="shrink-0 font-medium text-attention-foreground">
                        {{ formatCurrency(finances.owners.advanced) }}
                    </dd>
                </div>
            </dl>
        </DashboardPanel>

        <DashboardPanel
            title="Inadimplência"
            description="Aluguéis e cobranças em atraso."
        >
            <div class="space-y-4 p-5">
                <div>
                    <p
                        class="text-2xl font-semibold tabular-nums"
                        :class="{
                            'text-rose-600 dark:text-rose-400':
                                finances.delinquency.total > 0,
                        }"
                    >
                        {{ formatCurrency(finances.delinquency.total) }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            finances.delinquency.count === 0
                                ? 'Nenhuma cobrança atrasada'
                                : finances.delinquency.count === 1
                                  ? '1 cobrança atrasada'
                                  : `${finances.delinquency.count} cobranças atrasadas`
                        }}
                    </p>
                </div>

                <ul v-if="finances.delinquency.total > 0" class="space-y-2.5">
                    <li
                        v-for="bucket in delinquencyBuckets"
                        :key="bucket.label"
                        class="space-y-1"
                    >
                        <div
                            class="flex items-center justify-between gap-3 text-sm tabular-nums"
                        >
                            <span class="text-muted-foreground">{{
                                bucket.label
                            }}</span>
                            <span class="font-medium">{{
                                formatCurrency(bucket.value)
                            }}</span>
                        </div>
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="bucket.bar"
                                :style="{ width: `${bucket.width}%` }"
                            />
                        </div>
                    </li>
                </ul>

                <p class="border-t pt-3 text-sm text-muted-foreground">
                    <span class="font-semibold text-foreground tabular-nums">{{
                        formatRate(finances.delinquency.rate)
                    }}</span>
                    do que venceu em {{ monthLabel }} ainda não foi pago.
                </p>
            </div>
        </DashboardPanel>
    </div>
</template>
