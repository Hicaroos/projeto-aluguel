<script setup lang="ts">
import { router, useHttp, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, UserRound } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import { formatDocument } from '@/lib/formatters';
import { index, update } from '@/routes/owner-selection';
import type { OwnerSelectorOption } from '@/types';

const ALL_OWNERS = 'all';

const page = usePage();

const selector = computed(() => page.props.ownerSelector);

const selectedValue = computed(() =>
    selector.value?.selected ? String(selector.value.selected.id) : ALL_OWNERS,
);

/** Loaded the first time the selector opens, so pages don't carry every owner around. */
const owners = ref<OwnerSelectorOption[] | null>(null);

const http = useHttp<Record<string, never>, OwnerSelectorOption[]>();

async function loadOwners(open: boolean) {
    if (!open || owners.value !== null || http.processing) {
        return;
    }

    owners.value = await http.get(index().url);
}

function labelOf(value: unknown): string {
    if (value === ALL_OWNERS || !value) {
        return 'Todos os proprietários';
    }

    return (
        owners.value?.find((owner) => String(owner.id) === value)?.name ??
        selector.value?.selected?.name ??
        ''
    );
}

function selectOwner(value: unknown) {
    if (typeof value !== 'string' || value === selectedValue.value) {
        return;
    }

    router.put(update().url, {
        owner_id: value === ALL_OWNERS ? null : Number(value),
    });
}
</script>

<template>
    <ComboboxRoot
        v-if="selector"
        :model-value="selectedValue"
        open-on-click
        class="min-w-0 flex-1 sm:flex-none"
        @update:model-value="selectOwner"
        @update:open="loadOwners"
    >
        <ComboboxAnchor
            class="flex h-8 w-full items-center gap-2 rounded-md border border-input bg-transparent pl-3 shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 sm:w-56 dark:bg-input/30"
            :class="{ 'border-primary/50 bg-primary/5': selector.selected }"
        >
            <UserRound class="size-4 shrink-0 text-muted-foreground" />
            <ComboboxInput
                :display-value="labelOf"
                placeholder="Buscar proprietário…"
                aria-label="Proprietário exibido"
                autocomplete="off"
                class="h-full min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            />
            <ComboboxTrigger
                class="flex h-full items-center px-2 text-muted-foreground"
                tabindex="-1"
            >
                <ChevronsUpDown class="size-4 opacity-60" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                align="end"
                :side-offset="4"
                class="z-50 max-h-80 w-72 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            >
                <ComboboxViewport class="max-h-80 overflow-y-auto p-1">
                    <div
                        v-if="owners === null"
                        class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground"
                    >
                        <Spinner />
                        Carregando proprietários…
                    </div>
                    <template v-else>
                        <ComboboxEmpty
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhum proprietário encontrado.
                        </ComboboxEmpty>
                        <ComboboxItem
                            :value="ALL_OWNERS"
                            text-value="Todos os proprietários"
                            class="relative flex cursor-pointer items-center rounded-sm py-1.5 pr-8 pl-2 text-sm font-medium outline-hidden select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        >
                            Todos os proprietários
                            <ComboboxItemIndicator
                                class="absolute right-2 flex size-3.5 items-center justify-center"
                            >
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                        <ComboboxItem
                            v-for="owner in owners"
                            :key="owner.id"
                            :value="String(owner.id)"
                            :text-value="`${owner.name} ${owner.cpf_cnpj ?? ''} ${formatDocument(owner.cpf_cnpj)}`"
                            class="relative flex cursor-pointer items-center rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        >
                            <div class="min-w-0">
                                <p class="truncate">{{ owner.name }}</p>
                                <p
                                    v-if="owner.cpf_cnpj"
                                    class="truncate text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ formatDocument(owner.cpf_cnpj) }}
                                </p>
                            </div>
                            <ComboboxItemIndicator
                                class="absolute right-2 flex size-3.5 items-center justify-center"
                            >
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </template>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
