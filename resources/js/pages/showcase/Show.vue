<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Bath,
    BedDouble,
    CarFront,
    Check,
    CircleCheck,
    Hash,
    LandPlot,
    MapPin,
    Maximize,
    MessageCircle,
    Ruler,
    Share2,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import ShowcaseVisitController from '@/actions/App/Http/Controllers/ShowcaseVisitController';
import ShowcaseCard from '@/components/showcase/ShowcaseCard.vue';
import ShowcaseGallery from '@/components/showcase/ShowcaseGallery.vue';
import { maskPhone } from '@/lib/formatters';
import {
    formatArea,
    formatShowcasePrice,
    offerLabel,
    plural,
    propertyMessage,
    purposeParams,
    purposeWords,
    whatsappUrl,
} from '@/lib/showcase';
import { home, index } from '@/routes/showcase';
import type {
    ShowcaseCard as ShowcaseCardData,
    ShowcaseProperty,
    ShowcasePurpose,
    ShowcaseSite,
} from '@/types';

const props = defineProps<{
    site: ShowcaseSite;
    property: ShowcaseProperty;
    others: ShowcaseCardData[];
    neighborhoods: string[];
}>();

const mainPurpose = computed<ShowcasePurpose>(() =>
    props.property.sale_price !== null ? 'sale' : 'rent',
);
const isOfferedBothWays = computed(
    () => props.property.sale_price !== null && props.property.rent !== null,
);

function listingUrl(neighborhood = '') {
    return index(props.site.slug, {
        query: {
            finalidade: purposeParams[mainPurpose.value],
            bairro: neighborhood || undefined,
        },
    });
}

type Feature = { icon: Component; value: string; label: string };

const features = computed<Feature[]>(() => {
    const property = props.property;
    const list: (Feature | null)[] = [
        property.bedrooms
            ? {
                  icon: BedDouble,
                  value: plural(property.bedrooms, 'quarto', 'quartos'),
                  label: property.suites
                      ? `sendo ${plural(property.suites, 'suíte', 'suítes')}`
                      : 'no total',
              }
            : null,
        property.bathrooms
            ? {
                  icon: Bath,
                  value: plural(property.bathrooms, 'banheiro', 'banheiros'),
                  label: 'contando as suítes',
              }
            : null,
        property.parking_spaces
            ? {
                  icon: CarFront,
                  value: plural(property.parking_spaces, 'vaga', 'vagas'),
                  label: 'de garagem',
              }
            : null,
        property.built_area
            ? {
                  icon: Maximize,
                  value: formatArea(property.built_area),
                  label: 'de área construída',
              }
            : null,
        property.land_area
            ? {
                  icon: LandPlot,
                  value: formatArea(property.land_area),
                  label: 'de terreno',
              }
            : null,
        property.land_front && property.land_depth
            ? {
                  icon: Ruler,
                  value: `${property.land_front.toLocaleString('pt-BR')} × ${property.land_depth.toLocaleString('pt-BR')} m`,
                  label: 'frente × fundo',
              }
            : null,
    ];

    return list.filter((feature): feature is Feature => feature !== null);
});

const chatUrl = computed(() =>
    whatsappUrl(props.site, propertyMessage(props.property)),
);

const periods = [
    { value: 'manha', label: 'Manhã' },
    { value: 'tarde', label: 'Tarde' },
    { value: 'sabado', label: 'Sábado' },
];

const name = ref('');
const phone = ref('');
const period = ref('manha');
const purpose = ref<ShowcasePurpose>(mainPurpose.value);
const sentTo = ref<string | null>(null);

function onPhoneInput(event: Event) {
    phone.value = maskPhone((event.target as HTMLInputElement).value);
}

function onSent() {
    sentTo.value = name.value.trim().split(/\s+/)[0] ?? '';
}

const periodPhrase = computed(
    () =>
        ({ manha: 'pela manhã', tarde: 'pela tarde', sabado: 'no sábado' })[
            period.value
        ],
);

const linkCopied = ref(false);
/** The link shown to copy by hand, when the browser won't copy it. */
const linkToCopy = ref<string | null>(null);

function copyLink() {
    const link = window.location.href;

    navigator.clipboard
        .writeText(link)
        .then(() => (linkCopied.value = true))
        .catch(() => (linkToCopy.value = link));
}

function selectLink(event: FocusEvent) {
    (event.target as HTMLInputElement).select();
}

const visitBox = ref<HTMLElement | null>(null);

