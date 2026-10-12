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
    owners: DashboardAgencyFinances['owners'];
}>();

const figures = computed(() => [
    {
        label: 'Recebido dos inquilinos no mês',
        value: props.owners.received,
        highlight: false,
    },
    {
        label: 'A repassar hoje',
        value: props.owners.available,
        highlight: true,
    },
    {
        label: 'Aguardando o prazo',
        value: props.owners.pending,
        highlight: false,
    },
    {
        label: 'Repassado no mês',
        value: props.owners.paidOut,
        highlight: false,
    },
]);
</script>

<template>
    <DashboardPanel
        title="Dinheiro dos proprietários"
        description="O que a imobiliária recebeu dos inquilinos e deve repassar."
    >
        <template #action>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="payoutsIndex()">
                    Repasses
                    <ArrowRight class="size-4" />
                </Link>
            </Button>
        </template>

        <dl
            class="grid gap-px bg-border sm:grid-cols-2"
            :class="owners.advanced > 0 ? 'xl:grid-cols-5' : 'xl:grid-cols-4'"
        >
            <div
                v-for="figure in figures"
                :key="figure.label"
                class="bg-card px-5 py-4"
            >
                <dt
                    class="text-xs"
                    :class="
                        figure.highlight
                            ? 'font-medium text-primary'
                            : 'text-muted-foreground'
                    "
                >
                    {{ figure.label }}
                </dt>
                <dd
                    class="mt-1 text-lg font-semibold tabular-nums"
                    :class="{ 'text-primary': figure.highlight }"
                >
                    {{ formatCurrency(figure.value) }}
                </dd>
            </div>
            <div v-if="owners.advanced > 0" class="bg-card px-5 py-4">
                <dt class="text-xs text-muted-foreground">
                    Aluguel garantido adiantado
                </dt>
                <dd
                    class="mt-1 text-lg font-semibold text-attention-foreground tabular-nums"
                >
                    {{ formatCurrency(owners.advanced) }}
                </dd>
                <dd class="text-xs text-muted-foreground">
                    A imobiliária pagou e o inquilino ainda deve.
                </dd>
            </div>
        </dl>
    </DashboardPanel>
</template>
