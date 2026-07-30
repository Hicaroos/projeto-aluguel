<script setup lang="ts">
import { Building2 } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <Building2 :class="className" v-bind="$attrs" />
</template>
