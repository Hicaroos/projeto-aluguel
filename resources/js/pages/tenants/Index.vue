<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Eye,
    MoreHorizontal,
    Pencil,
    Plus,
    SearchX,
    Trash2,
    UserPlus,
    Users,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
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
import { formatDate, formatDocument, formatPhone } from '@/lib/formatters';
import TenantDetailsDialog from '@/pages/tenants/TenantDetailsDialog.vue';
import TenantForm from '@/pages/tenants/TenantForm.vue';
import { destroy, index } from '@/routes/tenants';
import type { Tenant, TenantPaginator } from '@/types';

const props = defineProps<{
    tenants: TenantPaginator;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inquilinos', href: '/tenants' }],
    },
});

const search = ref(props.filters.search);

watchDebounced(
    search,
    (value) => {
        router.get(
            index().url,
            { search: value },
            { preserveState: true, replace: true, only: ['tenants'] },
        );
    },
    { debounce: 350 },
);

const isFormDialogOpen = ref(false);
const formDialogTenant = ref<Tenant | null>(null);

function openCreateDialog() {
    formDialogTenant.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(tenant: Tenant) {
    tenantToShow.value = null;
    formDialogTenant.value = tenant;
    isFormDialogOpen.value = true;
}

const tenantToShow = ref<Tenant | null>(null);

const tenantToDelete = ref<Tenant | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!tenantToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(tenantToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            tenantToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Inquilinos" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Inquilinos"
            description="Mantenha os dados de contato dos seus inquilinos organizados."
        >
            <Button @click="openCreateDialog">
                <Plus class="size-4" />
                Cadastrar inquilino
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
                v-if="tenants.data.length === 0 && filters.search"
                :icon="SearchX"
                title="Nenhum inquilino encontrado"
                :description="`Não encontramos resultados para “${filters.search}”. Tente buscar por outro termo.`"
            >
                <Button variant="outline" size="sm" @click="search = ''">
                    Limpar busca
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="tenants.data.length === 0"
                :icon="Users"
                title="Nenhum inquilino cadastrado"
                description="Cadastre seus inquilinos para poder vinculá-los aos contratos de aluguel."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            class="h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >Inquilino</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase md:table-cell"
                            >CPF/CNPJ</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:table-cell"
                            >Telefone</TableHead
                        >
                        <TableHead
                            class="hidden h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase lg:table-cell"
                            >Cadastrado em</TableHead
                        >
                        <TableHead class="h-11 w-0 px-4"
                            ><span class="sr-only">Ações</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="tenant in tenants.data"
                        :key="tenant.id"
                        class="cursor-pointer"
                        @click="tenantToShow = tenant"
                    >
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <Avatar class="size-10">
                                    <AvatarFallback
                                        class="bg-primary/10 text-sm font-medium text-primary"
                                    >
                                        {{ getInitials(tenant.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ tenant.name }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ tenant.email ?? 'Sem e-mail' }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 tabular-nums md:table-cell"
                        >
                            {{ formatDocument(tenant.cpf_cnpj) }}
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 tabular-nums sm:table-cell"
                        >
                            {{ formatPhone(tenant.phone) }}
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-muted-foreground lg:table-cell"
                        >
                            {{ formatDate(tenant.created_at) }}
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
                                        @click="tenantToShow = tenant"
                                    >
                                        <Eye class="size-4" />
                                        Ver detalhes
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="openEditDialog(tenant)"
                                    >
                                        <Pencil class="size-4" />
                                        Editar
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @click="tenantToDelete = tenant"
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
                v-if="tenants.data.length > 0"
                :paginator="tenants"
                item-label="inquilinos"
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
                        :is="formDialogTenant ? Pencil : UserPlus"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>{{
                        formDialogTenant
                            ? 'Editar inquilino'
                            : 'Cadastrar inquilino'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogTenant
                                ? 'Atualize os dados do inquilino.'
                                : 'Preencha os dados para cadastrar um novo inquilino.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <TenantForm
                :key="formDialogTenant?.id ?? 'create'"
                :tenant="formDialogTenant"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <TenantDetailsDialog
        :tenant="tenantToShow"
        @close="tenantToShow = null"
        @edit="openEditDialog"
    />

    <ConfirmDeleteDialog
        :open="!!tenantToDelete"
        title="Excluir inquilino?"
        :processing="isDeleting"
        @close="tenantToDelete = null"
        @confirm="confirmDelete"
    >
        O inquilino
        <span class="font-medium text-foreground">{{
            tenantToDelete?.name
        }}</span>
        será removido da sua lista. Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
