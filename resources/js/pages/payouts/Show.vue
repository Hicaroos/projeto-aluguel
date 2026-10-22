<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import {
    Clock,
    FileCheck,
    FileDown,
    HandCoins,
    Landmark,
    MessageCircle,
    Paperclip,
    Scale,
    ShieldCheck,
    Trash2,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { badgeTones, iconTones } from '@/lib/badge-tones';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatDocument, formatMonthYear } from '@/lib/formatters';
import { recordWhatsAppMessage } from '@/lib/whatsapp';
import AdjustmentDialog from '@/pages/payouts/AdjustmentDialog.vue';
import RegisterPayoutDialog from '@/pages/payouts/RegisterPayoutDialog.vue';
import { destroy as destroyEntry } from '@/routes/ledger-entries';
import {
    destroy as destroyPayout,
    whatsapp as payoutWhatsApp,
} from '@/routes/owner-payouts';
import {
    destroy as destroyReceipt,
    show as showReceipt,
    store as storeReceipt,
} from '@/routes/owner-payouts/receipt';
import { index, pdf, show } from '@/routes/payouts';
import type {
    OwnerStatement,
    StatementEntry,
    StatementOwner,
    StatementPropertyOption,
    WhatsAppMessage,
} from '@/types';

const props = defineProps<{
    owner: StatementOwner;
    month: string;
    statement: OwnerStatement;
    properties: StatementPropertyOption[];
    /** The WhatsApp notice of each payout in the statement, by payout. */
    payoutMessages: Record<number, WhatsAppMessage>;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Repasses', href: index().url },
        { title: props.owner.name, href: show(props.owner.id).url },
    ],
});

function changeMonth(month: string) {
    router.get(show(props.owner.id).url, { month }, { preserveScroll: true });
}

const isPayoutDialogOpen = ref(false);
const isAdjustmentDialogOpen = ref(false);

const entryToDelete = ref<StatementEntry | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    const entry = entryToDelete.value;

    if (!entry) {
        return;
    }

    isDeleting.value = true;

    router.delete(
        entry.owner_payout_id
            ? destroyPayout(entry.owner_payout_id).url
            : destroyEntry(entry.id).url,
        {
            preserveScroll: true,
            onFinish: () => {
                isDeleting.value = false;
                entryToDelete.value = null;
            },
        },
    );
}

const receiptInput = ref<HTMLInputElement | null>(null);
const payoutChoosingReceipt = ref<number | null>(null);
const payoutUploadingReceipt = ref<number | null>(null);

function chooseReceipt(payoutId: number) {
    payoutChoosingReceipt.value = payoutId;
    receiptInput.value?.click();
}

function uploadReceipt(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    const payoutId = payoutChoosingReceipt.value;
    input.value = '';

    if (!file || payoutId === null) {
        return;
    }

    payoutUploadingReceipt.value = payoutId;

    router.post(
        storeReceipt(payoutId).url,
        { receipt: file },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(
                    errors.receipt ?? 'Não foi possível enviar o comprovante.',
                ),
            onFinish: () => (payoutUploadingReceipt.value = null),
        },
    );
}

const receiptToRemove = ref<StatementEntry | null>(null);
const isRemovingReceipt = ref(false);

function confirmRemoveReceipt() {
    const payoutId = receiptToRemove.value?.owner_payout_id;

    if (!payoutId) {
        return;
    }

    isRemovingReceipt.value = true;

    router.delete(destroyReceipt(payoutId).url, {
        preserveScroll: true,
        onFinish: () => {
            isRemovingReceipt.value = false;
            receiptToRemove.value = null;
        },
    });
}

const summaryCards = computed(() => [
    {
        label: 'Disponível para repasse',
        value: props.statement.available,
        icon: Wallet,
        class: 'bg-primary/10 text-primary',
    },
    {
        label: 'Aguardando o prazo',
        value: props.statement.pending,
        icon: Clock,
        class: iconTones.warning,
    },
    {
        label: 'Saldo do mês anterior',
        value: props.statement.opening_balance,
        icon: Landmark,
        class: 'bg-muted text-muted-foreground',
    },
    {
        label: 'Saldo ao fim do mês',
        value: props.statement.closing_balance,
        icon: Scale,
        class: 'bg-muted text-muted-foreground',
    },
]);

function shareLabel(share: string | null): string | null {
    return share !== null && Number(share) < 100
        ? `parte de ${Number(share)}%`
        : null;
}

const headClass =
    'h-11 px-4 text-xs font-medium tracking-wide text-muted-foreground uppercase';
