<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowRight, CalendarPlus, Info } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LeaseRenewalController from '@/actions/App/Http/Controllers/LeaseRenewalController';
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
import { formatDate } from '@/lib/formatters';
import type { Lease } from '@/types';

type RenewalTerm = 12 | 24 | 30 | 'custom';

const props = defineProps<{
    lease: Lease | null;
}>();

const emit = defineEmits<{
    close: [];
    renewed: [leaseId: number];
}>();

const terms: { value: RenewalTerm; label: string }[] = [
    { value: 12, label: '12 meses' },
    { value: 24, label: '24 meses' },
    { value: 30, label: '30 meses' },
    { value: 'custom', label: 'Outra data' },
];

const term = ref<RenewalTerm>(12);
const customEndDate = ref('');

watch(
    () => props.lease?.id,
    () => {
        term.value = 12;
        customEndDate.value = '';
    },
    { immediate: true },
);

/**
 * Add whole months to an ISO date, keeping the day or capping it to the last day of
 * a shorter month (e.g. 2026-02-28 + 12 months = 2027-02-28).
 */
function addMonths(isoDate: string, months: number): string {
    const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1 + months, 1));
    const lastDay = new Date(
        Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0),
    ).getUTCDate();

    target.setUTCDate(Math.min(day, lastDay));

    return target.toISOString().slice(0, 10);
}

/** The earliest new end date: the day after the current one. */
const minCustomEndDate = computed(() => {
    if (!props.lease) {
        return '';
    }

    const date = new Date(`${props.lease.end_date.slice(0, 10)}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + 1);

    return date.toISOString().slice(0, 10);
});

const newEndDate = computed(() => {
    if (!props.lease) {
        return '';
    }

    return term.value === 'custom'
        ? customEndDate.value
        : addMonths(props.lease.end_date, term.value);
});
</script>

<template>
    <Dialog :open="!!lease" @update:open="(open) => !open && emit('close')">
        <DialogScrollContent v-if="lease" class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <CalendarPlus class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle>Renovar contrato</DialogTitle>
                    <DialogDescription class="truncate">
                        {{ lease.tenant.name }} · {{ lease.property.street }},
                        {{ lease.property.number }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Form
                v-bind="LeaseRenewalController.store.form(lease)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                :options="{ preserveScroll: true }"
                @success="emit('renewed', lease.id)"
            >
                <input type="hidden" name="new_end_date" :value="newEndDate" />

                <div class="grid gap-2">
                    <Label>Prorrogar por</Label>
                    <div
                        class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 sm:grid-cols-4"
                        role="radiogroup"
                        aria-label="Prazo da renovação"
                    >
                        <button
                            v-for="option in terms"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="term === option.value"
                            class="rounded-md px-2 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                term === option.value
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="term = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div v-if="term === 'custom'" class="grid gap-2">
                    <Label for="renewal_end_date">Novo término</Label>
                    <Input
                        id="renewal_end_date"
                        v-model="customEndDate"
                        type="date"
                        :min="minCustomEndDate"
                    />
                </div>

                <div class="grid gap-2">
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border bg-muted/40 px-4 py-3 text-sm"
                    >
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Término atual
                            </p>
                            <p class="font-medium tabular-nums">
                                {{ formatDate(lease.end_date) }}
                            </p>
                        </div>
                        <ArrowRight class="size-4 text-muted-foreground" />
                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">
                                Novo término
                            </p>
                            <p
                                class="text-base font-semibold text-primary tabular-nums"
                            >
                                {{ newEndDate ? formatDate(newEndDate) : '—' }}
                            </p>
                        </div>
                    </div>
                    <InputError :message="errors.new_end_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="renewal_notes">
                        Observação
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="renewal_notes"
                        name="notes"
                        placeholder="Ex.: renovado conforme combinado com o inquilino"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <p class="flex gap-1.5 text-xs text-muted-foreground">
                    <Info class="mt-0.5 size-3.5 shrink-0" />
                    O valor do aluguel continua o mesmo. Se o aniversário do
                    contrato entrar no novo prazo, o reajuste anual fica
                    disponível logo em seguida. Depois de renovar, gere o termo
                    aditivo nos detalhes do contrato.
                </p>

                <DialogFooter class="border-t pt-6">
                    <DialogClose as-child>
                        <Button type="button" variant="outline"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="processing || !newEndDate">
                        <Spinner v-if="processing" />
                        Renovar contrato
                    </Button>
                </DialogFooter>
            </Form>
        </DialogScrollContent>
    </Dialog>
</template>
