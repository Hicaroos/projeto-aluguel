<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    Clock,
    Coins,
    Eye,
    MoreHorizontal,
    Pencil,
    Plus,
    SearchX,
    Trash2,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortableTableHead from '@/components/SortableTableHead.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useOpenSelectedRecord } from '@/composables/useOpenSelectedRecord';
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
import { nextSort } from '@/lib/table-sort';
import type { TableSort } from '@/lib/table-sort';
import ExpenseDetailsDialog from '@/pages/expenses/ExpenseDetailsDialog.vue';
import ExpenseForm from '@/pages/expenses/ExpenseForm.vue';
import PayExpenseDialog from '@/pages/expenses/PayExpenseDialog.vue';
import { destroy, index } from '@/routes/expenses';
import type {
    Expense,
    ExpenseDisplayStatus,
    ExpensePaginator,
    ExpensePropertyOption,
    ExpenseSummary,
    ExpenseType,
} from '@/types';

const props = defineProps<{
    expenses: ExpensePaginator;
    summary: ExpenseSummary;
    filters: TableSort & {
        month: string;
        search: string;
        status: ExpenseDisplayStatus | null;
        type: ExpenseType | null;
    };
    selected: Expense | null;
    properties: ExpensePropertyOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Despesas', href: '/expenses' }],
    },
});

const search = ref(props.filters.search);
const status = ref<ExpenseDisplayStatus | 'all'>(props.filters.status ?? 'all');
const type = ref<ExpenseType | 'all'>(props.filters.type ?? 'all');

