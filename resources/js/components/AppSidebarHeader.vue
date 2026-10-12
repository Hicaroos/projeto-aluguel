<script setup lang="ts">
import BranchSelector from '@/components/BranchSelector.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import OwnerSelector from '@/components/OwnerSelector.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex min-h-16 shrink-0 flex-wrap items-center gap-2 border-b border-sidebar-border/70 px-6 py-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-12 sm:flex-nowrap sm:py-0 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div
            class="flex w-full items-center gap-2 empty:hidden sm:ml-auto sm:w-auto"
        >
            <OwnerSelector />
            <BranchSelector />
        </div>
    </header>
</template>
