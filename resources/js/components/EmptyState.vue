<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    icon: LucideIcon;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center"
    >
        <div
            class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <component :is="icon" class="size-6" />
        </div>
        <div class="max-w-sm space-y-1">
            <h3 class="font-medium">{{ title }}</h3>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <slot />
    </div>
</template>
