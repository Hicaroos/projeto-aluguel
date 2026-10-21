import { Node } from '@tiptap/vue-3';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        ownersRepeat: {
            toggleOwnersRepeat: () => ReturnType;
        };
    }
}

/**
 * Wraps text that the contract repeats once for each landlord of the property, the landlord
 * variables inside it naming one owner at a time. Saved as <div data-repeat="owners">.
 */
export const OwnersRepeatNode = Node.create({
    name: 'ownersRepeat',
    group: 'block',
    content: 'block+',
    defining: true,

    parseHTML() {
        return [{ tag: 'div[data-repeat="owners"]' }];
    },

    renderHTML() {
        return [
            'div',
            {
                'data-repeat': 'owners',
                class: 'contract-owners-repeat',
                'data-label': 'Repete para cada locador',
            },
            0,
        ];
    },

    addCommands() {
        return {
            toggleOwnersRepeat:
                () =>
                ({ commands }) =>
                    this.editor.isActive(this.name)
                        ? commands.lift(this.name)
                        : commands.wrapIn(this.name),
        };
    },
});
