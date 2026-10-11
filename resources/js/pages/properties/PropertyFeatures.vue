<script setup lang="ts">
import {
    Bath,
    BedDouble,
    BedSingle,
    Car,
    LandPlot,
    MoveHorizontal,
    MoveVertical,
    Ruler,
    Sparkles,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import type { Property } from '@/types';

const props = defineProps<{
    property: Pick<
        Property,
        | 'bedrooms'
        | 'suites'
        | 'bathrooms'
        | 'parking_spaces'
        | 'built_area'
        | 'land_area'
        | 'land_front'
        | 'land_depth'
        | 'description'
    >;
}>();

type Feature = { icon: LucideIcon; value: string; label: string };

function measure(value: string, unit: string): string {
    return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(Number(value))} ${unit}`;
}

function count(
    value: number,
    singular: string,
    plural: string,
): Feature['label'] {
    return value === 1 ? singular : plural;
}

const features = computed<Feature[]>(() => {
    const property = props.property;
    const items: Feature[] = [];

    if (property.bedrooms) {
        items.push({
            icon: BedDouble,
            value: String(property.bedrooms),
            label: count(property.bedrooms, 'Quarto', 'Quartos'),
        });
    }

    if (property.suites) {
        items.push({
            icon: BedSingle,
            value: String(property.suites),
            label: count(property.suites, 'Suíte', 'Suítes'),
        });
    }

    if (property.bathrooms) {
        items.push({
            icon: Bath,
            value: String(property.bathrooms),
            label: count(property.bathrooms, 'Banheiro', 'Banheiros'),
        });
    }

    if (property.parking_spaces) {
        items.push({
            icon: Car,
            value: String(property.parking_spaces),
            label: count(property.parking_spaces, 'Vaga', 'Vagas'),
        });
    }

    if (property.built_area) {
        items.push({
            icon: Ruler,
            value: measure(property.built_area, 'm²'),
            label: 'Área construída',
        });
    }

    if (property.land_area) {
        items.push({
            icon: LandPlot,
            value: measure(property.land_area, 'm²'),
            label: 'Área do terreno',
        });
    }

    if (property.land_front) {
        items.push({
            icon: MoveHorizontal,
            value: measure(property.land_front, 'm'),
            label: 'Frente',
        });
    }

    if (property.land_depth) {
        items.push({
            icon: MoveVertical,
            value: measure(property.land_depth, 'm'),
            label: 'Fundo',
        });
    }

    return items;
});
</script>

<template>
    <div
        v-if="features.length > 0 || property.description"
        class="caracteristicas space-y-3"
    >
        <h3
            class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
            <Sparkles class="size-3.5" />
            Características
        </h3>

        <ul
            v-if="features.length > 0"
            class="grid grid-cols-2 gap-2 sm:grid-cols-4"
        >
            <li
                v-for="feature in features"
                :key="feature.label"
                class="flex items-center gap-3 rounded-lg border bg-muted/30 p-3"
            >
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <component :is="feature.icon" class="size-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold tabular-nums">
                        {{ feature.value }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ feature.label }}
                    </p>
                </div>
            </li>
        </ul>

        <p
            v-if="property.description"
            class="rounded-lg border p-3 text-sm whitespace-pre-line text-muted-foreground"
        >
            {{ property.description }}
        </p>
    </div>
</template>
