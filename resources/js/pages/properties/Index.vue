<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
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
import { formatCurrency } from '@/lib/currency';
import {
    propertyStatusLabels,
    propertyTypeLabels,
} from '@/lib/property-labels';
import PropertyDetailsDialog from '@/pages/properties/PropertyDetailsDialog.vue';
import PropertyForm from '@/pages/properties/PropertyForm.vue';
import { destroy, index } from '@/routes/properties';
import type { Property, PropertyOwnerOption, PropertyPaginator } from '@/types';

const props = defineProps<{
    properties: PropertyPaginator;
    filters: { search: string };
    accountType: 'single_owner' | 'agency';
    owners: PropertyOwnerOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Imóveis', href: '/properties' }],
    },
});

const search = ref(props.filters.search);

watchDebounced(
    search,
    (value) => {
        router.get(
            index().url,
            { search: value },
            { preserveState: true, replace: true, only: ['properties'] },
        );
    },
    { debounce: 350 },
);

const isFormDialogOpen = ref(false);
const formDialogProperty = ref<Property | null>(null);

function openCreateDialog() {
    formDialogProperty.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(property: Property) {
    formDialogProperty.value = property;
    isFormDialogOpen.value = true;
}

const propertyToShow = ref<Property | null>(null);

const propertyToDelete = ref<Property | null>(null);

function confirmDelete() {
    if (!propertyToDelete.value) {
        return;
    }

    router.delete(destroy(propertyToDelete.value).url, {
        onFinish: () => {
            propertyToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Imóveis" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Input
                v-model="search"
                placeholder="Buscar por rua, bairro, cidade ou CEP"
                class="max-w-sm"
            />
            <Button @click="openCreateDialog">
                <Plus class="size-4" />
                Novo imóvel
            </Button>
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Endereço</TableHead>
                        <TableHead>Cidade/UF</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead>Valor do aluguel</TableHead>
                        <TableHead>Situação</TableHead>
                        <TableHead class="w-0"
                            ><span class="sr-only">Ações</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty
                        v-if="properties.data.length === 0"
                        :colspan="6"
                    >
                        Nenhum imóvel cadastrado ainda.
                    </TableEmpty>
                    <TableRow
                        v-for="property in properties.data"
                        :key="property.id"
                    >
                        <TableCell
                            >{{ property.street }},
                            {{ property.number }}</TableCell
                        >
                        <TableCell
                            >{{ property.city }}/{{ property.state }}</TableCell
                        >
                        <TableCell>{{
                            propertyTypeLabels[property.type]
                        }}</TableCell>
                        <TableCell>{{
                            formatCurrency(property.rent_amount)
                        }}</TableCell>
                        <TableCell>
                            <Badge variant="outline">{{
                                propertyStatusLabels[property.status]
                            }}</Badge>
                        </TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon-sm">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        @click="propertyToShow = property"
                                    >
                                        Ver detalhes
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="openEditDialog(property)"
                                    >
                                        Editar
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @click="propertyToDelete = property"
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
            v-if="properties.links.length > 3"
            class="flex items-center justify-center gap-1"
        >
            <template
                v-for="(link, linkIndex) in properties.links"
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
                    formDialogProperty ? 'Editar imóvel' : 'Novo imóvel'
                }}</DialogTitle>
            </DialogHeader>
            <PropertyForm
                :key="formDialogProperty?.id ?? 'create'"
                :property="formDialogProperty"
                :account-type="accountType"
                :owners="owners"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <PropertyDetailsDialog
        :property="propertyToShow"
        @close="propertyToShow = null"
    />

    <Dialog
        :open="!!propertyToDelete"
        @update:open="(open) => !open && (propertyToDelete = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Excluir imóvel?</DialogTitle>
                <DialogDescription>
                    Esta ação não pode ser desfeita. O imóvel em
                    {{ propertyToDelete?.street }},
                    {{ propertyToDelete?.number }} será removido
                    permanentemente.
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