function goToVisitForm() {
    visitBox.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <Head :title="property.headline" />

    <div class="sc-wrap">
        <div class="sc-prop-head">
            <nav class="sc-crumbs" aria-label="Você está em">
                <Link :href="home(site.slug)">Início</Link>
                <span aria-hidden="true">›</span>
                <Link :href="listingUrl()">
                    {{ mainPurpose === 'sale' ? 'Comprar' : 'Alugar' }}
                </Link>
                <span aria-hidden="true">›</span>
                <Link :href="listingUrl(property.neighborhood)">
                    {{ property.neighborhood }}
                </Link>
                <span aria-hidden="true">›</span>
                <span>REF {{ property.ref }}</span>
            </nav>

            <div class="sc-prop-title">
                <div>
                    <h1>{{ property.headline }}</h1>
                    <div class="sc-prop-meta">
                        <span class="sc-pill">{{ offerLabel(property) }}</span>
                        <span>
                            <MapPin />
                            {{ property.neighborhood }}, {{ property.city }},
                            {{ property.state }}
                        </span>
                        <span class="sc-num"
                            ><Hash />REF {{ property.ref }}</span
                        >
                    </div>
                </div>
                <button
                    v-if="linkToCopy === null"
                    type="button"
                    class="sc-share"
                    :aria-label="
                        linkCopied ? 'Link copiado' : 'Copiar link do imóvel'
                    "
                    :title="
                        linkCopied ? 'Link copiado' : 'Copiar link do imóvel'
                    "
                    @click="copyLink"
                >
                    <Check v-if="linkCopied" />
                    <Share2 v-else />
                </button>
            </div>
            <input
                v-if="linkToCopy !== null"
                class="sc-input sc-copy-field"
                :value="linkToCopy"
                readonly
                aria-label="Link do imóvel"
                @focus="selectLink"
            />
        </div>

        <ShowcaseGallery :property="property" />

        <div class="sc-prop-body">
            <div>
                <section v-if="features.length > 0" class="sc-panel">
                    <h2>Características</h2>
                    <dl class="sc-features sc-num">
                        <div
                            v-for="feature in features"
                            :key="feature.label"
                            class="sc-feature"
                        >
                            <span class="sc-feature-icon">
                                <component :is="feature.icon" />
                            </span>
                            <div>
                                <dd>{{ feature.value }}</dd>
                                <dt>{{ feature.label }}</dt>
                            </div>
                        </div>
                    </dl>
                </section>

                <section v-if="property.description" class="sc-panel">
                    <h2>Sobre o imóvel</h2>
                    <p class="sc-about">{{ property.description }}</p>
                </section>
            </div>

            <aside class="sc-aside" aria-label="Preço e contato">
                <div class="sc-price-card">
                    <div class="sc-price-top sc-num">
                        <div
                            v-if="property.sale_price !== null"
                            class="sc-price-line"
                        >
                            <small>Venda</small>
                            <strong>{{
                                formatShowcasePrice(property.sale_price)
                            }}</strong>
                        </div>
                        <div
                            v-if="property.rent !== null"
                            class="sc-price-line"
                        >
                            <small>Aluguel</small>
                            <strong>
                                {{ formatShowcasePrice(property.rent) }}
                                <span>/mês</span>
                            </strong>
                        </div>
                        <p class="sc-price-note">
                            O endereço completo é informado no agendamento da
                            visita.
                        </p>
                    </div>

                    <div v-if="chatUrl" class="sc-price-actions">
                        <a
                            class="sc-button sc-button-whatsapp sc-button-block"
                            :href="chatUrl"
                            target="_blank"
                            rel="noopener"
                        >
                            <MessageCircle />
                            Conversar no WhatsApp
                        </a>
                    </div>

                    <div ref="visitBox" class="sc-visit">
                        <h3>Agendar uma visita</h3>

                        <div
                            v-if="sentTo !== null"
                            class="sc-sent"
                            role="status"
                        >
                            <CircleCheck />
                            <div>
                                <strong>
                                    Pedido enviado{{
                                        sentTo ? `, ${sentTo}` : ''
                                    }}.
                                </strong>
                                {{ site.name }} vai chamar você no WhatsApp para
                                combinar a visita, de preferência
                                {{ periodPhrase }}.
                            </div>
                        </div>

                        <Form
                            v-else
                            v-bind="
                                ShowcaseVisitController.store.form([
                                    site.slug,
                                    property.id,
                                ])
                            "
                            v-slot="{ errors, processing }"
                            :options="{ preserveScroll: true }"
                            class="sc-form"
                            @success="onSent"
                        >
                            <div class="sc-field">
                                <label for="visit-name">Seu nome</label>
                                <input
                                    id="visit-name"
                                    v-model="name"
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
                                <label for="visit-phone">WhatsApp</label>
                                <input
                                    id="visit-phone"
                                    :value="phone"
                                    class="sc-input sc-num"
                                    name="phone"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    placeholder="(00) 00000-0000"
                                    maxlength="15"
                                    @input="onPhoneInput"
                                />
                                <span v-if="errors.phone" class="sc-error">{{
                                    errors.phone
                                }}</span>
                            </div>
                            <div v-if="isOfferedBothWays" class="sc-field">
                                <span class="sc-field-label">Quero</span>
                                <div
                                    class="sc-choices"
                                    role="group"
                                    aria-label="Quero"
                                >
                                    <button
                                        v-for="option in [
                                            'sale',
                                            'rent',
                                        ] as const"
                                        :key="option"
                                        type="button"
                                        :aria-pressed="purpose === option"
                                        @click="purpose = option"
                                    >
                                        {{
                                            option === 'sale'
                                                ? 'Comprar'
                                                : 'Alugar'
                                        }}
                                    </button>
                                </div>
                                <span v-if="errors.purpose" class="sc-error">{{
                                    errors.purpose
                                }}</span>
                            </div>
                            <div class="sc-field">
                                <span class="sc-field-label"
                                    >Melhor horário</span
                                >
                                <div
                                    class="sc-choices"
                                    role="group"
                                    aria-label="Melhor horário"
                                >
                                    <button
                                        v-for="option in periods"
                                        :key="option.value"
                                        type="button"
                                        :aria-pressed="period === option.value"
                                        @click="period = option.value"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                            <input
                                type="hidden"
                                name="period"
                                :value="period"
                            />
                            <input
                                type="hidden"
                                name="purpose"
                                :value="purpose"
                            />
                            <div class="sc-honeypot" aria-hidden="true">
                                <label for="visit-website">Site</label>
                                <input
                                    id="visit-website"
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
                                {{ processing ? 'Enviando…' : 'Pedir visita' }}
                            </button>
                        </Form>
                    </div>
                </div>
            </aside>
        </div>

        <section v-if="others.length > 0" style="padding-bottom: 48px">
            <div class="sc-section-head">
                <div>
                    <h2>Imóveis parecidos</h2>
                    <p>
                        No bairro {{ property.neighborhood }} e em bairros
                        próximos
                    </p>
                </div>
                <Link class="sc-link" :href="listingUrl()">Ver todos</Link>
            </div>
            <div class="sc-grid">
                <ShowcaseCard
                    v-for="other in others"
                    :key="other.id"
                    :slug="site.slug"
                    :property="other"
                    :purpose="mainPurpose"
                />
            </div>
        </section>

        <div class="sc-seo">
            <div>
                <h3>Busque outros imóveis</h3>
                <p>
                    <Link :href="listingUrl()">
                        Imóveis {{ purposeWords[mainPurpose] }} em
                        {{ property.city }}
                    </Link>
                    ·
                    <Link :href="listingUrl(property.neighborhood)">
                        Imóveis {{ purposeWords[mainPurpose] }} no bairro
                        {{ property.neighborhood }}
                    </Link>
                </p>
            </div>
            <div v-if="neighborhoods.length > 1">
                <h3>Bairros com imóveis disponíveis</h3>
                <p>
                    <template
                        v-for="(name, position) in neighborhoods"
                        :key="name"
                    >
                        <template v-if="position > 0"> · </template>
                        <Link :href="listingUrl(name)">{{ name }}</Link>
                    </template>
                </p>
            </div>
        </div>
    </div>

    <div class="sc-mobile-bar">
        <div class="sc-num">
            <small>
                {{ property.sale_price !== null ? 'Venda' : 'Aluguel' }} · REF
                {{ property.ref }}
            </small>
            <strong>
                {{
                    formatShowcasePrice(
                        property.sale_price ?? property.rent ?? 0,
                    )
                }}<template v-if="property.sale_price === null">/mês</template>
            </strong>
        </div>
        <a
            v-if="chatUrl"
            class="sc-button sc-button-whatsapp"
            :href="chatUrl"
            target="_blank"
            rel="noopener"
        >
            <MessageCircle />
            WhatsApp
        </a>
        <button v-else type="button" class="sc-button" @click="goToVisitForm">
            Pedir visita
        </button>
    </div>
</template>
