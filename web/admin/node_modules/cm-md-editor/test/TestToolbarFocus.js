/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 *
 * Reaching the toolbar by keyboard: the roving tabindex that makes it a single tab
 * stop, toolbarInTabOrder:false which takes it out of the tab order, and the
 * focusToolbarShortcut that keeps it operable either way (WCAG 2.1.1).
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {makeEditor, keydown} from "./EditorHarness.js"

describe("TestToolbarFocus", () => {

    it("makes exactly one button the toolbar's tab stop by default", () => {
        const {editor} = makeEditor("x", 1)
        const buttons = editor.toolbarButtons()
        assert.true(buttons.length > 1)
        assert.equal(buttons.filter(button => button.tabIndex === 0).length, 1)
        assert.equal(buttons[0].tabIndex, 0)
    })

    it("takes every button out of the tab order with toolbarInTabOrder:false", () => {
        const {editor} = makeEditor("x", 1, 1, {toolbarInTabOrder: false})
        const buttons = editor.toolbarButtons()
        assert.true(buttons.length > 0)
        assert.equal(buttons.filter(button => button.tabIndex === 0).length, 0)
    })

    it("focuses the toolbar on the shortcut", () => {
        const {editor} = makeEditor("x", 1, 1, {toolbarInTabOrder: false})
        const e = keydown({key: "F10", altKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(document.activeElement, editor.toolbarButtons()[0])
    })

    it("ignores the shortcut when a modifier does not match", () => {
        const {editor} = makeEditor("x", 1)
        const e = keydown({key: "F10"}) // no Alt
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
    })

    it("accepts a custom focusToolbarShortcut", () => {
        const {editor} = makeEditor("x", 1, 1, {focusToolbarShortcut: "Ctrl+Shift+T"})
        editor.handleKeyDown(keydown({key: "F10", altKey: true})) // default no longer bound
        assert.true(editor.toolbarButtons().indexOf(document.activeElement) === -1)
        const e = keydown({key: "T", ctrlKey: true, shiftKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(document.activeElement, editor.toolbarButtons()[0])
    })

    it("offers no shortcut when focusToolbarShortcut is null", () => {
        const {editor} = makeEditor("x", 1, 1, {focusToolbarShortcut: null})
        const e = keydown({key: "F10", altKey: true})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
    })

    it("returns to the last used button and keeps it out of the tab order", () => {
        const {editor} = makeEditor("x", 1, 1, {toolbarInTabOrder: false})
        editor.handleKeyDown(keydown({key: "F10", altKey: true}))
        const buttons = editor.toolbarButtons()
        editor.handleToolbarKeydown(keydown({key: "ArrowRight"}))
        assert.equal(document.activeElement, buttons[1])
        // the arrow navigation must not re-arm a tab stop in this mode
        assert.equal(buttons.filter(button => button.tabIndex === 0).length, 0)
        editor.element.focus()
        editor.handleKeyDown(keydown({key: "F10", altKey: true}))
        assert.equal(document.activeElement, buttons[1])
    })

    it("moves the single tab stop with the focus when the toolbar is in the tab order", () => {
        const {editor} = makeEditor("x", 1)
        editor.handleKeyDown(keydown({key: "F10", altKey: true}))
        const buttons = editor.toolbarButtons()
        editor.handleToolbarKeydown(keydown({key: "ArrowRight"}))
        assert.equal(buttons[0].tabIndex, -1)
        assert.equal(buttons[1].tabIndex, 0)
    })

    it("returns focus to the textarea on Escape in the toolbar", () => {
        const {editor, textarea} = makeEditor("x", 1)
        editor.handleKeyDown(keydown({key: "F10", altKey: true}))
        editor.handleToolbarKeydown(keydown({key: "Escape"}))
        assert.equal(document.activeElement, textarea)
    })

    it("advertises the toolbar shortcut via aria", () => {
        const {textarea} = makeEditor("x", 1)
        assert.true(textarea.getAttribute("aria-keyshortcuts").split(" ").includes("Alt+F10"))
        const describedby = textarea.getAttribute("aria-describedby")
        const hint = document.getElementById(describedby.split(" ").pop())
        assert.true(hint.textContent.includes("Alt+F10"))
    })

    it("names the configured shortcut in the hint, not the default", () => {
        const {textarea} = makeEditor("x", 1, 1, {focusToolbarShortcut: "Ctrl+Shift+T"})
        const describedby = textarea.getAttribute("aria-describedby")
        const hint = document.getElementById(describedby.split(" ").pop())
        assert.true(hint.textContent.includes("Ctrl+Shift+T"))
        assert.true(textarea.getAttribute("aria-keyshortcuts").split(" ").includes("Control+Shift+T"))
    })
})
