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
    Underline,
    Undo2,
} from '@lucide/vue';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import { onBeforeUnmount } from 'vue';
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

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.contract-editor-content {
    min-height: 32rem;
    padding: 2rem clamp(1rem, 4vw, 3rem);
    outline: none;
    font-size: 0.95rem;
    line-height: 1.7;
}

.contract-editor-content > * + * {
    margin-top: 0.75rem;
}

.contract-editor-content h1 {
    text-align: center;
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
}

.contract-editor-content h2 {
    font-size: 1rem;
    font-weight: 700;
    margin-top: 1.5rem;
}

.contract-editor-content h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin-top: 1.25rem;
}

.contract-editor-content p {
    text-align: justify;
}

.contract-editor-content ul {
    list-style: disc;
    padding-left: 1.5rem;
}

.contract-editor-content ol {
    list-style: decimal;
    padding-left: 1.5rem;
}

.contract-editor-content blockquote {
    border-left: 3px solid var(--border);
    padding-left: 1rem;
    font-style: italic;
}

.contract-editor-content hr {
    border: 0;
    border-top: 1px solid var(--border);
    margin: 1.5rem 0;
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
