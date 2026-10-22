<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const appName = usePage().props.name;
</script>

<template>
    <main
        class="flex min-h-dvh items-center justify-center bg-background p-6 md:p-10"
    >
        <!-- Every sign-in screen gets the same fields and buttons: a little taller, rounded, on white, with pill buttons. -->
        <div
            class="flex w-full max-w-[360px] flex-col gap-8 **:data-[slot=button]:h-10 **:data-[slot=button]:rounded-full **:data-[slot=button]:font-semibold **:data-[slot=input]:h-10 **:data-[slot=input]:rounded-lg **:data-[slot=input]:bg-card **:data-[slot=input]:pl-3.5 **:data-[slot=select-trigger]:h-10! **:data-[slot=select-trigger]:rounded-lg **:data-[slot=select-trigger]:bg-card"
        >
            <Link
                :href="home()"
                class="flex items-center justify-center gap-2 text-sm font-semibold"
            >
                <div
                    class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-4" />
                </div>
                {{ appName }}
            </Link>

            <div class="space-y-2 text-center">
                <h1
                    v-if="title"
                    class="text-[1.875rem] leading-tight font-bold tracking-[-0.03em]"
                >
                    {{ title }}
                </h1>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>

            <slot />
        </div>
    </main>
</template>
