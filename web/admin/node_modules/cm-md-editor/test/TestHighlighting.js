/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {makeEditor} from "./EditorHarness.js"

// Highlighting emits inline color spans as rgba(<r,g,b>,...). We assert on the
// raw rgb triplet of the relevant color prop, picking props with unique values.
function segment(editor, text) {
    return editor.highlightTextSegment(editor.escapeHtml(text))
}

// A line-highlighting plugin: ordered-list markers only count inside <list>…</list>.
class ListGate {
    constructor(editor) {
        this.editor = editor
    }
    highlightLine(line, ctx) {
        const t = line.trim()
        if (t === '<list>') { ctx.state.inList = true; return null }
        if (t === '</list>') { ctx.state.inList = false; return null }
        if (!ctx.state.inList && /^\s*\d+\.\s/.test(line)) {
            return ctx.highlightInline(line, {skipOrderedList: true})
        }
        return null
    }
}

describe("TestHighlighting", () => {

    it("should color a heading line", () => {
        const {editor} = makeEditor("# Title")
        editor.updateHighlight()
        assert.true(editor.highlightLayer.innerHTML.includes("rgba(" + editor.props.colorHeading))
    })

    it("should color bold text", () => {
        const {editor} = makeEditor()
        assert.true(segment(editor, "**bold**").includes("255,180,80"))
    })

    it("should color italic text", () => {
        const {editor} = makeEditor()
        assert.true(segment(editor, "_ital_").includes("180,130,255"))
    })

    it("should color strikethrough text", () => {
        const {editor} = makeEditor()
        assert.true(segment(editor, "~~gone~~").includes("255,100,100"))
    })

    it("should color highlighted text", () => {
        const {editor} = makeEditor()
        assert.true(segment(editor, "==hi==").includes("230,200,90"))
    })

    it("should color inline code", () => {
        const {editor} = makeEditor()
        assert.true(editor.highlightInline("`code`").includes("130,170,200"))
    })

    it("should color a link", () => {
        const {editor} = makeEditor()
        assert.true(segment(editor, "[text](http://x)").includes("100,180,220"))
    })

    it("should color an unordered list marker", () => {
        const {editor} = makeEditor()
        assert.true(segment(editor, "- item").includes("100,200,150"))
    })

    it("should dim the backslash and emit the escaped character as an entity", () => {
        const {editor} = makeEditor()
        const esc = "rgba(" + editor.props.colorEscape + ",1)"
        // The backslash is wrapped in the escape color; the escaped * becomes &#42; so no
        // later inline rule can treat it as a delimiter.
        assert.true(segment(editor, "\\*").includes(esc + '">\\</span>&#42;'))
    })

    it("should escape an underscore as well", () => {
        const {editor} = makeEditor()
        const esc = "rgba(" + editor.props.colorEscape + ",1)"
        assert.true(segment(editor, "\\_").includes(esc + '">\\</span>&#95;'))
    })

    it("should dim only the first of a double backslash, emitting the second as an entity", () => {
        const {editor} = makeEditor()
        const esc = "rgba(" + editor.props.colorEscape + ",1)"
        assert.true(segment(editor, "\\\\").includes(esc + '">\\</span>&#92;'))
    })

    it("should not dim a backslash before a non-markdown character", () => {
        const {editor} = makeEditor()
        const esc = "rgba(" + editor.props.colorEscape + ",1)"
        assert.false(segment(editor, "a\\b").includes(esc))
    })

    it("should not colour an escaped asterisk as bold", () => {
        const {editor} = makeEditor()
        // \*\* must not be read as bold ** markers
        assert.false(segment(editor, "\\*\\*").includes("rgba(" + editor.props.colorBold + ",1)"))
    })

    it("should not italicise text between escaped underscores", () => {
        const {editor} = makeEditor()
        // The screenshot case: \_not italic\_ must stay literal, not get the italic tint.
        assert.false(segment(editor, "\\_not italic\\_").includes("rgba(" + editor.props.colorItalic + ",1)"))
    })

    it("should not italicise text between escaped asterisks", () => {
        const {editor} = makeEditor()
        assert.false(segment(editor, "\\*not italic\\*").includes("rgba(" + editor.props.colorItalic + ",1)"))
    })

    it("should autolink a bare URL with the link color and underline", () => {
        const {editor} = makeEditor()
        const html = editor.highlightInline("see https://shaack.com/page")
        assert.true(html.includes("rgba(" + editor.props.colorLink + ",1)"))
        assert.true(html.includes("text-decoration:underline"))
    })

    it("should ignore markdown inside a bare URL", () => {
        const {editor} = makeEditor()
        // The screenshot case: underscores in the URL must not become italic.
        const html = editor.highlightInline("https://shaack.com/this_should_not_be_italic")
        assert.false(html.includes("rgba(" + editor.props.colorItalic + ",1)"))
    })

    it("should not autolink the URL of a markdown link", () => {
        const {editor} = makeEditor()
        // [t](url): the URL is part of the link syntax, highlighted as a link, not underlined.
        const html = editor.highlightInline("[t](https://shaack.com/a_b)")
        assert.true(html.includes("rgba(" + editor.props.colorLink + ",1)"))
        assert.false(html.includes("text-decoration:underline"))
        assert.false(html.includes("rgba(" + editor.props.colorItalic + ",1)"))
    })

    it("should protect inline code from other inline rules", () => {
        const {editor} = makeEditor()
        const html = editor.highlightInline("`**not bold**`")
        assert.true(html.includes("130,170,200"))   // code color present
        assert.false(html.includes("255,180,80"))   // bold color absent
    })

    it("should color a fenced code block", () => {
        const {editor} = makeEditor("```\nvar x = 1\n```")
        editor.updateHighlight()
        assert.true(editor.highlightLayer.innerHTML.includes("rgba(" + editor.props.colorCode))
    })

    it("should color YAML front matter", () => {
        const {editor} = makeEditor("---\ntitle: x\n---\nbody")
        editor.updateHighlight()
        assert.true(editor.highlightLayer.innerHTML.includes("rgba(" + editor.props.colorFrontMatter))
    })

    it("should end the highlight layer with a trailing newline", () => {
        const {editor} = makeEditor("line")
        editor.updateHighlight()
        assert.true(editor.highlightLayer.innerHTML.endsWith("\n"))
    })

    // --- ordered-list suppression + highlightLine plugin hook -------------------------------

    it("should color an ordered list marker by default", () => {
        const {editor} = makeEditor()
        assert.true(segment(editor, "1. item").includes("rgba(" + editor.props.colorList + ",1)"))
    })

    it("should suppress the ordered marker in highlightTextSegment when skipOrderedList is set", () => {
        const {editor} = makeEditor()
        const html = editor.highlightTextSegment(editor.escapeHtml("1. item"), {skipOrderedList: true})
        assert.false(html.includes("rgba(" + editor.props.colorList + ",1)"))
    })

    it("should forward skipOrderedList through highlightInline", () => {
        const {editor} = makeEditor()
        assert.true(editor.highlightInline("1. item").includes("rgba(" + editor.props.colorList + ",1)"))
        assert.false(editor.highlightInline("1. item", {skipOrderedList: true}).includes("rgba(" + editor.props.colorList + ",1)"))
    })

    it("should use a highlightLine plugin's returned html and skip built-in rules", () => {
        class Stub {
            constructor(editor) { this.editor = editor }
            highlightLine(line) { return line === "X" ? "STUB" : null }
        }
        const {editor} = makeEditor("# H\nX\n- a", 0, 0, {tools: [Stub]})
        editor.updateHighlight()
        assert.true(editor.highlightLayer.innerHTML.includes("STUB"))
    })

    it("should persist ctx.state across the lines of a pass", () => {
        const seen = []
        class Counter {
            constructor(editor) { this.editor = editor }
            highlightLine(line, ctx) { ctx.state.n = (ctx.state.n || 0) + 1; seen.push(ctx.state.n); return null }
        }
        const {editor} = makeEditor("a\nb\nc", 0, 0, {tools: [Counter]})
        // The constructor already ran one highlight pass over the (then empty)
        // textarea; ignore it and measure only the pass over the seeded value.
        seen.length = 0
        editor.updateHighlight()
        assert.equal(seen.join(","), "1,2,3")
    })

    it("should let a highlightLine plugin gate ordered lists by block state", () => {
        // Use a unique colorList so the count is unambiguous: by default colorList
        // shares its RGB value with colorHtmlTagBracket, so the <list>/</list> tag
        // brackets would otherwise be counted as ordered-list markers too.
        const {editor} = makeEditor("1. a\n<list>\n2. b\n</list>", 0, 0, {tools: [ListGate], colorList: "7,8,9"})
        editor.updateHighlight()
        const html = editor.highlightLayer.innerHTML
        // Only "2. " (inside the wrapper) is colored as an ordered marker; "1. " stays plain text.
        const orderedColor = new RegExp("rgba\\(" + editor.props.colorList + ",1\\)", "g")
        assert.equal((html.match(orderedColor) || []).length, 1)
    })
})
