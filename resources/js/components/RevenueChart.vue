<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatCurrency } from '@/lib/currency';
import { formatMonthYear } from '@/lib/formatters';
import type { MonthlyRevenue } from '@/types';

const props = defineProps<{
    data: MonthlyRevenue[];
}>();

const showTable = ref(false);
const hoveredIndex = ref<number | null>(null);

const hasData = computed(() =>
    props.data.some((month) => month.expected > 0 || month.received > 0),
);

const axisMax = computed(() => {
    const highest = Math.max(
        ...props.data.flatMap((month) => [month.expected, month.received]),
        1,
    );
    const roughStep = highest / 4;
    const magnitude = 10 ** Math.floor(Math.log10(roughStep));
    const niceStep =
        [1, 2, 2.5, 5, 10].find((factor) => factor * magnitude >= roughStep)! *
        magnitude;

    return niceStep * 4;
});

const ticks = computed(() =>
    [4, 3, 2, 1, 0].map((step) => (axisMax.value / 4) * step),
);

function barHeight(value: number): string {
    return `${(value / axisMax.value) * 100}%`;
}

function compactCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

function receivedShare(month: MonthlyRevenue): string {
    return month.expected > 0
        ? `${Math.round((month.received / month.expected) * 100)}%`
        : '—';
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                <span class="flex items-center gap-2">
                    <span class="size-2.5 rounded-sm bg-primary" />
                    Recebido
                </span>
                <span class="flex items-center gap-2">
                    <span
                        class="size-2.5 rounded-sm bg-zinc-300 dark:bg-zinc-600"
                    />
                    Previsto
                </span>
            </div>
            <button
                type="button"
                class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                @click="showTable = !showTable"
            >
                {{ showTable ? 'Ver como gráfico' : 'Ver como tabela' }}
            </button>
        </div>

        <table v-if="showTable" class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-muted-foreground">
                    <th class="py-2 font-medium">Mês</th>
                    <th class="py-2 text-right font-medium">Previsto</th>
                    <th class="py-2 text-right font-medium">Recebido</th>
                    <th class="py-2 text-right font-medium">% recebido</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="month in data"
                    :key="month.month"
                    class="border-b last:border-0"
                >
                    <td class="py-2">{{ formatMonthYear(month.month) }}</td>
                    <td class="py-2 text-right tabular-nums">
                        {{ formatCurrency(month.expected) }}
                    </td>
                    <td class="py-2 text-right tabular-nums">
                        {{ formatCurrency(month.received) }}
                    </td>
                    <td class="py-2 text-right tabular-nums">
                        {{ receivedShare(month) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-else class="flex gap-3">
            <div
                class="flex h-52 flex-col justify-between pb-6 text-right text-xs text-muted-foreground tabular-nums"
                aria-hidden="true"
            >
                <span
                    v-for="tick in ticks"
                    :key="tick"
                    class="-translate-y-1/2 leading-none first:translate-y-0 last:translate-y-0"
                    >{{ compactCurrency(tick) }}</span
                >
            </div>

            <div class="relative flex-1">
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between"
                    aria-hidden="true"
                >
                    <div
                        v-for="tick in ticks"
                        :key="tick"
                        class="h-px bg-border"
                    />
                </div>

                <p
                    v-if="!hasData"
                    class="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-sm text-muted-foreground"
                >
                    Ainda não há cobranças nos últimos meses.
                </p>

                <div class="relative flex h-52">
                    <div
                        v-for="(month, monthIndex) in data"
                        :key="month.month"
                        class="relative flex flex-1 flex-col items-center"
                        @mouseenter="hoveredIndex = monthIndex"
                        @mouseleave="hoveredIndex = null"
                    >
                        <div
                            class="flex h-full w-full items-end justify-center gap-0.5 pb-6"
                            :class="
                                hoveredIndex === monthIndex
                                    ? 'rounded-md bg-muted/60'
                                    : ''
                            "
                        >
                            <div
                                class="w-full max-w-5 rounded-t bg-zinc-300 transition-[height] dark:bg-zinc-600"
                                :style="{ height: barHeight(month.expected) }"
                            />
                            <div
                                class="w-full max-w-5 rounded-t bg-primary transition-[height]"
                                :style="{ height: barHeight(month.received) }"
                            />
                        </div>
                        <span
                            class="absolute bottom-0 text-xs text-muted-foreground capitalize"
                            >{{
                                formatMonthYear(month.month, 'short').split(
                                    ' ',
                                )[0]
                            }}</span
                        >

                        <div
                            v-if="hoveredIndex === monthIndex"
                            class="pointer-events-none absolute bottom-full z-10 mb-2 w-44 rounded-lg border bg-popover p-3 text-sm shadow-md"
                        >
                            <p class="mb-2 font-medium">
                                {{ formatMonthYear(month.month) }}
                            </p>
                            <p
                                class="flex items-center justify-between gap-2 text-muted-foreground"
                            >
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="size-2 rounded-sm bg-primary"
                                    />
                                    Recebido
                                </span>
                                <span
                                    class="font-medium text-foreground tabular-nums"
                                    >{{ formatCurrency(month.received) }}</span
                                >
                            </p>
                            <p
                                class="flex items-center justify-between gap-2 text-muted-foreground"
                            >
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="size-2 rounded-sm bg-zinc-300 dark:bg-zinc-600"
                                    />
                                    Previsto
                                </span>
                                <span
                                    class="font-medium text-foreground tabular-nums"
                                    >{{ formatCurrency(month.expected) }}</span
                                >
                            </p>
                            <p
                                class="mt-1 border-t pt-1 text-xs text-muted-foreground"
                            >
                                {{ receivedShare(month) }} recebido
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
