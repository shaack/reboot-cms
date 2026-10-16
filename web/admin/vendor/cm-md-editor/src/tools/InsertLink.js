export class InsertLink {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'link', title: 'Insert Link', iconFile: 'link-45deg.svg', action: () => this.insertLink()}]
    }
    insertLink() {
        const el = this.editor.element
        const start = el.selectionStart
        const end = el.selectionEnd
        const selected = el.value.substring(start, end)
        const url = prompt('Enter URL:')
        if (url === null) return
        const linkText = selected || 'link'
        el.focus()
        this.editor.selectLineRange(start, end)
        this.editor.insertTextAtCursor('[' + linkText + '](' + url + ')')
        el.selectionStart = start + 1
        el.selectionEnd = start + 1 + linkText.length
    }
}
