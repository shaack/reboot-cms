/**
 * MdEditor addon tool: Insert Block
 * A toolbar button that opens a menu of the available blocks and inserts the
 * example Markdown of the chosen one at the cursor position.
 *
 * The editor builds its toolbar buttons from a description (name, title,
 * icon, action), it does not take ready made elements. The menu is therefore
 * opened by the button's action and placed below the button.
 */
export class InsertBlock {
    constructor(editor, props) {
        this.editor = editor
        this.blocks = props?.blocks || {}
        this.menu = null
    }

    // Icon: "grid-1x2" from Bootstrap Icons (MIT)
    toolbarButtons() {
        return [{
            name: 'insert-block',
            title: 'Insert block',
            icon: '<path d="M6 1H1v14h5zm9 0h-5v5h5zm0 9v5h-5v-5zM0 1a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm9 0a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1zm1 8a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1z"/>',
            action: () => this.menu ? this.closeMenu() : this.openMenu()
        }]
    }

    button() {
        return this.editor.element.parentNode.querySelector('.mde-btn-insert-block')
    }

    openMenu() {
        const button = this.button()
        const names = Object.keys(this.blocks).sort()
        if (!button || names.length === 0) {
            return
        }
        const menu = document.createElement('div')
        menu.setAttribute('role', 'menu')
        menu.setAttribute('aria-label', 'Insert block')
        menu.style.cssText = 'position:fixed;z-index:1060;min-width:11rem;padding:4px;'
            + 'background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);'
            + 'border:1px solid var(--bs-border-color,rgba(128,128,128,0.3));border-radius:6px;'
            + 'box-shadow:0 8px 24px rgba(0,0,0,0.25);font-size:0.875rem;'
        const header = document.createElement('div')
        header.textContent = 'Insert block'
        header.style.cssText = 'padding:4px 10px 6px;font-size:0.75rem;opacity:0.6;'
        menu.appendChild(header)
        for (const name of names) {
            const item = document.createElement('button')
            item.type = 'button'
            item.setAttribute('role', 'menuitem')
            item.textContent = name
            item.style.cssText = 'display:block;width:100%;text-align:left;background:none;border:none;'
                + 'border-radius:4px;padding:5px 10px;color:inherit;cursor:pointer;'
            const highlight = (on) => { item.style.background = on ? 'rgba(128,128,128,0.2)' : 'none' }
            item.addEventListener('mouseenter', () => highlight(true))
            item.addEventListener('mouseleave', () => highlight(false))
            item.addEventListener('focus', () => highlight(true))
            item.addEventListener('blur', () => highlight(false))
            // keep the cursor of the editor where it is
            item.addEventListener('mousedown', (e) => e.preventDefault())
            item.addEventListener('click', () => this.insertBlock(name))
            menu.appendChild(item)
        }
        menu.addEventListener('keydown', (e) => this.handleMenuKey(e))
        document.body.appendChild(menu)
        const rect = button.getBoundingClientRect()
        const left = Math.min(rect.left, window.innerWidth - menu.offsetWidth - 8)
        menu.style.left = Math.max(8, left) + 'px'
        menu.style.top = (rect.bottom + 4) + 'px'
        button.setAttribute('aria-expanded', 'true')
        this.menu = menu
        this.onOutside = (e) => {
            if (!menu.contains(e.target) && !button.contains(e.target)) {
                this.closeMenu()
            }
        }
        document.addEventListener('mousedown', this.onOutside)
        window.addEventListener('resize', this.onResize = () => this.closeMenu())
        menu.querySelector('[role=menuitem]').focus()
    }

    handleMenuKey(e) {
        const items = Array.from(this.menu.querySelectorAll('[role=menuitem]'))
        const index = items.indexOf(document.activeElement)
        if (e.key === 'ArrowDown') {
            e.preventDefault()
            items[(index + 1) % items.length].focus()
        } else if (e.key === 'ArrowUp') {
            e.preventDefault()
            items[(index - 1 + items.length) % items.length].focus()
        } else if (e.key === 'Home' || e.key === 'End') {
            e.preventDefault()
            items[e.key === 'Home' ? 0 : items.length - 1].focus()
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            e.preventDefault()
            this.closeMenu()
            this.editor.element.focus()
        }
    }

    closeMenu() {
        if (!this.menu) {
            return
        }
        this.menu.remove()
        this.menu = null
        document.removeEventListener('mousedown', this.onOutside)
        window.removeEventListener('resize', this.onResize)
        const button = this.button()
        if (button) {
            button.setAttribute('aria-expanded', 'false')
        }
    }

    insertBlock(blockName) {
        const example = this.blocks[blockName]
        this.closeMenu()
        if (!example) {
            return
        }
        const text = '\n<!-- ' + blockName + ' -->\n\n' + example + '\n'
        this.editor.element.focus()
        this.editor.insertTextAtCursor(text)
        this.editor.updateHighlight()
    }
}
