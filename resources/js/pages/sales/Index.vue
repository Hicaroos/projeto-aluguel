<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { BadgeCheck, BadgeDollarSign, HandCoins, Handshake } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useOpenSelectedRecord } from '@/composables/useOpenSelectedRecord';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatMonthYear } from '@/lib/formatters';
import { propertyTypeIcons } from '@/lib/property-labels';
import { paymentPlanLabels } from '@/lib/sales-labels';
import SaleDialog from '@/pages/deals/SaleDialog.vue';
import SaleDetailsDialog from '@/pages/sales/SaleDetailsDialog.vue';
import { destroy, index } from '@/routes/sales';
import type {
    ContractTemplate,
    DealPropertyOption,
    Sale,
    SalesSummary,
    TeamMemberOption,
} from '@/types';

const props = defineProps<{
    month: string;
    sales: Sale[];
    summary: SalesSummary;
    selected: Sale | null;
    templates: Pick<ContractTemplate, 'id' | 'kind' | 'name' | 'is_default'>[];
    users: TeamMemberOption[];
    saleCommissionPercent: string | null;
    /** The properties for sale, to register a sale without the funnel. */
    properties: DealPropertyOption[];
    /** The property to sell, requested through `?sell={id}`. */
    sellPropertyId: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vendas', href: index() }],
    },
});

const page = usePage();

const can = usePermissions();

const saleToShow = ref<Sale | null>(null);

const isDirectSaleOpen = ref(false);
const directSalePropertyId = ref<number | null>(null);

function openDirectSaleDialog(propertyId: number | null = null) {
    directSalePropertyId.value = propertyId;
    isDirectSaleOpen.value = true;
}

/** Open the sale of the property requested from its details, then drop `?sell` from the URL. */
onMounted(() => {
    if (!props.sellPropertyId) {
        return;
    }

    openDirectSaleDialog(props.sellPropertyId);

    const url = new URL(window.location.href);
    url.searchParams.delete('sell');
    window.history.replaceState(window.history.state, '', url);
});

/** Keep the open details in sync with the reloaded page, e.g. after editing the sale. */
watch(
    () => [props.sales, props.selected],
    () => {
        const shown = saleToShow.value;

        if (!shown) {
            return;
        }

        saleToShow.value =
            props.sales.find((sale) => sale.id === shown.id) ??
            (props.selected?.id === shown.id ? props.selected : shown);
    },
);

const saleToEdit = ref<Sale | null>(null);

function openEditDialog(sale: Sale) {
    saleToShow.value = null;
    saleToEdit.value = sale;
}

const saleToDelete = ref<Sale | null>(null);
const isDeleting = ref(false);

function openDeleteDialog(sale: Sale) {
    saleToShow.value = null;
    saleToDelete.value = sale;
}

function confirmDelete() {
    if (!saleToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(saleToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            saleToDelete.value = null;
        },
    });
}

useOpenSelectedRecord(
    () => props.selected,
    (sale) => (saleToShow.value = sale),
);

const isAgency = computed(
    () => page.props.auth.user.account?.type === 'agency',
);

function changeMonth(month: string) {
    router.get(index().url, { month }, { preserveScroll: true });
}

const headClass =
    'h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase';
</script>

<template>
    <Head title="Vendas" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Vendas"
            :description="`Imóveis vendidos em ${formatMonthYear(month).toLowerCase()}.`"
        >
            <div class="flex flex-wrap items-center gap-2">
                <MonthNavigator :month="month" @change="changeMonth" />
                <Button v-if="can.manageSales" @click="openDirectSaleDialog()">
                    <BadgeCheck class="size-4" />
                    Registrar venda
                </Button>
            </div>
        </PageHeader>

        <div
            class="grid gap-4"
            :class="isAgency ? 'sm:grid-cols-3' : 'sm:grid-cols-2'"
        >
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Vendas</p>
                    <BadgeCheck class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ summary.count }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Valor vendido</p>
                    <BadgeDollarSign class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(summary.total) }}
                </p>
            </div>
            <div
                v-if="isAgency"
                class="rounded-xl border bg-card p-5 shadow-xs"
            >
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Comissões</p>
                    <HandCoins class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(summary.commission) }}
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <EmptyState
                v-if="sales.length === 0"
                :icon="Handshake"
                title="Nenhuma venda neste mês"
                description="As vendas fechadas no funil ou registradas aqui aparecem nesta lista."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <TableHead :class="headClass">Imóvel</TableHead>
                        <TableHead :class="[headClass, 'hidden md:table-cell']"
                            >Comprador</TableHead
                        >
                        <TableHead :class="[headClass, 'hidden lg:table-cell']"
                            >Data</TableHead
                        >
                        <TableHead :class="[headClass, 'text-right']"
                            >Valor</TableHead
                        >
                        <TableHead
                            v-if="isAgency"
                            :class="[
                                headClass,
                                'hidden text-right sm:table-cell',
                            ]"
                            >Comissão</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="sale in sales"
                        :key="sale.id"
                        class="cursor-pointer"
                        @click="saleToShow = sale"
                    >
                        <TableCell class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <component
                                        :is="
                                            propertyTypeIcons[
                                                sale.property.type
                                            ]
                                        "
                                        class="size-5"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ sale.property.street }},
                                        {{ sale.property.number }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ sale.property.neighborhood }}
                                        <template v-if="sale.seller">
                                            · de {{ sale.seller.name }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="hidden px-4 py-3 md:table-cell">
                            <p>{{ sale.buyer_name }}</p>
                            <p
                                v-if="sale.payment_plan"
                                class="text-sm text-muted-foreground"
                            >
                                {{ paymentPlanLabels[sale.payment_plan] }}
                            </p>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-muted-foreground lg:table-cell"
                        >
                            {{ formatDate(sale.sold_on) }}
                        </TableCell>
                        <TableCell
                            class="px-4 py-3 text-right font-medium tabular-nums"
                        >
                            {{ formatCurrency(sale.price) }}
                        </TableCell>
                        <TableCell
                            v-if="isAgency"
                            class="hidden px-4 py-3 text-right tabular-nums sm:table-cell"
                        >
                            <p>
                                {{
                                    sale.commission_amount
                                        ? formatCurrency(sale.commission_amount)
                                        : '—'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="sale.commission_percent">
                                    {{ Number(sale.commission_percent) }}%
                                </template>
                                <template v-if="sale.user">
                                    · {{ sale.user.name }}
                                </template>
                            </p>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <SaleDetailsDialog
        :sale="saleToShow"
        :is-agency="isAgency"
        :templates="templates"
        @close="saleToShow = null"
        @delete="openDeleteDialog"
        @edit="openEditDialog"
    />

    <SaleDialog
        :sale="saleToEdit"
        :users="users"
        :sale-commission-percent="saleCommissionPercent"
        @close="saleToEdit = null"
    />

    <SaleDialog
        :direct="isDirectSaleOpen"
        :properties="properties"
        :property-id="directSalePropertyId"
        :users="users"
        :sale-commission-percent="saleCommissionPercent"
        @close="isDirectSaleOpen = false"
    />

    <ConfirmDeleteDialog
        :open="!!saleToDelete"
        title="Excluir venda?"
        :processing="isDeleting"
        @close="saleToDelete = null"
        @confirm="confirmDelete"
    >
        A venda de
        <span class="font-medium text-foreground"
            >{{ saleToDelete?.property.street }},
            {{ saleToDelete?.property.number }}</span
        >
        será removida. O imóvel volta a ficar disponível e a negociação volta
        para o funil, na etapa antes do fechamento.
    </ConfirmDeleteDialog>
</template>
