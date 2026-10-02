<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarCheck, CircleX, FileCheck } from '@lucide/vue';
import { ref, watch } from 'vue';
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
import { daysUntil } from '@/lib/formatters';
import { finish } from '@/routes/leases';
import type { Lease } from '@/types';

const props = defineProps<{
    lease: Lease | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const selectedStatus = ref<'ended' | 'terminated'>('ended');
const processing = ref(false);

const options = [
    {
        value: 'ended' as const,
        icon: CalendarCheck,
        title: 'Encerrado',
        description: 'O contrato chegou ao fim do prazo combinado.',
    },
    {
        value: 'terminated' as const,
        icon: CircleX,
        title: 'Rescindido',
        description: 'O contrato foi interrompido antes do prazo.',
    },
];

watch(
    () => props.lease,
    (lease) => {
        if (lease) {
            selectedStatus.value =
                daysUntil(lease.end_date) <= 0 ? 'ended' : 'terminated';
        }
    },
);

function confirm() {
    if (!props.lease) {
        return;
    }

    processing.value = true;

    router.patch(
        finish(props.lease).url,
        { status: selectedStatus.value },
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog :open="!!lease" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="lease" class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <FileCheck class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>Finalizar contrato</DialogTitle>
                    <DialogDescription>
                        O imóvel em {{ lease.property.street }},
                        {{ lease.property.number }} voltará a ficar disponível.
                    </DialogDescription>
                </div>
            </DialogHeader>

            <div class="grid gap-3" role="radiogroup">
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="selectedStatus === option.value"
                    class="flex items-start gap-3 rounded-lg border p-4 text-left transition-colors hover:bg-accent"
                    :class="
                        selectedStatus === option.value
                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                            : ''
                    "
                    @click="selectedStatus = option.value"
                >
                    <component
                        :is="option.icon"
                        class="mt-0.5 size-5 shrink-0"
                        :class="
                            selectedStatus === option.value
                                ? 'text-primary'
                                : 'text-muted-foreground'
                        "
                    />
                    <div>
                        <p class="font-medium">{{ option.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ option.description }}
                        </p>
                    </div>
                </button>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button :disabled="processing" @click="confirm">
                    <Spinner v-if="processing" />
                    Finalizar contrato
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
