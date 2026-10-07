<script setup lang="ts">
import {
    Bold,
    Heading1,
    Heading2,
    Italic,
    List,
    ListOrdered,
    Minus,
    Redo2,
    SeparatorHorizontal,
    Underline,
    Undo2,
} from '@lucide/vue';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import { onBeforeUnmount } from 'vue';
import { PageBreakNode } from '@/components/contract-editor/PageBreakNode';
import { VariableNode } from '@/components/contract-editor/VariableNode';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

const props = defineProps<{
    /** Labels shown on each variable tag, keyed by variable. */
    labels: Record<string, string>;
}>();

const html = defineModel<string>({ required: true });

const editor = useEditor({
    content: html.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
            code: false,
            codeBlock: false,
            link: false,
        }),
        VariableNode.configure({ labels: props.labels }),
        PageBreakNode,
    ],
    editorProps: {
        attributes: {
            class: 'contract-editor-content',
            'aria-label': 'Texto do contrato',
        },
    },
    onUpdate: ({ editor }) => {
        html.value = editor.getHTML();
    },
});

onBeforeUnmount(() => editor.value?.destroy());

type ToolbarAction = {
    label: string;
    icon: Component;
    isActive?: () => boolean;
    run: () => void;
};

const toolbarGroups: ToolbarAction[][] = [
    [
        {
            label: 'Negrito',
            icon: Bold,
            isActive: () => !!editor.value?.isActive('bold'),
            run: () => editor.value?.chain().focus().toggleBold().run(),
        },
        {
            label: 'Itálico',
            icon: Italic,
            isActive: () => !!editor.value?.isActive('italic'),
            run: () => editor.value?.chain().focus().toggleItalic().run(),
        },
        {
            label: 'Sublinhado',
            icon: Underline,
            isActive: () => !!editor.value?.isActive('underline'),
            run: () => editor.value?.chain().focus().toggleUnderline().run(),
        },
    ],
    [
        {
            label: 'Título',
            icon: Heading1,
            isActive: () => !!editor.value?.isActive('heading', { level: 1 }),
            run: () =>
                editor.value?.chain().focus().toggleHeading({ level: 1 }).run(),
        },
        {
            label: 'Título de cláusula',
            icon: Heading2,
            isActive: () => !!editor.value?.isActive('heading', { level: 2 }),
            run: () =>
                editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
        },
    ],
    [
        {
            label: 'Lista',
            icon: List,
            isActive: () => !!editor.value?.isActive('bulletList'),
            run: () => editor.value?.chain().focus().toggleBulletList().run(),
        },
        {
            label: 'Lista numerada',
            icon: ListOrdered,
            isActive: () => !!editor.value?.isActive('orderedList'),
            run: () => editor.value?.chain().focus().toggleOrderedList().run(),
        },
        {
            label: 'Linha divisória',
            icon: Minus,
            run: () => editor.value?.chain().focus().setHorizontalRule().run(),
        },
        {
            label: 'Quebra de página',
            icon: SeparatorHorizontal,
            run: () => editor.value?.chain().focus().setPageBreak().run(),
        },
    ],
    [
        {
            label: 'Desfazer',
            icon: Undo2,
            run: () => editor.value?.chain().focus().undo().run(),
        },
        {
            label: 'Refazer',
            icon: Redo2,
            run: () => editor.value?.chain().focus().redo().run(),
        },
    ],
];

/**
 * Insert a variable tag where the cursor is.
 */
function insertVariable(key: string) {
    editor.value?.chain().focus().insertContractVariable(key).run();
}

defineExpose({ insertVariable });
</script>

<template>
    <div
        class="contract-editor overflow-hidden rounded-xl border bg-card shadow-xs"
    >
        <div
            class="sticky top-0 z-10 flex flex-wrap items-center gap-1 border-b bg-card/95 p-2 backdrop-blur"
            role="toolbar"
            aria-label="Formatação"
        >
            <template v-for="(group, index) in toolbarGroups" :key="index">
                <Separator
                    v-if="index > 0"
                    orientation="vertical"
                    class="mx-1 h-6!"
                />
                <Button
                    v-for="action in group"
                    :key="action.label"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :title="action.label"
                    :aria-label="action.label"
                    :aria-pressed="action.isActive?.()"
                    :class="{
                        'bg-primary/10 text-primary': action.isActive?.(),
                    }"
                    @click="action.run"
                >
                    <component :is="action.icon" class="size-4" />
                </Button>
            </template>
        </div>

        <div class="overflow-x-auto bg-muted/30 px-3 py-6 sm:px-6 sm:py-8">
            <EditorContent :editor="editor" />
        </div>
    </div>
</template>

