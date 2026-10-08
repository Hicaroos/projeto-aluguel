<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileText, MapPin, Pencil, UserRound, Wallet } from '@lucide/vue';
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
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatZipCode } from '@/lib/formatters';
import {
    propertyStatusBadgeClasses,
    propertyStatusDotClasses,
    propertyStatusLabels,
    propertyTypeIcons,
    propertyTypeLabels,
} from '@/lib/property-labels';
import PropertyPhotos from '@/pages/properties/PropertyPhotos.vue';
import { index as leasesIndex } from '@/routes/leases';
import { index as tenantsIndex } from '@/routes/tenants';
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
        <DialogContent
            v-if="property"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader class="flex-row items-start gap-4 text-left">
                <img
                    v-if="property.photos?.length"
                    :src="property.photos[0].thumbnail_url"
                    alt=""
                    class="size-12 shrink-0 rounded-xl border object-cover"
                />
                <div
                    v-else
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

            <PropertyPhotos :property="property" />

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

            <div v-if="property.active_lease" class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <FileText class="size-3.5" />
                    Contrato atual
                </h3>
                <div
                    class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <Avatar class="size-10">
                            <AvatarFallback
                                class="bg-primary/10 text-sm font-medium text-primary"
                            >
                                {{
                                    getInitials(
                                        property.active_lease.tenant.name,
                                    )
                                }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ property.active_lease.tenant.name }}
                            </p>
                            <p
                                class="text-sm text-muted-foreground tabular-nums"
                            >
                                {{
                                    formatDate(property.active_lease.start_date)
                                }}
                                a
                                {{ formatDate(property.active_lease.end_date) }}
                                ·
                                {{
                                    formatCurrency(property.active_lease.amount)
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="
                                    tenantsIndex({
                                        query: {
                                            show: property.active_lease
                                                .tenant_id,
                                        },
                                    })
                                "
                            >
                                <UserRound class="size-4" />
                                Ver inquilino
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="
                                    leasesIndex({
                                        query: {
                                            show: property.active_lease.id,
                                        },
                                    })
                                "
                            >
                                <FileText class="size-4" />
                                Ver contrato
                            </Link>
                        </Button>
                    </div>
                </div>
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
