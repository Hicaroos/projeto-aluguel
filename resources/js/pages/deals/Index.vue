<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    CalendarClock,
    Columns3,
    FileText,
    Handshake,
    House,
    MoreHorizontal,
    Plus,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { useOpenSelectedRecord } from '@/composables/useOpenSelectedRecord';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import { dealTypeLabels, formatVisitTime } from '@/lib/sales-labels';
import DealDetailsDialog from '@/pages/deals/DealDetailsDialog.vue';
import DealForm from '@/pages/deals/DealForm.vue';
import DealStagesDialog from '@/pages/deals/DealStagesDialog.vue';
import LostDealDialog from '@/pages/deals/LostDealDialog.vue';
import { destroy, index, move } from '@/routes/deals';
import type {
    Deal,
    DealLeadOption,
    DealPropertyOption,
    FunnelStage,
    TeamMemberOption,
} from '@/types';

const props = defineProps<{
    stages: FunnelStage[];
    selected: Deal | null;
    leads: DealLeadOption[];
    properties: DealPropertyOption[];
    users: TeamMemberOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Funil', href: index() }],
    },
});

const can = usePermissions();

/** The board as shown, moved around right away while the server catches up. */
const board = ref<FunnelStage[]>([]);

function resetBoard() {
    board.value = props.stages.map((stage) => ({
        ...stage,
        deals: [...stage.deals],
    }));
}

watch(() => props.stages, resetBoard, { immediate: true });

const lostStage = computed(() =>
    props.stages.find((stage) => stage.outcome === 'lost'),
);
const wonStage = computed(() =>
    props.stages.find((stage) => stage.outcome === 'won'),
);

const allDeals = computed(() => board.value.flatMap((stage) => stage.deals));

const dealToShow = ref<Deal | null>(null);

useOpenSelectedRecord(
    () => props.selected,
    (deal) => (dealToShow.value = deal),
);

/** Keep the open details in sync with the reloaded page, e.g. after scheduling a visit. */
watch(
    () => [props.stages, props.selected],
    () => {
        const shown = dealToShow.value;

        if (!shown) {
            return;
        }

        dealToShow.value =
            allDeals.value.find((deal) => deal.id === shown.id) ??
            (props.selected?.id === shown.id ? props.selected : shown);
    },
);

const isFormDialogOpen = ref(false);
const formDialogDeal = ref<Deal | null>(null);

function openCreateDialog() {
    formDialogDeal.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(deal: Deal) {
    dealToShow.value = null;
    formDialogDeal.value = deal;
    isFormDialogOpen.value = true;
}

const isStagesDialogOpen = ref(false);
const dealToLose = ref<Deal | null>(null);

function openLostDialog(deal: Deal) {
    dealToShow.value = null;
    dealToLose.value = deal;
}

function win(deal: Deal) {
    dealToShow.value = null;

    if (wonStage.value) {
        router.patch(
            move(deal).url,
            { deal_stage_id: wonStage.value.id },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        deal.type === 'rent'
                            ? 'Locação fechada. Agora cadastre o contrato de aluguel.'
                            : 'Venda fechada.',
                    ),
            },
        );
    }
}

/**
 * Move a deal to a place of a stage. Closing stages go through their own flow: a loss asks for
 * the reason first.
 */
function moveTo(deal: Deal, stage: FunnelStage, position?: number) {
    if (stage.outcome === 'lost') {
        if (deal.deal_stage_id !== stage.id) {
            openLostDialog(deal);
        }

        return;
    }

    if (stage.outcome === 'won') {
        if (deal.deal_stage_id !== stage.id) {
            win(deal);
        }

        return;
    }

    const source = board.value.find(
        (column) => column.id === deal.deal_stage_id,
    );
    const target = board.value.find((column) => column.id === stage.id);

    if (!source || !target) {
        return;
    }

    const from = source.deals.findIndex((item) => item.id === deal.id);
    let to = position ?? target.deals.length;

    if (source === target && from < to) {
        to -= 1;
    }

    if (source === target && from === to) {
        return;
    }

    source.deals.splice(from, 1);
    target.deals.splice(to, 0, { ...deal, deal_stage_id: target.id });

    router.patch(
        move(deal).url,
        { deal_stage_id: target.id, position: to },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                resetBoard();
                toast.error(
                    Object.values(errors)[0] ?? 'Não foi possível mover.',
                );
            },
        },
    );
}

const draggedDeal = ref<Deal | null>(null);
const dropTarget = ref<{ stageId: number; index: number } | null>(null);

