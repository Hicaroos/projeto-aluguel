<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import OwnerSelectionBanner from '@/components/OwnerSelectionBanner.vue';
import SimulatedDateBanner from '@/components/SimulatedDateBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <SimulatedDateBanner />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <OwnerSelectionBanner />
            <slot />
        </AppContent>
        <Toaster position="top-right" />
    </AppShell>
</template>
