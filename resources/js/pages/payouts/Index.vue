<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Clock,
    HandCoins,
    Percent,
    SearchX,
    ShieldCheck,
    Wallet,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { getInitials } from '@/composables/useInitials';
import { usePermissions } from '@/composables/usePermissions';
import { iconTones } from '@/lib/badge-tones';
import { formatCurrency } from '@/lib/currency';
import { formatDate } from '@/lib/formatters';
import { edit as editPayoutSettings } from '@/routes/payout-settings';
import { index, show } from '@/routes/payouts';
import type { OwnerBalance, PayoutTotals } from '@/types';

const props = defineProps<{
    owners: OwnerBalance[];
    totals: PayoutTotals;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Repasses', href: index() }],
    },
});

const can = usePermissions();

const search = ref(props.filters.search);

watchDebounced(
    search,
    () =>
        router.get(
            index().url,
            { search: search.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        ),
    { debounce: 300 },
);

const summaryCards = computed(() => [
    {
        label: 'A repassar hoje',
        value: props.totals.available,
        icon: Wallet,
        class: 'bg-primary/10 text-primary',
    },
    {
        label: 'Aguardando o prazo',
        value: props.totals.pending,
        icon: Clock,
        class: iconTones.warning,
    },
    {
        label: 'Aluguel garantido em aberto',
        value: props.totals.advanced,
        icon: ShieldCheck,
        class: iconTones.info,
    },
    {
        label: 'Taxas da imobiliária no mês',
        value: props.totals.fees,
        icon: Percent,
        class: iconTones.success,
    },
]);

const headClass =
    'h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase';
</script>

<template>
    <Head title="Repasses" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Repasses"
            description="O que cada proprietário tem a receber dos aluguéis, já descontadas as taxas e despesas."
        >
            <Button v-if="can.manageAgency" variant="outline" as-child>
                <Link :href="editPayoutSettings()">Taxas e repasses</Link>
            </Button>
        </PageHeader>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                    :class="card.class"
                >
                    <component :is="card.icon" class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p class="truncate text-lg font-semibold tabular-nums">
                        {{ formatCurrency(card.value) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="border-b p-4">
                <SearchInput
                    v-model="search"
                    placeholder="Buscar proprietário por nome ou documento"
                />
            </div>

            <EmptyState
                v-if="owners.length === 0 && filters.search"
                :icon="SearchX"
                title="Nenhum proprietário encontrado"
                description="Não encontramos proprietários com essa busca."
            >
                <Button variant="outline" size="sm" @click="search = ''">
                    Limpar busca
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="owners.length === 0"
                :icon="HandCoins"
                title="Nenhum repasse por enquanto"
                description="Quando um aluguel for recebido, o valor do proprietário aparece aqui, já com as taxas descontadas."
            />

            <Table v-else>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <TableHead :class="headClass">Proprietário</TableHead>
                        <TableHead :class="[headClass, 'hidden md:table-cell']">
                            Último repasse
                        </TableHead>
                        <TableHead
                            :class="[
                                headClass,
                                'hidden text-right sm:table-cell',
                            ]"
                        >
                            Aguardando
                        </TableHead>
                        <TableHead :class="[headClass, 'text-right']">
                            A repassar
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="owner in owners"
                        :key="owner.id"
                        class="cursor-pointer"
                        @click="router.visit(show(owner.id).url)"
                    >
                        <TableCell class="px-4 py-3">
                            <Link
                                :href="show(owner.id)"
                                class="flex items-center gap-3"
                                @click.stop
                            >
                                <Avatar class="size-9">
                                    <AvatarFallback
                                        class="bg-primary/10 text-xs font-medium text-primary"
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
                                        {{
                                            owner.pix_key
                                                ? `Pix: ${owner.pix_key}`
                                                : 'Sem chave Pix cadastrada'
                                        }}
                                    </p>
                                </div>
                            </Link>
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{
                                owner.last_payout_on
                                    ? formatDate(owner.last_payout_on)
                                    : '—'
                            }}
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-right text-muted-foreground tabular-nums sm:table-cell"
                        >
                            {{
                                owner.pending
                                    ? formatCurrency(owner.pending)
                                    : '—'
                            }}
                        </TableCell>
                        <TableCell
                            class="px-4 py-3 text-right font-medium tabular-nums"
                            :class="{ 'text-destructive': owner.available < 0 }"
                        >
                            {{ formatCurrency(owner.available) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
