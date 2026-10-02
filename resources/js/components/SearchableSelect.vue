<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
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
import { computed } from 'vue';

export type SearchableSelectOption = {
    value: string;
    label: string;
    description?: string;
};

const props = withDefaults(
    defineProps<{
        options: SearchableSelectOption[];
        id?: string;
        name?: string;
        placeholder?: string;
        emptyText?: string;
    }>(),
    {
        placeholder: 'Digite para buscar…',
        emptyText: 'Nenhum resultado encontrado.',
    },
);

const model = defineModel<string>({ default: '' });

const optionsByValue = computed(
    () => new Map(props.options.map((option) => [option.value, option])),
);

function displayValue(value: unknown): string {
    return optionsByValue.value.get(String(value ?? ''))?.label ?? '';
}
</script>

<template>
    <ComboboxRoot v-model="model" open-on-click>
        <ComboboxAnchor
            class="flex h-9 w-full items-center rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
        >
            <ComboboxInput
                :id="id"
                :display-value="displayValue"
                :placeholder="placeholder"
                autocomplete="off"
                class="h-full min-w-0 flex-1 bg-transparent px-3 text-base outline-none placeholder:text-muted-foreground md:text-sm"
            />
            <ComboboxTrigger
                class="flex h-full items-center px-2.5 text-muted-foreground"
                tabindex="-1"
            >
                <ChevronsUpDown class="size-4 opacity-60" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <input v-if="name" type="hidden" :name="name" :value="model" />

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-72 w-(--reka-combobox-trigger-width) overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            >
                <ComboboxViewport class="max-h-72 overflow-y-auto p-1">
                    <ComboboxEmpty
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ emptyText }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                        :text-value="`${option.label} ${option.description ?? ''}`"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        <div class="min-w-0">
                            <p class="truncate">{{ option.label }}</p>
                            <p
                                v-if="option.description"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ option.description }}
                            </p>
                        </div>
                        <ComboboxItemIndicator
                            class="absolute right-2 flex size-3.5 items-center justify-center"
                        >
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
