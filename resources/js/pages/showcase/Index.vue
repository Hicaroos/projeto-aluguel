<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import ShowcaseCard from '@/components/showcase/ShowcaseCard.vue';
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

const activeFilters = computed(
    () =>
        [
            props.filters.neighborhood !== '',
            props.filters.bedrooms > 0,
            props.filters.max > 0,
        ].filter(Boolean).length,
);

function query(
    changes: Partial<ShowcaseFilters> = {},
): Record<string, string | number | undefined> {
    const filters = { ...props.filters, ...changes };

    return {
        finalidade: purposeParams[filters.purpose],
        bairro: filters.neighborhood || undefined,
        quartos: filters.bedrooms || undefined,
        ate: filters.max || undefined,
        ordem: filters.sort !== 'recentes' ? filters.sort : undefined,
    };
}

function listing(changes: Partial<ShowcaseFilters> = {}) {
    return index(props.site.slug, { query: query(changes) });
}

function onSelect(
    key: 'neighborhood' | 'bedrooms' | 'max' | 'sort',
    event: Event,
) {
    const value = (event.target as HTMLSelectElement).value;

    router.get(
        listing({
            [key]:
                key === 'neighborhood' || key === 'sort'
                    ? value
                    : Number(value),
        } as Partial<ShowcaseFilters>).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
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
                    :href="listing({ purpose: option, max: 0 })"
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
                Tente outro bairro ou um valor maior. Se preferir, conte o que
                procura e {{ site.name }} avisa quando entrar algo.
            </p>
            <div class="sc-empty-actions">
                <Link
                    v-if="activeFilters > 0"
                    class="sc-button sc-button-outline"
                    :href="listing({ neighborhood: '', bedrooms: 0, max: 0 })"
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
