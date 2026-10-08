<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Eye,
    House,
    MoreHorizontal,
    Pencil,
    Plus,
    SearchX,
    Trash2,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
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
import { formatZipCode } from '@/lib/formatters';
import {
    propertyStatusBadgeClasses,
    propertyStatusDotClasses,
    propertyStatusLabels,
    propertyTypeIcons,
    propertyTypeLabels,
} from '@/lib/property-labels';
import { nextSort } from '@/lib/table-sort';
import type { TableSort } from '@/lib/table-sort';
import PropertyDetailsDialog from '@/pages/properties/PropertyDetailsDialog.vue';
import PropertyForm from '@/pages/properties/PropertyForm.vue';
import { destroy, index } from '@/routes/properties';
import type {
    Property,
    PropertyOwnerOption,
    PropertyPaginator,
    PropertyStatus,
    PropertyType,
} from '@/types';

const props = defineProps<{
    properties: PropertyPaginator;
    filters: TableSort & {
        search: string;
        type: PropertyType | null;
        status: PropertyStatus | null;
    };
    selected: Property | null;
    accountType: 'single_owner' | 'agency';
    owners: PropertyOwnerOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Imóveis', href: '/properties' }],
    },
});

const search = ref(props.filters.search);
const type = ref<PropertyType | 'all'>(props.filters.type ?? 'all');
const status = ref<PropertyStatus | 'all'>(props.filters.status ?? 'all');

function visit(overrides: Partial<TableSort> = {}) {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
            status: status.value === 'all' ? undefined : status.value,
            sort: props.filters.sort,
            direction: props.filters.direction,
            ...overrides,
        },
        { preserveState: true, replace: true, only: ['properties', 'filters'] },
    );
}

watchDebounced(search, () => visit(), { debounce: 350 });
watch([type, status], () => visit());

function sortBy(column: string) {
    visit(nextSort(props.filters, column));
}

const isFiltering = computed(
    () => !!props.filters.search || !!props.filters.type || !!props.filters.status,
);

function clearFilters() {
    search.value = '';
    type.value = 'all';
    status.value = 'all';
}

const isFormDialogOpen = ref(false);
const formDialogProperty = ref<Property | null>(null);

function openCreateDialog() {
    formDialogProperty.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(property: Property) {
    propertyToShow.value = null;
    formDialogProperty.value = property;
    isFormDialogOpen.value = true;
}

const propertyToShow = ref<Property | null>(null);

useOpenSelectedRecord(
    () => props.selected,
    (property) => (propertyToShow.value = property),
);

/** Keep the open details in sync with the reloaded page, e.g. after adding photos. */
watch(
    () => [props.properties, props.selected],
    () => {
        const shown = propertyToShow.value;

        if (!shown) {
            return;
        }

        propertyToShow.value =
            props.properties.data.find((property) => property.id === shown.id) ??
            (props.selected?.id === shown.id ? props.selected : shown);
    },
);

const propertyToDelete = ref<Property | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!propertyToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(propertyToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            propertyToDelete.value = null;
        },
    });
}
</script>

