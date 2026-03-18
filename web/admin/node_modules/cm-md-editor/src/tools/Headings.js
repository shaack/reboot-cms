export class Headings {
    constructor(editor, props = {}) {
        this.editor = editor
        this.minLevel = props.minLevel || 1
        this.maxLevel = props.maxLevel || 3
    }
    toolbarButtons() {
        const buttons = []
        for (let level = this.minLevel; level <= this.maxLevel; level++) {
            buttons.push({
                name: 'h' + level,
                title: 'Heading ' + level,
                iconFile: 'type-h' + level + '.svg',
                action: () => this.toggleHeading(level)
            })
        }
        return buttons
    }
    toggleHeading(level) {
        const editor = this.editor
        editor.element.focus()
        const {lineStart, lineEnd, line} = editor.getCurrentLineInfo()
        const prefix = '#'.repeat(level) + ' '
        const headingMatch = line.match(/^(#{1,6}) /)
        editor.selectLineRange(lineStart, lineEnd)
        if (headingMatch && headingMatch[1].length === level) {
            editor.insertTextAtCursor(line.substring(prefix.length))
        } else if (headingMatch) {
            editor.insertTextAtCursor(prefix + line.substring(headingMatch[0].length))
        } else {
            editor.insertTextAtCursor(prefix + line)
        }
    }
}
