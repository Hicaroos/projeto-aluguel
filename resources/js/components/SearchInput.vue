<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { Input } from '@/components/ui/input';

defineProps<{
    placeholder?: string;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="relative w-full sm:max-w-sm">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="model"
            type="search"
            :placeholder="placeholder"
            class="pr-9 pl-9 [&::-webkit-search-cancel-button]:hidden"
        />
        <button
            v-if="model"
            type="button"
            class="absolute top-1/2 right-2 -translate-y-1/2 rounded-sm p-1 text-muted-foreground transition-colors hover:text-foreground"
            @click="model = ''"
        >
            <X class="size-3.5" />
            <span class="sr-only">Limpar busca</span>
        </button>
    </div>
</template>