<template>

    <Head title="Imóveis" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader title="Imóveis" description="Cadastre e acompanhe todos os imóveis da sua carteira.">
            <Button @click="openCreateDialog">
                <Plus class="size-4" />
                Cadastrar imóvel
            </Button>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                <SearchInput v-model="search" placeholder="Buscar por rua, bairro, cidade ou CEP" />
                <Select v-model="type">
                    <SelectTrigger class="w-full sm:w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos os tipos</SelectItem>
                        <SelectItem v-for="(label, key) in propertyTypeLabels" :key="key" :value="key">
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="status">
                    <SelectTrigger class="w-full sm:w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas as situações</SelectItem>
                        <SelectItem v-for="(label, key) in propertyStatusLabels" :key="key" :value="key">
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <EmptyState v-if="properties.data.length === 0 && isFiltering" :icon="SearchX"
                title="Nenhum imóvel encontrado"
                description="Não encontramos imóveis com esses filtros. Tente buscar por outro termo.">
                <Button variant="outline" size="sm" @click="clearFilters">
                    Limpar filtros
                </Button>
            </EmptyState>

            <EmptyState v-else-if="properties.data.length === 0" :icon="House" title="Nenhum imóvel cadastrado"
                description="Cadastre seu primeiro imóvel para começar a gerenciar contratos e recebimentos." />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <SortableTableHead column="street" label="Imóvel" :current="filters" @sort="sortBy" />
                        <SortableTableHead column="city" label="Localização" :current="filters"
                            class="hidden md:table-cell" @sort="sortBy" />
                        <SortableTableHead column="type" label="Tipo" :current="filters" class="hidden lg:table-cell"
                            @sort="sortBy" />
                        <SortableTableHead column="rent_amount" label="Aluguel" :current="filters" align="right"
                            class="text-right" @sort="sortBy" />
                        <SortableTableHead column="status" label="Situação" :current="filters"
                            class="hidden sm:table-cell" @sort="sortBy" />
                        <TableHead class="h-11 w-0 px-4"><span class="sr-only">Ações</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="property in properties.data" :key="property.id" class="cursor-pointer"
                        @click="propertyToShow = property">
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img v-if="property.photos?.length" :src="property.photos[0].thumbnail_url" alt=""
                                    loading="lazy" class="size-10 shrink-0 rounded-lg border object-cover" />
                                <div v-else
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <component :is="propertyTypeIcons[property.type]" class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ property.street }},
                                        {{ property.number }}
                                    </p>
                                    <p class="truncate text-sm text-muted-foreground">
                                        {{ property.neighborhood }}
                                        <template v-if="property.complement">
                                            · {{ property.complement }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 md:table-cell">
                            <p>{{ property.city }}/{{ property.state }}</p>
                            <p class="text-sm text-muted-foreground">
                                CEP {{ formatZipCode(property.zip_code) }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                            {{ propertyTypeLabels[property.type] }}
                        </TableCell>
                        <TableCell class="px-4 py-3 text-right font-medium tabular-nums">
                            {{ formatCurrency(property.rent_amount) }}
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 sm:table-cell">
                            <Badge variant="outline" :class="propertyStatusBadgeClasses[property.status]
                                ">
                                <span class="size-1.5 rounded-full" :class="propertyStatusDotClasses[
                                    property.status
                                    ]
                                    " />
                                {{ propertyStatusLabels[property.status] }}
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
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuItem @click="propertyToShow = property">
                                        <Eye class="size-4" />
                                        Ver detalhes
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="openEditDialog(property)">
                                        <Pencil class="size-4" />
                                        Editar
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" @click="propertyToDelete = property">
                                        <Trash2 class="size-4" />
                                        Excluir
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <TablePagination v-if="properties.data.length > 0" :paginator="properties" item-label="imóveis"
                item-label-singular="imóvel"
                class="border-t" />
        </div>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <component :is="formDialogProperty ? Pencil : House" class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>{{
                        formDialogProperty
                            ? 'Editar imóvel'
                            : 'Cadastrar imóvel'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogProperty
                                ? 'Atualize as informações do imóvel.'
                                : 'Preencha os dados para cadastrar um novo imóvel.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <PropertyForm :key="formDialogProperty?.id ?? 'create'" :property="formDialogProperty"
                :account-type="accountType" :owners="owners" @success="isFormDialogOpen = false" />
        </DialogScrollContent>
    </Dialog>

    <PropertyDetailsDialog :property="propertyToShow" @close="propertyToShow = null" @edit="openEditDialog" />

    <ConfirmDeleteDialog :open="!!propertyToDelete" title="Excluir imóvel?" :processing="isDeleting"
        @close="propertyToDelete = null" @confirm="confirmDelete">
        O imóvel em
        <span class="font-medium text-foreground">{{ propertyToDelete?.street }},
            {{ propertyToDelete?.number }}</span>
        será removido da sua lista. Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
