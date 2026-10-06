<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Eye,
    FileCheck,
    FilePlus,
    FileText,
    MoreHorizontal,
    Pencil,
    Plus,
    SearchX,
    Trash2,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref, watch } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortableTableHead from '@/components/SortableTableHead.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { getInitials } from '@/composables/useInitials';
import { useOpenSelectedRecord } from '@/composables/useOpenSelectedRecord';
import { formatCurrency } from '@/lib/currency';
import { formatDate } from '@/lib/formatters';
import {
    leaseDeadlineHint,
    leaseStatusBadgeClasses,
    leaseStatusDotClasses,
    leaseStatusLabels,
} from '@/lib/lease-labels';
import { nextSort } from '@/lib/table-sort';
import type { TableSort } from '@/lib/table-sort';
import LeaseDetailsDialog from '@/pages/leases/LeaseDetailsDialog.vue';
import LeaseFinishDialog from '@/pages/leases/LeaseFinishDialog.vue';
import LeaseForm from '@/pages/leases/LeaseForm.vue';
import { destroy, index } from '@/routes/leases';
import type {
    Lease,
    LeasePaginator,
    LeasePropertyOption,
    LeaseStatus,
    LeaseTenantOption,
} from '@/types';

const props = defineProps<{
    leases: LeasePaginator;
    filters: TableSort & {
        search: string;
        status: LeaseStatus | null;
    };
    selected: Lease | null;
    properties: LeasePropertyOption[];
    tenants: LeaseTenantOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Contratos', href: '/leases' }],
    },
});

const search = ref(props.filters.search);
const status = ref<LeaseStatus | 'all'>(props.filters.status ?? 'all');

function applyFilters(overrides: Partial<TableSort> = {}) {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            sort: props.filters.sort,
            direction: props.filters.direction,
            ...overrides,
        },
        { preserveState: true, replace: true, only: ['leases', 'filters'] },
    );
}

watchDebounced(search, () => applyFilters(), { debounce: 350 });
watch(status, () => applyFilters());

function sortBy(column: string) {
    applyFilters(nextSort(props.filters, column));
}

const isFiltering = () => !!props.filters.search || !!props.filters.status;

function clearFilters() {
    search.value = '';
    status.value = 'all';
}

const isFormDialogOpen = ref(false);
const formDialogLease = ref<Lease | null>(null);

