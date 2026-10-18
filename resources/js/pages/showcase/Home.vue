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
import { computed, ref } from 'vue';
import ShowcaseListingController from '@/actions/App/Http/Controllers/ShowcaseListingController';
import ShowcaseCard from '@/components/showcase/ShowcaseCard.vue';
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
    ShowcasePurpose,
    ShowcaseSite,
} from '@/types';

const props = defineProps<{
    site: ShowcaseSite;
    featured: ShowcaseCardData | null;
    /** The featured property's cover in full size, behind the hero. */
    heroPhoto: string | null;
    forSale: ShowcaseCardData[];
    forRent: ShowcaseCardData[];
    neighborhoods: string[];
}>();

const purposes = computed(() =>
    (['rent', 'sale'] as ShowcasePurpose[]).filter(
        (purpose) => props.site.offers[purpose] > 0,
    ),
);

/* ---------- Search ---------- */

const purpose = ref<ShowcasePurpose>(purposes.value[0] ?? 'rent');
const neighborhood = ref('');
const bedrooms = ref('0');
const max = ref('0');

function choosePurpose(chosen: ShowcasePurpose) {
    purpose.value = chosen;
    max.value = '0';
}

function listingUrl(chosen: ShowcasePurpose, chosenNeighborhood = '') {
    return index(props.site.slug, {
        query: {
            finalidade: purposeParams[chosen],
            bairro: chosenNeighborhood || undefined,
        },
    });
}

function search() {
    router.get(
        index(props.site.slug, {
            query: {
                finalidade: purposeParams[purpose.value],
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

const lead = computed(() =>
    props.site.is_agency
        ? 'Escolha o imóvel, peça uma visita pelo site e combine tudo direto com a equipe pelo WhatsApp.'
        : 'Escolha o imóvel e combine a visita direto com o proprietário.',
);

/* ---------- Carousels ---------- */

const sections = computed(() =>
    [
        {
            purpose: 'rent' as const,
            title: 'Imóveis para alugar',
            items: props.forRent,
            total: props.site.offers.rent,
        },
        {
            purpose: 'sale' as const,
            title: 'Imóveis à venda',
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
                <h1>{{ site.headline }}</h1>
                <p>{{ lead }}</p>
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
            <div class="sc-search-row">
                <div class="sc-field">
                    <label for="search-neighborhood">Bairro</label>
                    <select
                        id="search-neighborhood"
                        v-model="neighborhood"
                        class="sc-select"
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
            <p v-if="neighborhoods.length > 1" class="sc-search-foot">
                <span>Bairros:</span>
                <Link
                    v-for="name in neighborhoods.slice(0, 6)"
                    :key="name"
                    :href="listingUrl(purpose, name)"
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
            <a class="sc-promo" href="#anunciar" @click="goal = 'alugar'">
                <img v-if="photos[1]" :src="photos[1]" alt="" loading="lazy" />
                <strong>Anuncie seu imóvel com a gente</strong>
                <span>Fale com a equipe <ArrowRight /></span>
            </a>
            <a class="sc-promo" href="#anunciar" @click="goal = 'avaliar'">
                <img v-if="photos[2]" :src="photos[2]" alt="" loading="lazy" />
                <strong>Quanto vale o seu imóvel?</strong>
                <span>Peça uma avaliação <ArrowRight /></span>
            </a>
            <a class="sc-promo" href="#sobre">
                <img v-if="photos[3]" :src="photos[3]" alt="" loading="lazy" />
                <strong>Administração completa do aluguel</strong>
                <span>Conheça o serviço <ArrowRight /></span>
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
                    <h2>{{ section.title }}</h2>
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
                <h2>Por que escolher {{ site.name }}?</h2>
                <p class="sc-why-lead">
                    Cuidamos do imóvel do começo ao fim: do anúncio ao contrato,
                    da cobrança ao repasse do aluguel.
                </p>
                <div class="sc-benefits">
                    <div class="sc-benefit">
                        <span class="sc-benefit-icon"><Wallet /></span>
                        <div>
                            <h3>Repasse todo mês</h3>
                            <p>
                                O proprietário recebe o aluguel com o extrato de
                                cada mês.
                            </p>
                        </div>
                    </div>
                    <div class="sc-benefit">
                        <span class="sc-benefit-icon"><ShieldCheck /></span>
                        <div>
                            <h3>Inquilino com garantia</h3>
                            <p>
                                Contrato com fiador, caução ou seguro-fiança,
                                como for melhor para o imóvel.
                            </p>
                        </div>
                    </div>
                    <div class="sc-benefit">
                        <span class="sc-benefit-icon"><Clock /></span>
                        <div>
                            <h3>Cobrança acompanhada</h3>
                            <p>
                                A equipe cobra o aluguel e acompanha os atrasos
                                para você não precisar.
                            </p>
                        </div>
                    </div>
                    <div class="sc-benefit">
                        <span class="sc-benefit-icon"><MessageCircle /></span>
                        <div>
                            <h3>Atendimento direto</h3>
                            <p>
                                Interessados e proprietários falam com a equipe
                                pelo WhatsApp.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section v-if="site.is_agency" id="anunciar" class="sc-owner">
        <div class="sc-wrap">
            <div>
                <h2>Tem um imóvel para alugar ou vender?</h2>
                <p class="sc-owner-lead">
                    Deixe seus dados e a equipe de {{ site.name }} entra em
                    contato para avaliar o imóvel e combinar o anúncio.
                </p>
                <ul class="sc-owner-list">
                    <li>
                        <span class="sc-owner-icon"><TrendingUp /></span>
                        <div>
                            <strong>Avaliação de preço</strong>
                            <span>
                                Comparamos com imóveis parecidos no mesmo
                                bairro.
                            </span>
                        </div>
                    </li>
                    <li>
                        <span class="sc-owner-icon"><Megaphone /></span>
                        <div>
                            <strong>Divulgação no site</strong>
                            <span>
                                Fotos e anúncio prontos para compartilhar no
                                WhatsApp.
                            </span>
                        </div>
                    </li>
                    <li>
                        <span class="sc-owner-icon"><Users /></span>
                        <div>
                            <strong>Visitas acompanhadas</strong>
                            <span>
                                A equipe mostra o imóvel e atende os
                                interessados.
                            </span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="sc-owner-form">
                <h3>Anuncie com a gente</h3>
                <p>Responda em um minuto. A equipe chama você no WhatsApp.</p>

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
