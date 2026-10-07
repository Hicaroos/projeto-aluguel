<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowRight, Info, TrendingUp } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LeaseAdjustmentController from '@/actions/App/Http/Controllers/LeaseAdjustmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatCurrency } from '@/lib/currency';
import { formatDate } from '@/lib/formatters';
import {
    adjustedAmount,
    adjustmentIndexLabels,
    adjustmentIndexSources,
} from '@/lib/lease-labels';
import type { Lease } from '@/types';

type AdjustmentMode = 'percent' | 'amount';

const props = defineProps<{
    lease: Lease | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const mode = ref<AdjustmentMode>('percent');
const percent = ref('');
const amount = ref('');

watch(
    () => props.lease?.id,
    () => {
        mode.value =
            props.lease?.adjustment_index === 'negotiated'
                ? 'amount'
                : 'percent';
        percent.value = '';
        amount.value = '';
    },
    { immediate: true },
);

const isNegotiated = computed(
    () => props.lease?.adjustment_index === 'negotiated',
);

/** The adjusted rent and its percent, from whichever field the user is filling in. */
const preview = computed<{ amount: number; percent: number } | null>(() => {
    if (!props.lease) {
        return null;
    }

    const current = Number(props.lease.amount);

    if (mode.value === 'percent') {
        const value = Number(percent.value);

        return percent.value === '' || Number.isNaN(value)
            ? null
            : { amount: adjustedAmount(current, value), percent: value };
    }

    const value = Number(amount.value);

    return amount.value === '' || Number.isNaN(value) || current <= 0
        ? null
        : {
              amount: value,
              percent: Math.round((value / current - 1) * 10_000) / 100,
          };
});

const formatPercent = (value: number): string =>
    `${value > 0 ? '+' : ''}${value.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;

const modes: { value: AdjustmentMode; label: string }[] = [
    { value: 'percent', label: 'Pelo índice (%)' },
    { value: 'amount', label: 'Novo valor (R$)' },
];
</script>

<template>
    <Dialog :open="!!lease" @update:open="(open) => !open && emit('close')">
        <DialogScrollContent v-if="lease" class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <TrendingUp class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle>Aplicar reajuste anual</DialogTitle>
                    <DialogDescription class="truncate">
                        {{ lease.tenant.name }} · aniversário em
                        {{
                            lease.next_adjustment_date
                                ? formatDate(lease.next_adjustment_date)
                                : '—'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Form
                v-bind="LeaseAdjustmentController.store.form(lease)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
            >
                <input type="hidden" name="mode" :value="mode" />

                <div
                    class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1"
                    role="radiogroup"
                    aria-label="Como informar o reajuste"
                >
                    <button
                        v-for="option in modes"
                        :key="option.value"
                        type="button"
                        role="radio"
                        :aria-checked="mode === option.value"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            mode === option.value
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="mode = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div v-if="mode === 'percent'" class="grid gap-2">
                    <Label for="adjustment_percent">
                        {{
                            isNegotiated
                                ? 'Percentual combinado'
                                : `${adjustmentIndexLabels[lease.adjustment_index]} acumulado em 12 meses`
                        }}
                    </Label>
                    <div class="relative">
                        <Input
                            id="adjustment_percent"
                            v-model="percent"
                            name="percent"
                            type="number"
                            step="0.01"
                            min="-50"
                            max="100"
                            placeholder="Ex.: 4,52"
                            class="pr-8 tabular-nums"
                            autofocus
                        />
                        <span
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >%</span
                        >
                    </div>
                    <InputError :message="errors.percent" />
                    <p
                        v-if="!isNegotiated"
                        class="flex gap-1.5 text-xs text-muted-foreground"
                    >
                        <Info class="mt-0.5 size-3.5 shrink-0" />
                        Consulte o percentual no
                        {{ adjustmentIndexSources[lease.adjustment_index] }}.
                    </p>
                </div>

                <div v-else class="grid gap-2">
                    <Label for="adjustment_amount">Novo valor do aluguel</Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >R$</span
                        >
                        <Input
                            id="adjustment_amount"
                            v-model="amount"
                            name="new_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0,00"
                            class="pl-10 tabular-nums"
                            autofocus
                        />
                    </div>
                    <InputError :message="errors.new_amount" />
                    <p class="flex gap-1.5 text-xs text-muted-foreground">
                        <Info class="mt-0.5 size-3.5 shrink-0" />
                        Use quando o novo aluguel foi combinado com o inquilino.
                        O percentual é calculado para o histórico.
                    </p>
                </div>

                <div
                    class="flex items-center justify-between gap-3 rounded-lg border bg-muted/40 px-4 py-3 text-sm"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">Atual</p>
                        <p class="font-medium tabular-nums">
                            {{ formatCurrency(lease.amount) }}
                        </p>
                    </div>
                    <ArrowRight class="size-4 text-muted-foreground" />
                    <div class="text-right">
                        <p class="text-xs text-muted-foreground">
                            Novo aluguel
                            <span
                                v-if="preview"
                                class="font-medium tabular-nums"
                                >({{ formatPercent(preview.percent) }})</span
                            >
                        </p>
                        <p
                            class="text-base font-semibold text-primary tabular-nums"
                        >
                            {{ preview ? formatCurrency(preview.amount) : '—' }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="adjustment_notes">
                        Observação
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="adjustment_notes"
                        name="notes"
                        :placeholder="
                            isNegotiated
                                ? 'Ex.: valor combinado com o inquilino'
                                : 'Ex.: IGP-M de janeiro/2027'
                        "
                    />
                    <InputError :message="errors.notes" />
                </div>

                <p class="text-xs text-muted-foreground">
                    O novo valor vale a partir do mês do aniversário: as
                    cobranças desse mês em diante que ainda não têm pagamento
                    serão atualizadas.
                </p>

                <DialogFooter class="border-t pt-6">
                    <DialogClose as-child>
                        <Button type="button" variant="outline"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Aplicar reajuste
                    </Button>
                </DialogFooter>
            </Form>
        </DialogScrollContent>
    </Dialog>
</template>
