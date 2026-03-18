/**
 * MdEditor addon tool: Insert Page Link
 * Opens a modal showing the page tree and inserts a relative markdown link.
 */
export class InsertPageLink {
    constructor(editor, props) {
        this.editor = editor
        this.pagesUrl = props?.pagesUrl || "pages"
    }

    toolbarButtons() {
        return [{
            name: 'insert-page-link',
            title: 'Insert link to page',
            iconUrl: 'node_modules/cm-md-editor/src/tools/icons/link-45deg.svg',
            action: () => this.openBrowser()
        }]
    }

    openBrowser() {
        if (document.getElementById('insert-page-link-modal')) {
            document.getElementById('insert-page-link-modal').remove()
        }

        const modal = document.createElement('div')
        modal.id = 'insert-page-link-modal'
        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);'
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove()
        })

        const dialog = document.createElement('div')
        dialog.style.cssText = 'background:var(--bs-body-bg,#fff);border-radius:8px;width:90%;max-width:500px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,0.3);'

        const header = document.createElement('div')
        header.style.cssText = 'display:flex;align-items:center;padding:12px 16px;border-bottom:1px solid rgba(128,128,128,0.2);'
        header.innerHTML = '<strong style="flex:1">Insert link to page</strong>'
        const closeBtn = document.createElement('button')
        closeBtn.type = 'button'
        closeBtn.textContent = '×'
        closeBtn.style.cssText = 'background:none;border:none;font-size:1.5rem;cursor:pointer;color:inherit;padding:0 4px;line-height:1;opacity:0.6;'
        closeBtn.addEventListener('click', () => modal.remove())
        header.appendChild(closeBtn)

        const content = document.createElement('div')
        content.style.cssText = 'overflow-y:auto;flex:1;padding:12px 16px;'
        content.innerHTML = '<div style="text-align:center;padding:32px;opacity:0.5;">Loading…</div>'

        dialog.appendChild(header)
        dialog.appendChild(content)
        modal.appendChild(dialog)
        document.body.appendChild(modal)

        this.loadPages(content, modal)
    }

    loadPages(content, modal) {
        fetch(this.pagesUrl + '?list=1')
            .then(r => r.json())
            .then(pages => {
                content.innerHTML = ''
                if (!pages || pages.length === 0) {
                    content.innerHTML = '<div style="text-align:center;padding:32px;opacity:0.5;">No pages found</div>'
                    return
                }
                const tree = this.buildTree(pages)
                const list = this.renderTree(tree, modal)
                content.appendChild(list)
            })
            .catch(() => {
                content.innerHTML = '<div style="text-align:center;padding:32px;color:red;">Failed to load pages</div>'
            })
    }

    buildTree(pages) {
        const tree = {}
        for (const page of pages) {
            const parts = page.filePath.replace(/^\//, '').replace(/\.md$/, '').split('/')
            let node = tree
            for (let i = 0; i < parts.length - 1; i++) {
                if (!node[parts[i]]) node[parts[i]] = {}
                node = node[parts[i]]
            }
            const name = parts[parts.length - 1]
            if (!node._pages) node._pages = []
            node._pages.push({name, webPath: page.webPath})
        }
        return tree
    }

    renderTree(tree, modal, depth) {
        depth = depth || 0
        const ul = document.createElement('ul')
        ul.style.cssText = 'list-style:none;padding-left:' + (depth ? '20px' : '0') + ';margin:0;'

        // Pages first (index first, then alphabetically)
        const pages = (tree._pages || []).sort((a, b) => {
            if (a.name === 'index') return -1
            if (b.name === 'index') return 1
            return a.name.localeCompare(b.name)
        })
        for (const page of pages) {
            const li = document.createElement('li')
            li.style.cssText = 'margin:1px 0;'
            const link = document.createElement('a')
            link.href = '#'
            link.style.cssText = 'display:block;padding:4px 8px;border-radius:4px;text-decoration:none;color:inherit;transition:background 0.15s;'
            link.addEventListener('mouseenter', () => link.style.background = 'rgba(128,128,128,0.1)')
            link.addEventListener('mouseleave', () => link.style.background = 'none')
            link.innerHTML = '<span style="opacity:0.4;margin-right:4px;">&#128196;</span> '
                + this.escapeHtml(page.name)
                + '<span style="opacity:0.4;font-size:0.85em;margin-left:8px;">' + this.escapeHtml(page.webPath) + '</span>'
            link.addEventListener('click', (e) => {
                e.preventDefault()
                modal.remove()
                this.insertLink(page.webPath, page.name)
            })
            li.appendChild(link)
            ul.appendChild(li)
        }

        // Folders
        const folders = Object.keys(tree).filter(k => k !== '_pages').sort()
        for (const folder of folders) {
            const li = document.createElement('li')
            li.style.cssText = 'margin:2px 0;'
            const label = document.createElement('div')
            label.style.cssText = 'padding:4px 8px;font-weight:500;cursor:pointer;border-radius:4px;transition:background 0.15s;'
            label.addEventListener('mouseenter', () => label.style.background = 'rgba(128,128,128,0.05)')
            label.addEventListener('mouseleave', () => label.style.background = 'none')
            const subtree = this.renderTree(tree[folder], modal, depth + 1)
            label.innerHTML = '<span class="folder-toggle" style="display:inline-block;width:16px;text-align:center;opacity:0.4;">&#9660;</span> '
                + '<span style="opacity:0.4;margin-right:4px;">&#128193;</span> '
                + this.escapeHtml(folder)
            let collapsed = false
            label.addEventListener('click', () => {
                collapsed = !collapsed
                subtree.style.display = collapsed ? 'none' : 'block'
                label.querySelector('.folder-toggle').innerHTML = collapsed ? '&#9654;' : '&#9660;'
            })
            li.appendChild(label)
            li.appendChild(subtree)
            ul.appendChild(li)
        }

        return ul
    }

    insertLink(webPath, name) {
        const el = this.editor.element
        const start = el.selectionStart
        const end = el.selectionEnd
        const selected = el.value.substring(start, end)
        const linkText = selected || name
        const markdown = '[' + linkText + '](' + webPath + ')'
        el.focus()
        if (selected) {
            this.editor.selectLineRange(start, end)
        }
        this.editor.insertTextAtCursor(markdown)
        this.editor.updateHighlight()
    }

    escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
    }
}
