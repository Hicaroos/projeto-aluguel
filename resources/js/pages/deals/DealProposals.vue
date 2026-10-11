<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    Check,
    FilePlus2,
    FileText,
    MoreHorizontal,
    Repeat,
    Trash2,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import { formatDate, todayIsoDate } from '@/lib/formatters';
import {
    paymentPlanLabels,
    proposalStatusBadgeClasses,
    proposalStatusLabels,
} from '@/lib/sales-labels';
import { destroy, update } from '@/routes/deal-proposals';
import { store } from '@/routes/deals/proposals';
import type { Deal, DealProposal, ProposalStatus } from '@/types';

const props = defineProps<{
    deal: Deal;
}>();

const can = usePermissions();

const NONE = 'none';

const isAdding = ref(false);

const form = useForm({
    amount: props.deal.value ?? '',
    payment_plan: props.deal.lead.payment_plan ?? NONE,
    conditions: '',
    proposed_on: todayIsoDate(),
});

function addProposal() {
    form.transform((data) => ({
        ...data,
        payment_plan: data.payment_plan === NONE ? null : data.payment_plan,
    })).post(store(props.deal).url, {
        preserveScroll: true,
        onSuccess: () => {
            isAdding.value = false;
            form.reset('conditions');
        },
    });
}

function answer(proposal: DealProposal, status: ProposalStatus) {
    router.put(
        update(proposal).url,
        {
            amount: proposal.amount,
            payment_plan: proposal.payment_plan,
            conditions: proposal.conditions,
            proposed_on: proposal.proposed_on,
            status,
        },
        { preserveScroll: true },
    );
}

function removeProposal(proposal: DealProposal) {
    router.delete(destroy(proposal).url, { preserveScroll: true });
}
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h3
                class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                <FileText class="size-3.5" />
                Propostas
            </h3>
            <Button
                v-if="can.manageSales && !isAdding && !deal.closed_at"
                variant="outline"
                size="sm"
                @click="isAdding = true"
            >
                <FilePlus2 class="size-4" />
                Nova proposta
            </Button>
        </div>

        <form
            v-if="isAdding"
            class="grid gap-3 rounded-lg border bg-muted/40 p-3"
            @submit.prevent="addProposal"
        >
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="proposal_amount">Valor oferecido</Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >R$</span
                        >
                        <Input
                            id="proposal_amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="pl-10 tabular-nums"
                        />
                    </div>
                    <InputError :message="form.errors.amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="proposal_payment_plan">Pagamento</Label>
                    <Select v-model="form.payment_plan">
                        <SelectTrigger
                            id="proposal_payment_plan"
                            class="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">Não informado</SelectItem>
                            <SelectItem
                                v-for="(label, key) in paymentPlanLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="proposal_proposed_on">Data</Label>
                    <Input
                        id="proposal_proposed_on"
                        v-model="form.proposed_on"
                        type="date"
                        :max="todayIsoDate()"
                        required
                    />
                    <InputError :message="form.errors.proposed_on" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="proposal_conditions">Condições</Label>
                <Input
                    id="proposal_conditions"
                    v-model="form.conditions"
                    placeholder="Ex.: entrada de R$ 60 mil e o restante financiado"
                />
                <InputError :message="form.errors.conditions" />
            </div>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" @click="isAdding = false">
                    Cancelar
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Registrar proposta
                </Button>
            </div>
        </form>

        <p
            v-if="deal.proposals.length === 0 && !isAdding"
            class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
        >
            Nenhuma proposta ainda.
        </p>

        <ul
            v-else-if="deal.proposals.length > 0"
            class="divide-y rounded-lg border"
        >
            <li
                v-for="proposal in deal.proposals"
                :key="proposal.id"
                class="flex items-start gap-3 p-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 text-sm font-medium">
                        <span class="tabular-nums">{{
                            formatCurrency(proposal.amount)
                        }}</span>
                        <Badge
                            variant="outline"
                            :class="proposalStatusBadgeClasses[proposal.status]"
                        >
                            {{ proposalStatusLabels[proposal.status] }}
                        </Badge>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(proposal.proposed_on) }}
                        <template v-if="proposal.payment_plan">
                            · {{ paymentPlanLabels[proposal.payment_plan] }}
                        </template>
                    </p>
                    <p v-if="proposal.conditions" class="mt-1 text-sm">
                        {{ proposal.conditions }}
                    </p>
                </div>
                <DropdownMenu v-if="can.manageSales">
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon-sm">
                            <MoreHorizontal class="size-4" />
                            <span class="sr-only">Ações da proposta</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuItem
                            v-if="proposal.status !== 'accepted'"
                            @click="answer(proposal, 'accepted')"
                        >
                            <Check class="size-4" />
                            Aceita
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="proposal.status !== 'countered'"
                            @click="answer(proposal, 'countered')"
                        >
                            <Repeat class="size-4" />
                            Fiz contraproposta
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="proposal.status !== 'rejected'"
                            @click="answer(proposal, 'rejected')"
                        >
                            <X class="size-4" />
                            Recusada
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            @click="removeProposal(proposal)"
                        >
                            <Trash2 class="size-4" />
                            Remover
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
        </ul>
    </section>
</template>
