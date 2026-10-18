<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Eye,
    Handshake,
    MoreHorizontal,
    Pencil,
    Plus,
    SearchX,
    Trash2,
    UserPlus,
    UserSearch,
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
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import { formatPhone } from '@/lib/formatters';
import { propertyTypeLabels } from '@/lib/property-labels';
import {
    leadInterestBadgeClasses,
    leadInterestLabels,
    leadSourceLabels,
} from '@/lib/sales-labels';
import { nextSort } from '@/lib/table-sort';
import type { TableSort } from '@/lib/table-sort';
import DealForm from '@/pages/deals/DealForm.vue';
import LeadDetailsDialog from '@/pages/leads/LeadDetailsDialog.vue';
import LeadForm from '@/pages/leads/LeadForm.vue';
import { destroy, index } from '@/routes/leads';
import type {
    DealPropertyOption,
    Lead,
    LeadInterest,
    LeadPaginator,
    LeadSource,
    TeamMemberOption,
} from '@/types';

const can = usePermissions();

const props = defineProps<{
    leads: LeadPaginator;
    filters: TableSort & {
        search: string;
        interest: LeadInterest | null;
        source: LeadSource | null;
    };
    selected: Lead | null;
    users: TeamMemberOption[];
    properties: DealPropertyOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Interessados', href: '/leads' }],
    },
});

const search = ref(props.filters.search);
const interest = ref<LeadInterest | 'all'>(props.filters.interest ?? 'all');
const source = ref<LeadSource | 'all'>(props.filters.source ?? 'all');

function visit(overrides: Partial<TableSort> = {}) {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            interest: interest.value === 'all' ? undefined : interest.value,
            source: source.value === 'all' ? undefined : source.value,
            sort: props.filters.sort,
            direction: props.filters.direction,
            ...overrides,
        },
        { preserveState: true, replace: true, only: ['leads', 'filters'] },
    );
}

watchDebounced(search, () => visit(), { debounce: 350 });
watch([interest, source], () => visit());

function sortBy(column: string) {
    visit(nextSort(props.filters, column));
}

function clearFilters() {
    search.value = '';
    interest.value = 'all';
    source.value = 'all';
}

const isFormDialogOpen = ref(false);
const formDialogLead = ref<Lead | null>(null);

function openCreateDialog() {
    formDialogLead.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(lead: Lead) {
    leadToShow.value = null;
    formDialogLead.value = lead;
    isFormDialogOpen.value = true;
}

const leadToShow = ref<Lead | null>(null);

useOpenSelectedRecord(
    () => props.selected,
    (lead) => (leadToShow.value = lead),
);

const leadForNewDeal = ref<Lead | null>(null);

function openNewDeal(lead: Lead) {
    leadToShow.value = null;
    leadForNewDeal.value = lead;
}

const leadToDelete = ref<Lead | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!leadToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(leadToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            leadToDelete.value = null;
        },
    });
}

/** What the lead looks for, e.g. "Casa até R$ 300.000,00". */
function lookingFor(lead: Lead): string {
    const type = lead.property_type
        ? propertyTypeLabels[lead.property_type]
        : 'Imóvel';

    return lead.max_price
        ? `${type} até ${formatCurrency(lead.max_price)}`
        : type;
}
</script>

