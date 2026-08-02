/**
 * Author: Stefan Haack (https://shaack.com)
 * Date: 2023-11-12
 */
import {defaultTools} from "./tools/DefaultTools.js"

export class MdEditor {

    // Default unit of list indentation. Four spaces per level; a tab or a legacy two-space
    // level is still accepted when reading existing text (see removeTab).
    // Configurable per instance through the listIndent prop.
    static LIST_INDENT = '    '

    // Unique-id counter for per-instance accessibility elements (aria-describedby).
    static instanceCount = 0

    constructor(element, props) {
        this.element = element
        this.props = {
            listIndent: MdEditor.LIST_INDENT,
            // Tab indents list lines / inserts a tab. Set false for plain textarea
            // behaviour, where Tab moves focus to the next control. When true, the
            // editor still stays keyboard-escapable: pressing Escape arms a one-shot
            // release so the next Tab/Shift+Tab moves focus (WCAG 2.1.2).
            indentWithTab: true,
            // Screen-reader hint (aria-describedby) telling users how to move focus
            // out of the editor with the keyboard. Only used when indentWithTab.
            tabReleaseHint: 'Tab indents list items. Press Escape, then Tab, to move focus out of the editor.',
            // Optional accessible name applied to the textarea.
            ariaLabel: null,
            // Toolbar chrome tint (RGB triplet), applied at low alpha to the toolbar
            // background, borders, separators and button hover states.
            colorChrome: "128,128,128",
            colorHeading: "100,160,255",
            colorCode: "130,170,200",
            colorComment: "128,128,128",
            colorLink: "100,180,220",
            colorBlockquote: "100,200,150",
            colorList: "100,200,150",
            colorStrikethrough: "255,100,100",
            colorHighlight: "230,200,90",
            colorBold: "255,180,80",
            colorItalic: "180,130,255",
            colorHtmlTag: "100,160,255",
            colorHtmlTagBracket: "100,200,150",
            colorHtmlTagAttribute: "180,130,255",
            colorHtmlTagValue: "255,180,80",
            colorHorizontalRule: "128,128,200",
            colorEscape: "128,128,128",
            colorFrontMatter: "128,128,200",
            wordWrap: true,
            iconsPath: new URL('./tools/icons/', import.meta.url).href,
            tools: defaultTools,
            ...props
        }
        this.tools = this.props.tools.map(entry => {
            if (Array.isArray(entry)) {
                const [Tool, toolProps] = entry
                return new Tool(this, toolProps)
            }
            return new entry(this)
        })
        // --- Undo/redo history -------------------------------------------------
        // A self-contained undo stack. We deliberately do NOT use the browser's
        // native undo (document.execCommand('undo')): it is deprecated and, in
        // WebKit/Safari especially, unreliable once the field has been edited
        // programmatically. It can report success (execCommand returns true) yet
        // silently change nothing, which leaves Cmd-Z dead. Tracking our own
        // {value, selectionStart, selectionEnd} snapshots works identically in
        // every browser and regardless of the surrounding page.
        this.historyUndo = []
        this.historyRedo = []
        this.historyMax = 500
        this.historyPending = null   // baseline captured before an uncommitted burst of edits
        this.historyDebounce = null
        this.historyApplying = false // guard: suppress recording while we restore a snapshot
        this.historyLast = this.historySnapshot()
        // One-shot flag: Escape sets it so the next Tab moves focus instead of
        // indenting; any editing key clears it again (see handleKeyDown).
        this.tabMovesFocus = false
        this.element.addEventListener('keydown', (e) => this.handleKeyDown(e))
        this.element.addEventListener('blur', () => this.tabMovesFocus = false)
        this.createToolbar()
        this.createHighlightBackdrop()
        if (!this.wrapEnabled && this.highlightLayer) {
            this.highlightLayer.style.whiteSpace = 'pre'
            this.highlightLayer.style.overflowWrap = 'normal'
        }
        this.setupAccessibility()
    }

