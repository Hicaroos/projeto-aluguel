import { ref } from 'vue';
import { toast } from 'vue-sonner';

type SharedFile = { id: number; url: string };

/**
 * Most files Chrome and Edge accept in a single share: above it they refuse the share
 * with the same error as a lost tap, so larger selections must be shared in groups.
 */
export const MAX_SHARED_FILES = 10;

type SharingOptions = {
    /** The kind of file shared, used to tell whether the browser can share it. */
    mimeType: string;
    /** Confirmation shown after downloading, e.g. "3 fotos baixadas." */
    downloadedMessage: (count: number) => string;
};

type ShareOptions = {
    /** Base name of the shared files, numbered from 1 when there are several. */
    fileName: string;
    title: string;
    text: string;
};

const extensions: Record<string, string> = {
    'application/pdf': 'pdf',
    'image/png': 'png',
    'image/webp': 'webp',
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
 * Share files such as photos or receipts through the system share sheet (WhatsApp,
 * e-mail…), falling back to downloading them where the browser cannot share files.
 */
export function useFileSharing({
    mimeType,
    downloadedMessage,
}: SharingOptions) {
    const supportsSharing = canShareFiles([
        new File([''], `arquivo.${extensions[mimeType] ?? 'jpg'}`, {
            type: mimeType,
        }),
    ]);
    const isPreparing = ref(false);

    /**
     * Files already downloaded for the last share. Some browsers (Safari) only open the
     * share sheet right after a tap: when downloading the files takes that moment away,
     * the next tap shares these at once.
     */
    let prepared: { key: string; files: File[] } | null = null;

    async function filesFor(
        sharedFiles: SharedFile[],
        fileName: string,
    ): Promise<File[]> {
        const key = sharedFiles.map((file) => file.id).join(',');

        if (prepared?.key === key) {
            return prepared.files;
        }

        const files = await Promise.all(
            sharedFiles.map(async (file, index) => {
                const response = await fetch(file.url, {
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`File ${file.id} could not be loaded.`);
                }

                const blob = await response.blob();
                const type = blob.type || mimeType;
                const suffix = sharedFiles.length > 1 ? `-${index + 1}` : '';

                return new File(
                    [blob],
                    `${fileName}${suffix}.${extensions[type] ?? 'jpg'}`,
                    { type },
                );
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

        toast.success(downloadedMessage(files.length));
    }

    /**
     * Download the files, with no limit on how many.
     */
    async function download(sharedFiles: SharedFile[], fileName: string) {
        const files = await load(sharedFiles, fileName);

        if (files) {
            await saveFiles(files);
        }
    }

    async function share(sharedFiles: SharedFile[], options: ShareOptions) {
        const files = await load(sharedFiles, options.fileName);

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
                toast.info('Pronto. Toque em compartilhar novamente.');

                return;
            }

            toast.error('Não foi possível compartilhar.');
        }
    }

    /**
     * Load the files, telling the user when they could not be loaded.
     */
    async function load(
        sharedFiles: SharedFile[],
        fileName: string,
    ): Promise<File[] | null> {
        if (sharedFiles.length === 0 || isPreparing.value) {
            return null;
        }

        isPreparing.value = true;

        try {
            return await filesFor(sharedFiles, fileName);
        } catch {
            toast.error(
                'Não foi possível carregar os arquivos. Tente novamente.',
            );

            return null;
        } finally {
            isPreparing.value = false;
        }
    }

    return { supportsSharing, isPreparing, share, download };
}
