/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {makeEditor, keydown, snapshot} from "./EditorHarness.js"

function tool(editor, name) {
    return editor.tools.find(t => t.constructor.name === name)
}

describe("TestListEditing", () => {

    it("should indent a list line at its start with the list indent unit", () => {
        const {editor, textarea} = makeEditor("- item", 6)
        editor.insertTabAtLineStart()
        assert.equal(snapshot(textarea), "    - item|")
    })

    it("should outdent a two-space list indent", () => {
        const {editor, textarea} = makeEditor("  - item", 8)
        editor.removeTab()
        assert.equal(snapshot(textarea), "- item|")
    })

    it("should outdent a legacy tab indent", () => {
        const {editor, textarea} = makeEditor("\t- item", 7)
        editor.removeTab()
        assert.equal(snapshot(textarea), "- item|")
    })

    it("should indent a non-list line at its start via Tab", () => {
        const {editor, textarea} = makeEditor("hello", 2)
        const e = keydown({key: "Tab"})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "    he|llo")
    })

    it("should outdent a non-list line via Shift+Tab", () => {
        const {editor, textarea} = makeEditor("    hello", 6)
        const e = keydown({key: "Tab", shiftKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "he|llo")
    })

    it("should indent a list line via Tab", () => {
        const {editor, textarea} = makeEditor("- item", 3)
        const e = keydown({key: "Tab"})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "    - i|tem")
    })

    it("should outdent a list line via Shift+Tab", () => {
        const {editor, textarea} = makeEditor("    - item", 7)
        const e = keydown({key: "Tab", shiftKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "- i|tem")
    })

    it("should recognise an ordered-list line as list mode", () => {
        const {editor, textarea} = makeEditor("1. item", 4)
        const e = keydown({key: "Tab"})
        editor.handleKeyDown(e)
        assert.equal(snapshot(textarea), "    1. i|tem")
    })

    it("should continue an unordered list on Enter", () => {
        const {editor, textarea} = makeEditor("- item", 6)
        const e = keydown({key: "Enter"})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "- item\n- |")
    })

    it("should continue an indented unordered list on Enter, keeping the indent", () => {
        const {editor, textarea} = makeEditor("  - item", 8)
        const e = keydown({key: "Enter"})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "  - item\n  - |")
    })

    it("should continue an ordered list on Enter, incrementing the number", () => {
        const {editor, textarea} = makeEditor("1. item", 7)
        const e = keydown({key: "Enter"})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "1. item\n2. |")
    })

    it("should select the empty bullet on Enter so the browser removes it", () => {
        // On an empty "- " item, the editor extends the selection over the newline and
        // bullet and lets the native Enter replace it, ending the list. No preventDefault.
        const {editor, textarea} = makeEditor("- item\n- ", 9)
        const e = keydown({key: "Enter"})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
        assert.equal(snapshot(textarea), "- item[\n- ]")
    })

    it("should toggle a plain line into an unordered list item", () => {
        const {editor, textarea} = makeEditor("hello", 0)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "- hello")
    })

    it("should toggle an unordered list item back to a plain line", () => {
        const {editor, textarea} = makeEditor("- hello", 0)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "hello")
    })
})
