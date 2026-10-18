<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    House,
    Megaphone,
    MessageCircle,
    Search,
    ShieldCheck,
    TrendingUp,
    Users,
    Wallet,
    Clock,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ShowcaseListingController from '@/actions/App/Http/Controllers/ShowcaseListingController';
import ShowcaseCard from '@/components/showcase/ShowcaseCard.vue';
import ShowcaseEditableText from '@/components/showcase/ShowcaseEditableText.vue';
import { useShowcaseEditor } from '@/composables/useShowcaseEditor';
import { maskPhone } from '@/lib/formatters';
import {
    formatShowcasePrice,
    plural,
    priceSteps,
    purposeParams,
    purposeWords,
} from '@/lib/showcase';
import { index } from '@/routes/showcase';
import type {
    ShowcaseCard as ShowcaseCardData,
    ShowcaseCity,
    ShowcasePurpose,
    ShowcaseSite,
    ShowcaseTextKey,
} from '@/types';

const props = defineProps<{
    site: ShowcaseSite;
    featured: ShowcaseCardData | null;
    /** The featured property's cover in full size, behind the hero. */
    heroPhoto: string | null;
    forSale: ShowcaseCardData[];
    forRent: ShowcaseCardData[];
    neighborhoods: string[];
    /** The cities on the showcase; the search asks for a city only when there are several. */
    cities: ShowcaseCity[];
}>();

const purposes = computed(() =>
    (['rent', 'sale'] as ShowcasePurpose[]).filter(
        (purpose) => props.site.offers[purpose] > 0,
    ),
);

/* ---------- Search ---------- */

const purpose = ref<ShowcasePurpose>(purposes.value[0] ?? 'rent');
const city = ref('');
const neighborhood = ref('');
const bedrooms = ref('0');
const max = ref('0');

const hasCities = computed(() => props.cities.length > 1);

/** The neighborhoods of the chosen city, or of every city. */
const neighborhoodOptions = computed(() =>
    city.value
        ? (props.cities.find((option) => option.name === city.value)
              ?.neighborhoods ?? [])
        : props.neighborhoods,
);

/** Let go of a neighborhood that belongs to another city. */
watch(neighborhoodOptions, (options) => {
    if (!options.includes(neighborhood.value)) {
        neighborhood.value = '';
    }
});

function choosePurpose(chosen: ShowcasePurpose) {
    purpose.value = chosen;
    max.value = '0';
}

function listingUrl(
    chosen: ShowcasePurpose,
    chosenNeighborhood = '',
    chosenCity = '',
) {
    return index(props.site.slug, {
        query: {
            finalidade: purposeParams[chosen],
            cidade: chosenCity || undefined,
            bairro: chosenNeighborhood || undefined,
        },
    });
}

function search() {
    router.get(
        index(props.site.slug, {
            query: {
                finalidade: purposeParams[purpose.value],
                cidade: city.value || undefined,
                bairro: neighborhood.value || undefined,
                quartos: bedrooms.value !== '0' ? bedrooms.value : undefined,
                ate: max.value !== '0' ? max.value : undefined,
            },
        }).url,
    );
}

/** The number shown on the "why us" photo: what is on offer now, rentals first. */
const highlightOffer = computed<{ count: number; label: string } | null>(() => {
    const { rent, sale } = props.site.offers;

    if (rent > 0) {
        return {
            count: rent,
            label: `${rent === 1 ? 'imóvel' : 'imóveis'} para alugar agora`,
        };
    }

    return sale > 0
        ? {
              count: sale,
              label: `${sale === 1 ? 'imóvel' : 'imóveis'} à venda agora`,
          }
        : null;
});

const offersLine = computed(() =>
    [
        props.site.offers.rent > 0
            ? `${plural(props.site.offers.rent, 'imóvel', 'imóveis')} para alugar`
            : null,
        props.site.offers.sale > 0 ? `${props.site.offers.sale} à venda` : null,
    ]
        .filter(Boolean)
        .join(' · '),
);

