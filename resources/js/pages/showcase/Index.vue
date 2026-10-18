<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ChevronDown,
    MessageCircle,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ShowcaseCard from '@/components/showcase/ShowcaseCard.vue';
import {
    condoAmenityLabels,
    propertyAmenityLabels,
    propertyTypeLabels,
} from '@/lib/property-labels';
import {
    formatShowcasePrice,
    plural,
    priceSteps,
    purposeParams,
    purposeWords,
    whatsappUrl,
} from '@/lib/showcase';
import { home, index } from '@/routes/showcase';
import type {
    Paginator,
    PropertyAmenity,
    PropertyType,
    ShowcaseCard as ShowcaseCardData,
    ShowcaseFilters,
    ShowcasePurpose,
    ShowcaseSite,
} from '@/types';

const props = defineProps<{
    site: ShowcaseSite;
    filters: ShowcaseFilters;
    properties: Paginator<ShowcaseCardData>;
    neighborhoods: string[];
    /** The property types on the showcase. */
    types: PropertyType[];
    /** What the properties on the showcase offer. */
    amenities: PropertyAmenity[];
}>();

const sorts: Record<ShowcaseFilters['sort'], string> = {
    recentes: 'Mais recentes',
    'menor-preco': 'Menor preço',
    'maior-preco': 'Maior preço',
    'maior-area': 'Maior área',
};

const purposes = computed(() =>
    (['rent', 'sale'] as ShowcasePurpose[]).filter(
        (purpose) => props.site.offers[purpose] > 0,
    ),
);

const title = computed(
    () =>
        `Imóveis ${purposeWords[props.filters.purpose]}${props.filters.neighborhood ? ` no bairro ${props.filters.neighborhood}` : ''}`,
);

/** How many of the filters behind "Mais filtros" are in use. */
const moreFilters = computed(
    () =>
        [
            props.filters.type !== '',
            props.filters.min > 0,
            props.filters.bathrooms > 0,
            props.filters.parking > 0,
            props.filters.area > 0,
        ].filter(Boolean).length + props.filters.amenities.length,
);

const activeFilters = computed(
    () =>
        [
            props.filters.neighborhood !== '',
            props.filters.bedrooms > 0,
            props.filters.max > 0,
        ].filter(Boolean).length + moreFilters.value,
);

const noFilters: Partial<ShowcaseFilters> = {
    neighborhood: '',
    bedrooms: 0,
    max: 0,
    type: '',
    min: 0,
    bathrooms: 0,
    parking: 0,
    area: 0,
    amenities: [],
};

/** Opened by the visitor, and already open when they arrive with those filters in use. */
const isMoreOpen = ref(moreFilters.value > 0);

const areaSteps = [50, 80, 100, 150, 200, 300];

const amenityGroups = computed(() =>
    [
        { title: 'Do imóvel', labels: propertyAmenityLabels },
        { title: 'Do condomínio', labels: condoAmenityLabels },
    ]
        .map((group) => ({
            title: group.title,
            options: props.amenities
                .filter((amenity) => amenity in group.labels)
                .map((amenity) => ({
                    value: amenity,
                    label: group.labels[amenity] ?? amenity,
                })),
        }))
        .filter((group) => group.options.length > 0),
);

type AmenityGroup = (typeof amenityGroups.value)[number];

/** How many amenities each list shows before "Ver mais": about two rows on a computer. */
const LIST_SIZE = 10;

const expandedGroups = ref<string[]>([]);

/** A list opens by itself when one of its hidden amenities is checked. */
function isExpanded(group: AmenityGroup) {
    return (
        expandedGroups.value.includes(group.title) ||
        group.options
            .slice(LIST_SIZE)
            .some((option) => props.filters.amenities.includes(option.value))
    );
}

function visibleOptions(group: AmenityGroup) {
    return isExpanded(group)
        ? group.options
        : group.options.slice(0, LIST_SIZE);
}

function toggleGroup(title: string) {
    expandedGroups.value = expandedGroups.value.includes(title)
        ? expandedGroups.value.filter((other) => other !== title)
        : [...expandedGroups.value, title];
}

function query(
    changes: Partial<ShowcaseFilters> = {},
): Record<string, string | number | undefined> {
    const filters = { ...props.filters, ...changes };

    return {
        finalidade: purposeParams[filters.purpose],
        bairro: filters.neighborhood || undefined,
        quartos: filters.bedrooms || undefined,
        de: filters.min || undefined,
        ate: filters.max || undefined,
        tipo: filters.type || undefined,
        banheiros: filters.bathrooms || undefined,
        vagas: filters.parking || undefined,
        area: filters.area || undefined,
        comodidades: filters.amenities.join(',') || undefined,
        ordem: filters.sort !== 'recentes' ? filters.sort : undefined,
    };
}

function listing(changes: Partial<ShowcaseFilters> = {}) {
    return index(props.site.slug, { query: query(changes) });
}

