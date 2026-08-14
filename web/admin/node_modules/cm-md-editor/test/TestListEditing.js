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

    it("should turn every selected line into an unordered list item", () => {
        const {editor, textarea} = makeEditor("one\ntwo\nthree", 0, 13)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "- one\n- two\n- three")
    })

    it("should turn a selected unordered list back into plain lines", () => {
        const {editor, textarea} = makeEditor("- one\n- two\n- three", 0, 19)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "one\ntwo\nthree")
    })

    it("should keep the block selected so a second click toggles it back", () => {
        const {editor, textarea} = makeEditor("one\ntwo", 0, 7)
        const ul = tool(editor, "UnorderedList")
        ul.insertUnorderedList()
        assert.equal(snapshot(textarea), "[- one\n- two]")
        ul.insertUnorderedList()
        assert.equal(textarea.value, "one\ntwo")
    })

    it("should number every selected line for an ordered list", () => {
        const {editor, textarea} = makeEditor("one\ntwo\nthree", 0, 13)
        tool(editor, "OrderedList").insertOrderedList()
        assert.equal(textarea.value, "1. one\n2. two\n3. three")
    })

    it("should turn a selected ordered list back into plain lines", () => {
        const {editor, textarea} = makeEditor("1. one\n2. two", 0, 13)
        tool(editor, "OrderedList").insertOrderedList()
        assert.equal(textarea.value, "one\ntwo")
    })

    it("should normalise a partly marked selection into a full list", () => {
        const {editor, textarea} = makeEditor("- one\ntwo", 0, 9)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "- one\n- two")
    })

    it("should replace the marker when switching between list types", () => {
        const {editor, textarea} = makeEditor("1. one\n2. two", 0, 13)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "- one\n- two")
    })

    it("should keep the indent of nested list lines", () => {
        const {editor, textarea} = makeEditor("- one\n    - two", 0, 15)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "one\n    two")
    })

    it("should leave blank lines alone and not let them consume an ordinal", () => {
        const {editor, textarea} = makeEditor("one\n\ntwo", 0, 8)
        tool(editor, "OrderedList").insertOrderedList()
        assert.equal(textarea.value, "1. one\n\n2. two")
    })

    it("should only touch the lines the selection reaches", () => {
        const {editor, textarea} = makeEditor("one\ntwo\nthree", 0, 5)
        tool(editor, "UnorderedList").insertUnorderedList()
        assert.equal(textarea.value, "- one\n- two\nthree")
    })
})
