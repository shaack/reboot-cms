/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {MdEditor} from "../src/MdEditor.js"
import {makeEditor} from "./EditorHarness.js"

describe("TestCore", () => {

    it("should construct with default props", () => {
        const {editor} = makeEditor()
        assert.equal(editor.props.listIndent, "    ")
        assert.equal(editor.props.wordWrap, true)
        assert.true(editor.tools.length > 0)
    })

    it("should accept a custom listIndent prop", () => {
        const {editor} = makeEditor("", 0, 0, {listIndent: "\t"})
        assert.equal(editor.props.listIndent, "\t")
    })

    it("should expose the static default list indent", () => {
        assert.equal(MdEditor.LIST_INDENT, "    ")
    })

    it("should build the toolbar, backdrop and highlight layer", () => {
        const {editor, textarea} = makeEditor("x")
        assert.true(!!editor.backdrop)
        assert.true(!!editor.highlightLayer)
        assert.true(!!editor.wrapButton)
        // The textarea got wrapped, with a toolbar as a preceding sibling.
        assert.equal(textarea.parentNode.querySelector("textarea"), textarea)
    })

    it("should default colorChrome to a neutral grey", () => {
        const {editor} = makeEditor()
        assert.equal(editor.props.colorChrome, "128,128,128")
    })

    it("should apply a custom colorChrome to the toolbar background", () => {
        const {editor, textarea} = makeEditor("x", 0, 0, {colorChrome: "10,20,30"})
        // The toolbar sits after the textarea in the DOM (textarea first in tab
        // order), so find it by role rather than by sibling position.
        const toolbar = textarea.closest("div").querySelector('[role="toolbar"]')
        assert.true(toolbar.style.backgroundColor.includes("10, 20, 30"))
    })

    it("should report the current line for a caret in the middle line", () => {
        const {editor} = makeEditor("abc\ndef\nghi", 5)
        const info = editor.getCurrentLineInfo()
        assert.equal(info.lineStart, 4)
        assert.equal(info.lineEnd, 7)
        assert.equal(info.line, "def")
    })

    it("should report the current line for a caret at the very start", () => {
        const {editor} = makeEditor("abc\ndef", 0)
        const info = editor.getCurrentLineInfo()
        assert.equal(info.lineStart, 0)
        assert.equal(info.line, "abc")
    })

    it("should report the current line for a caret on the last line without newline", () => {
        const {editor} = makeEditor("abc\ndef", 7)
        const info = editor.getCurrentLineInfo()
        assert.equal(info.lineStart, 4)
        assert.equal(info.lineEnd, 7)
        assert.equal(info.line, "def")
    })

    it("should give the full line range for a collapsed caret", () => {
        const {editor} = makeEditor("aaa\nbbb\nccc", 5)
        const {blockStart, blockEnd} = editor.getSelectedLinesRange()
        assert.equal(blockStart, 4)
        assert.equal(blockEnd, 7)
    })

    it("should not pull in the following line when the selection ends at a line start", () => {
        // Selection "aaa\n" ends exactly at the start of "bbb".
        const {editor} = makeEditor("aaa\nbbb\nccc", 0, 4)
        const {blockStart, blockEnd} = editor.getSelectedLinesRange()
        assert.equal(blockStart, 0)
        assert.equal(blockEnd, 3)
    })

    it("should cover every touched line for a multi-line selection", () => {
        const {editor} = makeEditor("aaa\nbbb\nccc\nddd", 5, 9)
        const {blockStart, blockEnd} = editor.getSelectedLinesRange()
        assert.equal(blockStart, 4)
        assert.equal(blockEnd, 11)
    })

    it("should escape HTML special characters", () => {
        const {editor} = makeEditor()
        assert.equal(editor.escapeHtml("<a> & </a>"), "&lt;a&gt; &amp; &lt;/a&gt;")
        assert.equal(editor.escapeHtml("no specials"), "no specials")
    })
})
