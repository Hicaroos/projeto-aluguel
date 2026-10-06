<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { TableHead } from '@/components/ui/table';
import type { TableSort } from '@/lib/table-sort';
import { cn } from '@/lib/utils';

const props = defineProps<{
    column: string;
    label: string;
    current: TableSort;
    align?: 'left' | 'right';
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    sort: [column: string];
}>();

const isActive = computed(() => props.current.sort === props.column);
</script>

<template>
    <TableHead
        :class="
            cn(
                'h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase',
                props.class,
            )
        "
        :aria-sort="
            isActive
                ? current.direction === 'asc'
                    ? 'ascending'
                    : 'descending'
                : 'none'
        "
    >
        <button
            type="button"
            class="-mx-1 inline-flex items-center gap-1 rounded px-1 tracking-wide uppercase transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            :class="[
                isActive ? 'text-foreground' : '',
                align === 'right' ? 'flex-row-reverse' : '',
            ]"
            @click="emit('sort', column)"
        >
            {{ label }}
            <ArrowUp
                v-if="isActive && current.direction === 'asc'"
                class="size-3.5"
            />
            <ArrowDown v-else-if="isActive" class="size-3.5" />
            <ChevronsUpDown v-else class="size-3.5 opacity-40" />
        </button>
    </TableHead>
</template>
