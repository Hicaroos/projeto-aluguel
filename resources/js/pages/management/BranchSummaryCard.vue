<script setup lang="ts">
import { Building2, Landmark } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/lib/currency';
import type { BranchSummary } from '@/types';

defineProps<{
    title: string;
    subtitle?: string | null;
    summary: BranchSummary;
    /** The whole agency, shown apart from the branches. */
    isTotal?: boolean;
    isInactive?: boolean;
}>();

function percentage(part: number, total: number): number {
    return total > 0 ? Math.min(100, Math.round((part / total) * 100)) : 0;
}
</script>

<template>
    <article
        class="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs"
        :class="{
            'border-primary/40 bg-primary/5': isTotal,
            'opacity-70': isInactive,
        }"
    >
        <header class="flex items-start gap-3">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
                <component
                    :is="isTotal ? Landmark : Building2"
                    class="size-5"
                />
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h2 class="truncate font-medium">{{ title }}</h2>
                    <Badge v-if="isInactive" variant="outline">Inativa</Badge>
                </div>
                <p
                    v-if="subtitle"
                    class="truncate text-xs text-muted-foreground"
                >
                    {{ subtitle }}
                </p>
            </div>
        </header>

        <div>
            <div class="flex items-baseline justify-between gap-2">
                <p class="text-xs text-muted-foreground">Recebido no mês</p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    {{ percentage(summary.received, summary.expected) }}%
                </p>
            </div>
            <p class="text-xl font-semibold tabular-nums">
                {{ formatCurrency(summary.received) }}
            </p>
            <p class="text-xs text-muted-foreground tabular-nums">
                de {{ formatCurrency(summary.expected) }} previstos
            </p>
            <div
                class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                role="meter"
                :aria-valuenow="percentage(summary.received, summary.expected)"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="Recebido no mês"
            >
                <div
                    class="h-full rounded-full bg-primary"
                    :style="{
                        width: `${percentage(summary.received, summary.expected)}%`,
                    }"
                />
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t pt-4 text-sm">
            <div>
                <dt class="text-xs text-muted-foreground">Em atraso</dt>
                <dd
                    class="font-medium tabular-nums"
                    :class="{
                        'text-rose-600 dark:text-rose-400':
                            summary.overdueCount > 0,
                    }"
                >
                    {{ formatCurrency(summary.overdue) }}
                </dd>
                <dd class="text-xs text-muted-foreground">
                    {{
                        summary.overdueCount === 1
                            ? '1 cobrança'
                            : `${summary.overdueCount} cobranças`
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Ocupação</dt>
                <dd class="font-medium tabular-nums">
                    {{
                        percentage(
                            summary.rentedProperties,
                            summary.properties,
                        )
                    }}%
                </dd>
                <dd class="text-xs text-muted-foreground tabular-nums">
                    {{ summary.rentedProperties }} de
                    {{ summary.properties }} imóveis
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Contratos ativos</dt>
                <dd class="font-medium tabular-nums">
                    {{ summary.activeLeases }}
                </dd>
                <dd class="text-xs text-muted-foreground tabular-nums">
                    {{ summary.endingLeases }} vencem em 60 dias
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Imóveis vagos</dt>
                <dd class="font-medium tabular-nums">
                    {{ summary.vacantProperties }}
                </dd>
            </div>
        </dl>
    </article>
</template>
