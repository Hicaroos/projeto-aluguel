<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    formDialogTenant.value = tenant;
    isFormDialogOpen.value = true;
}

const tenantToShow = ref<Tenant | null>(null);

const tenantToDelete = ref<Tenant | null>(null);

function confirmDelete() {
    if (!tenantToDelete.value) {
        return;
    }

    router.delete(destroy(tenantToDelete.value).url, {
        onFinish: () => {
            tenantToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Inquilinos" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Input
                v-model="search"
                placeholder="Buscar por nome, CPF/CNPJ, e-mail ou telefone"
                class="max-w-sm"
            />
            <Button @click="openCreateDialog">
                <Plus class="size-4" />
                Novo inquilino
            </Button>
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nome</TableHead>
                        <TableHead>CPF/CNPJ</TableHead>
                        <TableHead>E-mail</TableHead>
                        <TableHead>Telefone</TableHead>
                        <TableHead class="w-0"
                            ><span class="sr-only">Ações</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="tenants.data.length === 0" :colspan="5">
                        Nenhum inquilino cadastrado ainda.
                    </TableEmpty>
                    <TableRow v-for="tenant in tenants.data" :key="tenant.id">
                        <TableCell class="font-medium">{{
                            tenant.name
                        }}</TableCell>
                        <TableCell>{{ tenant.cpf_cnpj ?? '—' }}</TableCell>
                        <TableCell>{{ tenant.email ?? '—' }}</TableCell>
                        <TableCell>{{ tenant.phone ?? '—' }}</TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon-sm">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        @click="tenantToShow = tenant"
                                    >
                                        Ver detalhes
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="openEditDialog(tenant)"
                                    >
                                        Editar
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @click="tenantToDelete = tenant"
                                    >
                                        Excluir
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="tenants.links.length > 3"
            class="flex items-center justify-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in tenants.links"
                :key="linkIndex"
            >
                <Button
                    v-if="link.url && !link.active"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link :href="link.url" preserve-scroll>
                        <span v-html="link.label" />
                    </Link>
                </Button>
                <Button
                    v-else
                    :variant="link.active ? 'default' : 'outline'"
                    size="sm"
                    disabled
                >
                    <span v-html="link.label" />
                </Button>
            </template>
        </div>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent>
            <DialogHeader>
                <DialogTitle>{{
                    formDialogTenant ? 'Editar inquilino' : 'Novo inquilino'
                }}</DialogTitle>
            </DialogHeader>
            <TenantForm
                :key="formDialogTenant?.id ?? 'create'"
                :tenant="formDialogTenant"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <TenantDetailsDialog :tenant="tenantToShow" @close="tenantToShow = null" />

    <Dialog
        :open="!!tenantToDelete"
        @update:open="(open) => !open && (tenantToDelete = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Excluir inquilino?</DialogTitle>
                <DialogDescription>
                    Esta ação não pode ser desfeita. O inquilino
                    {{ tenantToDelete?.name }} será removido permanentemente.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="confirmDelete"
                    >Excluir</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
