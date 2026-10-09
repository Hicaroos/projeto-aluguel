<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    MapPin,
    MoreHorizontal,
    Pencil,
    Phone,
    Plus,
    Power,
    PowerOff,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { formatDocument, formatPhone } from '@/lib/formatters';
import BranchForm from '@/pages/branches/BranchForm.vue';
import { destroy, index, status } from '@/routes/branches';
import type { Branch } from '@/types';

defineProps<{
    branches: Branch[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Unidades', href: index() }],
    },
});

const isFormDialogOpen = ref(false);
const formDialogBranch = ref<Branch | null>(null);

function openCreateDialog() {
    formDialogBranch.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(branch: Branch) {
    formDialogBranch.value = branch;
    isFormDialogOpen.value = true;
}

function toggleStatus(branch: Branch) {
    router.patch(status(branch).url, {}, { preserveScroll: true });
}

const branchToDelete = ref<Branch | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!branchToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(branchToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            branchToDelete.value = null;
        },
    });
}

function cityLine(branch: Branch): string | null {
    if (!branch.city) {
        return null;
    }

    return branch.state ? `${branch.city}/${branch.state}` : branch.city;
}
</script>

<template>
    <Head title="Unidades" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Unidades"
            description="Os escritórios da imobiliária. Cada imóvel pertence a uma unidade, e o seletor no topo filtra o sistema por ela."
        >
            <Button @click="openCreateDialog">
                <Plus class="size-4" />
                Cadastrar unidade
            </Button>
        </PageHeader>

        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="branch in branches"
                :key="branch.id"
                class="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs"
                :class="{ 'opacity-70': !branch.is_active }"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Building2 class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate font-medium">
                                {{ branch.name }}
                            </p>
                            <Badge v-if="!branch.is_active" variant="outline">
                                Inativa
                            </Badge>
                        </div>
                        <p
                            v-if="cityLine(branch)"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <MapPin class="size-3" />
                            {{ cityLine(branch) }}
                        </p>
                        <p
                            v-if="branch.phone"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Phone class="size-3" />
                            {{ formatPhone(branch.phone) }}
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon-sm">
                                <MoreHorizontal class="size-4" />
                                <span class="sr-only">Ações</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                            <DropdownMenuItem @click="openEditDialog(branch)">
                                <Pencil class="size-4" />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="toggleStatus(branch)">
                                <component
                                    :is="branch.is_active ? PowerOff : Power"
                                    class="size-4"
                                />
                                {{
                                    branch.is_active ? 'Desativar' : 'Reativar'
                                }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                :disabled="(branch.properties_count ?? 0) > 0"
                                @click="branchToDelete = branch"
                            >
                                <Trash2 class="size-4" />
                                Excluir
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <dl class="grid grid-cols-2 gap-3 border-t pt-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Imóveis</dt>
                        <dd class="font-medium tabular-nums">
                            {{ branch.properties_count ?? 0 }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Contratos ativos
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ branch.active_leases_count ?? 0 }}
                        </dd>
                    </div>
                    <div
                        v-if="branch.document || branch.creci"
                        class="col-span-2"
                    >
                        <dt class="text-xs text-muted-foreground">
                            Registro próprio
                        </dt>
                        <dd class="text-xs">
                            <template v-if="branch.document">
                                CNPJ {{ formatDocument(branch.document) }}
                            </template>
                            <template v-if="branch.document && branch.creci">
                                ·
                            </template>
                            <template v-if="branch.creci">
                                CRECI {{ branch.creci }}
                            </template>
                        </dd>
                    </div>
                </dl>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <component
                        :is="formDialogBranch ? Pencil : Building2"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>
                        {{
                            formDialogBranch
                                ? 'Editar unidade'
                                : 'Cadastrar unidade'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogBranch
                                ? 'Atualize as informações da unidade.'
                                : 'Um novo escritório da imobiliária, por exemplo em outra cidade.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <BranchForm
                :key="formDialogBranch?.id ?? 'create'"
                :branch="formDialogBranch"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <ConfirmDeleteDialog
        :open="!!branchToDelete"
        title="Excluir unidade?"
        :processing="isDeleting"
        @close="branchToDelete = null"
        @confirm="confirmDelete"
    >
        A unidade
        <span class="font-medium text-foreground">{{
            branchToDelete?.name
        }}</span>
        será removida. Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
