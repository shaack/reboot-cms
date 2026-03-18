export class Separator {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'separator', title: '', separator: true}]
    }
}
