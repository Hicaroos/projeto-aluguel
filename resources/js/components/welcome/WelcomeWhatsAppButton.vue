<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        href: string;
        variant?: 'solid' | 'outline';
        size?: 'sm' | 'lg';
    }>(),
    { variant: 'solid', size: 'lg' },
);
</script>

<template>
    <a
        :href="props.href"
        target="_blank"
        rel="noopener noreferrer"
        :class="
            cn(
                'inline-flex items-center gap-2.5 rounded-full font-semibold whitespace-nowrap transition-colors focus-visible:ring-4 focus-visible:ring-primary/30 focus-visible:outline-none',
                props.variant === 'solid'
                    ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                    : 'border-[1.5px] border-primary text-primary hover:bg-primary/5',
                props.size === 'lg'
                    ? 'px-7 py-3.5 text-[17px]'
                    : 'px-4 py-2 text-sm',
            )
        "
    >
        <MessageCircle
            v-if="props.variant === 'solid' && props.size === 'lg'"
            class="size-5"
            aria-hidden="true"
        />
        <slot>Falar no WhatsApp</slot>
    </a>
</template>