/** In edit mode the banners lose their links, so a click only picks the text to rewrite. */
const isEditing = useShowcaseEditor() !== null;

/* ---------- Carousels ---------- */

const sections = computed(() =>
    [
        {
            purpose: 'rent' as const,
            title: 'rent_title' as const,
            items: props.forRent,
            total: props.site.offers.rent,
        },
        {
            purpose: 'sale' as const,
            title: 'sale_title' as const,
            items: props.forSale,
            total: props.site.offers.sale,
        },
    ].filter((section) => section.items.length > 0),
);

const rails: Partial<Record<ShowcasePurpose, HTMLElement>> = {};

function setRail(chosen: ShowcasePurpose, element: unknown) {
    if (element instanceof HTMLElement) {
        rails[chosen] = element;
    }
}

function scrollRail(chosen: ShowcasePurpose, direction: number) {
    const rail = rails[chosen];

    rail?.scrollBy({
        left: direction * rail.clientWidth * 0.9,
        behavior: 'smooth',
    });
}

/** The "why us" benefits, in the account's words, each with its own icon. */
const benefits = ([Wallet, ShieldCheck, Clock, MessageCircle] as const).map(
    (icon, position) => ({
        icon,
        title: `benefit_${position + 1}_title` as ShowcaseTextKey,
        text: `benefit_${position + 1}_text` as ShowcaseTextKey,
    }),
);

/** What the agency does for owners who list with it, in the account's words. */
const ownerPoints = ([TrendingUp, Megaphone, Users] as const).map(
    (icon, position) => ({
        icon,
        title: `owner_point_${position + 1}_title` as ShowcaseTextKey,
        text: `owner_point_${position + 1}_text` as ShowcaseTextKey,
    }),
);

/** Photos of the properties on the showcase, to dress the banners and the "why us" section. */
const photos = computed(() => [
    ...new Set(
        [...props.forRent, ...props.forSale]
            .map((property) => property.photo)
            .filter((photo): photo is string => photo !== null),
    ),
]);

/* ---------- Owners who want to list ---------- */

const goals = [
    { value: 'alugar', label: 'Alugar meu imóvel' },
    { value: 'vender', label: 'Vender meu imóvel' },
    { value: 'avaliar', label: 'Avaliar meu imóvel' },
];

const goal = ref('alugar');
const ownerName = ref('');
const ownerPhone = ref('');
const ownerSentTo = ref<string | null>(null);

function onOwnerPhoneInput(event: Event) {
    ownerPhone.value = maskPhone((event.target as HTMLInputElement).value);
}

function onOwnerSent() {
    ownerSentTo.value = ownerName.value.trim().split(/\s+/)[0] ?? '';
}
</script>

