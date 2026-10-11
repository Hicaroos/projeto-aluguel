<script setup lang="ts">
import { ChevronDown, FileDown, Star } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { generate } from '@/routes/sales';
import type { ContractTemplate, ContractTemplateKind, Sale } from '@/types';

const props = defineProps<{
    sale: Sale;
    templates: Pick<ContractTemplate, 'id' | 'kind' | 'name' | 'is_default'>[];
}>();

const documents: { kind: ContractTemplateKind; label: string }[] = [
    { kind: 'sale_contract', label: 'Contrato de compra e venda' },
    { kind: 'down_payment_receipt', label: 'Recibo de sinal' },
];

const templatesByKind = computed(
    () =>
        Object.fromEntries(
            documents.map(({ kind }) => [
                kind,
                props.templates.filter((template) => template.kind === kind),
            ]),
        ) as Record<ContractTemplateKind, typeof props.templates>,
);

function url(kind: ContractTemplateKind, templateId?: number): string {
    return generate(
        { sale: props.sale.id, kind },
        templateId ? { query: { template: templateId } } : undefined,
    ).url;
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <template v-for="document in documents" :key="document.kind">
            <Button
                v-if="templatesByKind[document.kind].length <= 1"
                variant="outline"
                size="sm"
                as-child
            >
                <a :href="url(document.kind)" target="_blank">
                    <FileDown class="size-4" />
                    {{ document.label }}
                </a>
            </Button>
            <DropdownMenu v-else>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" size="sm">
                        <FileDown class="size-4" />
                        {{ document.label }}
                        <ChevronDown class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-64">
                    <DropdownMenuLabel>Escolha o modelo</DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="template in templatesByKind[document.kind]"
                        :key="template.id"
                        as-child
                    >
                        <a
                            :href="url(document.kind, template.id)"
                            target="_blank"
                        >
                            <span class="min-w-0 flex-1 truncate">{{
                                template.name
                            }}</span>
                            <Star
                                v-if="template.is_default"
                                class="size-3.5 text-primary"
                            />
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>
    </div>
</template>
