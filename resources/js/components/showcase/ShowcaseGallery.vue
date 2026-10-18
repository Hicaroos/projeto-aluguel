<script setup lang="ts">
import { ChevronLeft, ChevronRight, Images, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { ShowcaseProperty } from '@/types';

const props = defineProps<{
    property: ShowcaseProperty;
}>();

const total = computed(() => props.property.photos.length);

/** The photo shown large on the page; the arrows and thumbnails change it without opening anything. */
const current = ref(0);

function show(index: number) {
    current.value = (index + total.value) % total.value;
}

const thumbs = ref<HTMLElement | null>(null);

watch(current, async (index) => {
    await nextTick();
    thumbs.value?.children[index]?.scrollIntoView({
        block: 'nearest',
        inline: 'nearest',
        behavior: 'smooth',
    });
});

const openIndex = ref<number | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
let touchStart: number | null = null;

function open(index: number) {
    openIndex.value = index;
}

function close() {
    if (openIndex.value !== null) {
        current.value = openIndex.value;
    }

    openIndex.value = null;
}

function step(by: number) {
    if (openIndex.value === null) {
        return;
    }

    openIndex.value = (openIndex.value + by + total.value) % total.value;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close();
    } else if (event.key === 'ArrowLeft') {
        step(-1);
    } else if (event.key === 'ArrowRight') {
        step(1);
    }
}

function onTouchStart(event: TouchEvent) {
    touchStart = event.touches[0]?.clientX ?? null;
}

function onTouchEnd(event: TouchEvent, move: (by: number) => void) {
    const end = event.changedTouches[0]?.clientX;

    if (
        touchStart !== null &&
        end !== undefined &&
        Math.abs(end - touchStart) > 40
    ) {
        move(end < touchStart ? 1 : -1);
    }

    touchStart = null;
}

watch(openIndex, async (index, previous) => {
    if (index !== null && previous === null) {
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        closeButton.value?.focus();
    } else if (index === null) {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="sc-gallery">
        <div v-if="total === 0" class="sc-gallery-main">
            <div class="sc-no-photo">{{ property.title }}</div>
        </div>

        <template v-else>
            <div
                class="sc-gallery-main"
                @touchstart.passive="onTouchStart"
                @touchend="onTouchEnd($event, (by) => show(current + by))"
            >
                <button
                    type="button"
                    class="sc-gallery-photo"
                    :aria-label="`Ampliar foto ${current + 1} de ${total}`"
                    @click="open(current)"
                >
                    <img
                        :src="property.photos[current].url"
                        :alt="`${property.title}, foto ${current + 1}`"
                    />
                </button>
                <template v-if="total > 1">
                    <button
                        type="button"
                        class="sc-gallery-arrow is-prev"
                        aria-label="Foto anterior"
                        @click="show(current - 1)"
                    >
                        <ChevronLeft />
                    </button>
                    <button
                        type="button"
                        class="sc-gallery-arrow is-next"
                        aria-label="Próxima foto"
                        @click="show(current + 1)"
                    >
                        <ChevronRight />
                    </button>
                </template>
                <span class="sc-gallery-count sc-num">
                    <Images />
                    {{ current + 1 }} de {{ total }}
                </span>
            </div>

            <div v-if="total > 1" ref="thumbs" class="sc-thumbs">
                <button
                    v-for="(photo, index) in property.photos"
                    :key="photo.url"
                    type="button"
                    class="sc-thumb"
                    :aria-label="`Ver foto ${index + 1}`"
                    :aria-current="index === current"
                    @click="show(index)"
                >
                    <img :src="photo.thumbnail_url" alt="" loading="lazy" />
                </button>
            </div>
        </template>
    </div>

    <Teleport to="body">
        <div
            v-if="openIndex !== null"
            class="sc sc-lightbox"
            role="dialog"
            aria-modal="true"
            aria-label="Fotos do imóvel"
            style="min-height: 0"
            @touchstart.passive="onTouchStart"
            @touchend="onTouchEnd($event, step)"
        >
            <div class="sc-lightbox-top">
                <span class="sc-num">{{ openIndex + 1 }} / {{ total }}</span>
                <button
                    ref="closeButton"
                    type="button"
                    aria-label="Fechar fotos"
                    @click="close"
                >
                    <X />
                </button>
            </div>
            <div class="sc-lightbox-stage" @click.self="close">
                <button
                    v-if="total > 1"
                    type="button"
                    aria-label="Foto anterior"
                    @click="step(-1)"
                >
                    <ChevronLeft />
                </button>
                <img
                    :src="property.photos[openIndex].url"
                    :alt="`${property.title}, foto ${openIndex + 1}`"
                />
                <button
                    v-if="total > 1"
                    type="button"
                    aria-label="Próxima foto"
                    @click="step(1)"
                >
                    <ChevronRight />
                </button>
            </div>
        </div>
    </Teleport>
</template>
