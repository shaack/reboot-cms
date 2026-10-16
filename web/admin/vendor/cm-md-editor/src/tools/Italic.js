export class Italic {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'italic', title: 'Italic', iconFile: 'type-italic.svg', action: () => this.editor.toggleWrap('_')}]
    }
    keyboardShortcuts() {
        return [{key: 'i', ctrlOrMeta: true, action: () => this.editor.toggleWrap('_')}]
    }
}