<template>
    <Head title="Interessados" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Interessados"
            description="Quem procura um imóvel para comprar ou alugar."
        >
            <Button v-if="can.manageSales" @click="openCreateDialog">
                <Plus class="size-4" />
                Cadastrar interessado
            </Button>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Buscar por nome, celular ou e-mail"
                />
                <Select v-model="interest">
                    <SelectTrigger class="w-full sm:w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos os interesses</SelectItem>
                        <SelectItem
                            v-for="(label, key) in leadInterestLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="source">
                    <SelectTrigger class="w-full sm:w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas as origens</SelectItem>
                        <SelectItem
                            v-for="(label, key) in leadSourceLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <EmptyState
                v-if="
                    leads.data.length === 0 &&
                    (filters.search || filters.interest || filters.source)
                "
                :icon="SearchX"
                title="Nenhum interessado encontrado"
                description="Não encontramos interessados com esses filtros."
            >
                <Button variant="outline" size="sm" @click="clearFilters">
                    Limpar filtros
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="leads.data.length === 0"
                :icon="UserSearch"
                title="Nenhum interessado cadastrado"
                description="Cadastre quem procura um imóvel para acompanhar a negociação no funil."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <SortableTableHead
                            column="name"
                            label="Interessado"
                            :current="filters"
                            @sort="sortBy"
                        />
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase md:table-cell"
                            >Procura</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:table-cell"
                            >Interesse</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase lg:table-cell"
                            >Origem</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:table-cell"
                            >Negociações</TableHead
                        >
                        <TableHead class="h-11 w-0 px-4"
                            ><span class="sr-only">Ações</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="lead in leads.data"
                        :key="lead.id"
                        class="cursor-pointer"
                        @click="leadToShow = lead"
                    >
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <Avatar class="size-10">
                                    <AvatarFallback
                                        class="bg-primary/10 text-sm font-medium text-primary"
                                    >
                                        {{ getInitials(lead.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ lead.name }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground tabular-nums"
                                    >
                                        {{ formatPhone(lead.phone) }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{ lookingFor(lead) }}
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 sm:table-cell">
                            <Badge
                                variant="outline"
                                :class="leadInterestBadgeClasses[lead.interest]"
                            >
                                {{ leadInterestLabels[lead.interest] }}
                            </Badge>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-muted-foreground lg:table-cell"
                        >
                            {{
                                lead.source
                                    ? leadSourceLabels[lead.source]
                                    : '—'
                            }}
                            <p v-if="lead.user" class="text-xs">
                                {{ lead.user.name }}
                            </p>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 tabular-nums sm:table-cell"
                        >
                            {{ lead.deals_count ?? 0 }}
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
                                        @click="leadToShow = lead"
                                    >
                                        <Eye class="size-4" />
                                        Ver detalhes
                                    </DropdownMenuItem>
                                    <template v-if="can.manageSales">
                                        <DropdownMenuItem
                                            @click="openNewDeal(lead)"
                                        >
                                            <Handshake class="size-4" />
                                            Nova negociação
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="openEditDialog(lead)"
                                        >
                                            <Pencil class="size-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            @click="leadToDelete = lead"
                                        >
                                            <Trash2 class="size-4" />
                                            Excluir
                                        </DropdownMenuItem>
                                    </template>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <TablePagination
                v-if="leads.data.length > 0"
                :paginator="leads"
                item-label="interessados"
                item-label-singular="interessado"
                class="border-t"
            />
        </div>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent>
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <component
                        :is="formDialogLead ? Pencil : UserPlus"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>{{
                        formDialogLead
                            ? 'Editar interessado'
                            : 'Cadastrar interessado'
                    }}</DialogTitle>
                    <DialogDescription>
                        Contato e o que a pessoa procura.
                    </DialogDescription>
                </div>
            </DialogHeader>
            <LeadForm
                :key="formDialogLead?.id ?? 'create'"
                :lead="formDialogLead"
                :users="users"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <Dialog
        :open="!!leadForNewDeal"
        @update:open="(open) => !open && (leadForNewDeal = null)"
    >
        <DialogScrollContent class="sm:max-w-xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Handshake class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>Nova negociação</DialogTitle>
                    <DialogDescription>
                        {{ leadForNewDeal?.name }} entra no começo do funil.
                    </DialogDescription>
                </div>
            </DialogHeader>
            <DealForm
                v-if="leadForNewDeal"
                :key="leadForNewDeal.id"
                :leads="[leadForNewDeal]"
                :properties="properties"
                :users="users"
                @success="leadForNewDeal = null"
            />
        </DialogScrollContent>
    </Dialog>

    <LeadDetailsDialog
        :lead="leadToShow"
        @close="leadToShow = null"
        @edit="openEditDialog"
        @new-deal="openNewDeal"
    />

    <ConfirmDeleteDialog
        :open="!!leadToDelete"
        title="Excluir interessado?"
        :processing="isDeleting"
        @close="leadToDelete = null"
        @confirm="confirmDelete"
    >
        <span class="font-medium text-foreground">{{
            leadToDelete?.name
        }}</span>
        e as negociações dele serão removidos. Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
