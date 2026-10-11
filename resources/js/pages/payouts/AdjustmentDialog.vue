<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Scale } from '@lucide/vue';
import { ref } from 'vue';
import LedgerAdjustmentController from '@/actions/App/Http/Controllers/LedgerAdjustmentController';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { todayIsoDate } from '@/lib/formatters';
import type { StatementOwner, StatementPropertyOption } from '@/types';

defineProps<{
    open: boolean;
    owner: StatementOwner;
    properties: StatementPropertyOption[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const NONE = 'none';

const direction = ref<'credit' | 'debit'>('debit');
const propertyId = ref(NONE);
</script>

<template>
    <Dialog :open="open" @update:open="(isOpen) => !isOpen && emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Scale class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle>Lançar ajuste</DialogTitle>
                    <DialogDescription>
                        Para o que as regras não cobrem, como um desconto
                        combinado ou um reembolso.
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Form
                v-bind="LedgerAdjustmentController.store.form(owner.id)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
            >
                <input type="hidden" name="direction" :value="direction" />
                <input
                    type="hidden"
                    name="property_id"
                    :value="propertyId === NONE ? '' : propertyId"
                />

                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        :variant="direction === 'debit' ? 'default' : 'outline'"
                        @click="direction = 'debit'"
                    >
                        Descontar do proprietário
                    </Button>
                    <Button
                        type="button"
                        :variant="
                            direction === 'credit' ? 'default' : 'outline'
                        "
                        @click="direction = 'credit'"
                    >
                        Creditar ao proprietário
                    </Button>
                </div>

                <div class="grid gap-2">
                    <Label for="adjustment_description">Descrição</Label>
                    <Input
                        id="adjustment_description"
                        name="description"
                        placeholder="Ex.: troca do chuveiro, desconto combinado"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="adjustment_amount">Valor</Label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >R$</span
                            >
                            <Input
                                id="adjustment_amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="pl-10 tabular-nums"
                            />
                        </div>
                        <InputError :message="errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="adjustment_date">Data</Label>
                        <Input
                            id="adjustment_date"
                            name="occurred_on"
                            type="date"
                            :default-value="todayIsoDate()"
                        />
                        <InputError :message="errors.occurred_on" />
                    </div>
                </div>

                <div v-if="properties.length > 0" class="grid gap-2">
                    <Label for="adjustment_property">
                        Imóvel
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Select v-model="propertyId">
                        <SelectTrigger id="adjustment_property" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">Nenhum</SelectItem>
                            <SelectItem
                                v-for="property in properties"
                                :key="property.id"
                                :value="String(property.id)"
                            >
                                {{ property.street }}, {{ property.number
                                }}{{
                                    property.complement
                                        ? ` - ${property.complement}`
                                        : ''
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.property_id" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Lançar ajuste
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