    setupAccessibility() {
        if (this.props.ariaLabel) {
            this.element.setAttribute('aria-label', this.props.ariaLabel)
        }
        // With plain-textarea Tab behaviour there is no key trap to advise about.
        if (!this.props.indentWithTab) return
        this.element.setAttribute('aria-keyshortcuts', 'Escape')
        // A visually hidden hint, announced by screen readers on focus, that tells
        // users how to leave the editor with the keyboard (WCAG 2.1.2 requires the
        // escape method to be advertised).
        const hint = document.createElement('span')
        hint.id = 'md-editor-tabhelp-' + (MdEditor.instanceCount++)
        hint.textContent = this.props.tabReleaseHint
        hint.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;' +
            'margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;'
        this.element.insertAdjacentElement('afterend', hint)
        const existing = this.element.getAttribute('aria-describedby')
        this.element.setAttribute('aria-describedby', existing ? existing + ' ' + hint.id : hint.id)
    }

    createToolbar() {
        const wrapper = document.createElement('div')
        this.element.parentNode.insertBefore(wrapper, this.element)
        wrapper.appendChild(this.element)
        // Flex column so the toolbar can sit AFTER the textarea in the DOM (tab
        // order: textarea before toolbar — tabbing from a preceding field lands in
        // the text, not the buttons) while CSS order keeps it visually on top.
        wrapper.style.display = 'flex'
        wrapper.style.flexDirection = 'column'
        const toolbar = document.createElement('div')
        const chrome = this.props.colorChrome
        toolbar.style.cssText = `order:-1;display:flex;gap:1px;padding:2px;flex-wrap:wrap;background:rgba(${chrome},0.15);border:1px solid rgba(${chrome},0.3);border-bottom:none;border-radius:4px 4px 0 0;box-sizing:border-box;width:100%;`
        wrapper.appendChild(toolbar)
        this.element.style.borderRadius = '0 0 4px 4px'
        // WAI-ARIA toolbar pattern: one tab stop, arrow keys move between the
        // buttons (roving tabindex), Escape returns to the textarea.
        this.toolbarElement = toolbar
        toolbar.setAttribute('role', 'toolbar')
        toolbar.setAttribute('aria-label', this.props.toolbarLabel || 'Text formatting')
        toolbar.addEventListener('keydown', (event) => this.handleToolbarKeydown(event))
        for (const tool of this.tools) {
            if (typeof tool.toolbarButtons === 'function') {
                tool.toolbarButtons().forEach(btn => this.createToolbarButton(toolbar, btn))
            }
        }

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
        this.wrapButton.style.width = '32px'
        this.wrapButton.style.height = '28px'
        this.loadIcon('text-wrap.svg').then(svg => { this.wrapButton.innerHTML = svg })
        this.wrapButton.addEventListener('mouseenter', () => { this.wrapButton.style.opacity = '1'; this.wrapButton.style.background = `rgba(${chrome},0.2)` })
        this.wrapButton.addEventListener('mouseleave', () => { this.wrapButton.style.opacity = this.wrapEnabled ? '0.9' : '0.4'; this.wrapButton.style.background = 'none' })
        this.wrapButton.addEventListener('mousedown', (e) => e.preventDefault())
        this.wrapButton.addEventListener('click', (e) => {
            e.preventDefault()
            this.toggleWrapMode()
        })
        this.wrapButton.style.opacity = this.wrapEnabled ? '0.9' : '0.4'
        this.wrapButton.tabIndex = -1
        this.wrapButton.setAttribute('aria-label', this.wrapButton.title)
        toolbar.appendChild(this.wrapButton)
        // Apply saved wrap state
        if (!this.wrapEnabled) {
            this.element.style.whiteSpace = 'pre'
            this.element.style.overflowX = 'auto'
        }
        // Roving tabindex: exactly one button is the toolbar's tab stop
        const firstButton = toolbar.querySelector('button')
        if (firstButton) {
            firstButton.tabIndex = 0
        }
    }

    handleToolbarKeydown(event) {
        if (event.key === 'Escape') {
            this.element.focus()
            return
        }
        const buttons = Array.from(this.toolbarElement.querySelectorAll('button'))
        const index = buttons.indexOf(document.activeElement)
        if (index === -1) {
            return
        }
        let next = null
        if (event.key === 'ArrowRight') {
            next = buttons[(index + 1) % buttons.length]
        } else if (event.key === 'ArrowLeft') {
            next = buttons[(index - 1 + buttons.length) % buttons.length]
        } else if (event.key === 'Home') {
            next = buttons[0]
        } else if (event.key === 'End') {
            next = buttons[buttons.length - 1]
        }
        if (next) {
            event.preventDefault()
            // move the single tab stop with the focus, so re-entering the toolbar
            // returns to the last used button
            buttons.forEach(button => button.tabIndex = -1)
            next.tabIndex = 0
            next.focus()
        }
    }