function filterBy(changes: Partial<ShowcaseFilters>) {
    router.get(
        listing(changes).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function onSelect(
    key:
        | 'neighborhood'
        | 'bedrooms'
        | 'max'
        | 'sort'
        | 'type'
        | 'min'
        | 'bathrooms'
        | 'parking'
        | 'area',
    event: Event,
) {
    const value = (event.target as HTMLSelectElement).value;

    filterBy({
        [key]: ['neighborhood', 'sort', 'type'].includes(key)
            ? value
            : Number(value),
    } as Partial<ShowcaseFilters>);
}

function toggleAmenity(amenity: PropertyAmenity) {
    filterBy({
        amenities: props.filters.amenities.includes(amenity)
            ? props.filters.amenities.filter((other) => other !== amenity)
            : [...props.filters.amenities, amenity],
    });
}

const askUrl = computed(() =>
    whatsappUrl(
        props.site,
        `Olá! Procuro um imóvel ${props.filters.purpose === 'sale' ? 'para comprar' : 'para alugar'}${props.filters.neighborhood ? ` no bairro ${props.filters.neighborhood}` : ''} e não achei no site. Podem me avisar quando entrar algum?`,
    ),
);
</script>

<template>
    <Head :title="`${title} · ${site.name}`" />

    <section class="sc-page-head">
        <div class="sc-wrap">
            <nav class="sc-crumbs" aria-label="Você está em">
                <Link :href="home(site.slug)">Início</Link>
                <span aria-hidden="true">›</span>
                <span>Imóveis {{ purposeWords[filters.purpose] }}</span>
            </nav>
            <h1>{{ title }}</h1>

            <nav
                v-if="purposes.length > 1"
                class="sc-tabs"
                aria-label="Finalidade"
            >
                <Link
                    v-for="option in purposes"
                    :key="option"
                    class="sc-tab"
                    :href="listing({ purpose: option, max: 0, min: 0 })"
                    :aria-current="
                        filters.purpose === option ? 'page' : undefined
                    "
                    preserve-scroll
                >
                    {{ option === 'sale' ? 'Comprar' : 'Alugar' }}
                </Link>
            </nav>

            <div class="sc-filterbar">
                <div class="sc-field">
                    <label for="list-neighborhood">Bairro</label>
                    <select
                        id="list-neighborhood"
                        class="sc-select"
                        :value="filters.neighborhood"
                        @change="onSelect('neighborhood', $event)"
                    >
                        <option value="">Todos os bairros</option>
                        <option
                            v-for="name in neighborhoods"
                            :key="name"
                            :value="name"
                        >
                            {{ name }}
                        </option>
                    </select>
                </div>
                <div class="sc-field">
                    <label for="list-bedrooms">Quartos</label>
                    <select
                        id="list-bedrooms"
                        class="sc-select"
                        :value="String(filters.bedrooms)"
                        @change="onSelect('bedrooms', $event)"
                    >
                        <option value="0">Qualquer</option>
                        <option
                            v-for="count in [1, 2, 3, 4]"
                            :key="count"
                            :value="String(count)"
                        >
                            {{ count }} ou mais
                        </option>
                    </select>
                </div>
                <div class="sc-field">
                    <label for="list-max">Valor até</label>
                    <select
                        id="list-max"
                        class="sc-select"
                        :value="String(filters.max)"
                        @change="onSelect('max', $event)"
                    >
                        <option value="0">Sem limite</option>
                        <option
                            v-for="step in priceSteps[filters.purpose]"
                            :key="step"
                            :value="String(step)"
                        >
                            {{ formatShowcasePrice(step) }}
                        </option>
                    </select>
                </div>
                <div class="sc-field">
                    <label for="list-sort">Ordenar por</label>
                    <select
                        id="list-sort"
                        class="sc-select"
                        :value="filters.sort"
                        @change="onSelect('sort', $event)"
                    >
                        <option
                            v-for="(label, value) in sorts"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <button
                    type="button"
                    class="sc-button sc-button-outline sc-more-toggle"
                    :aria-expanded="isMoreOpen"
                    aria-controls="mais-filtros"
                    @click="isMoreOpen = !isMoreOpen"
                >
                    <SlidersHorizontal />
                    Mais filtros<template v-if="moreFilters > 0">
                        ({{ moreFilters }})</template
                    >
                    <ChevronDown class="sc-more-chevron" />
                </button>
            </div>

            <div v-if="isMoreOpen" id="mais-filtros" class="sc-more">
                <div class="sc-more-grid">
                    <div v-if="types.length > 1" class="sc-field">
                        <label for="list-type">Tipo</label>
                        <select
                            id="list-type"
                            class="sc-select"
                            :value="filters.type"
                            @change="onSelect('type', $event)"
                        >
                            <option value="">Todos os tipos</option>
                            <option
                                v-for="type in types"
                                :key="type"
                                :value="type"
                            >
                                {{ propertyTypeLabels[type] }}
                            </option>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label for="list-min">Valor a partir de</label>
                        <select
                            id="list-min"
                            class="sc-select"
                            :value="String(filters.min)"
                            @change="onSelect('min', $event)"
                        >
                            <option value="0">Sem mínimo</option>
                            <option
                                v-for="step in priceSteps[filters.purpose]"
                                :key="step"
                                :value="String(step)"
                            >
                                {{ formatShowcasePrice(step) }}
                            </option>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label for="list-bathrooms">Banheiros</label>
                        <select
                            id="list-bathrooms"
                            class="sc-select"
                            :value="String(filters.bathrooms)"
                            @change="onSelect('bathrooms', $event)"
                        >
                            <option value="0">Qualquer</option>
                            <option
                                v-for="count in [1, 2, 3, 4]"
                                :key="count"
                                :value="String(count)"
                            >
                                {{ count }} ou mais
                            </option>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label for="list-parking">Vagas de garagem</label>
                        <select
                            id="list-parking"
                            class="sc-select"
                            :value="String(filters.parking)"
                            @change="onSelect('parking', $event)"
                        >
                            <option value="0">Qualquer</option>
                            <option
                                v-for="count in [1, 2, 3]"
                                :key="count"
                                :value="String(count)"
                            >
                                {{ count }} ou mais
                            </option>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label for="list-area">Área mínima</label>
                        <select
                            id="list-area"
                            class="sc-select"
                            :value="String(filters.area)"
                            @change="onSelect('area', $event)"
                        >
                            <option value="0">Qualquer</option>
                            <option
                                v-for="step in areaSteps"
                                :key="step"
                                :value="String(step)"
                            >
                                {{ step }} m²
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="amenityGroups.length > 0" class="sc-more-lists">
                    <fieldset
                        v-for="group in amenityGroups"
                        :key="group.title"
                        class="sc-checks"
                    >
                        <legend class="sc-field-label">
                            Comodidades {{ group.title.toLowerCase() }}
                        </legend>
                        <div class="sc-checks-grid">
                            <label
                                v-for="option in visibleOptions(group)"
                                :key="option.value"
                                class="sc-check"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        filters.amenities.includes(option.value)
                                    "
                                    @change="toggleAmenity(option.value)"
                                />
                                {{ option.label }}
                            </label>
                        </div>
                        <button
                            v-if="group.options.length > LIST_SIZE"
                            type="button"
                            class="sc-link sc-checks-more"
                            @click="toggleGroup(group.title)"
                        >
                            {{
                                isExpanded(group)
                                    ? 'Ver menos'
                                    : `Ver mais (${group.options.length - LIST_SIZE})`
                            }}
                        </button>
                    </fieldset>
                </div>

                <div v-if="activeFilters > 0" class="sc-more-foot">
                    <Link
                        class="sc-link"
                        :href="listing(noFilters)"
                        preserve-scroll
                    >
                        Limpar filtros
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <div class="sc-wrap sc-results">
        <p class="sc-results-meta sc-num">
            {{
                plural(
                    properties.total,
                    'imóvel encontrado',
                    'imóveis encontrados',
                )
            }}
        </p>

        <div v-if="properties.data.length > 0" class="sc-grid">
            <ShowcaseCard
                v-for="property in properties.data"
                :key="property.id"
                :slug="site.slug"
                :property="property"
                :purpose="filters.purpose"
            />
        </div>

        <div v-else class="sc-empty">
            <h2>Nenhum imóvel com esses filtros</h2>
            <p>
                Tente tirar alguns filtros. Se preferir, conte o que procura e
                {{ site.name }} avisa quando entrar algo.
            </p>
            <div class="sc-empty-actions">
                <Link
                    v-if="activeFilters > 0"
                    class="sc-button sc-button-outline"
                    :href="listing(noFilters)"
                >
                    Limpar filtros
                </Link>
                <a
                    v-if="askUrl"
                    class="sc-button sc-button-whatsapp"
                    :href="askUrl"
                    target="_blank"
                    rel="noopener"
                >
                    <MessageCircle />
                    Contar o que procuro
                </a>
            </div>
        </div>

        <nav
            v-if="properties.last_page > 1"
            class="sc-pages"
            aria-label="Páginas"
        >
            <Link
                v-if="properties.prev_page_url"
                class="sc-button sc-button-outline"
                :href="properties.prev_page_url"
            >
                <ArrowLeft />
                Anteriores
            </Link>
            <span v-else />
            <span class="sc-muted sc-num">
                Página {{ properties.current_page }} de
                {{ properties.last_page }}
            </span>
            <Link
                v-if="properties.next_page_url"
                class="sc-button sc-button-outline"
                :href="properties.next_page_url"
            >
                Próximos
                <ArrowRight />
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
