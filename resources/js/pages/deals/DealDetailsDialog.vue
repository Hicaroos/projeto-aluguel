<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CircleX,
    FileSignature,
    House,
    MessageCircle,
    Pencil,
    Trash2,
    UserRound,
    Wallet,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePermissions } from '@/composables/usePermissions';
import { badgeTones } from '@/lib/badge-tones';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatPhone } from '@/lib/formatters';
import { propertyTypeIcons } from '@/lib/property-labels';
import { dealTypeLabels } from '@/lib/sales-labels';
import DealProposals from '@/pages/deals/DealProposals.vue';
import DealVisits from '@/pages/deals/DealVisits.vue';
import { lease } from '@/routes/deals';
import { index as leadsIndex } from '@/routes/leads';
import { index as propertiesIndex } from '@/routes/properties';
import { index as salesIndex } from '@/routes/sales';
import type { Deal, DealStage, TeamMemberOption } from '@/types';

const props = defineProps<{
    deal: Deal | null;
    stages: DealStage[];
    users: TeamMemberOption[];
}>();

const can = usePermissions();

const emit = defineEmits<{
    close: [];
    edit: [deal: Deal];
    lose: [deal: Deal];
    win: [deal: Deal];
    delete: [deal: Deal];
}>();

function stageOf(deal: Deal): DealStage | undefined {
    return props.stages.find((stage) => stage.id === deal.deal_stage_id);
}
</script>

<template>
    <Dialog :open="!!deal" @update:open="(open) => !open && emit('close')">
        <DialogContent
            v-if="deal"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl"
        >
            <DialogHeader class="text-left">
                <div class="flex flex-wrap items-center gap-2 pr-6">
                    <DialogTitle>{{ deal.lead.name }}</DialogTitle>
                    <Badge variant="outline">{{
                        dealTypeLabels[deal.type]
                    }}</Badge>
                    <Badge
                        variant="outline"
                        :class="{
                            [badgeTones.success]:
                                stageOf(deal)?.outcome === 'won',
                            [badgeTones.danger]:
                                stageOf(deal)?.outcome === 'lost',
                        }"
                    >
                        {{ stageOf(deal)?.name }}
                    </Badge>
                </div>
                <DialogDescription>
                    Negociação iniciada em {{ formatDate(deal.created_at) }}
                    <template v-if="deal.closed_at">
                        · encerrada em {{ formatDate(deal.closed_at) }}
                    </template>
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="deal.lost_reason"
                class="rounded-lg border p-3 text-sm"
                :class="badgeTones.danger"
            >
                <span class="font-medium">Motivo da perda:</span>
                {{ deal.lost_reason }}
            </p>

            <ul class="divide-y rounded-lg border">
                <li class="flex items-center gap-3 p-3">
                    <component
                        :is="
                            deal.property
                                ? propertyTypeIcons[deal.property.type]
                                : House
                        "
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">Imóvel</p>
                        <p class="truncate text-sm font-medium">
                            {{
                                deal.property
                                    ? `${deal.property.street}, ${deal.property.number} · ${deal.property.neighborhood}`
                                    : 'A definir'
                            }}
                        </p>
                    </div>
                    <Button
                        v-if="deal.property"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="
                                propertiesIndex({
                                    query: { show: deal.property.id },
                                })
                            "
                        >
                            Ver imóvel
                        </Link>
                    </Button>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <Wallet class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            {{
                                deal.type === 'sale'
                                    ? 'Valor da venda'
                                    : 'Valor do aluguel'
                            }}
                        </p>
                        <p class="text-sm font-medium tabular-nums">
                            {{ deal.value ? formatCurrency(deal.value) : '—' }}
                        </p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <UserRound class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">Interessado</p>
                        <p class="text-sm font-medium">
                            <Link
                                :href="
                                    leadsIndex({
                                        query: { show: deal.lead.id },
                                    })
                                "
                                class="text-primary hover:underline"
                            >
                                {{ deal.lead.name }}
                            </Link>
                            <span
                                v-if="deal.lead.phone"
                                class="font-normal text-muted-foreground tabular-nums"
                            >
                                · {{ formatPhone(deal.lead.phone) }}
                            </span>
                        </p>
                        <p
                            v-if="deal.user"
                            class="text-xs text-muted-foreground"
                        >
                            Responsável: {{ deal.user.name }}
                        </p>
                    </div>
                    <Button
                        v-if="deal.lead.phone"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a
                            :href="`https://wa.me/55${deal.lead.phone}`"
                            target="_blank"
                        >
                            <MessageCircle class="size-4" />
                            WhatsApp
                        </a>
                    </Button>
                </li>
            </ul>

            <DealVisits :deal="deal" :users="users" />

            <DealProposals :deal="deal" />

            <Button v-if="deal.sale" variant="outline" class="w-fit" as-child>
                <Link :href="salesIndex({ query: { show: deal.sale.id } })">
                    <BadgeCheck class="size-4" />
                    Ver venda
                </Link>
            </Button>

            <Button
                v-if="
                    deal.type === 'rent' &&
                    stageOf(deal)?.outcome === 'won' &&
                    can.manageRentals
                "
                variant="outline"
                class="w-fit"
                as-child
            >
                <Link :href="lease(deal)">
                    <FileSignature class="size-4" />
                    Cadastrar contrato
                </Link>
            </Button>

            <DialogFooter
                v-if="can.manageSales"
                class="flex-wrap gap-2 border-t pt-6"
            >
                <Button
                    variant="ghost"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive sm:mr-auto"
                    @click="emit('delete', deal)"
                >
                    <Trash2 class="size-4" />
                    Excluir
                </Button>
                <Button variant="outline" @click="emit('edit', deal)">
                    <Pencil class="size-4" />
                    Editar
                </Button>
                <template v-if="!deal.closed_at">
                    <Button variant="outline" @click="emit('lose', deal)">
                        <CircleX class="size-4" />
                        Marcar como perdida
                    </Button>
                    <Button @click="emit('win', deal)">
                        <BadgeCheck class="size-4" />
                        {{
                            deal.type === 'sale'
                                ? 'Registrar venda'
                                : 'Fechar locação'
                        }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
