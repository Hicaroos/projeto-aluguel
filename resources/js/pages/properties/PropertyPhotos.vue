<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    ImagePlus,
    Images,
    MoreHorizontal,
    Share2,
    Star,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PropertyPhotoController from '@/actions/App/Http/Controllers/PropertyPhotoController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import {
    MAX_SHARED_FILES,
    usePhotoSharing,
} from '@/composables/usePhotoSharing';
import { formatCurrency } from '@/lib/currency';
import { resizeImage } from '@/lib/image-resize';
import { propertyTypeLabels } from '@/lib/property-labels';
import type { Property, PropertyPhoto } from '@/types';

const MAX_PHOTOS = 20;

/** Longest side, in pixels, of the uploaded photo and of its thumbnail. */
const PHOTO_SIZE = 1920;
const THUMBNAIL_SIZE = 480;

const props = defineProps<{
    property: Property;
}>();

const photos = computed(() => props.property.photos ?? []);
const remaining = computed(() => MAX_PHOTOS - photos.value.length);

const {
    supportsSharing,
    isPreparing: isPreparingShare,
    share,
    download,
} = usePhotoSharing();

/** Short description sent along with the photos, e.g. to a client on WhatsApp. */
const shareText = computed(() => {
    const property = props.property;
    const complement = property.complement ? ` — ${property.complement}` : '';

    return [
        `${propertyTypeLabels[property.type]} · ${property.street}, ${property.number}${complement}`,
        `${property.neighborhood}, ${property.city}/${property.state}`,
        `Aluguel: ${formatCurrency(property.rent_amount)}/mês`,
    ].join('\n');
});

/** The photos split into groups the browser can share at once, e.g. "Fotos 11 a 15". */
const shareGroups = computed(() => {
    const groups: { label: string; photos: PropertyPhoto[] }[] = [];

    for (
        let start = 0;
        start < photos.value.length;
        start += MAX_SHARED_FILES
    ) {
        const group = photos.value.slice(start, start + MAX_SHARED_FILES);
        const end = start + group.length;

        groups.push({
            label:
                group.length === 1
                    ? `Foto ${end}`
                    : `Fotos ${start + 1} a ${end}`,
            photos: group,
        });
    }

    return groups;
});

const fileName = computed(() => `imovel-${props.property.id}`);

function sharePhotos(selection: PropertyPhoto[]) {
    share(selection, {
        fileName: fileName.value,
        title: `${props.property.street}, ${props.property.number}`,
        text: shareText.value,
    });
}

function downloadPhotos(selection: PropertyPhoto[]) {
    download(selection, fileName.value);
}

const fileInput = ref<HTMLInputElement | null>(null);
const upload = ref<{ current: number; total: number } | null>(null);

function chooseFiles() {
    fileInput.value?.click();
}

/**
 * Upload the chosen photos one at a time, so each request stays small and the
 * gallery fills in as they arrive.
 */
async function uploadFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    if (files.length === 0) {
        return;
    }

    const accepted = files.slice(0, Math.max(remaining.value, 0));

    if (accepted.length < files.length) {
        toast.warning(
            `Cada imóvel pode ter no máximo ${MAX_PHOTOS} fotos. ${files.length - accepted.length} não foram enviadas.`,
        );
    }

    let uploaded = 0;
    upload.value = { current: 0, total: accepted.length };

    for (const file of accepted) {
        upload.value.current++;

        try {
            const [photo, thumbnail] = await Promise.all([
                resizeImage(file, PHOTO_SIZE),
                resizeImage(file, THUMBNAIL_SIZE, 0.75),
            ]);

            if (await sendPhoto(photo, thumbnail)) {
                uploaded++;
            }
        } catch {
            toast.error(`Não foi possível ler a imagem ${file.name}.`);
        }
    }

    upload.value = null;

    if (uploaded > 0) {
        toast.success(
            uploaded === 1
                ? 'Foto adicionada.'
                : `${uploaded} fotos adicionadas.`,
        );
    }
}

function sendPhoto(photo: File, thumbnail: File): Promise<boolean> {
    return new Promise((resolve) => {
        let succeeded = false;

        router.post(
            PropertyPhotoController.store(props.property).url,
            { photo, thumbnail },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => (succeeded = true),
                onError: (errors) =>
                    toast.error(
                        errors.photo ??
                            errors.thumbnail ??
                            'Não foi possível enviar a foto.',
                    ),
                onFinish: () => resolve(succeeded),
            },
        );
    });
}