function visit(overrides: Record<string, string | undefined> = {}) {
    router.get(
        index().url,
        {
            month: props.filters.month,
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            type: type.value === 'all' ? undefined : type.value,
            sort: props.filters.sort,
            direction: props.filters.direction,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, () => visit(), { debounce: 350 });
watch([status, type], () => visit());

function sortBy(column: string) {
    visit(nextSort(props.filters, column));
}

const isFiltering = computed(
    () =>
        !!props.filters.search ||
        !!props.filters.status ||
        !!props.filters.type,
);

function clearFilters() {
    search.value = '';
    status.value = 'all';
    type.value = 'all';
}

const summaryCards = computed(() => [
    {
        label: 'Total do mês',
        value: props.summary.total,
        icon: Wallet,
        class: 'bg-muted text-foreground',
    },
    {
        label: 'Pagas',
        value: props.summary.paid,
        icon: CircleCheck,
        class: 'bg-primary/10 text-primary',
    },
    {
        label: 'A pagar',
        value: props.summary.pending,
        icon: Clock,
        class: 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
    },
    {
        label: 'Em atraso',
        value: props.summary.overdue,
        icon: TriangleAlert,
        class: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
    },
]);

const isFormDialogOpen = ref(false);
const formDialogExpense = ref<Expense | null>(null);

function openCreateDialog() {
    formDialogExpense.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(expense: Expense) {
    formDialogExpense.value = expense;
    isFormDialogOpen.value = true;
}

const expenseToShow = ref<Expense | null>(null);

useOpenSelectedRecord(
    () => props.selected,
    (expense) => (expenseToShow.value = expense),
);

/**
 * Close the details before opening another dialog from it, so they don't stack.
 */
function fromDetails(open: (expense: Expense) => void, expense: Expense) {
    expenseToShow.value = null;
    open(expense);
}

const expenseToPay = ref<Expense | null>(null);

const expenseToDelete = ref<Expense | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!expenseToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(expenseToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            expenseToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Despesas" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Despesas"
            description="Registre IPTU, condomínio, manutenções e outros custos dos seus imóveis."
        >
            <MonthNavigator
                :month="filters.month"
                @change="(month) => visit({ month })"
            />
            <Button @click="openCreateDialog">
                <Plus class="size-4" />
                Cadastrar despesa
            </Button>
        </PageHeader>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                    :class="card.class"
                >
                    <component :is="card.icon" class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p class="truncate text-lg font-semibold tabular-nums">
                        {{ formatCurrency(card.value) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Buscar por descrição, rua ou bairro"
                />
                <Select v-model="status">
                    <SelectTrigger class="w-full sm:w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas as situações</SelectItem>
                        <SelectItem
                            v-for="(label, key) in expenseStatusLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="type">
                    <SelectTrigger class="w-full sm:w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos os tipos</SelectItem>
                        <SelectItem
                            v-for="(label, key) in expenseTypeLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <EmptyState
                v-if="expenses.data.length === 0 && isFiltering"
                :icon="SearchX"
                title="Nenhuma despesa encontrada"
                description="Não encontramos despesas com esses filtros."
            >
                <Button variant="outline" size="sm" @click="clearFilters">
                    Limpar filtros
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="expenses.data.length === 0"
                :icon="Coins"
                title="Nenhuma despesa neste mês"
                description="As despesas cadastradas aparecem no mês do seu vencimento."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <SortableTableHead
                            column="type"
                            label="Despesa"
                            :current="filters"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="property"
                            label="Imóvel"
                            :current="filters"
                            class="hidden lg:table-cell"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="due_date"
                            label="Vencimento"
                            :current="filters"
                            class="hidden md:table-cell"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="amount"
                            label="Valor"
                            :current="filters"
                            align="right"
                            class="text-right"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="status"
                            label="Situação"
                            :current="filters"
                            class="hidden sm:table-cell"
                            @sort="sortBy"
                        />
                        <TableHead class="h-11 w-0 px-4"
                            ><span class="sr-only">Ações</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="expense in expenses.data"
                        :key="expense.id"
                        class="cursor-pointer"
                        @click="expenseToShow = expense"
                    >
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <component
                                        :is="expenseTypeIcons[expense.type]"
                                        class="size-5"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ expenseTypeLabels[expense.type] }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{
                                            expense.description ??
                                            `${expense.property.street}, ${expense.property.number}`
                                        }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 lg:table-cell">
                            <p class="truncate">
                                {{ expense.property.street }},
                                {{ expense.property.number }}
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ expense.property.neighborhood }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 md:table-cell">
                            <p class="tabular-nums">
                                {{ formatDate(expense.due_date) }}
                            </p>
                            <p
                                v-if="expenseDueHint(expense)"
                                class="text-sm font-medium"
                                :class="expenseDueHint(expense)?.class"
                            >
                                {{ expenseDueHint(expense)?.label }}
                            </p>
                            <p
                                v-else-if="expense.payment_date"
                                class="text-sm text-muted-foreground"
                            >
                                Paga em {{ formatDate(expense.payment_date) }}
                            </p>
                        </TableCell>
                        <TableCell
                            class="px-4 py-3 text-right font-medium tabular-nums"
                        >
                            {{ formatCurrency(expense.amount) }}
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 sm:table-cell">
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
                                {{
                                    expenseStatusLabels[
                                        expenseDisplayStatus(expense)
                                    ]
                                }}
                            </Badge>
                        </TableCell>
                        <TableCell class="px-4 py-3" @click.stop>
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="expense.status === 'pending'"
                                    variant="outline"
                                    size="sm"
                                    class="hidden xl:inline-flex"
                                    @click="expenseToPay = expense"
                                >
                                    <CircleCheck class="size-4" />
                                    Pagar
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <MoreHorizontal class="size-4" />
                                            <span class="sr-only">Ações</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-48"
                                    >
                                        <DropdownMenuItem
                                            @click="expenseToShow = expense"
                                        >
                                            <Eye class="size-4" />
                                            Ver detalhes
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="expense.status === 'pending'"
                                            @click="expenseToPay = expense"
                                        >
                                            <CircleCheck class="size-4" />
                                            Marcar como paga
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="openEditDialog(expense)"
                                        >
                                            <Pencil class="size-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            @click="expenseToDelete = expense"
                                        >
                                            <Trash2 class="size-4" />
                                            Excluir
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <TablePagination
                v-if="expenses.data.length > 0"
                :paginator="expenses"
                item-label="despesas"
                item-label-singular="despesa"
                class="border-t"
            />
        </div>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent class="sm:max-w-xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <component
                        :is="formDialogExpense ? Pencil : Coins"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>{{
                        formDialogExpense
                            ? 'Editar despesa'
                            : 'Cadastrar despesa'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogExpense
                                ? 'Atualize as informações da despesa.'
                                : 'Registre um custo de um dos seus imóveis.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <ExpenseForm
                :key="formDialogExpense?.id ?? 'create'"
                :expense="formDialogExpense"
                :properties="properties"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <ExpenseDetailsDialog
        :expense="expenseToShow"
        @close="expenseToShow = null"
        @edit="fromDetails(openEditDialog, $event)"
        @pay="fromDetails((expense) => (expenseToPay = expense), $event)"
        @delete="fromDetails((expense) => (expenseToDelete = expense), $event)"
    />

    <PayExpenseDialog :expense="expenseToPay" @close="expenseToPay = null" />

    <ConfirmDeleteDialog
        :open="!!expenseToDelete"
        title="Excluir despesa?"
        :processing="isDeleting"
        @close="expenseToDelete = null"
        @confirm="confirmDelete"
    >
        A despesa de
        <span class="font-medium text-foreground">{{
            expenseToDelete ? formatCurrency(expenseToDelete.amount) : ''
        }}</span>
        será removida. Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
