<script setup lang="ts">
import { Bold, Redo2, Undo2 } from '@lucide/vue';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';
import { VariableNode } from '@/components/contract-editor/VariableNode';
import { Button } from '@/components/ui/button';
import { documentToMessage, messageToDocument } from '@/lib/whatsapp';

const props = defineProps<{
    /** Labels shown on each variable tag, keyed by variable. */
    labels: Record<string, string>;
    /** Name of the message, read out for the text box. */
    name: string;
}>();

/** The message as WhatsApp gets it: plain lines, *bold* and {variables}. */
const text = defineModel<string>({ required: true });

const editor = useEditor({
    content: messageToDocument(text.value, props.labels),
    extensions: [
        StarterKit.configure({
            blockquote: false,
            bulletList: false,
            code: false,
            codeBlock: false,
            heading: false,
            horizontalRule: false,
            italic: false,
            link: false,
            listItem: false,
            listKeymap: false,
            orderedList: false,
            strike: false,
            trailingNode: false,
            underline: false,
        }),
        VariableNode.configure({ labels: props.labels }),
    ],
    editorProps: {
        attributes: {
            class: 'message-editor-content',
            'aria-label': props.name,
        },
    },
    onUpdate: ({ editor }) => {
        text.value = documentToMessage(editor.getJSON());
    },
});

/** Show a text set from outside, such as the default brought back. */
watch(text, (value) => {
    if (editor.value && value !== documentToMessage(editor.value.getJSON())) {
        editor.value.commands.setContent(
            messageToDocument(value, props.labels),
            { emitUpdate: false },
        );
    }
});

onBeforeUnmount(() => editor.value?.destroy());

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
        class="overflow-hidden rounded-md border border-input shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
    >
        <div
            class="flex items-center gap-1 border-b p-1"
            role="toolbar"
            aria-label="Formatação"
        >
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Negrito"
                aria-label="Negrito"
                :aria-pressed="editor?.isActive('bold')"
                :class="{
                    'bg-primary/10 text-primary': editor?.isActive('bold'),
                }"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Desfazer"
                aria-label="Desfazer"
                @click="editor?.chain().focus().undo().run()"
            >
                <Undo2 class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Refazer"
                aria-label="Refazer"
                @click="editor?.chain().focus().redo().run()"
            >
                <Redo2 class="size-4" />
            </Button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.message-editor-content {
    min-height: 10rem;
    padding: 0.5rem 0.75rem;
    outline: none;
    font-size: 1rem;
    line-height: 1.75;
    white-space: pre-wrap;
}

@media (min-width: 768px) {
    .message-editor-content {
        font-size: 0.875rem;
    }
}

.message-editor-content p {
    margin: 0;
}

.message-editor-content .contract-variable {
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

.message-editor-content strong .contract-variable {
    font-weight: 700;
}

.message-editor-content .contract-variable.ProseMirror-selectednode {
    outline: 2px solid var(--ring);
}
</style>
