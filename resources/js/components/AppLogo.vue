<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();

const agency = computed(() => {
    const account = page.props.auth.user?.account;

    return account?.type === 'agency' ? account : null;
});
</script>

<template>
    <div
        v-if="agency?.logo_url"
        class="flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white"
    >
        <img
            :src="agency.logo_url"
            :alt="agency.name"
            class="size-full object-contain"
        />
    </div>
    <div
        v-else
        class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            agency?.name ?? page.props.name
        }}</span>
        <span
            v-if="agency"
            class="truncate text-xs leading-tight text-muted-foreground"
            >{{ page.props.name }}</span
        >
    </div>
</template>
