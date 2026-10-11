<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarDays,
    FileDown,
    HandCoins,
    House,
    IdCard,
    KeyRound,
    Mail,
    MessageCircle,
    Pencil,
    Phone,
    StickyNote,
    Trash2,
    UserRound,
    Wallet,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatDocument, formatPhone } from '@/lib/formatters';
import { propertyTypeIcons, propertyTypeLabels } from '@/lib/property-labels';
import { paymentPlanLabels } from '@/lib/sales-labels';
import SaleDocumentButtons from '@/pages/sales/SaleDocumentButtons.vue';
import { index as dealsIndex } from '@/routes/deals';
import { index as propertiesIndex } from '@/routes/properties';
import type { ContractTemplate, Sale } from '@/types';

defineProps<{
    sale: Sale | null;
    /** Agencies earn commissions and name who sold. */
    isAgency: boolean;
    templates: Pick<ContractTemplate, 'id' | 'kind' | 'name' | 'is_default'>[];
}>();

const can = usePermissions();

const emit = defineEmits<{
    close: [];
    delete: [sale: Sale];
    edit: [sale: Sale];
}>();
</script>

<template>
    <Dialog :open="!!sale" @update:open="(open) => !open && emit('close')">
        <DialogContent
            v-if="sale"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader class="flex-row items-start gap-4 text-left">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <component
                        :is="propertyTypeIcons[sale.property.type]"
                        class="size-6"
                    />
                </div>
                <div class="min-w-0 space-y-1 pr-6">
                    <DialogTitle class="leading-snug">
                        {{ sale.property.street }}, {{ sale.property.number }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ propertyTypeLabels[sale.property.type] }} ·
                        {{ sale.property.neighborhood }} ·
                        {{ sale.property.city }}/{{ sale.property.state }}
                    </DialogDescription>
                    <p
                        class="flex items-center gap-1 text-xs font-medium text-primary"
                    >
                        <BadgeCheck class="size-3.5" />
                        Vendido em {{ formatDate(sale.sold_on) }}
                    </p>
                </div>
            </DialogHeader>

            <div
                class="flex flex-wrap items-center gap-x-8 gap-y-3 rounded-lg border bg-muted/40 p-4"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-10 items-center justify-center rounded-full bg-background text-primary shadow-xs"
                    >
                        <Wallet class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Valor da venda
                        </p>
                        <p class="text-xl font-semibold tabular-nums">
                            {{ formatCurrency(sale.price) }}
                        </p>
                        <p
                            v-if="sale.payment_plan"
                            class="text-xs text-muted-foreground"
                        >
                            {{ paymentPlanLabels[sale.payment_plan] }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="isAgency && sale.commission_amount"
                    class="flex items-center gap-4"
                >
                    <div
                        class="flex size-10 items-center justify-center rounded-full bg-background text-primary shadow-xs"
                    >
                        <HandCoins class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Comissão</p>
                        <p class="text-xl font-semibold tabular-nums">
                            {{ formatCurrency(sale.commission_amount) }}
                        </p>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ Number(sale.commission_percent) }}% do valor
                            <template v-if="sale.user">
                                · {{ sale.user.name }}
                            </template>
                        </p>
                    </div>
                </div>
            </div>

            <section class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <UserRound class="size-3.5" />
                    Comprador
                </h3>
                <ul class="divide-y rounded-lg border">
                    <li class="flex items-center gap-3 p-3">
                        <UserRound
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">Nome</p>
                            <p class="text-sm font-medium">
                                {{ sale.buyer_name }}
                            </p>
                        </div>
                    </li>
                    <li
                        v-if="sale.buyer_document"
                        class="flex items-center gap-3 p-3"
                    >
                        <IdCard class="size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                CPF/CNPJ
                            </p>
                            <p class="text-sm font-medium tabular-nums">
                                {{ formatDocument(sale.buyer_document) }}
                            </p>
                        </div>
                    </li>
                    <li
                        v-if="sale.buyer_phone"
                        class="flex items-center gap-3 p-3"
                    >
                        <Phone class="size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-muted-foreground">Celular</p>
                            <p class="text-sm font-medium tabular-nums">
                                {{ formatPhone(sale.buyer_phone) }}
                            </p>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="`https://wa.me/55${sale.buyer_phone}`"
                                target="_blank"
                            >
                                <MessageCircle class="size-4" />
                                WhatsApp
                            </a>
                        </Button>
                    </li>
                    <li
                        v-if="sale.buyer_email"
                        class="flex items-center gap-3 p-3"
                    >
                        <Mail class="size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">E-mail</p>
                            <a
                                :href="`mailto:${sale.buyer_email}`"
                                class="block truncate text-sm font-medium text-primary hover:underline"
                                >{{ sale.buyer_email }}</a
                            >
                        </div>
                    </li>
                </ul>
            </section>

            <section class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <House class="size-3.5" />
                    Vendedor
                </h3>
                <ul class="divide-y rounded-lg border">
                    <li class="flex items-center gap-3 p-3">
                        <UserRound
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Proprietário
                            </p>
                            <p class="text-sm font-medium">
                                {{ sale.seller?.name ?? '—' }}
                            </p>
                            <p
                                v-if="sale.seller?.cpf_cnpj"
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ formatDocument(sale.seller.cpf_cnpj) }}
                            </p>
                        </div>
                    </li>
                    <li class="flex items-center gap-3 p-3">
                        <CalendarDays
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Data da venda
                            </p>
                            <p class="text-sm font-medium">
                                {{ formatDate(sale.sold_on) }}
                            </p>
                        </div>
                    </li>
                    <li
                        v-if="sale.rental_outcome"
                        class="flex items-center gap-3 p-3"
                    >
                        <KeyRound
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">Aluguel</p>
                            <p class="text-sm font-medium">
                                {{
                                    sale.rental_outcome === 'keep'
                                        ? 'O contrato de aluguel continua'
                                        : 'O contrato de aluguel foi encerrado na venda'
                                }}
                            </p>
                        </div>
                    </li>
                    <li v-if="sale.notes" class="flex items-start gap-3 p-3">
                        <StickyNote
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Observações
                            </p>
                            <p class="text-sm whitespace-pre-line">
                                {{ sale.notes }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <FileDown class="size-3.5" />
                    Gerar documentos
                </h3>
                <SaleDocumentButtons :sale="sale" :templates="templates" />
                <p class="text-xs text-muted-foreground">
                    Os dados que faltarem saem como uma linha para completar à
                    mão.
                    <button
                        v-if="can.manageSales"
                        type="button"
                        class="font-medium text-primary hover:underline"
                        @click="emit('edit', sale)"
                    >
                        Completar os dados da venda
                    </button>
                </p>
            </section>

            <DialogFooter class="flex-wrap gap-2 border-t pt-6">
                <Button
                    v-if="can.manageSales"
                    variant="ghost"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive sm:mr-auto"
                    @click="emit('delete', sale)"
                >
                    <Trash2 class="size-4" />
                    Excluir venda
                </Button>
                <Button
                    v-if="can.manageSales"
                    variant="outline"
                    @click="emit('edit', sale)"
                >
                    <Pencil class="size-4" />
                    Editar venda
                </Button>
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            propertiesIndex({
                                query: { show: sale.property_id },
                            })
                        "
                    >
                        <House class="size-4" />
                        Ver imóvel
                    </Link>
                </Button>
                <Button v-if="sale.deal_id" variant="outline" as-child>
                    <Link :href="dealsIndex({ query: { show: sale.deal_id } })">
                        Ver negociação
                    </Link>
                </Button>
                <DialogClose as-child>
                    <Button>Fechar</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
