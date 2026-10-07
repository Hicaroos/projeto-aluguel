<script setup lang="ts">
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { maskCpf, maskDocument, maskPhone } from '@/lib/formatters';

type Mask = 'document' | 'cpf' | 'phone';

const props = defineProps<{
    /** document: CPF or CNPJ; cpf: CPF only; phone: landline or mobile with area code. */
    mask: Mask;
    defaultValue?: string | null;
}>();

defineOptions({ inheritAttrs: false });

const masks: Record<Mask, (value: string) => string> = {
    document: maskDocument,
    cpf: maskCpf,
    phone: maskPhone,
};

const maxLengths: Record<Mask, number> = {
    document: 18,
    cpf: 14,
    phone: 15,
};

const value = ref(masks[props.mask](props.defaultValue ?? ''));

watch(value, (current) => {
    const masked = masks[props.mask](current);

    if (masked !== current) {
        value.value = masked;
    }
});
</script>

<template>
    <Input
        v-bind="$attrs"
        v-model="value"
        inputmode="numeric"
        :maxlength="maxLengths[mask]"
    />
</template>
