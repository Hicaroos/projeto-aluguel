<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    House,
    IdCard,
    KeyRound,
    Mail,
    MapPin,
    Pencil,
    Phone,
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
import { formatDate, formatDocument, formatPhone } from '@/lib/formatters';
import {
    formatPersonAddress,
    formatPersonQualification,
} from '@/lib/person-labels';
import {
    propertyStatusBadgeClasses,
    propertyStatusLabels,
    propertyTypeIcons,
} from '@/lib/property-labels';
import { index as propertiesIndex } from '@/routes/properties';
import type { Owner } from '@/types';

defineProps<{
    owner: Owner | null;
}>();

const can = usePermissions();

const emit = defineEmits<{
    close: [];
    edit: [owner: Owner];
}>();
</script>

<template>
    <Dialog :open="!!owner" @update:open="(open) => !open && emit('close')">
        <DialogContent
            v-if="owner"
            class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader class="flex-row items-center gap-4 text-left">
                <Avatar class="size-14">
                    <AvatarFallback
                        class="bg-primary/10 text-lg font-semibold text-primary"
                    >
                        {{ getInitials(owner.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 space-y-1 pr-6">
                    <DialogTitle class="truncate">{{ owner.name }}</DialogTitle>
                    <DialogDescription>Proprietário</DialogDescription>
                </div>
            </DialogHeader>

            <ul class="divide-y rounded-lg border">
                <li class="flex items-center gap-3 p-3">
                    <IdCard class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">CPF/CNPJ</p>
                        <p class="text-sm font-medium tabular-nums">
                            {{ formatDocument(owner.cpf_cnpj) }}
                        </p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <Mail class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">E-mail</p>
                        <a
                            v-if="owner.email"
                            :href="`mailto:${owner.email}`"
                            class="block truncate text-sm font-medium text-primary hover:underline"
                            >{{ owner.email }}</a
                        >
                        <p v-else class="text-sm font-medium">—</p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <Phone class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Celular</p>
                        <a
                            v-if="owner.phone"
                            :href="`tel:${owner.phone}`"
                            class="text-sm font-medium text-primary tabular-nums hover:underline"
                            >{{ formatPhone(owner.phone) }}</a
                        >
                        <p v-else class="text-sm font-medium">—</p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <KeyRound class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Chave Pix</p>
                        <p class="text-sm font-medium break-all">
                            {{ owner.pix_key ?? '—' }}
                        </p>
                    </div>
                </li>
                <li
                    v-if="formatPersonQualification(owner)"
                    class="flex items-center gap-3 p-3"
                >
                    <UserRound class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            Qualificação
                        </p>
                        <p class="text-sm font-medium">
                            {{ formatPersonQualification(owner) }}
                        </p>
                    </div>
                </li>
                <li
                    v-if="formatPersonAddress(owner)"
                    class="flex items-center gap-3 p-3"
                >
                    <MapPin class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Endereço</p>
                        <p class="text-sm font-medium">
                            {{ formatPersonAddress(owner) }}
                        </p>
                    </div>
                </li>
                <li class="flex items-center gap-3 p-3">
                    <CalendarDays
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            Cadastrado em
                        </p>
                        <p class="text-sm font-medium">
                            {{ formatDate(owner.created_at) }}
                        </p>
                    </div>
                </li>
            </ul>

            <div class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <House class="size-3.5" />
                    Imóveis
                </h3>

                <p
                    v-if="!owner.properties?.length"
                    class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    Nenhum imóvel deste proprietário cadastrado ainda.
                </p>

                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="property in owner.properties"
                        :key="property.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <component
                                :is="propertyTypeIcons[property.type]"
                                class="size-4"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <span class="min-w-0 truncate">
                                    {{ property.street }},
                                    {{ property.number }}
                                </span>
                                <Badge
                                    variant="outline"
                                    :class="
                                        propertyStatusBadgeClasses[
                                            property.status
                                        ]
                                    "
                                >
                                    {{ propertyStatusLabels[property.status] }}
                                </Badge>
                            </p>
                            <p
                                class="truncate text-xs text-muted-foreground tabular-nums"
                            >
                                {{ property.neighborhood }} ·
                                {{ formatCurrency(property.rent_amount) }}
                                <template v-if="property.branch">
                                    · {{ property.branch.name }}
                                </template>
                            </p>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="
                                    propertiesIndex({
                                        query: { show: property.id },
                                    })
                                "
                            >
                                <House class="size-4" />
                                Ver
                            </Link>
                        </Button>
                    </li>
                </ul>
            </div>

            <DialogFooter class="border-t pt-6">
                <DialogClose as-child>
                    <Button variant="outline">Fechar</Button>
                </DialogClose>
                <Button v-if="can.manageRentals" @click="emit('edit', owner)">
                    <Pencil class="size-4" />
                    Editar proprietário
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