<style>
/*
 * The text column mirrors the PDF (resources/views/pdf/lease-contract.blade.php): same width as the
 * A4 text area (21cm minus 2.5cm margins on each side), font, size and spacing, so lines break where
 * they will in the contract. Small marks on the side margins show roughly where each page ends
 * (A4 height minus the top and bottom margins).
 */
@font-face {
    font-family: 'Contract Serif';
    src: url('../../../fonts/DejaVuSerif.ttf') format('truetype');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Contract Serif';
    src: url('../../../fonts/DejaVuSerif-Bold.ttf') format('truetype');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Contract Serif';
    src: url('../../../fonts/DejaVuSerif-Italic.ttf') format('truetype');
    font-weight: 400;
    font-style: italic;
    font-display: swap;
}

@font-face {
    font-family: 'Contract Serif';
    src: url('../../../fonts/DejaVuSerif-BoldItalic.ttf') format('truetype');
    font-weight: 700;
    font-style: italic;
    font-display: swap;
}

.contract-editor-content {
    --page-text-width: 16cm;
    --page-text-height: 24.4cm;
    --page-padding-x: 1.5cm;
    --page-padding-top: 1.75cm;
    --page-mark: color-mix(in oklab, var(--muted-foreground) 40%, transparent);
    --page-mark-lines: repeating-linear-gradient(
        to bottom,
        transparent 0,
        transparent calc(var(--page-text-height) - 1px),
        var(--page-mark) calc(var(--page-text-height) - 1px),
        var(--page-mark) var(--page-text-height)
    );

    box-sizing: content-box;
    width: var(--page-text-width);
    min-height: 18cm;
    margin: 0 auto;
    padding: var(--page-padding-top) var(--page-padding-x) 2cm;
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    outline: none;
    background-color: var(--card);
    background-image: var(--page-mark-lines), var(--page-mark-lines);
    background-repeat: no-repeat;
    background-size:
        0.6cm calc(100% - var(--page-padding-top)),
        0.6cm calc(100% - var(--page-padding-top));
    background-position:
        left 0.45cm top var(--page-padding-top),
        right 0.45cm top var(--page-padding-top);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
    color: var(--foreground);
    font-family: 'Contract Serif', 'DejaVu Serif', Georgia, serif;
    font-size: 11pt;
    line-height: 1.5;
    transition: border-color 150ms;
}

.contract-editor-content:focus-within {
    border-color: color-mix(in oklab, var(--ring) 50%, var(--border));
}

.contract-editor-content > :first-child {
    margin-top: 0;
}

.contract-editor-content h1 {
    margin: 0 0 18pt;
    text-align: center;
    font-size: 14pt;
    font-weight: 700;
}

.contract-editor-content h2 {
    margin: 16pt 0 6pt;
    font-size: 11pt;
    font-weight: 700;
}

.contract-editor-content h3 {
    margin: 12pt 0 6pt;
    font-size: 11pt;
    font-weight: 700;
}

.contract-editor-content p {
    margin: 0 0 8pt;
    text-align: justify;
}

.contract-editor-content ul,
.contract-editor-content ol {
    margin: 0 0 8pt;
    padding-left: 18pt;
}

.contract-editor-content ul {
    list-style: disc;
}

.contract-editor-content ol {
    list-style: decimal;
}

.contract-editor-content li p {
    margin: 0 0 4pt;
}

.contract-editor-content blockquote {
    margin: 0 0 8pt 18pt;
    font-style: italic;
}

.contract-editor-content hr {
    border: 0;
    border-top: 1px solid var(--border);
    margin: 12pt 0;
}

.contract-editor-content .contract-page-break {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 16pt 0;
    color: var(--muted-foreground);
    font-family: var(--font-sans, sans-serif);
    font-size: 0.7rem;
    font-weight: 500;
    line-height: 1;
    user-select: none;
}

.contract-editor-content .contract-page-break::before,
.contract-editor-content .contract-page-break::after {
    content: '';
    flex: 1;
    border-top: 1px dashed var(--border);
}

.contract-editor-content .contract-page-break.ProseMirror-selectednode {
    border-radius: 0.375rem;
    outline: 2px solid var(--ring);
    outline-offset: 4px;
}

.contract-editor-content .contract-variable {
    display: inline-block;
    padding: 0 0.4rem;
    border-radius: 0.375rem;
    background: color-mix(in oklab, var(--primary) 12%, transparent);
    color: var(--primary);
    font-size: 0.85em;
    font-weight: 500;
    line-height: 1.6;
    white-space: nowrap;
    cursor: default;
}

.contract-editor-content .contract-variable.ProseMirror-selectednode {
    outline: 2px solid var(--ring);
}
</style>
