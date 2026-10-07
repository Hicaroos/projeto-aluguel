<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, House, NotebookPen, Pencil, Trash2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
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
import { formatCurrency } from '@/lib/currency';
import {
    expenseDisplayStatus,
    expenseDueHint,
    expenseStatusBadgeClasses,
    expenseStatusDotClasses,
    expenseStatusLabels,
    expenseTypeIcons,
    expenseTypeLabels,
} from '@/lib/expense-labels';
import { formatDate } from '@/lib/formatters';
import { index as propertiesIndex } from '@/routes/properties';
import type { Expense } from '@/types';

defineProps<{
    expense: Expense | null;
}>();

const emit = defineEmits<{
    close: [];
    edit: [expense: Expense];
    pay: [expense: Expense];
    delete: [expense: Expense];
}>();
</script>

<template>
    <Dialog :open="!!expense" @update:open="(open) => !open && emit('close')">
        <DialogContent
            v-if="expense"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader class="flex-row items-start gap-4 text-left">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <component
                        :is="expenseTypeIcons[expense.type]"
                        class="size-6"
                    />
                </div>
                <div class="min-w-0 space-y-1.5 pr-6">
                    <DialogTitle class="leading-snug">
                        {{ expenseTypeLabels[expense.type] }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ expense.property.street }},
                        {{ expense.property.number }}
                        <template v-if="expense.property.complement">
                            - {{ expense.property.complement }}
                        </template>
                        · {{ expense.property.city }}/{{
                            expense.property.state
                        }}
                    </DialogDescription>
                    <Badge
                        variant="outline"
                        :class="
                            expenseStatusBadgeClasses[
                                expenseDisplayStatus(expense)
                            ]
                        "
                    >
                        <span
                            class="size-1.5 rounded-full"
                            :class="
                                expenseStatusDotClasses[
                                    expenseDisplayStatus(expense)
                                ]
                            "
                        />
                        {{ expenseStatusLabels[expenseDisplayStatus(expense)] }}
                    </Badge>
                </div>
            </DialogHeader>

            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border bg-muted/40 p-3">
                    <dt class="text-xs text-muted-foreground">Valor</dt>
                    <dd class="text-lg font-semibold tabular-nums">
                        {{ formatCurrency(expense.amount) }}
                    </dd>
                </div>
                <div class="rounded-lg border bg-muted/40 p-3">
                    <dt class="text-xs text-muted-foreground">Vencimento</dt>
                    <dd class="font-semibold tabular-nums">
                        {{ formatDate(expense.due_date) }}
                    </dd>
                    <dd
                        v-if="expenseDueHint(expense)"
                        class="text-xs font-medium"
                        :class="expenseDueHint(expense)?.class"
                    >
                        {{ expenseDueHint(expense)?.label }}
                    </dd>
                </div>
                <div class="rounded-lg border bg-muted/40 p-3">
                    <dt class="text-xs text-muted-foreground">Paga em</dt>
                    <dd
                        class="font-semibold tabular-nums"
                        :class="{ 'text-primary': expense.payment_date }"
                    >
                        {{
                            expense.payment_date
                                ? formatDate(expense.payment_date)
                                : 'Não paga'
                        }}
                    </dd>
                </div>
            </dl>

            <div v-if="expense.description" class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <NotebookPen class="size-3.5" />
                    Descrição
                </h3>
                <p
                    class="rounded-lg border bg-muted/40 p-3 text-sm whitespace-pre-line"
                >
                    {{ expense.description }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            propertiesIndex({
                                query: { show: expense.property_id },
                            })
                        "
                    >
                        <House class="size-4" />
                        Ver imóvel
                    </Link>
                </Button>
            </div>

            <DialogFooter class="border-t pt-6">
                <Button
                    variant="ghost"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive sm:mr-auto"
                    @click="emit('delete', expense)"
                >
                    <Trash2 class="size-4" />
                    Excluir
                </Button>
                <DialogClose as-child>
                    <Button variant="outline">Fechar</Button>
                </DialogClose>
                <Button variant="outline" @click="emit('edit', expense)">
                    <Pencil class="size-4" />
                    Editar
                </Button>
                <Button
                    v-if="expense.status === 'pending'"
                    @click="emit('pay', expense)"
                >
                    <CircleCheck class="size-4" />
                    Marcar como paga
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
