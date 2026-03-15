/**
 * Author: Stefan Haack (https://shaack.com)
 * Date: 2023-11-12
 */
export class MdEditor {

    constructor(element, props) {
        this.element = element
        this.props = {
            colorHeading: "100,160,255",
            colorCode: "130,170,200",
            colorComment: "128,128,128",
            colorLink: "100,180,220",
            colorBlockquote: "100,200,150",
            colorList: "100,200,150",
            colorStrikethrough: "255,100,100",
            colorBold: "255,180,80",
            colorItalic: "180,130,255",
            colorHtmlTag: "200,120,120",
            colorHorizontalRule: "128,128,200",
            colorEscape: "128,128,128",
            colorFrontMatter: "128,128,200",
            wordWrap: true,
            toolbarButtons: ["h1", "h2", "h3", "bold", "italic", "ul", "ol", "link", "image"],
            ...props
        }
        this.element.addEventListener('keydown', (e) => this.handleKeyDown(e))
        this.createToolbar()
        this.createHighlightBackdrop()
        if (!this.wrapEnabled && this.highlightLayer) {
            this.highlightLayer.style.whiteSpace = 'pre'
            this.highlightLayer.style.overflowWrap = 'normal'
        }
    }

    createToolbar() {
        const wrapper = document.createElement('div')
        this.element.parentNode.insertBefore(wrapper, this.element)
        wrapper.appendChild(this.element)
        const toolbar = document.createElement('div')
        toolbar.style.cssText = 'display:flex;gap:1px;padding:2px;flex-wrap:wrap;background:rgba(128,128,128,0.15);border:1px solid rgba(128,128,128,0.3);border-bottom:none;border-radius:4px 4px 0 0;box-sizing:border-box;width:100%;'
        wrapper.insertBefore(toolbar, this.element)
        this.element.style.borderRadius = '0 0 4px 4px'
        const allButtons = [
            {name: 'h1', title: 'Heading 1', icon: '<path d="M7.648 13V3H6.3v4.234H1.348V3H0v10h1.348V8.421H6.3V13zM14 13V3h-1.333l-2.381 1.766V6.12L12.6 4.443h.066V13z"/>', action: () => this.toggleHeading(1)},
            {name: 'h2', title: 'Heading 2', icon: '<path d="M7.495 13V3.201H6.174v4.15H1.32V3.2H0V13h1.32V8.513h4.854V13zm3.174-7.071v-.05c0-.934.66-1.752 1.801-1.752 1.005 0 1.76.639 1.76 1.651 0 .898-.582 1.58-1.12 2.19l-3.69 4.2V13h6.331v-1.149h-4.458v-.079L13.9 8.786c.919-1.048 1.666-1.874 1.666-3.101C15.565 4.149 14.35 3 12.499 3 10.46 3 9.384 4.393 9.384 5.879v.05z"/>', action: () => this.toggleHeading(2)},
            {name: 'h3', title: 'Heading 3', icon: '<path d="M11.07 8.4h1.049c1.174 0 1.99.69 2.004 1.724s-.802 1.786-2.068 1.779c-1.11-.007-1.905-.605-1.99-1.357h-1.21C8.926 11.91 10.116 13 12.028 13c1.99 0 3.439-1.188 3.404-2.87-.028-1.553-1.287-2.221-2.096-2.313v-.07c.724-.127 1.814-.935 1.772-2.293-.035-1.392-1.21-2.468-3.038-2.454-1.927.007-2.94 1.196-2.981 2.426h1.23c.064-.71.732-1.336 1.744-1.336 1.027 0 1.744.64 1.744 1.568.007.95-.738 1.639-1.744 1.639h-.991V8.4ZM7.495 13V3.201H6.174v4.15H1.32V3.2H0V13h1.32V8.513h4.854V13z"/>', action: () => this.toggleHeading(3)},
            {name: 'bold', title: 'Bold', icon: '<path d="M8.21 13c2.106 0 3.412-1.087 3.412-2.823 0-1.306-.984-2.283-2.324-2.386v-.055a2.176 2.176 0 0 0 1.852-2.14c0-1.51-1.162-2.46-3.014-2.46H3.843V13zM5.908 4.674h1.696c.963 0 1.517.451 1.517 1.244 0 .834-.629 1.32-1.73 1.32H5.908V4.673zm0 6.788V8.598h1.73c1.217 0 1.88.492 1.88 1.415 0 .943-.643 1.449-1.832 1.449H5.907z"/>', action: () => this.toggleBold()},
            {name: 'italic', title: 'Italic', icon: '<path d="M7.991 11.674 9.53 4.455c.123-.595.246-.71 1.347-.807l.11-.52H7.211l-.11.52c1.06.096 1.128.212 1.005.807L6.57 11.674c-.123.595-.246.71-1.346.806l-.11.52h3.774l.11-.52c-1.06-.095-1.129-.211-1.006-.806z"/>', action: () => this.toggleItalic()},
            {name: 'ul', title: 'Unordered List', icon: '<path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>', action: () => this.insertUnorderedList()},
            {name: 'ol', title: 'Ordered List', icon: '<path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5"/><path d="M1.713 11.865v-.474H2c.217 0 .363-.137.363-.317 0-.185-.158-.31-.361-.31-.223 0-.367.152-.373.31h-.59c.016-.467.373-.787.986-.787.588-.002.954.291.957.703a.595.595 0 0 1-.492.594v.033a.615.615 0 0 1 .569.631c.003.533-.502.8-1.051.8-.656 0-1-.37-1.008-.794h.582c.008.178.186.306.422.309.254 0 .424-.145.422-.35-.002-.195-.155-.348-.414-.348h-.3zm-.004-4.699h-.604v-.035c0-.408.295-.844.958-.844.583 0 .96.326.96.756 0 .389-.257.617-.476.848l-.537.572v.03h1.054V9H1.143v-.395l.957-.99c.138-.142.293-.304.293-.508 0-.18-.147-.32-.342-.32a.33.33 0 0 0-.342.338zM2.564 5h-.635V2.924h-.031l-.598.42v-.567l.629-.443h.635z"/>', action: () => this.insertOrderedList()},
            {name: 'link', title: 'Insert Link', icon: '<path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/><path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/>', action: () => this.insertLink()},
            {name: 'image', title: 'Insert Image', icon: '<path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/><path d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2zm13 1a.5.5 0 0 1 .5.5v6l-3.775-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12v.54L1 12.5v-9a.5.5 0 0 1 .5-.5z"/>', action: () => this.insertImage()},
        ]
        const buttons = allButtons.filter(btn => this.props.toolbarButtons.includes(btn.name))
        buttons.forEach(btn => {
            const button = document.createElement('button')
            button.type = 'button'
            button.title = btn.title
            button.style.cssText = 'background:none;border:none;border-radius:3px;cursor:pointer;padding:4px 6px;display:flex;align-items:center;justify-content:center;color:inherit;opacity:0.6;transition:opacity 0.15s,background 0.15s;'
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor">${btn.icon}</svg>`
            button.addEventListener('mouseenter', () => { button.style.opacity = '1'; button.style.background = 'rgba(128,128,128,0.2)' })
            button.addEventListener('mouseleave', () => { button.style.opacity = '0.6'; button.style.background = 'none' })
            button.addEventListener('mousedown', (e) => {
                e.preventDefault()
            })
            button.addEventListener('click', (e) => {
                e.preventDefault()
                btn.action()
            })
            toolbar.appendChild(button)
        })

        // Spacer to push wrap toggle to the right
        const spacer = document.createElement('div')
        spacer.style.cssText = 'flex:1;'
        toolbar.appendChild(spacer)

        // Wrap toggle button
        this.wrapStorageKey = 'mdEditor_wrap_' + (this.element.id || this.element.name || 'default')
        const savedWrap = localStorage.getItem(this.wrapStorageKey)
        this.wrapEnabled = savedWrap !== null ? savedWrap === 'true' : this.props.wordWrap
        this.wrapButton = document.createElement('button')
        this.wrapButton.type = 'button'
        this.wrapButton.title = 'Toggle word wrap'
        this.wrapButton.style.cssText = 'background:none;border:none;border-radius:3px;cursor:pointer;padding:4px 6px;display:flex;align-items:center;justify-content:center;color:inherit;opacity:0.6;transition:opacity 0.15s,background 0.15s;'
        this.wrapButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M2 3.5a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5m0 4a.5.5 0 0 1 .5-.5h9a2.5 2.5 0 0 1 0 5h-1.293l.647.646a.5.5 0 0 1-.708.708l-1.5-1.5a.5.5 0 0 1 0-.708l1.5-1.5a.5.5 0 0 1 .708.708l-.647.646H11.5a1.5 1.5 0 0 0 0-3h-9a.5.5 0 0 1-.5-.5m0 4a.5.5 0 0 1 .5-.5H7a.5.5 0 0 1 0 1H2.5a.5.5 0 0 1-.5-.5"/></svg>`
        this.wrapButton.addEventListener('mouseenter', () => { this.wrapButton.style.opacity = '1'; this.wrapButton.style.background = 'rgba(128,128,128,0.2)' })
        this.wrapButton.addEventListener('mouseleave', () => { this.wrapButton.style.opacity = this.wrapEnabled ? '0.9' : '0.4'; this.wrapButton.style.background = 'none' })
        this.wrapButton.addEventListener('mousedown', (e) => e.preventDefault())
        this.wrapButton.addEventListener('click', (e) => {
            e.preventDefault()
            this.toggleWrapMode()
        })
        this.wrapButton.style.opacity = this.wrapEnabled ? '0.9' : '0.4'
        toolbar.appendChild(this.wrapButton)
        // Apply saved wrap state
        if (!this.wrapEnabled) {
            this.element.style.whiteSpace = 'pre'
            this.element.style.overflowX = 'auto'
        }
    }

