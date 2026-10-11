<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Columns3, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/deal-stages';
import type { FunnelStage } from '@/types';

const props = defineProps<{
    open: boolean;
    stages: FunnelStage[];
}>();

const emit = defineEmits<{
    close: [];
}>();

type EditableStage = { id: number | null; name: string; deals: number };

const openStages = ref<EditableStage[]>([]);
const wonName = ref('');
const lostName = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        openStages.value = props.stages
            .filter((stage) => stage.outcome === null)
            .map((stage) => ({
                id: stage.id,
                name: stage.name,
                deals: stage.deals.length,
            }));
        wonName.value =
            props.stages.find((stage) => stage.outcome === 'won')?.name ?? '';
        lostName.value =
            props.stages.find((stage) => stage.outcome === 'lost')?.name ?? '';
        errors.value = {};
    },
    { immediate: true },
);

function moveStage(index: number, offset: number) {
    const target = index + offset;

    if (target < 0 || target >= openStages.value.length) {
        return;
    }

    const [stage] = openStages.value.splice(index, 1);
    openStages.value.splice(target, 0, stage);
}

function addStage() {
    openStages.value.push({ id: null, name: '', deals: 0 });
}

function removeStage(index: number) {
    openStages.value.splice(index, 1);
}

function save() {
    processing.value = true;

    router.put(
        update().url,
        {
            stages: openStages.value.map(({ id, name }) => ({ id, name })),
            won_name: wonName.value,
            lost_name: lostName.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
            onError: (validationErrors) => (errors.value = validationErrors),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(isOpen) => !isOpen && emit('close')">
        <DialogScrollContent class="sm:max-w-lg">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Columns3 class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>Etapas do funil</DialogTitle>
                    <DialogDescription>
                        Crie, renomeie e ordene as etapas do jeito que vocês
                        trabalham.
                    </DialogDescription>
                </div>
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="save">
                <section class="grid gap-2">
                    <Label>Etapas em andamento</Label>
                    <ul class="grid gap-2">
                        <li
                            v-for="(stage, index) in openStages"
                            :key="stage.id ?? `new-${index}`"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="w-5 text-right text-sm text-muted-foreground tabular-nums"
                                >{{ index + 1 }}.</span
                            >
                            <Input
                                v-model="stage.name"
                                maxlength="40"
                                placeholder="Nome da etapa"
                                :aria-label="`Nome da etapa ${index + 1}`"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="index === 0"
                                @click="moveStage(index, -1)"
                            >
                                <ArrowUp class="size-4" />
                                <span class="sr-only">Subir</span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="index === openStages.length - 1"
                                @click="moveStage(index, 1)"
                            >
                                <ArrowDown class="size-4" />
                                <span class="sr-only">Descer</span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:text-destructive"
                                :disabled="
                                    stage.deals > 0 || openStages.length === 1
                                "
                                :title="
                                    stage.deals > 0
                                        ? 'Mova as negociações desta etapa antes de removê-la'
                                        : undefined
                                "
                                @click="removeStage(index)"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">Remover</span>
                            </Button>
                        </li>
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-fit"
                        :disabled="openStages.length >= 12"
                        @click="addStage"
                    >
                        <Plus class="size-4" />
                        Adicionar etapa
                    </Button>
                    <InputError
                        :message="
                            errors.stages ??
                            Object.entries(errors).find(([key]) =>
                                key.startsWith('stages.'),
                            )?.[1]
                        "
                    />
                </section>

                <section class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="won_name">Etapa de fechamento</Label>
                        <Input id="won_name" v-model="wonName" maxlength="40" />
                        <InputError :message="errors.won_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lost_name">Etapa de perdidos</Label>
                        <Input
                            id="lost_name"
                            v-model="lostName"
                            maxlength="40"
                        />
                        <InputError :message="errors.lost_name" />
                    </div>
                    <p class="text-xs text-muted-foreground sm:col-span-2">
                        Estas duas sempre ficam no fim do funil: uma para os
                        negócios fechados e outra para os perdidos.
                    </p>
                </section>

                <DialogFooter class="border-t pt-6">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Salvar etapas
                    </Button>
                </DialogFooter>
            </form>
        </DialogScrollContent>
    </Dialog>
</template>
