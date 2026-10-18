<script setup lang="ts">
import { PencilLine } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { ShowcaseEditor } from '@/composables/useShowcaseEditor';
import { showcaseTextLabels } from '@/lib/showcase';
import { edit } from '@/routes/showcase-settings';

/**
 * The bar of the showcase edit mode: what the text being typed is and how long it may get, and the
 * buttons to save, discard or leave. Its buttons keep the focus on the text, so "Restaurar padrão"
 * knows which one to restore.
 */
const props = defineProps<{ editor: ShowcaseEditor }>();

const focused = computed(() => props.editor.focused.value);

const status = computed(() => {
    const count = props.editor.changedCount.value;

    if (count > 0) {
        return count === 1
            ? '1 alteração não salva'
            : `${count} alterações não salvas`;
    }

    return props.editor.recentlySaved.value
        ? 'Alterações salvas. Já estão na vitrine.'
        : 'Clique num texto da página para alterar.';
});

function leave(event: MouseEvent) {
    if (
        props.editor.shouldWarnOnLeave() &&
        !window.confirm('Sair sem salvar as alterações?')
    ) {
        event.preventDefault();

        return;
    }

    props.editor.allowLeaving();
}
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-50 p-3 sm:p-4">
        <div
            class="pointer-events-auto mx-auto flex max-w-3xl flex-col gap-3 rounded-xl border bg-card p-3 text-sm text-card-foreground shadow-lg sm:flex-row sm:items-center sm:pl-4"
        >
            <div class="min-w-0 flex-1">
                <template v-if="focused">
                    <p class="truncate font-medium">
                        {{ showcaseTextLabels[focused] }}
                    </p>
                    <p class="text-muted-foreground">
                        <span class="tabular-nums">
                            {{ editor.drafts[focused].length }}/{{
                                editor.limits[focused]
                            }}
                        </span>
                        caracteres ·
                        <button
                            type="button"
                            class="underline underline-offset-4 hover:text-foreground disabled:no-underline disabled:opacity-50"
                            :disabled="
                                editor.drafts[focused] ===
                                editor.defaults[focused]
                            "
                            @mousedown.prevent
                            @click="editor.restore(focused)"
                        >
                            Restaurar padrão
                        </button>
                    </p>
                </template>
                <template v-else>
                    <p class="flex items-center gap-1.5 font-medium">
                        <PencilLine class="size-4" />
                        Editando os textos da vitrine
                    </p>
                    <p class="text-muted-foreground">{{ status }}</p>
                </template>
                <p v-if="editor.error.value" class="text-destructive">
                    {{ editor.error.value }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="ghost" size="sm" as-child>
                    <a :href="edit().url" @click="leave">Sair</a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        editor.changedCount.value === 0 ||
                        editor.processing.value
                    "
                    @mousedown.prevent
                    @click="editor.discard()"
                >
                    Descartar
                </Button>
                <Button
                    size="sm"
                    :disabled="
                        editor.changedCount.value === 0 ||
                        editor.processing.value
                    "
                    @mousedown.prevent
                    @click="editor.save()"
                >
                    {{ editor.processing.value ? 'Salvando…' : 'Salvar' }}
                </Button>
            </div>
        </div>
    </div>
</template>
