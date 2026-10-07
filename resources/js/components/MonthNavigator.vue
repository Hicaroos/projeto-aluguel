<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { currentDate, formatMonthYear } from '@/lib/formatters';

const props = defineProps<{
    /** The selected month, as YYYY-MM. */
    month: string;
}>();

const emit = defineEmits<{
    change: [month: string];
}>();

function toMonthString(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

const currentMonth = toMonthString(currentDate());

function shift(months: number) {
    const [year, month] = props.month.split('-').map(Number);

    emit('change', toMonthString(new Date(year, month - 1 + months, 1)));
}
</script>

<template>
    <div class="flex items-center gap-2">
        <Button
            v-if="month !== currentMonth"
            variant="ghost"
            size="sm"
            @click="emit('change', currentMonth)"
        >
            Ir para o mês atual
        </Button>
        <div class="flex items-center gap-1 rounded-lg border bg-card p-1">
            <Button variant="ghost" size="icon-sm" @click="shift(-1)">
                <ChevronLeft class="size-4" />
                <span class="sr-only">Mês anterior</span>
            </Button>
            <span class="min-w-36 text-center text-sm font-medium">
                {{ formatMonthYear(`${month}-01`) }}
            </span>
            <Button variant="ghost" size="icon-sm" @click="shift(1)">
                <ChevronRight class="size-4" />
                <span class="sr-only">Próximo mês</span>
            </Button>
        </div>
    </div>
</template>
