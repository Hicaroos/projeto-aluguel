<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Mail, MapPin, MessageCircle, Phone, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import '../../css/showcase.css';
import { formatPhone } from '@/lib/formatters';
import { purposeParams, whatsappUrl } from '@/lib/showcase';
import { home, index } from '@/routes/showcase';
import type { ShowcasePurpose, ShowcaseSite } from '@/types';

const page = usePage<{ site: ShowcaseSite }>();
const site = computed(() => page.props.site);

const accent = computed(() => ({
    '--sc-accent': site.value.color.accent,
    '--sc-accent-strong': site.value.color.strong,
    '--sc-accent-wash': site.value.color.wash,
}));

/** The property page keeps its own bar with the price and WhatsApp on phones. */
const hasMobileBar = computed(() => page.component === 'showcase/Show');

const purposes = computed(() =>
    (['rent', 'sale'] as ShowcasePurpose[]).filter(
        (purpose) => site.value.offers[purpose] > 0,
    ),
);

const purposeNames: Record<ShowcasePurpose, string> = {
    sale: 'Comprar',
    rent: 'Alugar',
};

const listingNames: Record<ShowcasePurpose, string> = {
    sale: 'Imóveis à venda',
    rent: 'Imóveis para alugar',
};

function listing(purpose: ShowcasePurpose) {
    return index(site.value.slug, {
        query: { finalidade: purposeParams[purpose] },
    });
}

/** The purpose of the listing being shown, to mark it in the menu. */
const currentPurpose = computed(() => {
    if (page.component !== 'showcase/Index') {
        return null;
    }

    return (page.props as { filters?: { purpose: ShowcasePurpose } }).filters
        ?.purpose;
});

const homeUrl = computed(() => home(site.value.slug).url);
const initial = computed(() => site.value.name.trim().charAt(0).toUpperCase());

const generalWhatsapp = computed(() =>
    whatsappUrl(
        site.value,
        `Olá! Vim pelo site ${site.value.is_agency ? 'da' : 'de'} ${site.value.name} e quero ajuda para encontrar um imóvel.`,
    ),
);

const isMenuOpen = ref(false);

function closeMenuOnEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        isMenuOpen.value = false;
    }
}

let stopListening: (() => void) | undefined;

onMounted(() => {
    document.addEventListener('keydown', closeMenuOnEscape);
    stopListening = router.on('navigate', () => (isMenuOpen.value = false));
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', closeMenuOnEscape);
    stopListening?.();
});

const year = new Date().getFullYear();
</script>

