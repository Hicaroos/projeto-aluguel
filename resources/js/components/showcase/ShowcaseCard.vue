<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bath, BedDouble, CarFront, MapPin, Maximize } from '@lucide/vue';
import { computed } from 'vue';
import { formatArea, formatShowcasePrice, offerLabel } from '@/lib/showcase';
import { show } from '@/routes/showcase';
import type { ShowcaseCard, ShowcasePurpose } from '@/types';

const props = defineProps<{
    slug: string;
    property: ShowcaseCard;
    /** The price to lead with, when the property is offered both ways. */
    purpose?: ShowcasePurpose;
}>();

const leadsWithRent = computed(
    () =>
        props.property.rent !== null &&
        (props.purpose === 'rent' || props.property.sale_price === null),
);

const area = computed(
    () => props.property.built_area ?? props.property.land_area,
);
</script>

<template>
    <Link class="sc-card" :href="show([slug, property.id])">
        <div class="sc-photo">
            <img
                v-if="property.photo"
                :src="property.photo"
                alt=""
                loading="lazy"
            />
            <div v-else class="sc-no-photo">{{ property.title }}</div>
            <div class="sc-badges">
                <span class="sc-badge">{{ offerLabel(property) }}</span>
                <span v-if="property.is_new" class="sc-badge sc-badge-new">
                    Novo
                </span>
            </div>
            <span class="sc-ref-chip">REF {{ property.ref }}</span>
        </div>
        <div class="sc-card-body">
            <p class="sc-card-price sc-num">
                <template v-if="leadsWithRent">
                    {{ formatShowcasePrice(property.rent ?? 0) }}
                    <small>/mês</small>
                </template>
                <template v-else>
                    {{ formatShowcasePrice(property.sale_price ?? 0) }}
                    <small v-if="property.rent !== null">
                        ou {{ formatShowcasePrice(property.rent) }}/mês
                    </small>
                </template>
            </p>
            <h3 class="sc-card-title">{{ property.title }}</h3>
            <p class="sc-place">
                <MapPin />
                {{ property.neighborhood }}, {{ property.city }}
            </p>
            <p
                v-if="
                    property.bedrooms ||
                    property.bathrooms ||
                    property.parking_spaces ||
                    area
                "
                class="sc-specs sc-num"
            >
                <span v-if="property.bedrooms">
                    <BedDouble />
                    {{ property.bedrooms }}
                    {{ property.bedrooms === 1 ? 'quarto' : 'quartos' }}
                </span>
                <span v-if="property.bathrooms">
                    <Bath />
                    {{ property.bathrooms }} banh.
                </span>
                <span v-if="property.parking_spaces">
                    <CarFront />
                    {{ property.parking_spaces }}
                    {{ property.parking_spaces === 1 ? 'vaga' : 'vagas' }}
                </span>
                <span v-if="area">
                    <Maximize />
                    {{ formatArea(area) }}
                </span>
            </p>
        </div>
    </Link>
</template>
