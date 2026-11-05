/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Minimal YAML syntax highlighting for a textarea with `data-yaml-editor`.
 * Like the Markdown editor, the textarea stays the input and becomes transparent,
 * a layer behind it shows the same text in color. It is a line based highlighter
 * for config files, not a YAML parser: keys, comments, strings, numbers,
 * constants like `true` or `null`, and the punctuation of lists and flow style.
 */
(function () {
    function escapeHtml(text) {
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    }

    function span(type, text) {
        return '<span class="yaml-' + type + '">' + escapeHtml(text) + '</span>'
    }

    function scalar(word) {
        if (/^[-+]?(\d[\d_]*)(\.\d+)?([eE][-+]?\d+)?$|^0x[0-9a-fA-F]+$/.test(word)) {
            return span('number', word)
        }
        if (/^(true|false|yes|no|on|off|null|~)$/i.test(word)) {
            return span('constant', word)
        }
        return escapeHtml(word)
    }

    // Everything after the key of a line, or a list item: scalars, quoted
    // strings, comments and flow style like `{ areas: [ /customers ] }`
    function highlightValue(text) {
        let html = ''
        let word = ''
        const flush = () => {
            if (word) {
                html += scalar(word)
                word = ''
            }
        }
        let i = 0
        while (i < text.length) {
            const c = text[i]
            if (c === '#' && (i === 0 || /\s/.test(text[i - 1]))) {
                flush()
                return html + span('comment', text.slice(i))
            }
            if ((c === '"' || c === "'") && !word) {
                const match = text.slice(i).match(c === '"' ? /^"(?:\\.|[^"\\])*"?/ : /^'(?:''|[^'])*'?/)
                html += span('string', match[0])
                i += match[0].length
                continue
            }
            if ('[]{},'.includes(c)) {
                flush()
                html += span('punct', c)
                i++
                continue
            }
            if (c === ':' && word && (i + 1 === text.length || /\s/.test(text[i + 1]))) {
                // a key inside a flow mapping
                html += span('key', word) + span('punct', ':')
                word = ''
                i++
                continue
            }
            if (/\s/.test(c)) {
                flush()
                html += c
                i++
                continue
            }
            word += c
            i++
        }
        flush()
        return html
    }

    function highlightLine(line) {
        const indent = line.match(/^\s*/)[0]
        let rest = line.slice(indent.length)
        let html = indent
        if (rest.startsWith('#')) {
            return html + span('comment', rest)
        }
        if (rest === '---' || rest === '...') {
            return html + span('punct', rest)
        }
        let match
        while ((match = rest.match(/^-(\s+|$)/))) {
            html += span('punct', '-') + match[1]
            rest = rest.slice(match[0].length)
        }
        match = rest.match(/^("(?:\\.|[^"\\])*"|'(?:''|[^'])*'|[^\s#'"\[\]{},:][^#:]*?)(\s*):(\s|$)/)
        if (match) {
            html += span('key', match[1]) + match[2] + span('punct', ':') + match[3]
            rest = rest.slice(match[0].length)
        }
        return html + highlightValue(rest)
    }

    function highlight(text) {
        // a trailing newline needs content after it, or the layer is one line short
        return text.split('\n').map(highlightLine).join('\n') + (text.endsWith('\n') ? ' ' : '')
    }

    function attach(textarea) {
        const wrapper = document.createElement('div')
        wrapper.className = 'yaml-editor'
        textarea.parentNode.insertBefore(wrapper, textarea)
        const backdrop = document.createElement('div')
        backdrop.className = 'yaml-editor-backdrop'
        backdrop.setAttribute('aria-hidden', 'true')
        const layer = document.createElement('div')
        layer.className = 'yaml-editor-layer'
        backdrop.appendChild(layer)
        wrapper.appendChild(backdrop)
        wrapper.appendChild(textarea)
        textarea.classList.add('yaml-editor-input')

        const syncStyles = () => {
            const cs = window.getComputedStyle(textarea)
            ;['font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'tab-size',
                'padding-top', 'padding-right', 'padding-bottom', 'padding-left'].forEach(property => {
                layer.style.setProperty(property, cs.getPropertyValue(property))
            })
            const borderTop = parseInt(cs.getPropertyValue('border-top-width')) || 0
            const borderLeft = parseInt(cs.getPropertyValue('border-left-width')) || 0
            // clientWidth leaves out the scrollbar, so the lines wrap at the same place
            layer.style.width = textarea.clientWidth + 'px'
            backdrop.style.top = (textarea.offsetTop + borderTop) + 'px'
            backdrop.style.left = (textarea.offsetLeft + borderLeft) + 'px'
            backdrop.style.width = textarea.clientWidth + 'px'
            backdrop.style.height = textarea.clientHeight + 'px'
        }
        const syncScroll = () => {
            layer.style.transform = 'translate(' + (-textarea.scrollLeft) + 'px, ' + (-textarea.scrollTop) + 'px)'
        }
        const update = () => {
            layer.innerHTML = highlight(textarea.value)
            syncScroll()
        }
        textarea.addEventListener('input', update)
        textarea.addEventListener('scroll', syncScroll)
        new ResizeObserver(() => {
            syncStyles()
            update()
        }).observe(textarea)
        syncStyles()
        update()
    }

    document.querySelectorAll('textarea[data-yaml-editor]').forEach(attach)
})()
