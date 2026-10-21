<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleX } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { iconTones } from '@/lib/badge-tones';
import { move } from '@/routes/deals';
import type { Deal, DealStage } from '@/types';

const props = defineProps<{
    deal: Deal | null;
    lostStage: DealStage | undefined;
}>();

const emit = defineEmits<{
    close: [];
}>();

const SUGGESTIONS = [
    'Achou caro',
    'Financiamento negado',
    'Comprou outro imóvel',
    'Desistiu',
    'Sem retorno',
];

const reason = ref('');
const error = ref<string | undefined>();
const processing = ref(false);

watch(
    () => props.deal,
    () => {
        reason.value = '';
        error.value = undefined;
    },
);

function confirm() {
    if (!props.deal || !props.lostStage) {
        return;
    }

    processing.value = true;

    router.patch(
        move(props.deal).url,
        { deal_stage_id: props.lostStage.id, lost_reason: reason.value },
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
            onError: (errors) => (error.value = errors.lost_reason),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog :open="!!deal" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full"
                    :class="iconTones.danger"
                >
                    <CircleX class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>Negociação perdida</DialogTitle>
                    <DialogDescription>
                        Por que a negociação com {{ deal?.lead.name }} não deu
                        certo?
                    </DialogDescription>
                </div>
            </DialogHeader>

            <form class="grid gap-3" @submit.prevent="confirm">
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="suggestion in SUGGESTIONS"
                        :key="suggestion"
                        type="button"
                        size="sm"
                        :variant="reason === suggestion ? 'default' : 'outline'"
                        @click="reason = suggestion"
                    >
                        {{ suggestion }}
                    </Button>
                </div>
                <div class="grid gap-2">
                    <Label for="lost_reason">Motivo</Label>
                    <Input
                        id="lost_reason"
                        v-model="reason"
                        maxlength="255"
                        placeholder="Ou escreva o motivo"
                    />
                    <InputError :message="error" />
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        Marcar como perdida
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
