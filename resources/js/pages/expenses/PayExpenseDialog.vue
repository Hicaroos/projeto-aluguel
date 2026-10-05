<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
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
import { Spinner } from '@/components/ui/spinner';
import { formatCurrency } from '@/lib/currency';
import { expenseTypeLabels } from '@/lib/expense-labels';
import { todayIsoDate } from '@/lib/formatters';
import type { Expense } from '@/types';

defineProps<{
    expense: Expense | null;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Dialog :open="!!expense" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="expense" class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <CircleCheck class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle>Marcar como paga</DialogTitle>
                    <DialogDescription class="truncate">
                        {{ expenseTypeLabels[expense.type] }} ·
                        {{ formatCurrency(expense.amount) }} ·
                        {{ expense.property.street }},
                        {{ expense.property.number }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Form
                v-bind="ExpenseController.pay.form(expense)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
            >
                <div class="grid gap-2">
                    <Label for="expense_payment_date">Data do pagamento</Label>
                    <Input
                        id="expense_payment_date"
                        name="payment_date"
                        type="date"
                        :max="todayIsoDate()"
                        :default-value="todayIsoDate()"
                    />
                    <InputError :message="errors.payment_date" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Confirmar pagamento
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
