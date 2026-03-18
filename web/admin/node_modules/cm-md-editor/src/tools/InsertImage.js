export class InsertImage {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'image', title: 'Insert Image', iconFile: 'card-image.svg', action: () => this.insertImage()}]
    }
    insertImage() {
        const el = this.editor.element
        const start = el.selectionStart
        const end = el.selectionEnd
        const selected = el.value.substring(start, end)
        const url = prompt('Enter image URL:')
        if (url === null) return
        const altText = selected || 'image'
        el.focus()
        this.editor.selectLineRange(start, end)
        this.editor.insertTextAtCursor('![' + altText + '](' + url + ')')
        el.selectionStart = start + 2
        el.selectionEnd = start + 2 + altText.length
    }
}