    createToolbarButton(toolbar, btn) {
        const chrome = this.props.colorChrome
        if (btn.separator) {
            const sep = document.createElement('div')
            sep.style.cssText = `width:1px;align-self:stretch;margin:3px 5px;background:rgba(${chrome},0.4);`
            toolbar.appendChild(sep)
            return
        }
        const button = document.createElement('button')
        button.type = 'button'
        button.title = btn.title
        button.tabIndex = -1 // roving tabindex, see createToolbar/handleToolbarKeydown
        if (btn.title) {
            button.setAttribute('aria-label', btn.title)
        }
        if (btn.name) {
            button.dataset.name = btn.name
            button.classList.add('mde-btn-' + btn.name)
        }
        button.style.cssText = 'background:none;border:none;border-radius:3px;cursor:pointer;padding:4px 6px;display:flex;align-items:center;justify-content:center;color:inherit;opacity:0.6;transition:opacity 0.15s,background 0.15s;'
        if (btn.icon) {
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor">${btn.icon}</svg>`
        } else if (btn.iconUrl) {
            button.style.width = '32px'
            button.style.height = '28px'
            this.loadIconFromUrl(btn.iconUrl).then(svg => { button.innerHTML = svg })
        } else if (btn.iconFile) {
            button.style.width = '32px'
            button.style.height = '28px'
            this.loadIcon(btn.iconFile).then(svg => { button.innerHTML = svg })
        }
        button.addEventListener('mouseenter', () => { button.style.opacity = '1'; button.style.background = `rgba(${chrome},0.2)` })
        button.addEventListener('mouseleave', () => { button.style.opacity = '0.6'; button.style.background = 'none' })
        button.addEventListener('mousedown', (e) => e.preventDefault())
        button.addEventListener('click', (e) => {
            e.preventDefault()
            btn.action()
        })
        toolbar.appendChild(button)
    }

    loadIcon(filename) {
        const base = this.props.iconsPath.startsWith('http')
            ? this.props.iconsPath
            : new URL(this.props.iconsPath, window.location.href).href
        return this.loadIconFromUrl(new URL(filename, base).href)
    }

