export class Bold {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'bold', title: 'Bold', iconFile: 'type-bold.svg', action: () => this.editor.toggleWrap('**')}]
    }
    keyboardShortcuts() {
        return [{key: 'b', ctrlOrMeta: true, action: () => this.editor.toggleWrap('**')}]
    }
}
