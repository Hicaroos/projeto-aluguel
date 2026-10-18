<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useShowcaseEditor } from '@/composables/useShowcaseEditor';
import { showcaseTextLabels } from '@/lib/showcase';
import type { ShowcaseEditableKey } from '@/types';

/**
 * A showcase text that the account rewrites right where it shows, while the showcase is in edit mode.
 * On the public showcase it is only the text.
 */
const props = withDefaults(
    defineProps<{
        field: ShowcaseEditableKey;
        text: string;
        as?: string;
    }>(),
    { as: 'span' },
);

const editor = useShowcaseEditor();
const element = ref<HTMLElement | null>(null);
let textOnFocus = '';

const draft = computed(() => editor?.drafts[props.field] ?? props.text);
const limit = computed(() => editor?.limits[props.field] ?? Infinity);

function placeCaretAtEnd(target: HTMLElement) {
    const range = document.createRange();
    range.selectNodeContents(target);
    range.collapse(false);
    window.getSelection()?.removeAllRanges();
    window.getSelection()?.addRange(range);
}

/** Write the draft into the element, unless the element already shows it, so typing keeps the caret. */
function show(text: string) {
    const target = element.value;

    if (!target || target.textContent === text) {
        return;
    }

    target.textContent = text;

    if (document.activeElement === target) {
        placeCaretAtEnd(target);
    }
}

onMounted(() => show(draft.value));
watch(draft, show);

function onFocus() {
    textOnFocus = draft.value;

    if (editor) {
        editor.focused.value = props.field;
    }
}

function onBlur() {
    if (!editor) {
        return;
    }

    if (draft.value.trim() === '') {
        editor.restore(props.field);
    }

    if (editor.focused.value === props.field) {
        editor.focused.value = null;
    }
}

/** Refuse typing past the text's limit, counting what the typing replaces. */
function onBeforeInput(event: InputEvent) {
    if (event.inputType !== 'insertText' || !element.value) {
        return;
    }

    const length = (element.value.textContent ?? '').length;
    const selected = window.getSelection()?.toString().length ?? 0;

    if (length - selected + (event.data?.length ?? 0) > limit.value) {
        event.preventDefault();
    }
}

/** Keep the text on one line and within its limit, however it was typed or pasted. */
function onInput() {
    const target = element.value;

    if (!target || !editor) {
        return;
    }

    const typed = target.textContent ?? '';
    const text = typed.replace(/\s*\n\s*/g, ' ').slice(0, limit.value);

    // Browsers may leave a line break behind in an emptied text, which would hide its placeholder.
    if (text !== typed || (text === '' && target.childNodes.length > 0)) {
        target.textContent = text;
        placeCaretAtEnd(target);
    }

    editor.set(props.field, text);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        event.preventDefault();
        element.value?.blur();
    }

    if (event.key === 'Escape') {
        editor?.set(props.field, textOnFocus);
        element.value?.blur();
    }
}
</script>

<template>
    <component :is="as" v-if="!editor">{{ text }}</component>
    <component
        :is="as"
        v-else
        ref="element"
        class="sc-editable"
        contenteditable="plaintext-only"
        role="textbox"
        spellcheck="true"
        :aria-label="showcaseTextLabels[field]"
        :data-placeholder="editor.defaults[field]"
        @focus="onFocus"
        @blur="onBlur"
        @beforeinput="onBeforeInput"
        @input="onInput"
        @keydown="onKeydown"
    />
</template>
