<script setup lang="ts">
import { MapPin, Pencil, Wallet } from '@lucide/vue';
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
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatZipCode } from '@/lib/formatters';
import {
    propertyStatusBadgeClasses,
    propertyStatusDotClasses,
    propertyStatusLabels,
    propertyTypeIcons,
    propertyTypeLabels,
} from '@/lib/property-labels';
import type { Property } from '@/types';

defineProps<{
    property: Property | null;
}>();

const emit = defineEmits<{
    close: [];
    edit: [property: Property];
}>();
</script>

<template>
    <Dialog :open="!!property" @update:open="(open) => !open && emit('close')">
        <DialogContent v-if="property" class="sm:max-w-xl">
            <DialogHeader class="flex-row items-start gap-4 text-left">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <component
                        :is="propertyTypeIcons[property.type]"
                        class="size-6"
                    />
                </div>
                <div class="min-w-0 space-y-1.5 pr-6">
                    <DialogTitle class="leading-snug">
                        {{ property.street }}, {{ property.number }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ propertyTypeLabels[property.type] }} ·
                        {{ property.city }}/{{ property.state }}
                    </DialogDescription>
                    <Badge
                        variant="outline"
                        :class="propertyStatusBadgeClasses[property.status]"
                    >
                        <span
                            class="size-1.5 rounded-full"
                            :class="propertyStatusDotClasses[property.status]"
                        />
                        {{ propertyStatusLabels[property.status] }}
                    </Badge>
                </div>
            </DialogHeader>

            <div
                class="flex items-center gap-4 rounded-lg border bg-muted/40 p-4"
            >
                <div
                    class="flex size-10 items-center justify-center rounded-full bg-background text-primary shadow-xs"
                >
                    <Wallet class="size-5" />
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        Valor do aluguel
                    </p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ formatCurrency(property.rent_amount) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >/mês</span
                        >
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <MapPin class="size-3.5" />
                    Endereço
                </h3>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div class="col-span-2">
                        <dt class="text-muted-foreground">Logradouro</dt>
                        <dd class="font-medium">
                            {{ property.street }}, {{ property.number }}
                            <span v-if="property.complement">
                                — {{ property.complement }}</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Bairro</dt>
                        <dd class="font-medium">{{ property.neighborhood }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">CEP</dt>
                        <dd class="font-medium">
                            {{ formatZipCode(property.zip_code) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Cidade/UF</dt>
                        <dd class="font-medium">
                            {{ property.city }}/{{ property.state }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Cadastrado em</dt>
                        <dd class="font-medium">
                            {{ formatDate(property.created_at) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <DialogFooter class="border-t pt-6">
                <DialogClose as-child>
                    <Button variant="outline">Fechar</Button>
                </DialogClose>
                <Button @click="emit('edit', property)">
                    <Pencil class="size-4" />
                    Editar imóvel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
