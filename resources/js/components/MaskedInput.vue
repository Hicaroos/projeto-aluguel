<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { Input } from '@/components/ui/input';
import {
    maskCpf,
    maskDocument,
    maskPhone,
    maskZipCode,
} from '@/lib/formatters';

type Mask = 'document' | 'cpf' | 'phone' | 'zip';

const props = defineProps<{
    /** document: CPF or CNPJ; cpf: CPF only; phone: landline or mobile with area code; zip: CEP. */
    mask: Mask;
    defaultValue?: string | null;
}>();

/** Optional: the masked value, for forms that react to it, such as the zip code lookup. */
const model = defineModel<string>();

defineOptions({ inheritAttrs: false });

const masks: Record<Mask, (value: string) => string> = {
    document: maskDocument,
    cpf: maskCpf,
    phone: maskPhone,
    zip: maskZipCode,
};

const maxLengths: Record<Mask, number> = {
    document: 18,
    cpf: 14,
    phone: 15,
    zip: 9,
};

const value = ref(masks[props.mask](model.value ?? props.defaultValue ?? ''));
const field = ref<ComponentPublicInstance | null>(null);

const isDigit = (char: string | undefined): boolean =>
    char !== undefined && char >= '0' && char <= '9';

/**
 * Let Backspace and Delete jump over the dots, dashes and brackets, which the mask would put right
 * back, so they remove the digit next to them instead.
 */
function skipSeparators(event: KeyboardEvent) {
    const input = event.target as HTMLInputElement;
    const start = input.selectionStart;

    if (start === null || start !== input.selectionEnd) {
        return;
    }

    let position = start;

    if (event.key === 'Backspace') {
        while (position > 0 && !isDigit(input.value[position - 1])) {
            position--;
        }
    } else if (event.key === 'Delete') {
        while (
            position < input.value.length &&
            !isDigit(input.value[position])
        ) {
            position++;
        }
    }

    if (position !== start) {
        input.setSelectionRange(position, position);
    }
}

/** Find where the cursor goes to stay right after the given number of digits. */
function caretAfterDigits(text: string, digits: number): number {
    if (digits === 0) {
        return 0;
    }

    let seen = 0;

    for (let index = 0; index < text.length; index++) {
        if (isDigit(text[index]) && ++seen === digits) {
            return index + 1;
        }
    }

    return text.length;
}

watch(value, async (current) => {
    const input = field.value?.$el as HTMLInputElement | undefined;

    // Right after typing, the field still holds what was typed, with the cursor where the user typed:
    // count the digits before it to put the cursor back after them once the mask rewrites the text,
    // which would otherwise send it to the end.
    const digits =
        input && document.activeElement === input && input.value === current
            ? current
                  .slice(0, input.selectionStart ?? current.length)
                  .replace(/\D/g, '').length
            : null;

    const masked = masks[props.mask](current);

    if (masked !== current) {
        value.value = masked;
    }

    model.value = masked;

    if (input === undefined || digits === null) {
        return;
    }

    await nextTick();

    if (document.activeElement === input) {
        const position = caretAfterDigits(input.value, digits);

        input.setSelectionRange(position, position);
    }
});

watch(model, (current) => {
    if (current !== undefined && current !== value.value) {
        value.value = masks[props.mask](current);
    }
});
</script>

<template>
    <Input
        ref="field"
        v-bind="$attrs"
        v-model="value"
        inputmode="numeric"
        :maxlength="maxLengths[mask]"
        @keydown="skipSeparators"
    />
</template>