</script>

<template>
    <Head :title="`Repasses de ${owner.name}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="owner.name"
            :description="
                [
                    owner.cpf_cnpj ? formatDocument(owner.cpf_cnpj) : null,
                    owner.pix_key
                        ? `Pix: ${owner.pix_key}`
                        : 'Sem chave Pix cadastrada',
                ]
                    .filter(Boolean)
                    .join(' · ')
            "
        >
            <MonthNavigator :month="month" @change="changeMonth" />
        </PageHeader>

        <div class="flex flex-wrap gap-2">
            <Button
                :disabled="statement.available <= 0"
                @click="isPayoutDialogOpen = true"
            >
                <HandCoins class="size-4" />
                Registrar repasse
            </Button>
            <Button variant="outline" @click="isAdjustmentDialogOpen = true">
                <Scale class="size-4" />
                Lançar ajuste
            </Button>
            <Button variant="outline" as-child>
                <a
                    :href="pdf(owner.id, { query: { month } }).url"
                    target="_blank"
                >
                    <FileDown class="size-4" />
                    Extrato em PDF
                </a>
            </Button>
        </div>

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
                    <p
                        class="truncate text-lg font-semibold tabular-nums"
                        :class="{ 'text-destructive': card.value < 0 }"
                    >
                        {{ formatCurrency(card.value) }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="statement.deposits.length > 0"
            class="flex items-start gap-3 rounded-xl border bg-card p-4 text-sm shadow-xs"
        >
            <ShieldCheck class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div class="space-y-1">
                <p class="font-medium">Cauções em guarda da imobiliária</p>
                <p
                    v-for="deposit in statement.deposits"
                    :key="deposit.lease_id"
                    class="text-muted-foreground"
                >
                    {{ deposit.property }} ({{ deposit.tenant }}):
                    <span class="font-medium text-foreground tabular-nums">{{
                        formatCurrency(deposit.amount)
                    }}</span>
                </p>
                <p class="text-xs text-muted-foreground">
                    Informativo: a caução não entra no saldo até o acerto no fim
                    do contrato.
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <Table>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <TableHead :class="[headClass, 'w-28']">Data</TableHead>
                        <TableHead :class="headClass">Lançamento</TableHead>
                        <TableHead :class="[headClass, 'text-right']">
                            Valor
                        </TableHead>
                        <TableHead
                            :class="[
                                headClass,
                                'hidden text-right sm:table-cell',
                            ]"
                        >
                            Saldo
                        </TableHead>
                        <TableHead :class="[headClass, 'w-12']" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow class="hover:bg-transparent">
                        <TableCell class="px-4 py-3" />
                        <TableCell class="px-4 py-3 text-muted-foreground">
                            Saldo do mês anterior
                        </TableCell>
                        <TableCell class="px-4 py-3" />
                        <TableCell
                            class="hidden px-4 py-3 text-right tabular-nums sm:table-cell"
                        >
                            {{ formatCurrency(statement.opening_balance) }}
                        </TableCell>
                        <TableCell class="px-4 py-3" />
                    </TableRow>
                    <TableRow v-if="statement.entries.length === 0">
                        <TableCell
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            Nenhum lançamento em
                            {{ formatMonthYear(`${month}-01`).toLowerCase() }}.
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="entry in statement.entries"
                        :key="entry.id"
                    >
                        <TableCell
                            class="px-4 py-3 align-top text-muted-foreground tabular-nums"
                        >
                            {{ formatDate(entry.occurred_on) }}
                        </TableCell>
                        <TableCell class="px-4 py-3 align-top">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{
                                    entry.description
                                }}</span>
                                <Badge
                                    v-if="entry.is_pending"
                                    variant="outline"
                                    :class="badgeTones.warning"
                                >
                                    A partir de
                                    {{ formatDate(entry.available_on) }}
                                </Badge>
                            </div>
                            <p
                                v-if="
                                    entry.property ||
                                    shareLabel(entry.share_percent)
                                "
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    [
                                        entry.property
                                            ? `${entry.property.street}, ${entry.property.number}`
                                            : null,
                                        shareLabel(entry.share_percent),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </p>
                            <div
                                v-if="entry.owner_payout_id"
                                class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                            >
                                <template v-if="entry.payout_receipt_name">
                                    <a
                                        :href="
                                            showReceipt(entry.owner_payout_id)
                                                .url
                                        "
                                        target="_blank"
                                        class="inline-flex max-w-full min-w-0 items-center gap-1.5 text-primary hover:underline"
                                    >
                                        <FileCheck class="size-3.5 shrink-0" />
                                        <span class="truncate">{{
                                            entry.payout_receipt_name
                                        }}</span>
                                    </a>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                                        @click="receiptToRemove = entry"
                                    >
                                        <X class="size-3.5" />
                                        Remover
                                    </button>
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="payoutUploadingReceipt !== null"
                                    @click="
                                        chooseReceipt(entry.owner_payout_id)
                                    "
                                >
                                    <Spinner
                                        v-if="
                                            payoutUploadingReceipt ===
                                            entry.owner_payout_id
                                        "
                                        class="size-3.5"
                                    />
                                    <Paperclip v-else class="size-3.5" />
                                    Anexar comprovante
                                </button>
                                <a
                                    v-if="
                                        payoutMessages[entry.owner_payout_id]
                                            ?.url
                                    "
                                    :href="
                                        payoutMessages[entry.owner_payout_id]
                                            .url ?? undefined
                                    "
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                    @click="
                                        recordWhatsAppMessage(
                                            payoutWhatsApp(
                                                entry.owner_payout_id,
                                            ).url,
                                        )
                                    "
                                >
                                    <MessageCircle class="size-3.5" />
                                    Avisar no WhatsApp
                                </a>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 text-muted-foreground opacity-50"
                                    title="Cadastre o celular do proprietário para avisar pelo WhatsApp."
                                >
                                    <MessageCircle class="size-3.5" />
                                    Avisar no WhatsApp
                                </span>
                                <span
                                    v-if="
                                        payoutMessages[entry.owner_payout_id]
                                            ?.last_sent
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    Avisado em
                                    {{
                                        formatDate(
                                            payoutMessages[
                                                entry.owner_payout_id
                                            ].last_sent!.sent_at,
                                        )
                                    }}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell
                            class="px-4 py-3 text-right align-top font-medium whitespace-nowrap tabular-nums"
                            :class="
                                Number(entry.amount) < 0
                                    ? 'text-destructive'
                                    : 'text-primary'
                            "
                        >
                            {{ formatCurrency(entry.amount) }}
                        </TableCell>
                        <TableCell
                            class="hidden px-4 py-3 text-right align-top whitespace-nowrap text-muted-foreground tabular-nums sm:table-cell"
                        >
                            {{ formatCurrency(entry.balance) }}
                        </TableCell>
                        <TableCell class="px-2 py-2 align-top">
                            <Button
                                v-if="
                                    entry.is_adjustment || entry.owner_payout_id
                                "
                                variant="ghost"
                                size="icon"
                                :aria-label="`Excluir ${entry.description}`"
                                @click="entryToDelete = entry"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <input
        ref="receiptInput"
        type="file"
        accept="application/pdf,image/jpeg,image/png,image/webp"
        class="hidden"
        @change="uploadReceipt"
    />

    <ConfirmDeleteDialog
        :open="!!receiptToRemove"
        title="Remover comprovante?"
        :processing="isRemovingReceipt"
        @close="receiptToRemove = null"
        @confirm="confirmRemoveReceipt"
    >
        O arquivo
        <span class="font-medium text-foreground">{{
            receiptToRemove?.payout_receipt_name
        }}</span>
        será apagado. O repasse continua registrado.
    </ConfirmDeleteDialog>

    <RegisterPayoutDialog
        v-if="isPayoutDialogOpen"
        :open="isPayoutDialogOpen"
        :owner="owner"
        :available="statement.available"
        @close="isPayoutDialogOpen = false"
    />

    <AdjustmentDialog
        v-if="isAdjustmentDialogOpen"
        :open="isAdjustmentDialogOpen"
        :owner="owner"
        :properties="properties"
        @close="isAdjustmentDialogOpen = false"
    />

    <ConfirmDeleteDialog
        :open="!!entryToDelete"
        :title="
            entryToDelete?.owner_payout_id
                ? 'Excluir repasse?'
                : 'Excluir ajuste?'
        "
        :processing="isDeleting"
        @close="entryToDelete = null"
        @confirm="confirmDelete"
    >
        <template v-if="entryToDelete?.owner_payout_id">
            O repasse de
            <span class="font-medium text-foreground">{{
                formatCurrency(Math.abs(Number(entryToDelete.amount)))
            }}</span>
            será removido e o valor volta para o saldo do proprietário.
        </template>
        <template v-else>
            O ajuste
            <span class="font-medium text-foreground">{{
                entryToDelete?.description
            }}</span>
            será removido do extrato.
        </template>
    </ConfirmDeleteDialog>
</template>
