import {toggleListBlock} from "./listBlock.js"

export class OrderedList {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'ol', title: 'Ordered List', iconFile: 'list-ol.svg', action: () => this.insertOrderedList()}]
    }
    insertOrderedList() {
        toggleListBlock(this.editor, 'ol')
    }
}