function openCreateDialog() {
    formDialogLease.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(lease: Lease) {
    leaseToShow.value = null;
    formDialogLease.value = lease;
    isFormDialogOpen.value = true;
}

const leaseToShow = ref<Lease | null>(null);

useOpenSelectedRecord(
    () => props.selected,
    (lease) => (leaseToShow.value = lease),
);

const leaseToFinish = ref<Lease | null>(null);

function openFinishDialog(lease: Lease) {
    leaseToShow.value = null;
    leaseToFinish.value = lease;
}

const leaseToDelete = ref<Lease | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!leaseToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(leaseToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            leaseToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Contratos" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Contratos"
            description="Acompanhe a vigência e os valores dos contratos de aluguel."
        >
            <Button @click="openCreateDialog">
                <Plus class="size-4" />
                Cadastrar contrato
            </Button>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Buscar por inquilino, rua, bairro ou cidade"
                />
                <Select v-model="status">
                    <SelectTrigger class="w-full sm:w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas as situações</SelectItem>
                        <SelectItem
                            v-for="(label, key) in leaseStatusLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <EmptyState
                v-if="leases.data.length === 0 && isFiltering()"
                :icon="SearchX"
                title="Nenhum contrato encontrado"
                description="Não encontramos contratos com esses filtros. Tente buscar por outro termo."
            >
                <Button variant="outline" size="sm" @click="clearFilters">
                    Limpar filtros
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="leases.data.length === 0"
                :icon="FileText"
                title="Nenhum contrato cadastrado"
                description="Cadastre um contrato para vincular um inquilino a um dos seus imóveis."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <SortableTableHead
                            column="tenant"
                            label="Contrato"
                            :current="filters"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="start_date"
                            label="Vigência"
                            :current="filters"
                            class="hidden md:table-cell"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="amount"
                            label="Aluguel"
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
                        v-for="lease in leases.data"
                        :key="lease.id"
                        class="cursor-pointer"
                        @click="leaseToShow = lease"
                    >
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <Avatar class="size-10">
                                    <AvatarFallback
                                        class="bg-primary/10 text-sm font-medium text-primary"
                                    >
                                        {{ getInitials(lease.tenant.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ lease.tenant.name }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ lease.property.street }},
                                        {{ lease.property.number }} ·
                                        {{ lease.property.neighborhood }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 md:table-cell">
                            <p class="tabular-nums">
                                {{ formatDate(lease.start_date) }} –
                                {{ formatDate(lease.end_date) }}
                            </p>
                            <p
                                v-if="leaseDeadlineHint(lease)"
                                class="text-sm font-medium"
                                :class="leaseDeadlineHint(lease)?.class"
                            >
                                {{ leaseDeadlineHint(lease)?.label }}
                            </p>
                        </TableCell>
                        <TableCell class="px-4 py-3 text-right">
                            <p class="font-medium tabular-nums">
                                {{ formatCurrency(lease.amount) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Vence dia {{ lease.due_day }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 sm:table-cell">
                            <Badge
                                variant="outline"
                                :class="leaseStatusBadgeClasses[lease.status]"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="leaseStatusDotClasses[lease.status]"
                                />
                                {{ leaseStatusLabels[lease.status] }}
                            </Badge>
                        </TableCell>
                        <TableCell class="px-4 py-3" @click.stop>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon-sm">
                                        <MoreHorizontal class="size-4" />
                                        <span class="sr-only">Ações</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuItem
                                        @click="leaseToShow = lease"
                                    >
                                        <Eye class="size-4" />
                                        Ver detalhes
                                    </DropdownMenuItem>
                                    <template v-if="lease.status === 'active'">
                                        <DropdownMenuItem
                                            @click="openEditDialog(lease)"
                                        >
                                            <Pencil class="size-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="openFinishDialog(lease)"
                                        >
                                            <FileCheck class="size-4" />
                                            Finalizar contrato
                                        </DropdownMenuItem>
                                    </template>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @click="leaseToDelete = lease"
                                    >
                                        <Trash2 class="size-4" />
                                        Excluir
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <TablePagination
                v-if="leases.data.length > 0"
                :paginator="leases"
                item-label="contratos"
                item-label-singular="contrato"
                class="border-t"
            />
        </div>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <component
                        :is="formDialogLease ? Pencil : FilePlus"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>{{
                        formDialogLease
                            ? 'Editar contrato'
                            : 'Cadastrar contrato'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogLease
                                ? 'Atualize as informações do contrato.'
                                : 'Vincule um inquilino a um imóvel disponível.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <LeaseForm
                :key="formDialogLease?.id ?? 'create'"
                :lease="formDialogLease"
                :properties="properties"
                :tenants="tenants"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <LeaseDetailsDialog
        :lease="leaseToShow"
        @close="leaseToShow = null"
        @edit="openEditDialog"
        @finish="openFinishDialog"
    />

    <LeaseFinishDialog :lease="leaseToFinish" @close="leaseToFinish = null" />

    <ConfirmDeleteDialog
        :open="!!leaseToDelete"
        title="Excluir contrato?"
        :processing="isDeleting"
        @close="leaseToDelete = null"
        @confirm="confirmDelete"
    >
        O contrato de
        <span class="font-medium text-foreground">{{
            leaseToDelete?.tenant.name
        }}</span>
        será removido.
        <template v-if="leaseToDelete?.status === 'active'">
            O imóvel voltará a ficar disponível.
        </template>
        Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
