export class UnorderedList {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'ul', title: 'Unordered List', iconFile: 'list-ul.svg', action: () => this.insertUnorderedList()}]
    }
    insertUnorderedList() {
        const editor = this.editor
        const {lineStart, lineEnd, line} = editor.getCurrentLineInfo()
        editor.selectLineRange(lineStart, lineEnd)
        if (line.startsWith('- ')) {
            editor.insertTextAtCursor(line.substring(2))
        } else {
            editor.insertTextAtCursor('- ' + line)
        }
    }
}
