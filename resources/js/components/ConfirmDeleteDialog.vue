<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    open: boolean;
    title: string;
    processing?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="(value) => !value && emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="items-center gap-4 sm:flex-row sm:items-start">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                >
                    <TriangleAlert class="size-5" />
                </div>
                <div class="space-y-2">
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>
                        <slot />
                    </DialogDescription>
                </div>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    <Spinner v-if="processing" />
                    Excluir
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
