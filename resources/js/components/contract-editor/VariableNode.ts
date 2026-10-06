import { mergeAttributes, Node } from '@tiptap/vue-3';

export type VariableNodeOptions = {
    /** Labels shown on each tag, keyed by variable. */
    labels: Record<string, string>;
};

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        contractVariable: {
            insertContractVariable: (key: string) => ReturnType;
        };
    }
}

/**
 * An inline, non-editable tag for a contract variable, saved as <span data-variable="key"></span>
 * and replaced by the lease details when the contract is generated.
 */
export const VariableNode = Node.create<VariableNodeOptions>({
    name: 'contractVariable',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addOptions() {
        return { labels: {} };
    },

    addAttributes() {
        return {
            key: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-variable'),
                renderHTML: (attributes) => ({
                    'data-variable': attributes.key,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-variable]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes)];
    },

    renderText({ node }) {
        return `[${this.options.labels[node.attrs.key] ?? node.attrs.key}]`;
    },

    addNodeView() {
        return ({ node }) => {
            const tag = document.createElement('span');
            tag.dataset.variable = node.attrs.key;
            tag.className = 'contract-variable';
            tag.textContent =
                this.options.labels[node.attrs.key] ?? node.attrs.key;

            return { dom: tag };
        };
    },

    addCommands() {
        return {
            insertContractVariable:
                (key) =>
                ({ commands }) =>
                    commands.insertContent({
                        type: this.name,
                        attrs: { key },
                    }),
        };
    },
});
