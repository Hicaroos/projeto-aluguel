<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CalendarClock } from '@lucide/vue';
import { formatDate } from '@/lib/formatters';

const page = usePage();
</script>

<template>
    <div
        v-if="page.props.isSimulatedToday"
        class="flex items-center justify-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-1.5 text-center text-xs font-medium text-amber-800 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300"
        role="status"
    >
        <CalendarClock class="size-3.5 shrink-0" />
        Data simulada: o sistema está funcionando como se hoje fosse
        {{ formatDate(page.props.today) }} (APP_FAKE_TODAY).
    </div>
</template>