    createHighlightBackdrop() {
        const container = this.element.parentNode
        container.style.position = 'relative'
        this.backdrop = document.createElement('div')
        this.highlightLayer = document.createElement('div')
        this.backdrop.appendChild(this.highlightLayer)
        container.appendChild(this.backdrop)

        // Copy textarea computed styles to backdrop
        const cs = window.getComputedStyle(this.element)
        this.backdrop.style.cssText = `position:absolute;overflow:hidden;pointer-events:none;z-index:1;`
        this.highlightLayer.style.cssText = `white-space:pre-wrap;word-wrap:break-word;overflow-wrap:break-word;`

        const syncStyles = () => {
            const cs = window.getComputedStyle(this.element)
            const props = ['font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing',
                'tab-size', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left']
            props.forEach(p => this.highlightLayer.style[p.replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = cs.getPropertyValue(p))
            this.highlightLayer.style.boxSizing = 'border-box'
            // Use clientWidth to match the textarea's content area (excludes scrollbar)
            this.highlightLayer.style.width = this.element.clientWidth + 'px'
            const borderTop = parseInt(cs.getPropertyValue('border-top-width')) || 0
            const borderLeft = parseInt(cs.getPropertyValue('border-left-width')) || 0
            this.backdrop.style.top = (this.element.offsetTop + borderTop) + 'px'
            this.backdrop.style.left = (this.element.offsetLeft + borderLeft) + 'px'
            this.backdrop.style.width = this.element.clientWidth + 'px'
            this.backdrop.style.height = this.element.clientHeight + 'px'
        }
        syncStyles()

        // Move textarea bg to backdrop, make textarea transparent so backdrop shows through
        const originalBg = cs.getPropertyValue('background-color')
        this.backdrop.style.background = originalBg
        this.element.style.overscrollBehavior = 'none'
        this.element.style.background = 'transparent'
        this.element.style.position = 'relative'
        this.element.style.zIndex = '2'
        const originalColor = cs.color
        this.element.style.caretColor = originalColor
        this.element.style.color = 'transparent'
        this.highlightLayer.style.color = originalColor

        // Sync scroll via transform — no visible jitter since all text is in the highlight layer
        const syncScroll = () => {
            this.highlightLayer.style.transform = `translate(${-this.element.scrollLeft}px, ${-this.element.scrollTop}px)`
        }
        this.element.addEventListener('scroll', syncScroll)

        // Update on input
        this.element.addEventListener('input', () => this.updateHighlight())
        new ResizeObserver(() => { syncStyles(); this.updateHighlight() }).observe(this.element)
        this.updateHighlight()
    }

    colorSpan(colorProp, content) {
        return '<span style="color:rgba(' + this.props[colorProp] + ',1)">' + content + '</span>'
    }

    updateHighlight() {
        const text = this.element.value
        const lines = text.split('\n')
        let html = ''
        let inCodeBlock = false
        let inHtmlComment = false
        let inFrontMatter = false
        // YAML front matter must start at the very first line
        if (lines.length > 0 && lines[0].trim() === '---') {
            inFrontMatter = true
        }

        for (let i = 0; i < lines.length; i++) {
            if (i > 0) html += '\n'
            const line = lines[i]

            // YAML front matter
            if (inFrontMatter) {
                html += this.colorSpan('colorFrontMatter', this.escapeHtml(line))
                if (i > 0 && line.trim() === '---') {
                    inFrontMatter = false
                }
                continue
            }

            // Fenced code block delimiter
            if (/^`{3,}/.test(line)) {
                inCodeBlock = !inCodeBlock
                html += this.colorSpan('colorCode', this.escapeHtml(line))
                continue
            }
            if (inCodeBlock) {
                html += this.colorSpan('colorCode', this.escapeHtml(line))
                continue
            }

            // HTML comment handling (can span multiple lines)
            if (inHtmlComment) {
                const endIdx = line.indexOf('-->')
                if (endIdx !== -1) {
                    inHtmlComment = false
                    html += this.colorSpan('colorComment', this.escapeHtml(line.substring(0, endIdx + 3)))
                    html += this.highlightInline(line.substring(endIdx + 3))
                } else {
                    html += this.colorSpan('colorComment', this.escapeHtml(line))
                }
                continue
            }
            if (line.trimStart().startsWith('<!--')) {
                const endIdx = line.indexOf('-->', line.indexOf('<!--') + 4)
                if (endIdx !== -1) {
                    // Single-line comment
                    html += this.colorSpan('colorComment', this.escapeHtml(line))
                } else {
                    // Multi-line comment starts
                    inHtmlComment = true
                    html += this.colorSpan('colorComment', this.escapeHtml(line))
                }
                continue
            }

            // Horizontal rule (3+ of same -, *, or _ with optional spaces)
            if (/^\s{0,3}([-*_])\s*(\1\s*){2,}$/.test(line)) {
                html += this.colorSpan('colorHorizontalRule', this.escapeHtml(line))
                continue
            }

            // Headings
            const headingMatch = line.match(/^(#{1,6}) /)
            if (headingMatch) {
                const opacity = Math.max(0.3, 1 - (headingMatch[1].length - 1) * 0.1)
                html += '<span style="color:rgba(' + this.props.colorHeading + ',' + opacity + ')">' + this.escapeHtml(line) + '</span>'
                continue
            }

            // Reference link definition [ref]: url
            const refMatch = line.match(/^(\s{0,3}\[)([^\]]+)(\]:\s+)(.+)$/)
            if (refMatch) {
                html += this.colorSpan('colorLink', this.escapeHtml(refMatch[1]))
                    + this.colorSpan('colorLink', this.escapeHtml(refMatch[2]))
                    + this.colorSpan('colorLink', this.escapeHtml(refMatch[3]))
                    + this.colorSpan('colorLink', this.escapeHtml(refMatch[4]))
                continue
            }

            // Blockquote prefix
            let prefix = ''
            let rest = line
            const bqMatch = line.match(/^(\s*>+\s?)/)
            if (bqMatch) {
                prefix = this.colorSpan('colorBlockquote', this.escapeHtml(bqMatch[0]))
                rest = line.substring(bqMatch[0].length)
            }

            html += prefix + this.highlightInline(rest)
        }
        // Trailing newline so the backdrop height matches the textarea
        this.highlightLayer.innerHTML = html + '\n'
    }

    highlightInline(line) {
        // Split by inline code to protect code content from other highlighting
        const segments = []
        let lastIndex = 0
        const codeRegex = /`([^`]+)`/g
        let match

        while ((match = codeRegex.exec(line)) !== null) {
            if (match.index > lastIndex) {
                segments.push({type: 'text', content: line.substring(lastIndex, match.index)})
            }
            segments.push({type: 'code', content: match[0]})
            lastIndex = codeRegex.lastIndex
        }
        if (lastIndex < line.length) {
            segments.push({type: 'text', content: line.substring(lastIndex)})
        }
        if (segments.length === 0) {
            segments.push({type: 'text', content: ''})
        }

        let result = ''
        for (const seg of segments) {
            if (seg.type === 'code') {
                result += this.colorSpan('colorCode', this.escapeHtml(seg.content))
            } else {
                result += this.highlightTextSegment(this.escapeHtml(seg.content))
            }
        }
        return result
    }

    highlightTextSegment(escaped) {
        let result = escaped
        const c = (prop) => this.props[prop]

        // Escape sequences: dim the backslash before markdown punctuation
        result = result.replace(/\\([\\`*_{}[\]()#+\-.!~|])/g,
            '<span style="color:rgba(' + c('colorEscape') + ',1)">\\</span>$1')

        // Unordered list markers with optional task list checkbox
        result = result.replace(/^((?:\t|  )*)(- )(\[[ xX]\] )?/, (_, tabs, marker, task) => {
            let r = tabs + this.colorSpan('colorList', marker)
            if (task) {
                r += this.colorSpan('colorList', task)
            }
            return r
        })

        // Ordered list markers
        result = result.replace(/^((?:\t|  )*)(\d+\. )/, (_, tabs, marker) =>
            tabs + this.colorSpan('colorList', marker))

        // Images ![alt](url) and Links [text](url)
        result = result.replace(/(!?\[)(.*?)(\]\()(.+?)(\))/g, (_, p1, p2, p3, p4, p5) =>
            this.colorSpan('colorLink', p1) + this.colorSpan('colorLink', p2) + this.colorSpan('colorLink', p3) + this.colorSpan('colorLink', p4) + this.colorSpan('colorLink', p5))

        // Reference links [text][ref]
        result = result.replace(/(\[)(.*?)(\]\[)(.*?)(\])/g, (_, p1, p2, p3, p4, p5) =>
            this.colorSpan('colorLink', p1) + this.colorSpan('colorLink', p2) + this.colorSpan('colorLink', p3) + this.colorSpan('colorLink', p4) + this.colorSpan('colorLink', p5))

        // Strikethrough ~~text~~
        result = result.replace(/(~~)(.*?)(~~)/g, (_, p1, p2, p3) =>
            this.colorSpan('colorStrikethrough', p1) + this.colorSpan('colorStrikethrough', p2) + this.colorSpan('colorStrikethrough', p3))

        // Bold **text**
        result = result.replace(/(\*\*)(.*?)(\*\*)/g, (_, p1, p2, p3) =>
            this.colorSpan('colorBold', p1) + this.colorSpan('colorBold', p2) + this.colorSpan('colorBold', p3))

        // Italic _text_ or *text* (single asterisk, after bold has been handled)
        result = result.replace(/((?:^|[^\\*]))(\_)(.*?[^\\])(\_)/g, (_, pre, p1, p2, p3) =>
            pre + this.colorSpan('colorItalic', p1) + this.colorSpan('colorItalic', p2) + this.colorSpan('colorItalic', p3))
        result = result.replace(/((?:^|[^\\*]))(\*)((?!\*).+?[^\\])(\*)/g, (_, pre, p1, p2, p3) =>
            pre + this.colorSpan('colorItalic', p1) + this.colorSpan('colorItalic', p2) + this.colorSpan('colorItalic', p3))

        // HTML tags
        result = result.replace(/(&lt;)(\/?[a-zA-Z]\w*)(.*?)(&gt;)/g, (_, p1, p2, p3, p4) =>
            this.colorSpan('colorHtmlTag', p1 + p2 + p3 + p4))

        return result
    }

    toggleWrapMode() {
        this.wrapEnabled = !this.wrapEnabled
        localStorage.setItem(this.wrapStorageKey, this.wrapEnabled)
        const wrap = this.wrapEnabled
        this.element.style.whiteSpace = wrap ? 'pre-wrap' : 'pre'
        this.element.style.overflowX = wrap ? 'hidden' : 'auto'
        this.highlightLayer.style.whiteSpace = wrap ? 'pre-wrap' : 'pre'
        this.highlightLayer.style.overflowWrap = wrap ? 'break-word' : 'normal'
        this.wrapButton.style.opacity = wrap ? '0.9' : '0.4'
        this.updateHighlight()
    }

    escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    }

    getCurrentLineInfo() {
        const start = this.element.selectionStart
        const text = this.element.value
        const lineStart = start === 0 ? 0 : text.lastIndexOf('\n', start - 1) + 1
        let lineEnd = text.indexOf('\n', start)
        if (lineEnd === -1) lineEnd = text.length
        const line = text.substring(lineStart, lineEnd)
        return {lineStart, lineEnd, line}
    }

    selectLineRange(lineStart, lineEnd) {
        this.element.selectionStart = lineStart
        this.element.selectionEnd = lineEnd
    }

    toggleHeading(level) {
        this.element.focus()
        const {lineStart, lineEnd, line} = this.getCurrentLineInfo()
        const prefix = '#'.repeat(level) + ' '
        const headingMatch = line.match(/^(#{1,6}) /)
        this.selectLineRange(lineStart, lineEnd)
        if (headingMatch && headingMatch[1].length === level) {
            this.insertTextAtCursor(line.substring(prefix.length))
        } else if (headingMatch) {
            this.insertTextAtCursor(prefix + line.substring(headingMatch[0].length))
        } else {
            this.insertTextAtCursor(prefix + line)
        }
    }

    toggleWrap(marker) {
        const start = this.element.selectionStart
        const end = this.element.selectionEnd
        const text = this.element.value
        const len = marker.length
        const before = text.substring(start - len, start)
        const after = text.substring(end, end + len)
        if (before === marker && after === marker) {
            // Remove markers, keep selection on the inner text
            this.element.selectionStart = start - len
            this.element.selectionEnd = end + len
            const selected = text.substring(start, end)
            this.insertTextAtCursor(selected)
            this.element.selectionStart = start - len
            this.element.selectionEnd = end - len
        } else if (start !== end) {
            // Wrap selection, keep selection on the inner text
            this.insertTextAtCursor(marker + text.substring(start, end) + marker)
            this.element.selectionStart = start + len
            this.element.selectionEnd = end + len
        } else {
            this.insertTextAtCursor(marker + marker)
            this.element.selectionStart = this.element.selectionEnd = start + len
        }
    }

    toggleBold() {
        this.toggleWrap('**')
    }

    toggleItalic() {
        this.toggleWrap('_')
    }

    insertUnorderedList() {
        const {lineStart, lineEnd, line} = this.getCurrentLineInfo()
        this.selectLineRange(lineStart, lineEnd)
        if (line.startsWith('- ')) {
            this.insertTextAtCursor(line.substring(2))
        } else {
            this.insertTextAtCursor('- ' + line)
        }
    }

    insertOrderedList() {
        const {lineStart, lineEnd, line} = this.getCurrentLineInfo()
        this.selectLineRange(lineStart, lineEnd)
        const olMatch = line.match(/^\d+\. /)
        if (olMatch) {
            this.insertTextAtCursor(line.substring(olMatch[0].length))
        } else {
            this.insertTextAtCursor('1. ' + line)
        }
    }

    insertLink() {
        const start = this.element.selectionStart
        const end = this.element.selectionEnd
        const selected = this.element.value.substring(start, end)
        const url = prompt('Enter URL:')
        if (url === null) return
        const linkText = selected || 'link'
        this.element.focus()
        this.selectLineRange(start, end)
        this.insertTextAtCursor('[' + linkText + '](' + url + ')')
        this.element.selectionStart = start + 1
        this.element.selectionEnd = start + 1 + linkText.length
    }

    insertImage() {
        const start = this.element.selectionStart
        const end = this.element.selectionEnd
        const selected = this.element.value.substring(start, end)
        const url = prompt('Enter image URL:')
        if (url === null) return
        const altText = selected || 'image'
        this.element.focus()
        this.selectLineRange(start, end)
        this.insertTextAtCursor('![' + altText + '](' + url + ')')
        this.element.selectionStart = start + 2
        this.element.selectionEnd = start + 2 + altText.length
    }

    insertTextAtCursor(text) {
        // execCommand is deprecated, but without alternative to insert text and preserve the correct undo/redo stack
        document.execCommand("insertText", false, text)
    }

    handleKeyDown(e) {
        const start = this.element.selectionStart
        const end = this.element.selectionEnd
        const before = this.element.value.substring(0, start)
        const selected = this.element.value.substring(start, end)
        const currentLine = before.substring(before.lastIndexOf('\n') + 1)
        const isListMode = currentLine.match(/^\t*- /) || currentLine.match(/^\t*\d+\. /)
        if (e.key === 'Tab') {
            e.preventDefault()
            if (isListMode) {
                if (!e.shiftKey) {
                    this.insertTabAtLineStart()
                } else {
                    this.removeTab()
                }
            } else {
                this.insertTabAtCursorPosition()
            }
        } else if (e.key === 'Enter') {
            this.handleEnterKey(e)
        } else if (e.ctrlKey || e.metaKey) {
            if (e.key === 'b') { // bold
                e.preventDefault()
                this.toggleBold()
            } else if (e.key === 'i') { // italic
                e.preventDefault()
                this.toggleItalic()
            } else if (e.key === 'e') { // game todo this could be an extension
                e.preventDefault()
                this.insertTextAtCursor('[game id="' + selected + '"]')
                this.element.selectionStart = start + 10
                this.element.selectionEnd = start + 10 + selected.length
            }
        }
    }

    handleEnterKey(e) {
        const start = this.element.selectionStart
        const before = this.element.value.substring(0, start)
        const currentLine = before.substring(before.lastIndexOf('\n') + 1)
        const matchEmptyUl = currentLine.match(/^(\s*- )$/)
        const matchEmptyOl = currentLine.match(/^(\s*)\d+\. $/)
        const matchHyphen = currentLine.match(/^(\s*- )/)
        const matchOl = currentLine.match(/^(\s*)(\d+)\. ./)
        if (matchEmptyUl) {
            this.element.selectionStart = this.element.selectionEnd - matchEmptyUl[1].length - 1
        } else if (matchEmptyOl) {
            this.element.selectionStart = this.element.selectionEnd - matchEmptyOl[0].length - 1
        } else if (matchHyphen) {
            e.preventDefault()
            this.insertTextAtCursor('\n' + matchHyphen[1])
        } else if (matchOl) {
            e.preventDefault()
            const nextNum = parseInt(matchOl[2]) + 1
            this.insertTextAtCursor('\n' + matchOl[1] + nextNum + '. ')
        }
    }

    insertTabAtCursorPosition() {
        this.insertTextAtCursor('\t')
    }

    insertTabAtLineStart() {
        const start = this.element.selectionStart
        const before = this.element.value.substring(0, start)
        const lineStart = before.lastIndexOf('\n') + 1
        this.element.selectionStart = this.element.selectionEnd = lineStart
        this.insertTextAtCursor('\t')
        this.element.selectionStart = this.element.selectionEnd = start + 1
    }

    // test

    removeTab() {
        const start = this.element.selectionStart
        const before = this.element.value.substring(0, start)
        const lineStart = before.lastIndexOf('\n') + 1
        const currentLine = before.substring(lineStart)
        if (currentLine.startsWith('\t')) {
            this.element.selectionStart = lineStart
            this.element.selectionEnd = lineStart + 1
            this.insertTextAtCursor("")
            this.element.selectionStart = this.element.selectionEnd = start - 1
        }
    }
}
