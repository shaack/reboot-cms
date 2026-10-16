export class Strikethrough {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'strikethrough', title: 'Strikethrough', iconFile: 'type-strikethrough.svg', action: () => this.editor.toggleWrap('~~')}]
    }
}
