<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Download,
    ExternalLink,
    FileImage,
    FileText,
    FolderOpen,
    MoreHorizontal,
    Paperclip,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import LeaseDocumentController from '@/actions/App/Http/Controllers/LeaseDocumentController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatFileSize } from '@/lib/formatters';
import { resizeImage } from '@/lib/image-resize';
import { leaseDocumentTypeLabels } from '@/lib/lease-labels';
import type { Lease, LeaseDocument, LeaseDocumentType } from '@/types';

const MAX_DOCUMENTS = 30;
const MAX_FILE_SIZE = 10 * 1024 * 1024;

/** Longest side, in pixels, of photographed pages: enough to keep the text legible. */
const PAGE_IMAGE_SIZE = 2400;

const props = defineProps<{
    lease: Lease;
}>();

const documents = computed(() => props.lease.documents ?? []);
const remaining = computed(() => MAX_DOCUMENTS - documents.value.length);

const isUploadDialogOpen = ref(false);
const documentType = ref<LeaseDocumentType>('signed_contract');
const chosenFiles = ref<File[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);
const upload = ref<{ current: number; total: number } | null>(null);

function openUploadDialog() {
    documentType.value = documents.value.some(
        (document) => document.type === 'signed_contract',
    )
        ? 'other'
        : 'signed_contract';
    chosenFiles.value = [];
    isUploadDialogOpen.value = true;
}

function addFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    const tooLarge = files.filter(
        (file) => file.type === 'application/pdf' && file.size > MAX_FILE_SIZE,
    );

    if (tooLarge.length > 0) {
        toast.error(
            `${tooLarge.map((file) => file.name).join(', ')}: o PDF passa de 10 MB.`,
        );
    }

    chosenFiles.value = [
        ...chosenFiles.value,
        ...files.filter((file) => !tooLarge.includes(file)),
    ].slice(0, Math.max(remaining.value, 0));
}

function removeChosenFile(index: number) {
    chosenFiles.value = chosenFiles.value.filter(
        (_, position) => position !== index,
    );
}

/** Shrink photographed pages; PDFs go as they are. */
async function prepareFile(file: File): Promise<File> {
    return file.type.startsWith('image/')
        ? resizeImage(file, PAGE_IMAGE_SIZE, 0.85)
        : file;
}

/**
 * Upload the chosen files one at a time, each as a document of the chosen type.
 */
async function uploadFiles() {
    const files = chosenFiles.value;

    if (files.length === 0 || upload.value) {
        return;
    }

    let uploaded = 0;
    upload.value = { current: 0, total: files.length };

    for (const file of files) {
        upload.value.current++;

        try {
            if (await sendDocument(await prepareFile(file))) {
                uploaded++;
            }
        } catch {
            toast.error(`Não foi possível ler o arquivo ${file.name}.`);
        }
    }

    upload.value = null;

    if (uploaded > 0) {
        isUploadDialogOpen.value = false;
        toast.success(
            uploaded === 1
                ? 'Documento anexado.'
                : `${uploaded} documentos anexados.`,
        );
    }
}

function sendDocument(file: File): Promise<boolean> {
    return new Promise((resolve) => {
        let succeeded = false;

        router.post(
            LeaseDocumentController.store(props.lease).url,
            { type: documentType.value, file },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => (succeeded = true),
                onError: (errors) =>
                    toast.error(
                        `${file.name}: ${errors.file ?? errors.type ?? 'não foi possível enviar o arquivo.'}`,
                    ),
                onFinish: () => resolve(succeeded),
            },
        );
    });
}

function isImage(document: LeaseDocument): boolean {
    return document.mime_type.startsWith('image/');
}

const documentToDelete = ref<LeaseDocument | null>(null);
const isDeleting = ref(false);

