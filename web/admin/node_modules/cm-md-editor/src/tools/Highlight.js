export class Highlight {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'highlight', title: 'Highlight', iconFile: 'highlighter.svg', action: () => this.editor.toggleWrap('==')}]
    }
}
