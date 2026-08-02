/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 *
 * Tab behaviour and its keyboard-accessibility escape hatch (WCAG 2.1.2): Tab
 * indents by default, Escape releases it for one Tab so focus can move out, and
 * indentWithTab:false restores plain-textarea behaviour.
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {makeEditor, keydown} from "./EditorHarness.js"

describe("TestTab", () => {

    it("indents a list line and captures the Tab by default", () => {
        const {editor, textarea} = makeEditor("- item", 6)
        const e = keydown({key: "Tab"})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.true(textarea.value.startsWith("    - item"))
    })

    it("releases Tab after Escape so the next Tab moves focus", () => {
        const {editor, textarea} = makeEditor("- item", 6)
        editor.handleKeyDown(keydown({key: "Escape"}))
        const e = keydown({key: "Tab"})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented) // not captured -> browser moves focus
        assert.equal(textarea.value, "- item")
    })

    it("re-arms Tab indentation when an editing key follows Escape", () => {
        const {editor} = makeEditor("- item", 6)
        editor.handleKeyDown(keydown({key: "Escape"}))
        editor.handleKeyDown(keydown({key: "a"})) // disarms the release
        const e = keydown({key: "Tab"})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
    })

    it("keeps the release armed through a bare Shift key so Shift+Tab can escape", () => {
        const {editor} = makeEditor("- item", 6)
        editor.handleKeyDown(keydown({key: "Escape"}))
        editor.handleKeyDown(keydown({key: "Shift", shiftKey: true})) // modifier only
        const e = keydown({key: "Tab", shiftKey: true})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
    })

    it("indentWithTab:false makes Tab behave like a plain textarea", () => {
        const {editor, textarea} = makeEditor("- item", 6, 6, {indentWithTab: false})
        const e = keydown({key: "Tab"})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
        assert.equal(textarea.value, "- item")
    })

    it("advertises the escape method via aria when indenting with Tab", () => {
        const {textarea} = makeEditor("x", 1)
        assert.equal(textarea.getAttribute("aria-keyshortcuts"), "Escape")
        const describedby = textarea.getAttribute("aria-describedby")
        assert.true(!!describedby)
        const hint = document.getElementById(describedby.split(" ").pop())
        assert.true(!!hint && hint.textContent.length > 0)
    })

    it("omits the Tab escape hint when indentWithTab is false", () => {
        const {textarea} = makeEditor("x", 1, 1, {indentWithTab: false})
        assert.equal(textarea.getAttribute("aria-keyshortcuts"), null)
        assert.equal(textarea.getAttribute("aria-describedby"), null)
    })

    it("applies the ariaLabel prop to the textarea", () => {
        const {textarea} = makeEditor("x", 1, 1, {ariaLabel: "Page content"})
        assert.equal(textarea.getAttribute("aria-label"), "Page content")
    })
})
