<script setup lang="ts">
import type { ShowcaseSite } from '@/types';

/** The brand icons the icon set leaves out, drawn in the same stroke style. */
defineProps<{
    network: ShowcaseSite['socials'][number]['network'];
}>();
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="network === 'instagram'">
            <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle
                cx="17.2"
                cy="6.8"
                r=".9"
                fill="currentColor"
                stroke="none"
            />
        </template>
        <path
            v-else-if="network === 'facebook'"
            d="M14.5 21v-7h2.6l.4-3h-3V9.2c0-.9.3-1.5 1.6-1.5h1.6V5a20 20 0 00-2.3-.1c-2.3 0-3.9 1.4-3.9 4V11H8.9v3h2.6v7"
        />
        <template v-else>
            <rect x="2.5" y="5.5" width="19" height="13" rx="4" />
            <path d="M10.2 9.4v5.2l4.5-2.6z" fill="currentColor" />
        </template>
    </svg>
</template>