function startDrag(event: DragEvent, deal: Deal) {
    draggedDeal.value = deal;
    event.dataTransfer?.setData('text/plain', String(deal.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function endDrag() {
    draggedDeal.value = null;
    dropTarget.value = null;
}

function dragOverCard(stage: FunnelStage, index: number) {
    dropTarget.value = { stageId: stage.id, index };
}

function dragOverColumn(stage: FunnelStage) {
    if (dropTarget.value?.stageId !== stage.id) {
        dropTarget.value = { stageId: stage.id, index: stage.deals.length };
    }
}

function drop(stage: FunnelStage) {
    const deal = draggedDeal.value;
    const position =
        dropTarget.value?.stageId === stage.id
            ? dropTarget.value.index
            : stage.deals.length;

    endDrag();

    if (deal) {
        moveTo(deal, stage, position);
    }
}

function stageTotal(stage: FunnelStage): number {
    return stage.deals.reduce((sum, deal) => sum + Number(deal.value ?? 0), 0);
}

/** The next visit still to happen, if any. */
function nextVisit(deal: Deal) {
    return [...deal.visits]
        .filter((visit) => visit.status === 'scheduled')
        .sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at))[0];
}

function columnClasses(stage: FunnelStage): string {
    if (stage.outcome === 'won') {
        return 'bg-primary/5 border-primary/20';
    }

    if (stage.outcome === 'lost') {
        return 'bg-rose-50/60 border-rose-200 dark:bg-rose-950/20 dark:border-rose-900';
    }

    return 'bg-muted/40';
}

const dealToDelete = ref<Deal | null>(null);
const isDeleting = ref(false);

function openDeleteDialog(deal: Deal) {
    dealToShow.value = null;
    dealToDelete.value = deal;
}

function confirmDelete() {
    if (!dealToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy(dealToDelete.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            dealToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Funil" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Funil"
            description="Acompanhe cada negociação, do primeiro contato ao fechamento. Arraste os cards entre as etapas."
        >
            <Button
                v-if="can.manageSales"
                variant="outline"
                @click="isStagesDialogOpen = true"
            >
                <Columns3 class="size-4" />
                Etapas
            </Button>
            <Button v-if="can.manageSales" @click="openCreateDialog">
                <Plus class="size-4" />
                Nova negociação
            </Button>
        </PageHeader>

        <div class="-mx-4 overflow-x-auto px-4 pb-2 md:-mx-6 md:px-6">
            <div class="flex min-h-[60vh] gap-4">
                <section
                    v-for="stage in board"
                    :key="stage.id"
                    class="flex w-72 shrink-0 flex-col rounded-xl border"
                    :class="[
                        columnClasses(stage),
                        {
                            'ring-2 ring-primary/40':
                                draggedDeal && dropTarget?.stageId === stage.id,
                        },
                    ]"
                    @dragover.prevent="dragOverColumn(stage)"
                    @drop.prevent="drop(stage)"
                >
                    <header class="space-y-0.5 border-b px-3 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="truncate text-sm font-semibold">
                                {{ stage.name }}
                            </h2>
                            <Badge variant="secondary" class="tabular-nums">
                                {{ stage.deals.length }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ formatCurrency(stageTotal(stage)) }}
                            <template v-if="stage.outcome">
                                · últimos 30 dias
                            </template>
                        </p>
                    </header>

                    <ul class="flex flex-1 flex-col gap-2 p-2">
                        <li
                            v-for="(deal, dealIndex) in stage.deals"
                            :key="deal.id"
                            :draggable="can.manageSales"
                            class="group cursor-pointer rounded-lg border bg-card p-3 shadow-xs transition-shadow hover:shadow-sm"
                            :class="{
                                'opacity-40': draggedDeal?.id === deal.id,
                                'border-t-2 border-t-primary':
                                    draggedDeal &&
                                    dropTarget?.stageId === stage.id &&
                                    dropTarget.index === dealIndex,
                            }"
                            @dragstart="startDrag($event, deal)"
                            @dragend="endDrag"
                            @dragover.prevent.stop="
                                dragOverCard(stage, dealIndex)
                            "
                            @drop.prevent.stop="drop(stage)"
                            @click="dealToShow = deal"
                        >
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ deal.lead.name }}
                                    </p>
                                    <p
                                        class="flex items-center gap-1 truncate text-xs text-muted-foreground"
                                    >
                                        <House class="size-3 shrink-0" />
                                        <span class="truncate">
                                            {{
                                                deal.property
                                                    ? `${deal.property.street}, ${deal.property.number}`
                                                    : 'Imóvel a definir'
                                            }}
                                        </span>
                                    </p>
                                </div>
                                <DropdownMenu v-if="can.manageSales">
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="-mt-1 -mr-1 size-7"
                                            @click.stop
                                        >
                                            <MoreHorizontal class="size-4" />
                                            <span class="sr-only">Ações</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-52"
                                        @click.stop
                                    >
                                        <DropdownMenuLabel
                                            class="flex items-center gap-2 text-xs font-normal text-muted-foreground"
                                        >
                                            <ArrowRightLeft class="size-3.5" />
                                            Mover para
                                        </DropdownMenuLabel>
                                        <DropdownMenuItem
                                            v-for="target in board"
                                            :key="target.id"
                                            :disabled="
                                                target.id === deal.deal_stage_id
                                            "
                                            @click="moveTo(deal, target)"
                                        >
                                            {{ target.name }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>

                            <div
                                class="mt-2 flex items-center justify-between gap-2"
                            >
                                <p class="text-sm font-semibold tabular-nums">
                                    {{
                                        deal.value
                                            ? formatCurrency(deal.value)
                                            : '—'
                                    }}
                                </p>
                                <div class="flex items-center gap-1.5">
                                    <Badge
                                        v-if="deal.type === 'rent'"
                                        variant="outline"
                                        class="text-[11px]"
                                    >
                                        {{ dealTypeLabels[deal.type] }}
                                    </Badge>
                                    <span
                                        v-if="deal.proposals.length > 0"
                                        class="flex items-center gap-0.5 text-xs text-muted-foreground"
                                        :title="`${deal.proposals.length} proposta(s)`"
                                    >
                                        <FileText class="size-3" />
                                        {{ deal.proposals.length }}
                                    </span>
                                    <Avatar
                                        v-if="deal.user"
                                        class="size-6"
                                        :title="deal.user.name"
                                    >
                                        <AvatarFallback
                                            class="bg-primary/10 text-[10px] text-primary"
                                        >
                                            {{ getInitials(deal.user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </div>
                            </div>

                            <p
                                v-if="nextVisit(deal)"
                                class="mt-2 flex items-center gap-1 rounded-md bg-sky-50 px-2 py-1 text-xs text-sky-700 dark:bg-sky-950/60 dark:text-sky-300"
                            >
                                <CalendarClock class="size-3" />
                                Visita
                                {{
                                    formatVisitTime(
                                        nextVisit(deal)!.scheduled_at,
                                    )
                                }}
                            </p>
                            <p
                                v-if="deal.lost_reason"
                                class="mt-2 truncate text-xs text-rose-700 dark:text-rose-300"
                            >
                                {{ deal.lost_reason }}
                            </p>
                        </li>

                        <li
                            v-if="stage.deals.length === 0"
                            class="flex flex-1 flex-col items-center justify-center gap-1 rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground"
                        >
                            <Handshake class="size-4" />
                            Nenhuma negociação
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent class="sm:max-w-xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Handshake class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>
                        {{
                            formDialogDeal
                                ? 'Editar negociação'
                                : 'Nova negociação'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogDeal
                                ? 'Atualize o imóvel, o valor ou o responsável.'
                                : 'A negociação entra na primeira etapa do funil.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <p
                v-if="!formDialogDeal && leads.length === 0"
                class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
            >
                Cadastre o interessado primeiro, na tela de Interessados.
            </p>
            <DealForm
                v-else
                :key="formDialogDeal?.id ?? 'create'"
                :deal="formDialogDeal"
                :leads="leads"
                :properties="properties"
                :users="users"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <DealDetailsDialog
        :deal="dealToShow"
        :stages="stages"
        :users="users"
        @close="dealToShow = null"
        @edit="openEditDialog"
        @lose="openLostDialog"
        @win="win"
        @delete="openDeleteDialog"
    />

    <DealStagesDialog
        :open="isStagesDialogOpen"
        :stages="stages"
        @close="isStagesDialogOpen = false"
    />

    <LostDealDialog
        :deal="dealToLose"
        :lost-stage="lostStage"
        @close="dealToLose = null"
    />

    <ConfirmDeleteDialog
        :open="!!dealToDelete"
        title="Excluir negociação?"
        :processing="isDeleting"
        @close="dealToDelete = null"
        @confirm="confirmDelete"
    >
        A negociação com
        <span class="font-medium text-foreground">{{
            dealToDelete?.lead.name
        }}</span>
        e as visitas e propostas dela serão removidas.
    </ConfirmDeleteDialog>
</template>
