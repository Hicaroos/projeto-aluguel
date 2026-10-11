<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FileSignature,
    HandCoins,
    Handshake,
    HardHat,
    MoreHorizontal,
    Pencil,
    Plus,
    Star,
    Trash2,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import type { ContractTemplate, ContractTemplateKind } from '@/types';

type TemplateListItem = Pick<
    ContractTemplate,
    'id' | 'kind' | 'name' | 'is_default' | 'updated_at'
>;

const props = defineProps<{
    templates: TemplateListItem[];
    kinds: { value: ContractTemplateKind; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Modelos de contrato', href: '/contract-templates' },
        ],
    },
});

const kindDetails: Record<
    ContractTemplateKind,
    { icon: LucideIcon; description: string }
> = {
    lease: {
        icon: FileSignature,
        description: 'Gerado nos detalhes de cada contrato de aluguel.',
    },
    sale_contract: {
        icon: Handshake,
        description: 'Gerado nos detalhes de cada venda.',
    },
    down_payment_receipt: {
        icon: HandCoins,
        description:
            'Comprova o sinal pago pelo comprador. Gerado nos detalhes da venda.',
    },
    service_receipt: {
        icon: HardHat,
        description:
            'Assinado pelo prestador que fez um serviço no imóvel. Gerado nos detalhes da despesa.',
    },
};

const sections = computed(() =>
    props.kinds.map((kind) => ({
        ...kind,
        ...kindDetails[kind.value],
        templates: props.templates.filter(
            (template) => template.kind === kind.value,
        ),
    })),
);

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

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <PageHeader
            title="Modelos de contrato"
            description="Textos usados para gerar os documentos em PDF: contratos de aluguel, contratos de compra e venda, recibos de sinal e recibos de serviço."
        />

        <section
            v-for="section in sections"
            :key="section.value"
            class="space-y-4"
        >
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="space-y-0.5">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <component
                            :is="section.icon"
                            class="size-4 text-primary"
                        />
                        {{ section.label }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ section.description }}
                    </p>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="create({ query: { kind: section.value } })">
                        <Plus class="size-4" />
                        Cadastrar modelo
                    </Link>
                </Button>
            </div>

            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <li
                    v-for="template in section.templates"
                    :key="template.id"
                    class="group relative flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs transition-colors hover:border-primary/40"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <component :is="section.icon" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <Link
                                :href="edit(template)"
                                class="block truncate font-medium after:absolute after:inset-0 focus-visible:outline-none"
                            >
                                {{ template.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                Atualizado em
                                {{ formatDate(template.updated_at) }}
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
                                    :disabled="section.templates.length === 1"
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
        </section>
    </div>

    <ConfirmDeleteDialog
        :open="!!templateToDelete"
        title="Excluir modelo?"
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
            Outro modelo do mesmo tipo passará a ser o padrão.
        </template>
        Esta ação não pode ser desfeita.
    </ConfirmDeleteDialog>
</template>
