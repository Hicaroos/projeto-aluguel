<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginator } from '@/types';

const props = defineProps<{
    paginator: Paginator<unknown>;
    itemLabel: string;
    itemLabelSingular: string;
}>();

const pageLinks = computed(() => props.paginator.links.slice(1, -1));
</script>

<template>
    <div
        v-if="paginator.total > 0"
        class="flex flex-col items-center justify-between gap-3 px-4 py-3 sm:flex-row"
    >
        <p class="text-sm text-muted-foreground">
            <span class="font-medium text-foreground">{{
                paginator.total
            }}</span>
            {{ paginator.total === 1 ? itemLabelSingular : itemLabel }}
            <template v-if="paginator.last_page > 1">
                · Página {{ paginator.current_page }} de
                {{ paginator.last_page }}
            </template>
        </p>

        <nav
            v-if="paginator.last_page > 1"
            class="flex items-center gap-1"
            aria-label="Paginação"
        >
            <Button
                variant="outline"
                size="icon-sm"
                :disabled="!paginator.prev_page_url"
                :as-child="!!paginator.prev_page_url"
            >
                <Link
                    v-if="paginator.prev_page_url"
                    :href="paginator.prev_page_url"
                    preserve-scroll
                >
                    <ChevronLeft class="size-4" />
                    <span class="sr-only">Página anterior</span>
                </Link>
                <ChevronLeft v-else class="size-4" />
            </Button>

            <template v-for="(link, linkIndex) in pageLinks" :key="linkIndex">
                <span
                    v-if="!link.url"
                    class="px-2 text-sm text-muted-foreground"
                    >…</span
                >
                <Button
                    v-else
                    :variant="link.active ? 'default' : 'ghost'"
                    size="icon-sm"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll>{{
                        link.label
                    }}</Link>
                </Button>
            </template>

            <Button
                variant="outline"
                size="icon-sm"
                :disabled="!paginator.next_page_url"
                :as-child="!!paginator.next_page_url"
            >
                <Link
                    v-if="paginator.next_page_url"
                    :href="paginator.next_page_url"
                    preserve-scroll
                >
                    <ChevronRight class="size-4" />
                    <span class="sr-only">Próxima página</span>
                </Link>
                <ChevronRight v-else class="size-4" />
            </Button>
        </nav>
    </div>
</template>
