<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <section class="flex flex-col rounded-xl border bg-card shadow-xs">
        <header
            class="flex items-start justify-between gap-4 border-b px-5 py-4"
        >
            <div class="space-y-0.5">
                <h2 class="font-semibold">{{ title }}</h2>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <slot name="action" />
        </header>
        <div class="flex-1">
            <slot />
        </div>
    </section>
</template>
