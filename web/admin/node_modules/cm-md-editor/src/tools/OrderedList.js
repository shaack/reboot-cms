export class OrderedList {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'ol', title: 'Ordered List', iconFile: 'list-ol.svg', action: () => this.insertOrderedList()}]
    }
    insertOrderedList() {
        const editor = this.editor
        const {lineStart, lineEnd, line} = editor.getCurrentLineInfo()
        editor.selectLineRange(lineStart, lineEnd)
        const olMatch = line.match(/^\d+\. /)
        if (olMatch) {
            editor.insertTextAtCursor(line.substring(olMatch[0].length))
        } else {
            editor.insertTextAtCursor('1. ' + line)
        }
    }
}
