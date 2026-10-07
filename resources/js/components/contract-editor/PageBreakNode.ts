import { Node } from '@tiptap/vue-3';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        pageBreak: {
            setPageBreak: () => ReturnType;
        };
    }
}

/**
 * Forces the next content onto a new page in the generated PDF. Saved as <hr data-page-break="true">.
 */
export const PageBreakNode = Node.create({
    name: 'pageBreak',
    group: 'block',
    atom: true,
    selectable: true,

    parseHTML() {
        // Runs before the regular horizontal rule, which also matches <hr>.
        return [{ tag: 'hr[data-page-break]', priority: 60 }];
    },

    renderHTML() {
        return ['hr', { 'data-page-break': 'true' }];
    },

    addNodeView() {
        return () => {
            const marker = document.createElement('div');
            marker.className = 'contract-page-break';
            marker.contentEditable = 'false';
            marker.textContent = 'Quebra de página';

            return { dom: marker };
        };
    },

    addCommands() {
        return {
            setPageBreak:
                () =>
                ({ commands }) =>
                    commands.insertContent({ type: this.name }),
        };
    },
});