<template>
    <Head :title="site.name" />

    <section
        class="sc-hero"
        :class="{ 'has-photo': heroPhoto }"
        :style="
            heroPhoto ? { '--sc-hero-photo': `url('${heroPhoto}')` } : undefined
        "
    >
        <div class="sc-wrap">
            <div class="sc-hero-copy">
                <p class="sc-hero-eyebrow">
                    <House />
                    {{ offersLine || site.name }}
                </p>
                <ShowcaseEditableText
                    as="h1"
                    field="headline"
                    :text="site.headline"
                />
                <ShowcaseEditableText
                    as="p"
                    field="hero_lead"
                    :text="site.texts.hero_lead"
                />
            </div>
        </div>
    </section>

    <div class="sc-wrap">
        <form
            v-if="purposes.length > 0"
            class="sc-search"
            aria-label="Buscar imóveis"
            @submit.prevent="search"
        >
            <div v-if="purposes.length > 1" class="sc-tabs" role="tablist">
                <button
                    v-for="option in purposes"
                    :key="option"
                    type="button"
                    class="sc-tab"
                    role="tab"
                    :aria-selected="purpose === option"
                    @click="choosePurpose(option)"
                >
                    {{ option === 'sale' ? 'Comprar' : 'Alugar' }}
                </button>
            </div>
            <p v-else class="sc-search-label">
                Imóveis {{ purposeWords[purpose] }}
            </p>
            <div class="sc-search-row" :class="{ 'has-city': hasCities }">
                <div v-if="hasCities" class="sc-field">
                    <label for="search-city">Cidade</label>
                    <select id="search-city" v-model="city" class="sc-select">
                        <option value="">Todas as cidades</option>
                        <option
                            v-for="option in cities"
                            :key="option.name"
                            :value="option.name"
                        >
                            {{ option.name }}
                        </option>
                    </select>
                </div>
                <div class="sc-field">
                    <label for="search-neighborhood">Bairro</label>
                    <select
                        id="search-neighborhood"
                        v-model="neighborhood"
                        class="sc-select"
                    >
                        <option value="">Todos os bairros</option>
                        <option
                            v-for="name in neighborhoodOptions"
                            :key="name"
                            :value="name"
                        >
                            {{ name }}
                        </option>
                    </select>
                </div>
                <div class="sc-field">
                    <label for="search-bedrooms">Quartos</label>
                    <select
                        id="search-bedrooms"
                        v-model="bedrooms"
                        class="sc-select"
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
                    <label for="search-max">Valor até</label>
                    <select id="search-max" v-model="max" class="sc-select">
                        <option value="0">Sem limite</option>
                        <option
                            v-for="step in priceSteps[purpose]"
                            :key="step"
                            :value="String(step)"
                        >
                            {{ formatShowcasePrice(step) }}
                        </option>
                    </select>
                </div>
                <button type="submit" class="sc-button">
                    <Search />
                    Buscar
                </button>
            </div>
            <p v-if="neighborhoodOptions.length > 1" class="sc-search-foot">
                <span>Bairros:</span>
                <Link
                    v-for="name in neighborhoodOptions.slice(0, 6)"
                    :key="name"
                    :href="listingUrl(purpose, name, city)"
                >
                    {{ name }}
                </Link>
            </p>
        </form>
    </div>

    <section v-if="featured === null" class="sc-section">
        <div class="sc-wrap sc-empty">
            <h2>Novos imóveis em breve</h2>
            <p>
                Ainda não há imóveis publicados aqui. Fale com
                {{ site.name }} para saber o que está disponível.
            </p>
        </div>
    </section>

    <section v-if="site.is_agency" class="sc-section" style="padding-bottom: 0">
        <div class="sc-wrap sc-promos">
            <a
                class="sc-promo"
                :href="isEditing ? undefined : '#anunciar'"
                @click="goal = 'alugar'"
            >
                <img v-if="photos[1]" :src="photos[1]" alt="" loading="lazy" />
                <ShowcaseEditableText
                    as="strong"
                    field="banner_1_title"
                    :text="site.texts.banner_1_title"
                />
                <span>
                    <ShowcaseEditableText
                        field="banner_1_action"
                        :text="site.texts.banner_1_action"
                    />
                    <ArrowRight />
                </span>
            </a>
            <a
                class="sc-promo"
                :href="isEditing ? undefined : '#anunciar'"
                @click="goal = 'avaliar'"
            >
                <img v-if="photos[2]" :src="photos[2]" alt="" loading="lazy" />
                <ShowcaseEditableText
                    as="strong"
                    field="banner_2_title"
                    :text="site.texts.banner_2_title"
                />
                <span>
                    <ShowcaseEditableText
                        field="banner_2_action"
                        :text="site.texts.banner_2_action"
                    />
                    <ArrowRight />
                </span>
            </a>
            <a class="sc-promo" :href="isEditing ? undefined : '#sobre'">
                <img v-if="photos[3]" :src="photos[3]" alt="" loading="lazy" />
                <ShowcaseEditableText
                    as="strong"
                    field="banner_3_title"
                    :text="site.texts.banner_3_title"
                />
                <span>
                    <ShowcaseEditableText
                        field="banner_3_action"
                        :text="site.texts.banner_3_action"
                    />
                    <ArrowRight />
                </span>
            </a>
        </div>
    </section>

    <section
        v-for="(section, position) in sections"
        :key="section.purpose"
        class="sc-section"
        :style="position > 0 ? 'padding-top: 0' : undefined"
    >
        <div class="sc-wrap">
            <div class="sc-section-head">
                <div>
                    <ShowcaseEditableText
                        as="h2"
                        :field="section.title"
                        :text="site.texts[section.title]"
                    />
                    <p class="sc-num">
                        {{
                            plural(
                                section.total,
                                'imóvel disponível',
                                'imóveis disponíveis',
                            )
                        }}
                        ·
                        <Link
                            class="sc-link"
                            :href="listingUrl(section.purpose)"
                        >
                            Ver todos
                        </Link>
                    </p>
                </div>
                <div v-if="section.items.length > 1" class="sc-arrows">
                    <button
                        type="button"
                        aria-label="Anteriores"
                        @click="scrollRail(section.purpose, -1)"
                    >
                        <ChevronLeft />
                    </button>
                    <button
                        type="button"
                        aria-label="Próximos"
                        @click="scrollRail(section.purpose, 1)"
                    >
                        <ChevronRight />
                    </button>
                </div>
            </div>
            <div
                :ref="(element) => setRail(section.purpose, element)"
                class="sc-rail"
            >
                <ShowcaseCard
                    v-for="property in section.items"
                    :key="property.id"
                    :slug="site.slug"
                    :property="property"
                    :purpose="section.purpose"
                />
            </div>
        </div>
    </section>

    <section
        v-if="site.is_agency"
        id="sobre"
        class="sc-section"
        style="background: var(--sc-surface)"
    >
        <div class="sc-wrap sc-why">
            <div class="sc-why-photo">
                <div class="sc-photo">
                    <img
                        v-if="photos[0] ?? heroPhoto"
                        :src="photos[0] ?? heroPhoto ?? ''"
                        alt=""
                        loading="lazy"
                    />
                    <div v-else class="sc-no-photo">{{ site.name }}</div>
                </div>
                <div v-if="highlightOffer" class="sc-why-stat">
                    <strong class="sc-num">{{ highlightOffer.count }}</strong>
                    <span>{{ highlightOffer.label }}</span>
                </div>
            </div>
            <div>
                <ShowcaseEditableText
                    as="h2"
                    field="why_title"
                    :text="site.texts.why_title"
                />
                <ShowcaseEditableText
                    as="p"
                    class="sc-why-lead"
                    field="why_lead"
                    :text="site.texts.why_lead"
                />
                <div class="sc-benefits">
                    <div
                        v-for="(benefit, position) in benefits"
                        :key="position"
                        class="sc-benefit"
                    >
                        <span class="sc-benefit-icon">
                            <component :is="benefit.icon" />
                        </span>
                        <div>
                            <ShowcaseEditableText
                                as="h3"
                                :field="benefit.title"
                                :text="site.texts[benefit.title]"
                            />
                            <ShowcaseEditableText
                                as="p"
                                :field="benefit.text"
                                :text="site.texts[benefit.text]"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section v-if="site.is_agency" id="anunciar" class="sc-owner">
        <div class="sc-wrap">
            <div>
                <ShowcaseEditableText
                    as="h2"
                    field="owner_title"
                    :text="site.texts.owner_title"
                />
                <ShowcaseEditableText
                    as="p"
                    class="sc-owner-lead"
                    field="owner_lead"
                    :text="site.texts.owner_lead"
                />
                <ul class="sc-owner-list">
                    <li v-for="point in ownerPoints" :key="point.title">
                        <span class="sc-owner-icon">
                            <component :is="point.icon" />
                        </span>
                        <div>
                            <ShowcaseEditableText
                                as="strong"
                                :field="point.title"
                                :text="site.texts[point.title]"
                            />
                            <ShowcaseEditableText
                                :field="point.text"
                                :text="site.texts[point.text]"
                            />
                        </div>
                    </li>
                </ul>
            </div>

            <div class="sc-owner-form">
                <ShowcaseEditableText
                    as="h3"
                    field="owner_form_title"
                    :text="site.texts.owner_form_title"
                />
                <ShowcaseEditableText
                    as="p"
                    field="owner_form_lead"
                    :text="site.texts.owner_form_lead"
                />

                <div v-if="ownerSentTo !== null" class="sc-sent" role="status">
                    <CircleCheck />
                    <div>
                        <strong>
                            Recebemos seus dados{{
                                ownerSentTo ? `, ${ownerSentTo}` : ''
                            }}.
                        </strong>
                        A equipe de {{ site.name }} vai chamar você no WhatsApp
                        para falar sobre o imóvel.
                    </div>
                </div>

                <Form
                    v-else
                    v-bind="ShowcaseListingController.store.form(site.slug)"
                    v-slot="{ errors, processing }"
                    :options="{ preserveScroll: true }"
                    class="sc-form"
                    @success="onOwnerSent"
                >
                    <div class="sc-two">
                        <div class="sc-field">
                            <label for="owner-name">Nome</label>
                            <input
                                id="owner-name"
                                v-model="ownerName"
                                class="sc-input"
                                name="name"
                                autocomplete="name"
                                maxlength="120"
                            />
                            <span v-if="errors.name" class="sc-error">{{
                                errors.name
                            }}</span>
                        </div>
                        <div class="sc-field">
                            <label for="owner-phone">WhatsApp</label>
                            <input
                                id="owner-phone"
                                :value="ownerPhone"
                                class="sc-input sc-num"
                                name="phone"
                                inputmode="numeric"
                                autocomplete="tel"
                                placeholder="(00) 00000-0000"
                                maxlength="15"
                                @input="onOwnerPhoneInput"
                            />
                            <span v-if="errors.phone" class="sc-error">{{
                                errors.phone
                            }}</span>
                        </div>
                    </div>
                    <div class="sc-two">
                        <div class="sc-field">
                            <label for="owner-goal">Quero</label>
                            <select
                                id="owner-goal"
                                v-model="goal"
                                class="sc-select"
                                name="goal"
                            >
                                <option
                                    v-for="option in goals"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <span v-if="errors.goal" class="sc-error">{{
                                errors.goal
                            }}</span>
                        </div>
                        <div class="sc-field">
                            <label for="owner-neighborhood">Bairro</label>
                            <input
                                id="owner-neighborhood"
                                class="sc-input"
                                name="neighborhood"
                                placeholder="Onde fica?"
                                maxlength="120"
                            />
                        </div>
                    </div>
                    <div class="sc-field">
                        <label for="owner-message">Sobre o imóvel</label>
                        <textarea
                            id="owner-message"
                            class="sc-textarea"
                            name="message"
                            maxlength="1000"
                            placeholder="Tipo, quartos, se está ocupado…"
                        />
                        <span v-if="errors.message" class="sc-error">{{
                            errors.message
                        }}</span>
                    </div>
                    <div class="sc-honeypot" aria-hidden="true">
                        <label for="owner-website">Site</label>
                        <input
                            id="owner-website"
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>
                    <button
                        type="submit"
                        class="sc-button sc-button-block"
                        :disabled="processing"
                    >
                        {{ processing ? 'Enviando…' : 'Quero anunciar' }}
                    </button>
                </Form>
            </div>
        </div>
    </section>
</template>
