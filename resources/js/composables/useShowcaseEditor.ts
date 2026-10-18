import { router } from '@inertiajs/vue3';
import { computed, inject, provide, reactive, ref } from 'vue';
import type { InjectionKey } from 'vue';
import { update } from '@/routes/showcase-settings/texts';
import type {
    ShowcaseEditableKey,
    ShowcaseEditorSettings,
    ShowcaseSite,
} from '@/types';

type Texts = Record<ShowcaseEditableKey, string>;

/** The showcase texts as the page shows them now, the home title among them. */
function shownTexts(site: ShowcaseSite): Texts {
    return { ...site.texts, headline: site.headline };
}

function createShowcaseEditor(
    site: ShowcaseSite,
    settings: ShowcaseEditorSettings,
) {
    const saved = ref<Texts>(shownTexts(site));
    const drafts = reactive<Texts>({ ...saved.value });
    const focused = ref<ShowcaseEditableKey | null>(null);
    const processing = ref(false);
    const error = ref<string | null>(null);
    const recentlySaved = ref(false);
    let isLeaving = false;

    const changedCount = computed(
        () =>
            (Object.keys(drafts) as ShowcaseEditableKey[]).filter(
                (key) => drafts[key] !== saved.value[key],
            ).length,
    );

    function set(key: ShowcaseEditableKey, text: string) {
        drafts[key] = text;
        recentlySaved.value = false;
    }

    function restore(key: ShowcaseEditableKey) {
        set(key, settings.defaults[key]);
    }

    function discard() {
        Object.assign(drafts, saved.value);
        error.value = null;
    }

    function save() {
        const { headline, ...texts } = drafts;

        router.put(
            update().url,
            {
                showcase_headline:
                    headline === settings.defaults.headline ? null : headline,
                showcase_texts: texts,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    processing.value = true;
                    error.value = null;
                },
                onSuccess: (page) => {
                    saved.value = shownTexts(page.props.site as ShowcaseSite);
                    Object.assign(drafts, saved.value);
                    recentlySaved.value = true;
                },
                onError: (errors) => {
                    error.value = Object.values(errors)[0] ?? null;
                },
                onFinish: () => (processing.value = false),
            },
        );
    }

    /** Let the next page load go without asking about unsaved changes, once the account chose to leave. */
    function allowLeaving() {
        isLeaving = true;
    }

    function shouldWarnOnLeave(): boolean {
        return !isLeaving && changedCount.value > 0;
    }

    return {
        defaults: settings.defaults,
        limits: settings.limits,
        drafts,
        focused,
        processing,
        error,
        recentlySaved,
        changedCount,
        set,
        restore,
        discard,
        save,
        allowLeaving,
        shouldWarnOnLeave,
    };
}

export type ShowcaseEditor = ReturnType<typeof createShowcaseEditor>;

const editorKey: InjectionKey<ShowcaseEditor> = Symbol('showcase-editor');

/**
 * Start editing the showcase texts in place, for the layout and the page below it.
 */
export function provideShowcaseEditor(
    site: ShowcaseSite,
    settings: ShowcaseEditorSettings,
): ShowcaseEditor {
    const editor = createShowcaseEditor(site, settings);

    provide(editorKey, editor);

    return editor;
}

/**
 * Get the editor of the showcase texts, or null on the public showcase.
 */
export function useShowcaseEditor(): ShowcaseEditor | null {
    return inject(editorKey, null);
}