const processingPhotoId = ref<number | null>(null);

function makeCover(photo: PropertyPhoto) {
    processingPhotoId.value = photo.id;

    router.patch(
        PropertyPhotoController.makeCover(photo).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            // The new cover moves to the front: keep showing it in the viewer.
            onSuccess: () => {
                if (viewingIndex.value !== null) {
                    viewingIndex.value = 0;
                }
            },
            onFinish: () => (processingPhotoId.value = null),
        },
    );
}

const photoToDelete = ref<PropertyPhoto | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!photoToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(PropertyPhotoController.destroy(photoToDelete.value).url, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isDeleting.value = false;
            photoToDelete.value = null;

            if (
                viewingIndex.value !== null &&
                viewingIndex.value >= photos.value.length
            ) {
                viewingIndex.value = photos.value.length
                    ? photos.value.length - 1
                    : null;
            }
        },
    });
}

const viewingIndex = ref<number | null>(null);
const viewingPhoto = computed(() =>
    viewingIndex.value === null ? null : photos.value[viewingIndex.value],
);

function showPhoto(offset: number) {
    if (viewingIndex.value === null || photos.value.length === 0) {
        return;
    }

    viewingIndex.value =
        (viewingIndex.value + offset + photos.value.length) %
        photos.value.length;
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3
                class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                <Images class="size-3.5" />
                Fotos
                <span v-if="photos.length" class="font-normal tabular-nums"
                    >{{ photos.length }}/{{ MAX_PHOTOS }}</span
                >
            </h3>
            <div v-if="photos.length > 0" class="flex gap-2">
                <DropdownMenu v-if="supportsSharing">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isPreparingShare"
                        >
                            <Spinner v-if="isPreparingShare" />
                            <Share2 v-else class="size-4" />
                            Compartilhar
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <template v-if="shareGroups.length > 1">
                            <DropdownMenuLabel
                                class="text-xs font-normal text-muted-foreground"
                            >
                                Até {{ MAX_SHARED_FILES }} fotos por vez
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                v-for="group in shareGroups"
                                :key="group.label"
                                @click="sharePhotos(group.photos)"
                            >
                                <Share2 class="size-4" />
                                {{ group.label }}
                            </DropdownMenuItem>
                        </template>
                        <DropdownMenuItem v-else @click="sharePhotos(photos)">
                            <Share2 class="size-4" />
                            {{
                                photos.length === 1
                                    ? 'Compartilhar foto'
                                    : 'Compartilhar fotos'
                            }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="downloadPhotos(photos)">
                            <Download class="size-4" />
                            {{
                                photos.length === 1
                                    ? 'Baixar foto'
                                    : 'Baixar todas as fotos'
                            }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    :disabled="isPreparingShare"
                    @click="downloadPhotos(photos)"
                >
                    <Spinner v-if="isPreparingShare" />
                    <Download v-else class="size-4" />
                    Baixar fotos
                </Button>
                <Button
                    v-if="remaining > 0"
                    variant="outline"
                    size="sm"
                    :disabled="!!upload"
                    @click="chooseFiles"
                >
                    <ImagePlus class="size-4" />
                    Adicionar fotos
                </Button>
            </div>
        </div>

        <input
            ref="fileInput"
            type="file"
            class="hidden"
            accept="image/jpeg,image/png,image/webp"
            multiple
            @change="uploadFiles"
        />

        <button
            v-if="photos.length === 0 && !upload"
            type="button"
            class="flex w-full flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:bg-muted/40"
            @click="chooseFiles"
        >
            <ImagePlus class="size-6 text-primary" />
            <span class="font-medium text-foreground">Adicionar fotos</span>
            <span>JPG, PNG ou WebP · até {{ MAX_PHOTOS }} fotos</span>
        </button>

        <div v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            <div
                v-for="(photo, index) in photos"
                :key="photo.id"
                class="group relative aspect-[4/3] overflow-hidden rounded-lg border bg-muted"
            >
                <button
                    type="button"
                    class="block size-full"
                    @click="viewingIndex = index"
                >
                    <img
                        :src="photo.thumbnail_url"
                        :alt="`Foto ${index + 1} do imóvel`"
                        loading="lazy"
                        class="size-full object-cover transition-transform group-hover:scale-105"
                    />
                </button>
                <span
                    v-if="index === 0"
                    class="pointer-events-none absolute bottom-1 left-1 flex items-center gap-1 rounded-md bg-background/90 px-1.5 py-0.5 text-[11px] font-medium shadow-xs"
                >
                    <Star class="size-3 fill-current text-attention-strong" />
                    Capa
                </span>
                <div
                    v-if="processingPhotoId === photo.id"
                    class="absolute inset-0 flex items-center justify-center bg-background/60"
                >
                    <Spinner />
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="secondary"
                            size="icon-sm"
                            class="absolute top-1 right-1 size-7 bg-background/90 shadow-xs hover:bg-background"
                        >
                            <MoreHorizontal class="size-4" />
                            <span class="sr-only">Ações da foto</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem
                            v-if="index > 0"
                            @click="makeCover(photo)"
                        >
                            <Star class="size-4" />
                            Definir como capa
                        </DropdownMenuItem>
                        <DropdownMenuSeparator v-if="index > 0" />
                        <DropdownMenuItem
                            variant="destructive"
                            @click="photoToDelete = photo"
                        >
                            <Trash2 class="size-4" />
                            Remover
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div
                v-if="upload"
                class="flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-lg border border-dashed text-xs text-muted-foreground"
            >
                <Spinner />
                Enviando {{ upload.current }} de {{ upload.total }}
            </div>
        </div>

        <Dialog
            :open="!!viewingPhoto"
            @update:open="(open) => !open && (viewingIndex = null)"
        >
            <DialogContent
                v-if="viewingPhoto && viewingIndex !== null"
                class="gap-4 p-3 sm:max-w-4xl"
                @keydown.left="showPhoto(-1)"
                @keydown.right="showPhoto(1)"
            >
                <DialogTitle class="sr-only">Fotos do imóvel</DialogTitle>
                <DialogDescription class="sr-only">
                    Foto {{ viewingIndex + 1 }} de {{ photos.length }}
                </DialogDescription>
                <div
                    class="relative flex items-center justify-center overflow-hidden rounded-lg bg-muted"
                >
                    <img
                        :src="viewingPhoto.url"
                        :alt="`Foto ${viewingIndex + 1} do imóvel`"
                        class="max-h-[75dvh] w-full object-contain"
                    />
                    <template v-if="photos.length > 1">
                        <Button
                            variant="secondary"
                            size="icon"
                            class="absolute left-2 rounded-full bg-background/90 shadow-xs"
                            @click="showPhoto(-1)"
                        >
                            <ChevronLeft class="size-5" />
                            <span class="sr-only">Foto anterior</span>
                        </Button>
                        <Button
                            variant="secondary"
                            size="icon"
                            class="absolute right-2 rounded-full bg-background/90 shadow-xs"
                            @click="showPhoto(1)"
                        >
                            <ChevronRight class="size-5" />
                            <span class="sr-only">Próxima foto</span>
                        </Button>
                    </template>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-2 px-1 pb-1"
                >
                    <p class="text-sm text-muted-foreground tabular-nums">
                        {{ viewingIndex + 1 }} de {{ photos.length }}
                        <span v-if="viewingIndex === 0"> · Capa</span>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="supportsSharing"
                            variant="outline"
                            size="sm"
                            :disabled="isPreparingShare"
                            @click="sharePhotos([viewingPhoto])"
                        >
                            <Spinner v-if="isPreparingShare" />
                            <Share2 v-else class="size-4" />
                            Compartilhar
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isPreparingShare"
                            @click="downloadPhotos([viewingPhoto])"
                        >
                            <Download class="size-4" />
                            Baixar
                        </Button>
                        <Button
                            v-if="viewingIndex > 0"
                            variant="outline"
                            size="sm"
                            :disabled="processingPhotoId === viewingPhoto.id"
                            @click="makeCover(viewingPhoto)"
                        >
                            <Star class="size-4" />
                            Definir como capa
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="photoToDelete = viewingPhoto"
                        >
                            <Trash2 class="size-4" />
                            Remover
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteDialog
            :open="!!photoToDelete"
            title="Remover foto?"
            :processing="isDeleting"
            @close="photoToDelete = null"
            @confirm="confirmDelete"
        >
            A foto será apagada definitivamente. Esta ação não pode ser
            desfeita.
        </ConfirmDeleteDialog>
    </div>
</template>
