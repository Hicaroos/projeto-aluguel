<script setup lang="ts">
import { Briefcase } from '@lucide/vue';
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/currency';
import type { DashboardAgencyFinances } from '@/types';

const props = defineProps<{
    open: boolean;
    revenue: DashboardAgencyFinances['revenue'];
    /** The month name, e.g. "outubro de 2026". */
    monthLabel: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const lines = computed(() => [
    { label: 'Taxas de administração', value: props.revenue.adminFees },
    {
        label: 'Taxas de intermediação',
        value: props.revenue.intermediationFees,
    },
    { label: 'Taxas de renovação', value: props.revenue.renewalFees },
    { label: 'Comissões de venda', value: props.revenue.saleCommissions },
    {
        label: 'Multa e juros que ficaram com a imobiliária',
        value: props.revenue.lateCharges,
    },
    {
        label: 'Cobranças extras que ficaram com a imobiliária',
        value: props.revenue.extraCharges,
    },
]);
</script>

<template>
    <Dialog :open="open" @update:open="(value) => !value && emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="flex-row items-start gap-4 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Briefcase class="size-5" />
                </div>
                <div class="space-y-1.5">
                    <DialogTitle>Receita da imobiliária</DialogTitle>
                    <DialogDescription>
                        O que a imobiliária ganhou em {{ monthLabel }}.
                    </DialogDescription>
                </div>
            </DialogHeader>

            <dl class="divide-y rounded-lg border text-sm tabular-nums">
                <div
                    v-for="line in lines"
                    :key="line.label"
                    class="flex items-center justify-between gap-3 px-4 py-2.5"
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
                    class="flex items-center justify-between gap-3 px-4 py-2.5"
                >
                    <dt class="text-muted-foreground">
                        Despesas pagas pela imobiliária
                    </dt>
                    <dd
                        class="shrink-0 font-medium"
                        :class="
                            revenue.expenses > 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-muted-foreground/60'
                        "
                    >
                        − {{ formatCurrency(revenue.expenses) }}
                    </dd>
                </div>
                <div
                    class="flex items-center justify-between gap-3 bg-muted/40 px-4 py-3"
                >
                    <dt class="font-semibold">Resultado</dt>
                    <dd
                        class="text-base font-semibold"
                        :class="
                            revenue.result < 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-primary'
                        "
                    >
                        {{ formatCurrency(revenue.result) }}
                    </dd>
                </div>
            </dl>
        </DialogContent>
    </Dialog>
</template>
