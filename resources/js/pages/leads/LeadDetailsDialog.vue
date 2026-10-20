<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Globe,
    Handshake,
    Mail,
    MessageCircle,
    Pencil,
    Phone,
    Plus,
    Search,
    StickyNote,
    UserRound,
} from '@lucide/vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import { getInitials } from '@/composables/useInitials';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatPhone } from '@/lib/formatters';
import { propertyTypeLabels } from '@/lib/property-labels';
import {
    dealTypeLabels,
    leadInterestBadgeClasses,
    leadInterestLabels,
    leadSourceLabels,
    paymentPlanLabels,
} from '@/lib/sales-labels';
import { index as dealsIndex } from '@/routes/deals';
import type { Lead } from '@/types';

defineProps<{
    lead: Lead | null;
}>();

const can = usePermissions();

const emit = defineEmits<{
    close: [];
    edit: [lead: Lead];
    'new-deal': [lead: Lead];
}>();

/** What the lead looks for, e.g. "Casa · até R$ 300.000,00 · Financiamento". */
function lookingFor(lead: Lead): string {
    return [
        lead.property_type ? propertyTypeLabels[lead.property_type] : null,
        lead.max_price ? `até ${formatCurrency(lead.max_price)}` : null,
        lead.payment_plan ? paymentPlanLabels[lead.payment_plan] : null,
        lead.neighborhoods,
    ]
        .filter(Boolean)
        .join(' · ');
}

function whatsAppUrl(phone: string): string {
    return `https://wa.me/55${phone}`;
}
</script>

<template>
    <Dialog :open="!!lead" @update:open="(open) => !open && emit('close')">
        <DialogContent
            v-if="lead"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader class="flex-row items-center gap-4 text-left">
                <Avatar class="size-14">
                    <AvatarFallback
                        class="bg-primary/10 text-lg font-semibold text-primary"
                    >
                        {{ getInitials(lead.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 space-y-1 pr-6">
                    <DialogTitle class="truncate">{{ lead.name }}</DialogTitle>
                    <DialogDescription class="flex items-center gap-2">
                        Interessado em
                        <Badge
                            variant="outline"
                            :class="leadInterestBadgeClasses[lead.interest]"
                        >
                            {{ leadInterestLabels[lead.interest] }}
                        </Badge>
                    </DialogDescription>
                </div>
            </DialogHeader>

            <ul class="divide-y rounded-lg border">
                <li class="flex items-center gap-3 p-3">
                    <Phone class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">Celular</p>
                        <p class="text-sm font-medium tabular-nums">
                            {{ formatPhone(lead.phone) }}
                        </p>
                    </div>
                    <Button
                        v-if="lead.phone"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a :href="whatsAppUrl(lead.phone)" target="_blank">
                            <MessageCircle class="size-4" />
                            WhatsApp
                        </a>
                    </Button>
                </li>
                <li v-if="lead.email" class="flex items-center gap-3 p-3">
                    <Mail class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">E-mail</p>
                        <a
                            :href="`mailto:${lead.email}`"
                            class="block truncate text-sm font-medium text-primary hover:underline"
                            >{{ lead.email }}</a
                        >
                    </div>
                </li>
                <li v-if="lookingFor(lead)" class="flex items-center gap-3 p-3">
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Procura</p>
                        <p class="text-sm font-medium">
                            {{ lookingFor(lead) }}
                        </p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <UserRound class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Origem</p>
                        <p class="text-sm font-medium">
                            {{
                                lead.source
                                    ? leadSourceLabels[lead.source]
                                    : 'Não informada'
                            }}
                            <template v-if="lead.user">
                                · responsável {{ lead.user.name }}
                            </template>
                        </p>
                    </div>
                </li>
                <li v-if="lead.notes" class="flex items-start gap-3 p-3">
                    <StickyNote
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Anotações</p>
                        <p class="text-sm whitespace-pre-line">
                            {{ lead.notes }}
                        </p>
                    </div>
                </li>
            </ul>

            <div v-if="lead.showcase_requests?.length" class="space-y-3">
                <div class="space-y-1">
                    <h3
                        class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <Globe class="size-3.5" />
                        Pedidos pela vitrine
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Enviados pelo site por quem informou este telefone. Nome
                        e telefone não são confirmados: confira com a pessoa
                        antes de agir.
                    </p>
                </div>

                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="request in lead.showcase_requests"
                        :key="request.id"
                        class="space-y-1 p-3"
                    >
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ formatDate(request.created_at) }} · como
                            {{ request.name }}
                        </p>
                        <p class="text-sm font-medium">{{ request.summary }}</p>
                        <p
                            v-if="request.message"
                            class="text-sm whitespace-pre-line text-muted-foreground"
                        >
                            “{{ request.message }}”
                        </p>
                    </li>
                </ul>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h3
                        class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <Handshake class="size-3.5" />
                        Negociações
                    </h3>
                    <Button
                        v-if="can.manageSales"
                        variant="outline"
                        size="sm"
                        @click="emit('new-deal', lead)"
                    >
                        <Plus class="size-4" />
                        Nova negociação
                    </Button>
                </div>

                <p
                    v-if="!lead.deals?.length"
                    class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    Nenhuma negociação ainda.
                </p>

                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="deal in lead.deals"
                        :key="deal.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <span class="min-w-0 truncate">
                                    {{
                                        deal.property
                                            ? `${deal.property.street}, ${deal.property.number}`
                                            : 'Imóvel a definir'
                                    }}
                                </span>
                                <Badge variant="outline">
                                    {{ deal.stage.name }}
                                </Badge>
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ dealTypeLabels[deal.type] }}
                                <template v-if="deal.value">
                                    · {{ formatCurrency(deal.value) }}
                                </template>
                            </p>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="dealsIndex({ query: { show: deal.id } })"
                            >
                                Ver no funil
                            </Link>
                        </Button>
                    </li>
                </ul>
            </div>

            <DialogFooter class="border-t pt-6">
                <DialogClose as-child>
                    <Button variant="outline">Fechar</Button>
                </DialogClose>
                <Button v-if="can.manageSales" @click="emit('edit', lead)">
                    <Pencil class="size-4" />
                    Editar interessado
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
