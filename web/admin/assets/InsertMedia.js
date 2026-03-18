/**
 * MdEditor addon tool: Insert Media
 * Opens a modal media browser and inserts a markdown image at cursor position.
 */
export class InsertMedia {
    constructor(editor, props) {
        this.editor = editor
        this.mediaUrl = props?.mediaUrl || "media"
    }

    toolbarButtons() {
        return [{
            name: 'insert-media',
            title: 'Insert image from media',
            iconUrl: 'node_modules/cm-md-editor/src/tools/icons/card-image.svg',
            action: () => this.openBrowser()
        }]
    }

    openBrowser() {
        if (document.getElementById('insert-media-modal')) {
            document.getElementById('insert-media-modal').remove()
        }

        const modal = document.createElement('div')
        modal.id = 'insert-media-modal'
        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);'
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove()
        })

        const dialog = document.createElement('div')
        dialog.style.cssText = 'background:var(--bs-body-bg,#fff);border-radius:8px;width:90%;max-width:700px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,0.3);'

        const header = document.createElement('div')
        header.style.cssText = 'display:flex;align-items:center;padding:12px 16px;border-bottom:1px solid rgba(128,128,128,0.2);'
        header.innerHTML = '<strong style="flex:1">Insert image from media</strong>'
        const closeBtn = document.createElement('button')
        closeBtn.type = 'button'
        closeBtn.textContent = '×'
        closeBtn.style.cssText = 'background:none;border:none;font-size:1.5rem;cursor:pointer;color:inherit;padding:0 4px;line-height:1;opacity:0.6;'
        closeBtn.addEventListener('click', () => modal.remove())
        header.appendChild(closeBtn)

        const breadcrumb = document.createElement('div')
        breadcrumb.style.cssText = 'padding:8px 16px;border-bottom:1px solid rgba(128,128,128,0.1);font-size:0.85rem;'

        const content = document.createElement('div')
        content.style.cssText = 'overflow-y:auto;flex:1;padding:8px;'

        dialog.appendChild(header)
        dialog.appendChild(breadcrumb)
        dialog.appendChild(content)
        modal.appendChild(dialog)
        document.body.appendChild(modal)

        this.loadFolder('', breadcrumb, content, modal)
    }

    loadFolder(path, breadcrumb, content, modal) {
        // Build breadcrumb
        const parts = path ? path.split('/') : []
        let bc = '<a href="#" data-path="" style="color:inherit;">media</a>'
        let accumulated = ''
        for (const part of parts) {
            accumulated += (accumulated ? '/' : '') + part
            bc += ' / <a href="#" data-path="' + accumulated + '" style="color:inherit;">' + part + '</a>'
        }
        breadcrumb.innerHTML = bc
        breadcrumb.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault()
                this.loadFolder(a.dataset.path, breadcrumb, content, modal)
            })
        })

        content.innerHTML = '<div style="text-align:center;padding:32px;opacity:0.5;">Loading…</div>'

        fetch(this.mediaUrl + '?list=1&path=' + encodeURIComponent(path))
            .then(r => r.json())
            .then(data => {
                content.innerHTML = ''
                if (!data.items || data.items.length === 0) {
                    content.innerHTML = '<div style="text-align:center;padding:32px;opacity:0.5;">Empty folder</div>'
                    return
                }

                const grid = document.createElement('div')
                grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;'

                for (const item of data.items) {
                    const cell = document.createElement('div')
                    cell.style.cssText = 'border:1px solid rgba(128,128,128,0.2);border-radius:6px;padding:8px;cursor:pointer;text-align:center;transition:background 0.15s;display:flex;flex-direction:column;align-items:center;gap:4px;'
                    cell.addEventListener('mouseenter', () => cell.style.background = 'rgba(128,128,128,0.1)')
                    cell.addEventListener('mouseleave', () => cell.style.background = 'none')

                    if (item.isDir) {
                        cell.innerHTML = '<div style="font-size:2.5rem;line-height:1;">&#128193;</div>'
                            + '<div style="font-size:0.8rem;word-break:break-all;">' + this.escapeHtml(item.name) + '</div>'
                        cell.addEventListener('click', () => {
                            this.loadFolder(item.subPath, breadcrumb, content, modal)
                        })
                    } else if (item.isImage) {
                        cell.innerHTML = '<img src="' + this.escapeHtml(item.webPath) + '" style="width:100%;height:80px;object-fit:cover;border-radius:3px;" alt="">'
                            + '<div style="font-size:0.75rem;word-break:break-all;opacity:0.8;">' + this.escapeHtml(item.name) + '</div>'
                        cell.addEventListener('click', () => {
                            modal.remove()
                            this.insertImage(item.webPath, item.name)
                        })
                    } else {
                        cell.innerHTML = '<div style="font-size:2.5rem;line-height:1;">&#128196;</div>'
                            + '<div style="font-size:0.8rem;word-break:break-all;opacity:0.5;">' + this.escapeHtml(item.name) + '</div>'
                        cell.style.opacity = '0.4'
                        cell.style.cursor = 'default'
                    }
                    grid.appendChild(cell)
                }
                content.appendChild(grid)
            })
            .catch(() => {
                content.innerHTML = '<div style="text-align:center;padding:32px;color:red;">Failed to load media</div>'
            })
    }

    insertImage(webPath, fileName) {
        const altText = fileName.replace(/\.[^.]+$/, '').replace(/[_-]/g, ' ')
        const markdown = '![' + altText + '](' + webPath + ')'
        this.editor.element.focus()
        this.editor.insertTextAtCursor(markdown)
        this.editor.updateHighlight()
    }

    escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
    }
}
