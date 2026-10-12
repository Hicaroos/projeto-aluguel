<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/branch-selection';

const ALL_BRANCHES = 'all';

const page = usePage();

const selector = computed(() => page.props.branchSelector);

const selectedValue = computed(() =>
    selector.value?.selectedId
        ? String(selector.value.selectedId)
        : ALL_BRANCHES,
);

function selectBranch(value: unknown) {
    if (typeof value !== 'string' || value === selectedValue.value) {
        return;
    }

    router.put(update().url, {
        branch_id: value === ALL_BRANCHES ? null : Number(value),
    });
}
</script>

<template>
    <Select
        v-if="selector && selector.branches.length > 1"
        :model-value="selectedValue"
        @update:model-value="selectBranch"
    >
        <SelectTrigger
            size="sm"
            class="min-w-0 flex-1 gap-2 sm:w-auto sm:max-w-56 sm:min-w-44 sm:flex-none"
            aria-label="Unidade exibida"
        >
            <Building2 class="size-4 shrink-0 text-muted-foreground" />
            <SelectValue />
        </SelectTrigger>
        <SelectContent align="end">
            <SelectItem :value="ALL_BRANCHES">Todas as unidades</SelectItem>
            <SelectItem
                v-for="branch in selector.branches"
                :key="branch.id"
                :value="String(branch.id)"
            >
                {{ branch.name }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
