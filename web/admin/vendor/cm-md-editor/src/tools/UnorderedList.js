import {toggleListBlock} from "./listBlock.js"

export class UnorderedList {
    constructor(editor) {
        this.editor = editor
    }
    toolbarButtons() {
        return [{name: 'ul', title: 'Unordered List', iconFile: 'list-ul.svg', action: () => this.insertUnorderedList()}]
    }
    insertUnorderedList() {
        toggleListBlock(this.editor, 'ul')
    }
}
