<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Contact,
    Eye,
    MoreHorizontal,
    Pencil,
    Plus,
    SearchX,
    Trash2,
    UserPlus,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortableTableHead from '@/components/SortableTableHead.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { formatDocument, formatPhone } from '@/lib/formatters';
import { nextSort } from '@/lib/table-sort';
import type { TableSort } from '@/lib/table-sort';
import OwnerDetailsDialog from '@/pages/owners/OwnerDetailsDialog.vue';
import OwnerForm from '@/pages/owners/OwnerForm.vue';
import { destroy, index } from '@/routes/owners';
import type { Owner, OwnerPaginator } from '@/types';

const can = usePermissions();

const props = defineProps<{
    owners: OwnerPaginator;
    filters: TableSort & { search: string };
    selected: Owner | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Proprietários', href: '/owners' }],
    },
});

const search = ref(props.filters.search);

function visit(overrides: Partial<TableSort> = {}) {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            sort: props.filters.sort,
            direction: props.filters.direction,
            ...overrides,
        },
        { preserveState: true, replace: true, only: ['owners', 'filters'] },
    );
}

watchDebounced(search, () => visit(), { debounce: 350 });

function sortBy(column: string) {
    visit(nextSort(props.filters, column));
}

const isFormDialogOpen = ref(false);
const formDialogOwner = ref<Owner | null>(null);

function openCreateDialog() {
    formDialogOwner.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(owner: Owner) {
    ownerToShow.value = null;
    formDialogOwner.value = owner;
    isFormDialogOpen.value = true;
}

const ownerToShow = ref<Owner | null>(null);

useOpenSelectedRecord(
    () => props.selected,
    (owner) => (ownerToShow.value = owner),
);

const ownerToDelete = ref<Owner | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!ownerToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(ownerToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            ownerToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Proprietários" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Proprietários"
            description="Os donos dos imóveis que a imobiliária administra."
        >
            <Button v-if="can.manageRentals" @click="openCreateDialog">
                <Plus class="size-4" />
                Cadastrar proprietário
            </Button>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="border-b p-4">
                <SearchInput
                    v-model="search"
                    placeholder="Buscar por nome, CPF/CNPJ, e-mail ou telefone"
                />
            </div>

            <EmptyState
                v-if="owners.data.length === 0 && filters.search"
                :icon="SearchX"
                title="Nenhum proprietário encontrado"
                :description="`Não encontramos resultados para “${filters.search}”. Tente buscar por outro termo.`"
            >
                <Button variant="outline" size="sm" @click="search = ''">
                    Limpar busca
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="owners.data.length === 0"
                :icon="Contact"
                title="Nenhum proprietário cadastrado"
                description="Cadastre os proprietários para poder vincular os imóveis a eles."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <SortableTableHead
                            column="name"
                            label="Proprietário"
                            :current="filters"
                            @sort="sortBy"
                        />
                        <SortableTableHead
                            column="cpf_cnpj"
                            label="CPF/CNPJ"
                            :current="filters"
                            class="hidden md:table-cell"
                            @sort="sortBy"
                        />
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:table-cell"
                            >Celular</TableHead
                        >
                        <SortableTableHead
                            column="properties_count"
                            label="Imóveis"
                            :current="filters"
                            class="hidden lg:table-cell"
                            @sort="sortBy"
                        />
                        <TableHead class="h-11 w-0 px-4"
                            ><span class="sr-only">Ações</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="owner in owners.data"
                        :key="owner.id"
                        class="cursor-pointer"
                        @click="ownerToShow = owner"
                    >
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <Avatar class="size-10">
                                    <AvatarFallback
                                        class="bg-primary/10 text-sm font-medium text-primary"
                                    >
                                        {{ getInitials(owner.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ owner.name }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ owner.email ?? 'Sem e-mail' }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 tabular-nums md:table-cell"
                        >
                            {{ formatDocument(owner.cpf_cnpj) }}
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 tabular-nums sm:table-cell"
                        >
                            {{ formatPhone(owner.phone) }}
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 tabular-nums lg:table-cell"
                        >
                            {{ owner.properties_count ?? 0 }}
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
                                    <DropdownMenuItem
                                        @click="ownerToShow = owner"
                                    >
                                        <Eye class="size-4" />
                                        Ver detalhes
                                    </DropdownMenuItem>
                                    <template v-if="can.manageRentals">
                                        <DropdownMenuItem
                                            @click="openEditDialog(owner)"
                                        >
                                            <Pencil class="size-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            :disabled="
                                                (owner.properties_count ?? 0) >
                                                0
                                            "
                                            @click="ownerToDelete = owner"
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
                v-if="owners.data.length > 0"
                :paginator="owners"
                item-label="proprietários"
                item-label-singular="proprietário"
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
                        :is="formDialogOwner ? Pencil : UserPlus"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>{{
                        formDialogOwner
                            ? 'Editar proprietário'
                            : 'Cadastrar proprietário'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogOwner
                                ? 'Atualize os dados do proprietário.'
                                : 'Preencha os dados do dono do imóvel.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <OwnerForm
                :key="formDialogOwner?.id ?? 'create'"
                :owner="formDialogOwner"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <OwnerDetailsDialog
        :owner="ownerToShow"
        @close="ownerToShow = null"
        @edit="openEditDialog"
    />

    <ConfirmDeleteDialog
        :open="!!ownerToDelete"
        title="Excluir proprietário?"
        :processing="isDeleting"
        @close="ownerToDelete = null"
        @confirm="confirmDelete"
    >
        O proprietário
        <span class="font-medium text-foreground">{{
            ownerToDelete?.name
        }}</span>
        será removido da sua lista. Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