function confirmDelete() {
    if (!documentToDelete.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(LeaseDocumentController.destroy(documentToDelete.value).url, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isDeleting.value = false;
            documentToDelete.value = null;
        },
    });
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3
                class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                <FolderOpen class="size-3.5" />
                Documentos
            </h3>
            <Button
                v-if="documents.length > 0 && remaining > 0"
                variant="outline"
                size="sm"
                @click="openUploadDialog"
            >
                <Paperclip class="size-4" />
                Anexar
            </Button>
        </div>

        <button
            v-if="documents.length === 0"
            type="button"
            class="flex w-full flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:bg-muted/40"
            @click="openUploadDialog"
        >
            <Paperclip class="size-6 text-primary" />
            <span class="font-medium text-foreground">Anexar documentos</span>
            <span>Contrato assinado, vistorias, aditivos… · PDF ou imagem</span>
        </button>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="document in documents"
                :key="document.id"
                class="flex items-center gap-3 p-3"
            >
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <component
                        :is="isImage(document) ? FileImage : FileText"
                        class="size-4"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <a
                        :href="document.url"
                        target="_blank"
                        class="block truncate text-sm font-medium hover:underline"
                        :title="document.name"
                    >
                        {{ document.name }}
                    </a>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ leaseDocumentTypeLabels[document.type] }} ·
                        {{ formatFileSize(document.size) }} ·
                        {{ formatDate(document.created_at) }}
                    </p>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon-sm">
                            <MoreHorizontal class="size-4" />
                            <span class="sr-only">Ações do documento</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem as-child>
                            <a :href="document.url" target="_blank">
                                <ExternalLink class="size-4" />
                                Abrir
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <a :href="document.download_url">
                                <Download class="size-4" />
                                Baixar
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            @click="documentToDelete = document"
                        >
                            <Trash2 class="size-4" />
                            Remover
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
        </ul>

        <Dialog
            :open="isUploadDialogOpen"
            @update:open="(open) => !upload && (isUploadDialogOpen = open)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Anexar documentos</DialogTitle>
                    <DialogDescription>
                        PDF ou fotos das páginas (JPG, PNG ou WebP), até 10 MB
                        cada.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-2">
                    <Label for="document_type">Tipo do documento</Label>
                    <Select v-model="documentType" :disabled="!!upload">
                        <SelectTrigger id="document_type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in leaseDocumentTypeLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid grid-cols-1 gap-2">
                    <Label>Arquivos</Label>
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        accept="application/pdf,image/jpeg,image/png,image/webp"
                        multiple
                        @change="addFiles"
                    />
                    <ul
                        v-if="chosenFiles.length > 0"
                        class="divide-y rounded-lg border text-sm"
                    >
                        <li
                            v-for="(file, index) in chosenFiles"
                            :key="`${file.name}-${index}`"
                            class="flex items-center gap-2 py-2 pr-1 pl-3"
                        >
                            <component
                                :is="
                                    file.type.startsWith('image/')
                                        ? FileImage
                                        : FileText
                                "
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="min-w-0 flex-1 truncate">{{
                                file.name
                            }}</span>
                            <span
                                class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                >{{ formatFileSize(file.size) }}</span
                            >
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :disabled="!!upload"
                                @click="removeChosenFile(index)"
                            >
                                <X class="size-4" />
                                <span class="sr-only">Tirar arquivo</span>
                            </Button>
                        </li>
                    </ul>
                    <Button
                        variant="outline"
                        class="w-full border-dashed"
                        :disabled="!!upload || chosenFiles.length >= remaining"
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-4" />
                        {{
                            chosenFiles.length > 0
                                ? 'Escolher mais arquivos'
                                : 'Escolher arquivos'
                        }}
                    </Button>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline" :disabled="!!upload"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button
                        :disabled="chosenFiles.length === 0 || !!upload"
                        @click="uploadFiles"
                    >
                        <Spinner v-if="upload" />
                        <Upload v-else class="size-4" />
                        {{
                            upload
                                ? `Enviando ${upload.current} de ${upload.total}`
                                : 'Enviar'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteDialog
            :open="!!documentToDelete"
            title="Remover documento?"
            :processing="isDeleting"
            @close="documentToDelete = null"
            @confirm="confirmDelete"
        >
            O arquivo
            <span class="font-medium break-all text-foreground">{{
                documentToDelete?.name
            }}</span>
            será apagado definitivamente. Esta ação não pode ser desfeita.
        </ConfirmDeleteDialog>
    </div>
</template>
