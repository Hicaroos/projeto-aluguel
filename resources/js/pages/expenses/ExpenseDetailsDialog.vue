<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    CircleCheck,
    FileDown,
    HardHat,
    House,
    NotebookPen,
    Pencil,
    Star,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import ExpenseDocumentController from '@/actions/App/Http/Controllers/ExpenseDocumentController';
import AttachedDocuments from '@/components/AttachedDocuments.vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import {
    expenseDisplayStatus,
    expenseDocumentTypeLabels,
    expenseDueHint,
    expenseStatusBadgeClasses,
    expenseStatusDotClasses,
    expenseStatusLabels,
    expenseTypeIcons,
    expenseTypeLabels,
    isServiceExpense,
} from '@/lib/expense-labels';
import { formatDate, formatDocument } from '@/lib/formatters';
import { index as propertiesIndex } from '@/routes/properties';
import type {
    AttachedDocument,
    ContractTemplateOption,
    Expense,
} from '@/types';

const props = defineProps<{
    expense: Expense | null;
    /** The service receipt templates to choose from. */
    templates: ContractTemplateOption[];
}>();

const emit = defineEmits<{
    close: [];
    edit: [expense: Expense];
    pay: [expense: Expense];
    delete: [expense: Expense];
}>();

const can = usePermissions();

const isService = computed(
    () => !!props.expense && isServiceExpense(props.expense.type),
);

function receiptUrl(expense: Expense, templateId?: number): string {
    return ExpenseDocumentController.receipt(
        expense,
        templateId ? { query: { template: templateId } } : undefined,
    ).url;
}
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

            <div v-if="isService" class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <HardHat class="size-3.5" />
                    Prestador do serviço
                </h3>
                <div class="rounded-lg border bg-muted/40 p-3 text-sm">
                    <template v-if="expense.provider_name">
                        <p class="font-medium">{{ expense.provider_name }}</p>
                        <p
                            v-if="expense.provider_document"
                            class="text-muted-foreground tabular-nums"
                        >
                            {{ formatDocument(expense.provider_document) }}
                        </p>
                    </template>
                    <p v-else class="text-muted-foreground">
                        Não informado. O recibo sai com linhas para o prestador
                        preencher à mão.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <template v-if="isService">
                    <Button
                        v-if="templates.length <= 1"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a :href="receiptUrl(expense)" target="_blank">
                            <FileDown class="size-4" />
                            Recibo do prestador
                        </a>
                    </Button>
                    <DropdownMenu v-else>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm">
                                <FileDown class="size-4" />
                                Recibo do prestador
                                <ChevronDown class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-64">
                            <DropdownMenuLabel
                                >Escolha o modelo</DropdownMenuLabel
                            >
                            <DropdownMenuItem
                                v-for="template in templates"
                                :key="template.id"
                                as-child
                            >
                                <a
                                    :href="receiptUrl(expense, template.id)"
                                    target="_blank"
                                >
                                    <span class="min-w-0 flex-1 truncate">{{
                                        template.name
                                    }}</span>
                                    <Star
                                        v-if="template.is_default"
                                        class="size-3.5 text-primary"
                                    />
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
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

            <AttachedDocuments
                :documents="expense.documents ?? []"
                :type-labels="expenseDocumentTypeLabels"
                :primary-type="isService ? 'signed_receipt' : 'bill'"
                :upload-url="ExpenseDocumentController.store(expense).url"
                :delete-url="
                    (document: AttachedDocument) =>
                        ExpenseDocumentController.destroy(document).url
                "
                :can-manage="can.manageFinance"
                :empty-hint="
                    isService
                        ? 'Recibo assinado pelo prestador, nota fiscal, fotos…'
                        : 'Boleto, comprovante de pagamento…'
                "
            />

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