    loadIconFromUrl(url) {
        return fetch(url)
            .then(r => r.text())
            .then(svg => svg.replace(/width="16"/, 'width="20"').replace(/height="16"/, 'height="20"'))
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
        this.backdrop.style.background = cs.getPropertyValue('background-color')
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
        this.element.addEventListener('input', () => {
            this.recordHistory()
            this.updateHighlight()
        })
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

        // Per-pass context handed to line-highlighting plugins (tools with a highlightLine hook).
        // `state` is a fresh, mutable object that persists across the lines of this pass, so a
        // plugin can track block state (e.g. being inside a custom wrapper). `highlightInline`
        // and `escapeHtml` are the built-in helpers, so a plugin can reuse the default rendering
        // (optionally suppressing a rule via opts, e.g. {skipOrderedList: true}).
        const ctx = {
            state: {},
            lineIndex: 0,
            escapeHtml: (s) => this.escapeHtml(s),
            highlightInline: (l, opts) => this.highlightInline(l, opts)
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

            // Line-highlighting plugins: a tool may fully render this line, e.g. to suppress a
            // built-in rule inside its own block. Runs after the structural code / comment /
            // front-matter states, so plugins only see normal content lines. A returned string
            // is used verbatim and the built-in rules below are skipped for this line.
            ctx.lineIndex = i
            let handledByPlugin = false
            for (const tool of this.tools) {
                if (typeof tool.highlightLine === 'function') {
                    const rendered = tool.highlightLine(line, ctx)
                    if (typeof rendered === 'string') {
                        html += rendered
                        handledByPlugin = true
                        break
                    }
                }
            }
            if (handledByPlugin) continue

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

    highlightInline(line, opts = {}) {
        // Split the line into protected tokens (inline code and bare URLs) and plain text.
        // Protected tokens are emitted verbatim (only HTML-escaped), so markdown inside them
        // — e.g. the underscores in https://host/a_b_c — is never treated as formatting.
        const segments = []
        let lastIndex = 0
        // Inline code, OR an autolink: an http(s) URL not part of markdown link / html
        // attribute syntax (that context is handled by highlightTextSegment instead).
        const tokenRegex = /`[^`]+`|https?:\/\/[^\s<>()\[\]"'`]+/g
        let match

        while ((match = tokenRegex.exec(line)) !== null) {
            const token = match[0]
            const isCode = token[0] === '`'
            if (!isCode) {
                // Only autolink a *bare* URL. When the character before it belongs to
                // markdown/html syntax, leave the URL in the text so its surrounding
                // construct (e.g. [text](url)) is highlighted as a whole.
                const prev = match.index > 0 ? line[match.index - 1] : ''
                if (prev === '(' || prev === '<' || prev === '"' || prev === "'" || prev === ']') {
                    continue
                }
            }
            if (match.index > lastIndex) {
                segments.push({type: 'text', content: line.substring(lastIndex, match.index)})
            }
            segments.push({type: isCode ? 'code' : 'url', content: token})
            lastIndex = tokenRegex.lastIndex
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
            } else if (seg.type === 'url') {
                result += '<span style="color:rgba(' + this.props.colorLink + ',1);text-decoration:underline">'
                    + this.escapeHtml(seg.content) + '</span>'
            } else {
                result += this.highlightTextSegment(this.escapeHtml(seg.content), opts)
            }
        }
        return result
    }

    highlightTextSegment(escaped, opts = {}) {
        let result = escaped
        const c = (prop) => this.props[prop]

        // Escape sequences: dim the backslash before markdown punctuation and emit the
        // escaped character as a numeric HTML entity. The entity renders as the literal
        // character but is no longer a bare '*'/'_'/'['… delimiter, so the inline rules
        // below leave it alone (e.g. \_not italic\_ stays literal, not italicised).
        result = result.replace(/\\([\\`*_{}[\]()#+\-.!~|])/g, (_, ch) =>
            '<span style="color:rgba(' + c('colorEscape') + ',1)">\\</span>&#' + ch.charCodeAt(0) + ';')

        // Unordered list markers with optional task list checkbox
        result = result.replace(/^([\t ]*)(- )(\[[ xX]\] )?/, (_, tabs, marker, task) => {
            let r = tabs + this.colorSpan('colorList', marker)
            if (task) {
                r += this.colorSpan('colorList', task)
            }
            return r
        })

        // Ordered list markers (a plugin can suppress this rule for a line via skipOrderedList,
        // e.g. to keep "1. e4" as plain text outside an explicit list wrapper)
        if (!opts.skipOrderedList) {
            result = result.replace(/^([\t ]*)(\d+\. )/, (_, tabs, marker) =>
                tabs + this.colorSpan('colorList', marker))
        }

        // Images ![alt](url) and Links [text](url)
        result = result.replace(/(!?\[)(.*?)(\]\()(.+?)(\))/g, (_, p1, p2, p3, p4, p5) =>
            this.colorSpan('colorLink', p1) + this.colorSpan('colorLink', p2) + this.colorSpan('colorLink', p3) + this.colorSpan('colorLink', p4) + this.colorSpan('colorLink', p5))

        // Reference links [text][ref]
        result = result.replace(/(\[)(.*?)(\]\[)(.*?)(\])/g, (_, p1, p2, p3, p4, p5) =>
            this.colorSpan('colorLink', p1) + this.colorSpan('colorLink', p2) + this.colorSpan('colorLink', p3) + this.colorSpan('colorLink', p4) + this.colorSpan('colorLink', p5))

        // Strikethrough ~~text~~
        result = result.replace(/(~~)(.*?)(~~)/g, (_, p1, p2, p3) =>
            this.colorSpan('colorStrikethrough', p1) + this.colorSpan('colorStrikethrough', p2) + this.colorSpan('colorStrikethrough', p3))

        // Highlight ==text==
        result = result.replace(/(==)(.*?)(==)/g, (_, p1, p2, p3) =>
            this.colorSpan('colorHighlight', p1) + this.colorSpan('colorHighlight', p2) + this.colorSpan('colorHighlight', p3))

        // Bold **text**
        result = result.replace(/(\*\*)(.*?)(\*\*)/g, (_, p1, p2, p3) =>
            this.colorSpan('colorBold', p1) + this.colorSpan('colorBold', p2) + this.colorSpan('colorBold', p3))

        // Italic _text_ or *text* (single asterisk, after bold has been handled)
        result = result.replace(/((?:^|[^\\*]))(\_)(.*?[^\\])(\_)/g, (_, pre, p1, p2, p3) =>
            pre + this.colorSpan('colorItalic', p1) + this.colorSpan('colorItalic', p2) + this.colorSpan('colorItalic', p3))
        result = result.replace(/((?:^|[^\\*]))(\*)((?!\*).+?[^\\])(\*)/g, (_, pre, p1, p2, p3) =>
            pre + this.colorSpan('colorItalic', p1) + this.colorSpan('colorItalic', p2) + this.colorSpan('colorItalic', p3))

        // HTML tags, three-tone: syntax characters (< / > = " ') in colorHtmlTagBracket,
        // tag name in colorHtmlTag, attribute names in colorHtmlTagAttribute, attribute
        // values in colorHtmlTagValue
        result = result.replace(/(&lt;\/?)([a-zA-Z]\w*)(.*?)(\/?&gt;)/g, (_, p1, p2, p3, p4) => {
            const attrs = p3.replace(/([\w-]+)(\s*=\s*)(["'])(.*?)\3/g, (m, name, eq, quote, value) =>
                this.colorSpan('colorHtmlTagAttribute', name)
                + this.colorSpan('colorHtmlTagBracket', eq + quote)
                + this.colorSpan('colorHtmlTagValue', value)
                + this.colorSpan('colorHtmlTagBracket', quote))
            return this.colorSpan('colorHtmlTagBracket', p1)
                + this.colorSpan('colorHtmlTag', p2)
                + this.colorSpan('colorHtmlTag', attrs)
                + this.colorSpan('colorHtmlTagBracket', p4)
        })

        // Tool inline highlighting
        for (const tool of this.tools) {
            if (typeof tool.highlightInline === 'function') {
                result = tool.highlightInline(result)
            }
        }

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

    insertTextAtCursor(text) {
        // Replace the current selection (or insert at the caret) via the standard
        // setRangeText API and notify listeners with an input event. We no longer
        // use document.execCommand("insertText"): it is deprecated and unreliable
        // in Safari, and the editor keeps its own undo history (see below), so the
        // native undo stack no longer needs to be preserved.
        this.element.focus()
        const start = this.element.selectionStart
        const end = this.element.selectionEnd
        this.element.setRangeText(text, start, end, 'end')
        this.element.dispatchEvent(new InputEvent('input', {bubbles: true}))
    }

    // --- Undo/redo ----------------------------------------------------------

    historySnapshot() {
        return {
            value: this.element.value,
            selectionStart: this.element.selectionStart,
            selectionEnd: this.element.selectionEnd
        }
    }

    // Called on every input event. Remembers the state *before* the current burst
    // of edits and, after a short idle pause, commits it as one undo step, so that
    // continuous typing collapses into sensible chunks instead of one step per
    // character. Any pending burst is also flushed by undo()/redo() before they act.
    recordHistory() {
        if (this.historyApplying) return
        if (this.historyPending === null) {
            this.historyPending = this.historyLast
        }
        this.historyRedo = []
        if (this.historyDebounce) clearTimeout(this.historyDebounce)
        this.historyDebounce = setTimeout(() => this.commitHistory(), 400)
        this.historyLast = this.historySnapshot()
    }

    // Discard the undo/redo history and treat the current textarea value as the
    // new baseline. Call this after replacing the content programmatically (e.g.
    // after loading or saving a document) so the old content can't be undone into.
    resetHistory() {
        this.historyUndo = []
        this.historyRedo = []
        this.historyPending = null
        if (this.historyDebounce) {
            clearTimeout(this.historyDebounce)
            this.historyDebounce = null
        }
        this.historyLast = this.historySnapshot()
    }

    commitHistory() {
        if (this.historyDebounce) {
            clearTimeout(this.historyDebounce)
            this.historyDebounce = null
        }
        if (this.historyPending === null) return
        this.historyUndo.push(this.historyPending)
        if (this.historyUndo.length > this.historyMax) this.historyUndo.shift()
        this.historyPending = null
    }

    restoreHistory(state) {
        this.historyApplying = true
        const previous = this.element.value
        this.element.focus()
        this.element.value = state.value
        // Place the caret at the change site (end of the differing region), not at
        // a stored position. Restoring the pre-edit selection would, for the very
        // first snapshot, jump the caret to 0/0 (top-left) on the last undo.
        const caret = this.changeCaret(previous, state.value)
        this.element.selectionStart = this.element.selectionEnd = caret
        this.updateHighlight()
        // Let the host page react (dirty tracking, live preview, …). The guard
        // above keeps this synthetic input out of the history.
        this.element.dispatchEvent(new InputEvent('input', {bubbles: true}))
        this.historyApplying = false
        this.historyLast = this.historySnapshot()
    }

    // Index at the end of the region where strings `a` and `b` differ, derived
    // from their common prefix and suffix. Used to position the caret after an
    // undo/redo so it lands where the (un)done edit was.
    changeCaret(a, b) {
        const la = a.length, lb = b.length
        const min = Math.min(la, lb)
        let prefix = 0
        while (prefix < min && a[prefix] === b[prefix]) prefix++
        let suffix = 0
        while (suffix < min - prefix && a[la - 1 - suffix] === b[lb - 1 - suffix]) suffix++
        return lb - suffix
    }

    undo() {
        this.commitHistory()
        if (this.historyUndo.length === 0) return
        this.historyRedo.push(this.historySnapshot())
        this.restoreHistory(this.historyUndo.pop())
    }

    redo() {
        this.commitHistory()
        if (this.historyRedo.length === 0) return
        this.historyUndo.push(this.historySnapshot())
        this.restoreHistory(this.historyRedo.pop())
    }

    handleKeyDown(e) {
        // Accessibility (WCAG 2.1.2, "No Keyboard Trap"): Escape arms a one-shot
        // release so the next Tab / Shift+Tab moves focus out of the editor instead
        // of indenting. Any other key re-arms indentation. Bare modifier keys must
        // NOT disarm it, otherwise Shift+Tab (which fires a Shift keydown first)
        // could never escape.
        if (this.props.indentWithTab) {
            if (e.key === 'Escape') {
                this.tabMovesFocus = true
            } else if (e.key !== 'Tab' && !['Shift', 'Control', 'Alt', 'Meta'].includes(e.key)) {
                this.tabMovesFocus = false
            }
        }
        // Undo/redo via our own history stack (see constructor and undo()/redo()).
        // Cmd-Z / Cmd-Shift-Z (macOS) and Ctrl-Z / Ctrl-Y (Windows/Linux).
        const undoKey = e.key.toLowerCase()
        if ((e.ctrlKey || e.metaKey) && (undoKey === 'z' || undoKey === 'y')) {
            // Swallow the event fully — this both drives our own undo and stops the
            // browser from performing stray tab/history actions on Cmd-Z (a real
            // Safari behaviour when its native undo is unavailable).
            e.preventDefault()
            e.stopPropagation()
            if (undoKey === 'y' || e.shiftKey) {
                this.redo()
            } else {
                this.undo()
            }
            return
        }
        // Alt+Up/Down: move the current line(s) up or down. Works on any line, not only
        // list items. Option+Arrow is a plain text-navigation key, so preventDefault
        // reliably suppresses the default and it triggers no macOS system beep (unlike
        // Cmd-based combos). Indent/outdent stays on Tab / Shift-Tab.
        if (e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
            if (e.key === 'ArrowUp') {
                e.preventDefault()
                this.moveLines(-1)
                return
            } else if (e.key === 'ArrowDown') {
                e.preventDefault()
                this.moveLines(1)
                return
            }
        }
        if (e.key === 'Tab') {
            // Let Tab move focus when indentation is disabled, or once Escape has
            // armed the one-shot release. Not calling preventDefault lets the
            // browser perform its native focus move.
            if (!this.props.indentWithTab || this.tabMovesFocus) {
                this.tabMovesFocus = false
                return
            }
            // Indent (Tab) or outdent (Shift+Tab) the current line, in lists and
            // everywhere else alike.
            e.preventDefault()
            if (e.shiftKey) {
                this.removeTab()
            } else {
                this.insertTabAtLineStart()
            }
        } else if (e.key === 'Enter') {
            this.handleEnterKey(e)
        } else if (e.ctrlKey || e.metaKey) {
            for (const tool of this.tools) {
                if (typeof tool.keyboardShortcuts === 'function') {
                    for (const shortcut of tool.keyboardShortcuts()) {
                        if (shortcut.ctrlOrMeta && shortcut.key === e.key) {
                            e.preventDefault()
                            shortcut.action(e)
                            return
                        }
                    }
                }
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

    // Indent the current line by one level (props.listIndent). In lists this nests the
    // item under its parent; on any other line it simply indents the line.
    insertTabAtLineStart() {
        const start = this.element.selectionStart
        const before = this.element.value.substring(0, start)
        const lineStart = before.lastIndexOf('\n') + 1
        this.element.selectionStart = this.element.selectionEnd = lineStart
        const indent = this.props.listIndent
        this.insertTextAtCursor(indent)
        this.element.selectionStart = this.element.selectionEnd = start + indent.length
    }

    removeTab() {
        const start = this.element.selectionStart
        const before = this.element.value.substring(0, start)
        const lineStart = before.lastIndexOf('\n') + 1
        const currentLine = before.substring(lineStart)
        // Remove one level of indentation: the configured unit, or a legacy tab / two spaces.
        let removeLen = 0
        if (currentLine.startsWith(this.props.listIndent)) {
            removeLen = this.props.listIndent.length
        } else if (currentLine.startsWith('\t')) {
            removeLen = 1
        } else if (currentLine.startsWith('  ')) {
            removeLen = 2
        }
        if (removeLen > 0) {
            this.element.selectionStart = lineStart
            this.element.selectionEnd = lineStart + removeLen
            this.insertTextAtCursor("")
            this.element.selectionStart = this.element.selectionEnd = start - removeLen
        }
    }

    // Full-line range covered by the current selection. A selection that ends exactly at a
    // line start does not pull in that following (unselected) line.
    getSelectedLinesRange() {
        const value = this.element.value
        const selStart = this.element.selectionStart
        const selEnd = this.element.selectionEnd
        const blockStart = value.lastIndexOf('\n', selStart - 1) + 1
        let effEnd = selEnd
        if (selEnd > selStart && value[selEnd - 1] === '\n') effEnd = selEnd - 1
        let blockEnd = value.indexOf('\n', effEnd)
        if (blockEnd < 0) blockEnd = value.length
        return {blockStart, blockEnd}
    }

    // Move the selected line block up (dir < 0) or down (dir > 0), swapping it with the
    // neighbouring line. Selection follows the moved block. Uses insertText so the swap
    // lands as a single, undoable edit.
    moveLines(dir) {
        const value = this.element.value
        const selStart = this.element.selectionStart
        const selEnd = this.element.selectionEnd
        const {blockStart, blockEnd} = this.getSelectedLinesRange()
        const blockText = value.substring(blockStart, blockEnd)
        if (dir < 0) {
            if (blockStart === 0) return // already at the top
            const prevStart = value.lastIndexOf('\n', blockStart - 2) + 1
            const prevText = value.substring(prevStart, blockStart - 1)
            this.selectLineRange(prevStart, blockEnd)
            this.insertTextAtCursor(blockText + '\n' + prevText)
            const shift = -(prevText.length + 1)
            this.element.selectionStart = selStart + shift
            this.element.selectionEnd = selEnd + shift
        } else {
            if (blockEnd >= value.length) return // already at the bottom
            const nextStart = blockEnd + 1
            let nextEnd = value.indexOf('\n', nextStart)
            if (nextEnd < 0) nextEnd = value.length
            const nextText = value.substring(nextStart, nextEnd)
            this.selectLineRange(blockStart, nextEnd)
            this.insertTextAtCursor(nextText + '\n' + blockText)
            const shift = nextText.length + 1
            this.element.selectionStart = selStart + shift
            this.element.selectionEnd = selEnd + shift
        }
    }
}
