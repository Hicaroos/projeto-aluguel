<script setup lang="ts">
defineProps<{
    eyebrow: string;
    title: string;
    text: string;
    /** Wide tiles take more of their row, for the pictures that need the room. */
    wide?: boolean;
}>();
</script>

<template>
    <article
        :class="[
            'flex min-w-0 flex-col gap-7 rounded-[28px] bg-white p-8 sm:p-10',
            wide ? 'flex-[1.6_1_520px]' : 'flex-[1_1_300px]',
        ]"
    >
        <div>
            <div class="text-[15px] font-semibold text-zinc-500">
                {{ eyebrow }}
            </div>
            <h3
                class="mt-2 text-[30px] leading-[1.12] font-bold tracking-[-0.025em] text-balance"
            >
                {{ title }}
            </h3>
            <p class="mt-3 max-w-[480px] text-zinc-500">{{ text }}</p>
        </div>
        <div class="mt-auto">
            <slot />
        </div>
    </article>
</template>
