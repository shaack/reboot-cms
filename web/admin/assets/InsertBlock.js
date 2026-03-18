/**
 * MdEditor addon tool: Insert Block
 * Shows a select dropdown with available blocks and inserts the example markdown at cursor position.
 */
export class InsertBlock {
    constructor(editor, props) {
        this.editor = editor
        this.blocks = props?.blocks || {}
    }

    toolbarButtons() {
        // Return a config with a custom element instead of a standard button
        const container = document.createElement('div')
        container.style.cssText = 'display:flex;align-items:center;gap:2px;'

        const select = document.createElement('select')
        select.style.cssText = 'background:rgba(128,128,128,0.15);border:1px solid rgba(128,128,128,0.3);border-radius:3px;padding:2px 4px;color:inherit;font-size:12px;height:28px;cursor:pointer;'
        select.title = 'Select a block to insert'

        const defaultOption = document.createElement('option')
        defaultOption.value = ''
        defaultOption.textContent = 'Insert block…'
        defaultOption.disabled = true
        defaultOption.selected = true
        select.appendChild(defaultOption)

        const blockNames = Object.keys(this.blocks).sort()
        for (const name of blockNames) {
            const option = document.createElement('option')
            option.value = name
            option.textContent = name
            select.appendChild(option)
        }

        const button = document.createElement('button')
        button.type = 'button'
        button.textContent = '+'
        button.title = 'Insert selected block at cursor'
        button.style.cssText = 'background:none;border:1px solid rgba(128,128,128,0.3);border-radius:3px;cursor:pointer;padding:2px 8px;color:inherit;font-size:14px;font-weight:bold;height:28px;display:flex;align-items:center;opacity:0.6;transition:opacity 0.15s,background 0.15s;'
        button.addEventListener('mouseenter', () => { button.style.opacity = '1'; button.style.background = 'rgba(128,128,128,0.2)' })
        button.addEventListener('mouseleave', () => { button.style.opacity = '0.6'; button.style.background = 'none' })
        button.addEventListener('mousedown', (e) => e.preventDefault())
        button.addEventListener('click', (e) => {
            e.preventDefault()
            this.insertBlock(select)
        })

        container.appendChild(select)
        container.appendChild(button)

        return [{element: container}]
    }

    insertBlock(select) {
        const blockName = select.value
        if (!blockName) return
        const example = this.blocks[blockName]
        if (!example) return

        const text = '\n<!-- ' + blockName + ' -->\n\n' + example + '\n'
        this.editor.element.focus()
        this.editor.insertTextAtCursor(text)
        this.editor.updateHighlight()

        // Reset select
        select.selectedIndex = 0
    }
}
