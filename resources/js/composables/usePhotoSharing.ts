import { ref } from 'vue';
import { toast } from 'vue-sonner';

type SharedPhoto = { id: number; url: string };

/**
 * Most files Chrome and Edge accept in a single share: above it they refuse the share
 * with the same error as a lost tap, so larger selections must be shared in groups.
 */
export const MAX_SHARED_FILES = 10;

type ShareOptions = {
    /** Base name of the shared files, numbered from 1. */
    fileName: string;
    title: string;
    text: string;
};

/**
 * Determine whether the browser can hand files to the system share sheet
 * (phones, and Chrome/Edge on Windows).
 */
function canShareFiles(files: File[]): boolean {
    return (
        typeof navigator !== 'undefined' &&
        typeof navigator.canShare === 'function' &&
        navigator.canShare({ files })
    );
}

/**
 * Share photos through the system share sheet (WhatsApp, e-mail…), falling back to
 * downloading them where the browser cannot share files.
 */
export function usePhotoSharing() {
    const supportsSharing = canShareFiles([
        new File([''], 'foto.jpg', { type: 'image/jpeg' }),
    ]);
    const isPreparing = ref(false);

    /**
     * Files already downloaded for the last share. Some browsers (Safari) only open the
     * share sheet right after a tap: when downloading the photos takes that moment away,
     * the next tap shares these at once.
     */
    let prepared: { key: string; files: File[] } | null = null;

    async function filesFor(
        photos: SharedPhoto[],
        fileName: string,
    ): Promise<File[]> {
        const key = photos.map((photo) => photo.id).join(',');

        if (prepared?.key === key) {
            return prepared.files;
        }

        const files = await Promise.all(
            photos.map(async (photo, index) => {
                const response = await fetch(photo.url, {
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`Photo ${photo.id} could not be loaded.`);
                }

                const blob = await response.blob();
                const suffix = photos.length > 1 ? `-${index + 1}` : '';

                return new File([blob], `${fileName}${suffix}.jpg`, {
                    type: blob.type || 'image/jpeg',
                });
            }),
        );

        prepared = { key, files };

        return files;
    }

    /**
     * Save the files one after another: Chrome drops downloads when a page starts more
     * than 10 at the same moment.
     */
    async function saveFiles(files: File[]) {
        isPreparing.value = true;

        try {
            for (const [index, file] of files.entries()) {
                if (index > 0) {
                    await new Promise((resolve) => setTimeout(resolve, 300));
                }

                const url = URL.createObjectURL(file);
                const link = document.createElement('a');
                link.href = url;
                link.download = file.name;
                link.click();
                setTimeout(() => URL.revokeObjectURL(url), 10000);
            }
        } finally {
            isPreparing.value = false;
        }

        toast.success(
            files.length === 1
                ? 'Foto baixada.'
                : `${files.length} fotos baixadas.`,
        );
    }

    /**
     * Download the photos in full size, with no limit on how many.
     */
    async function download(photos: SharedPhoto[], fileName: string) {
        const files = await load(photos, fileName);

        if (files) {
            await saveFiles(files);
        }
    }

    async function share(photos: SharedPhoto[], options: ShareOptions) {
        const files = await load(photos, options.fileName);

        if (!files) {
            return;
        }

        if (!canShareFiles(files)) {
            await saveFiles(files);

            return;
        }

        try {
            await navigator.share({
                files,
                title: options.title,
                text: options.text,
            });
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            if (
                error instanceof DOMException &&
                error.name === 'NotAllowedError'
            ) {
                toast.info('Fotos prontas. Toque em compartilhar novamente.');

                return;
            }

            toast.error('Não foi possível compartilhar as fotos.');
        }
    }

    /**
     * Load the photos as files, telling the user when they could not be loaded.
     */
    async function load(
        photos: SharedPhoto[],
        fileName: string,
    ): Promise<File[] | null> {
        if (photos.length === 0 || isPreparing.value) {
            return null;
        }

        isPreparing.value = true;

        try {
            return await filesFor(photos, fileName);
        } catch {
            toast.error('Não foi possível carregar as fotos. Tente novamente.');

            return null;
        } finally {
            isPreparing.value = false;
        }
    }

    return { supportsSharing, isPreparing, share, download };
}
