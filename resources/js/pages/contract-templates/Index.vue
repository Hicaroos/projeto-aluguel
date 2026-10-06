<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FileSignature,
    MoreHorizontal,
    Pencil,
    Plus,
    Star,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate } from '@/lib/formatters';
import {
    create,
    defaultMethod as makeDefault,
    destroy,
    edit,
} from '@/routes/contract-templates';
import type { ContractTemplate } from '@/types';

defineProps<{
    templates: Pick<
        ContractTemplate,
        'id' | 'name' | 'is_default' | 'updated_at'
    >[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Modelos de contrato', href: '/contract-templates' },
        ],
    },
});

type TemplateListItem = Pick<ContractTemplate, 'id' | 'name' | 'is_default'>;

function setAsDefault(template: TemplateListItem) {
    router.patch(makeDefault(template).url, {}, { preserveScroll: true });
}

const templateToDelete = ref<TemplateListItem | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!templateToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(templateToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            templateToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Modelos de contrato" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Modelos de contrato"
            description="Textos usados para gerar o contrato em PDF, com os dados de cada locação."
        >
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Cadastrar modelo
                </Link>
            </Button>
        </PageHeader>

        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="template in templates"
                :key="template.id"
                class="group relative flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs transition-colors hover:border-primary/40"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <FileSignature class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-1">
                        <Link
                            :href="edit(template)"
                            class="block truncate font-medium after:absolute after:inset-0 focus-visible:outline-none"
                        >
                            {{ template.name }}
                        </Link>
                        <p class="text-xs text-muted-foreground">
                            Atualizado em {{ formatDate(template.updated_at) }}
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="relative z-10"
                            >
                                <MoreHorizontal class="size-4" />
                                <span class="sr-only">Ações</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-52">
                            <DropdownMenuItem as-child>
                                <Link :href="edit(template)">
                                    <Pencil class="size-4" />
                                    Editar
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="!template.is_default"
                                @click="setAsDefault(template)"
                            >
                                <Star class="size-4" />
                                Definir como padrão
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                :disabled="templates.length === 1"
                                @click="templateToDelete = template"
                            >
                                <Trash2 class="size-4" />
                                Excluir
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div>
                    <Badge
                        v-if="template.is_default"
                        variant="outline"
                        class="border-primary/25 bg-primary/10 text-primary"
                    >
                        <Star class="size-3" />
                        Padrão
                    </Badge>
                </div>
            </li>
        </ul>
    </div>

    <ConfirmDeleteDialog
        :open="!!templateToDelete"
        title="Excluir modelo de contrato?"
        :processing="isDeleting"
        @close="templateToDelete = null"
        @confirm="confirmDelete"
    >
        O modelo
        <span class="font-medium text-foreground">{{
            templateToDelete?.name
        }}</span>
        será removido.
        <template v-if="templateToDelete?.is_default">
            Outro modelo passará a ser o padrão.
        </template>
        Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