<template>
    <div class="sc" :class="{ 'has-mobile-bar': hasMobileBar }" :style="accent">
        <header class="sc-header">
            <div class="sc-wrap">
                <Link class="sc-brand" :href="homeUrl">
                    <img v-if="site.logo_url" :src="site.logo_url" alt="" />
                    <span v-else class="sc-brand-mark" aria-hidden="true">
                        {{ initial }}
                    </span>
                    <span class="sc-brand-text">
                        <span class="sc-brand-name">{{ site.name }}</span>
                        <span class="sc-brand-sub">
                            {{ site.creci ? `CRECI ${site.creci}` : 'Imóveis' }}
                        </span>
                    </span>
                </Link>

                <nav class="sc-nav" aria-label="Principal">
                    <Link
                        v-for="purpose in purposes"
                        :key="purpose"
                        :href="listing(purpose)"
                        :aria-current="
                            currentPurpose === purpose ? 'page' : undefined
                        "
                    >
                        {{ purposeNames[purpose] }}
                    </Link>
                    <template v-if="site.is_agency">
                        <Link :href="`${homeUrl}#anunciar`">
                            Anuncie seu imóvel
                        </Link>
                        <Link :href="`${homeUrl}#sobre`">Quem somos</Link>
                    </template>
                </nav>

                <div class="sc-header-contact">
                    <a
                        v-if="generalWhatsapp"
                        class="sc-button"
                        :href="generalWhatsapp"
                        target="_blank"
                        rel="noopener"
                    >
                        <MessageCircle />
                        <span class="sc-num">{{
                            formatPhone(site.whatsapp ?? '')
                        }}</span>
                    </a>
                    <span v-else-if="site.phone" class="sc-muted sc-num">
                        {{ formatPhone(site.phone) }}
                    </span>
                </div>

                <button
                    type="button"
                    class="sc-button sc-button-outline sc-menu-button"
                    :aria-expanded="isMenuOpen"
                    @click="isMenuOpen = true"
                >
                    Menu
                </button>
            </div>
        </header>

        <main class="sc-main">
            <slot />
        </main>

        <footer class="sc-footer">
            <div class="sc-wrap">
                <div class="sc-footer-grid">
                    <div>
                        <Link class="sc-brand" :href="homeUrl">
                            <span class="sc-brand-mark" aria-hidden="true">
                                {{ initial }}
                            </span>
                            <span class="sc-brand-text">
                                <span class="sc-brand-name">{{
                                    site.name
                                }}</span>
                                <span class="sc-brand-sub">Imóveis</span>
                            </span>
                        </Link>
                        <p class="sc-footer-about">{{ site.about }}</p>
                    </div>
                    <div>
                        <h3>Atendimento</h3>
                        <ul>
                            <li v-if="site.phone" class="sc-num">
                                <Phone />
                                {{ formatPhone(site.phone) }}
                            </li>
                            <li v-if="site.whatsapp" class="sc-num">
                                <MessageCircle />
                                <a
                                    :href="generalWhatsapp ?? undefined"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    WhatsApp {{ formatPhone(site.whatsapp) }}
                                </a>
                            </li>
                            <li v-if="site.email">
                                <Mail />
                                {{ site.email }}
                            </li>
                        </ul>
                    </div>
                    <div v-if="site.branches.length > 0">
                        <h3>Unidades</h3>
                        <ul>
                            <li
                                v-for="branch in site.branches"
                                :key="branch.name"
                            >
                                <MapPin />
                                <span>
                                    {{ branch.name
                                    }}<template v-if="branch.city">
                                        · {{ branch.city }}/{{ branch.state }}
                                    </template>
                                    <template v-if="branch.phone">
                                        <br />
                                        <span class="sc-num">{{
                                            formatPhone(branch.phone)
                                        }}</span>
                                    </template>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div v-else-if="site.office">
                        <h3>Escritório</h3>
                        <ul>
                            <li>
                                <MapPin />
                                {{ site.office }}
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3>Navegue</h3>
                        <ul>
                            <li v-for="purpose in purposes" :key="purpose">
                                <Link :href="listing(purpose)">
                                    {{ listingNames[purpose] }}
                                </Link>
                            </li>
                            <template v-if="site.is_agency">
                                <li>
                                    <Link :href="`${homeUrl}#anunciar`">
                                        Anuncie seu imóvel
                                    </Link>
                                </li>
                                <li>
                                    <Link :href="`${homeUrl}#sobre`">
                                        Quem somos
                                    </Link>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
                <div class="sc-footer-bottom">
                    <span>
                        © {{ year }} {{ site.name
                        }}<template v-if="site.creci">
                            · CRECI {{ site.creci }}</template
                        >
                    </span>
                    <span
                        >Preços e disponibilidade sujeitos a confirmação.</span
                    >
                </div>
            </div>
        </footer>

        <a
            v-if="generalWhatsapp"
            class="sc-wa-float"
            :href="generalWhatsapp"
            target="_blank"
            rel="noopener"
            aria-label="Conversar no WhatsApp"
        >
            <MessageCircle />
        </a>

        <div v-if="isMenuOpen" class="sc-menu" @click.self="isMenuOpen = false">
            <div
                class="sc-menu-panel"
                role="dialog"
                aria-modal="true"
                aria-label="Menu"
            >
                <div class="sc-menu-head">
                    <span class="sc-brand-name">{{ site.name }}</span>
                    <button
                        type="button"
                        class="sc-close"
                        aria-label="Fechar menu"
                        @click="isMenuOpen = false"
                    >
                        <X :size="20" />
                    </button>
                </div>
                <nav aria-label="Menu">
                    <Link :href="homeUrl">Início</Link>
                    <Link
                        v-for="purpose in purposes"
                        :key="purpose"
                        :href="listing(purpose)"
                    >
                        {{ listingNames[purpose] }}
                    </Link>
                    <template v-if="site.is_agency">
                        <Link :href="`${homeUrl}#anunciar`">
                            Anuncie seu imóvel
                        </Link>
                        <Link :href="`${homeUrl}#sobre`">Quem somos</Link>
                    </template>
                </nav>
                <a
                    v-if="generalWhatsapp"
                    class="sc-button sc-button-whatsapp sc-button-block"
                    :href="generalWhatsapp"
                    target="_blank"
                    rel="noopener"
                >
                    <MessageCircle />
                    Falar no WhatsApp
                </a>
                <p v-if="site.phone" class="sc-muted sc-num">
                    {{ formatPhone(site.phone) }}
                </p>
            </div>
        </div>
    </div>
</template>
