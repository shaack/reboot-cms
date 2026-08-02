/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 *
 * The editor keeps its own undo/redo history instead of relying on the
 * deprecated (and, in Safari, unreliable) document.execCommand undo. These tests
 * drive it through real `input` events and assert value + caret after undo/redo.
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {makeEditor, type, keydown, snapshot} from "./EditorHarness.js"

describe("TestUndo", () => {

    it("undo restores the value and caret before the last edit", () => {
        const {editor, textarea} = makeEditor("", 0)
        type(textarea, "hello")
        editor.undo()
        assert.equal(snapshot(textarea), "|")
    })

    it("places the caret at the change site after undo, not at the top-left", () => {
        const {editor, textarea} = makeEditor("hello world", 5)
        type(textarea, "XYZ") // -> "helloXYZ world", caret at 8
        editor.undo()
        assert.equal(snapshot(textarea), "hello| world")
    })

    it("redo re-applies an undone edit", () => {
        const {editor, textarea} = makeEditor("", 0)
        type(textarea, "hello")
        editor.undo()
        editor.redo()
        assert.equal(snapshot(textarea), "hello|")
    })

    it("coalesces an uninterrupted burst of typing into a single undo step", () => {
        const {editor, textarea} = makeEditor("", 0)
        type(textarea, "ab")
        type(textarea, "cd")
        editor.undo()
        assert.equal(snapshot(textarea), "|")
    })

    it("keeps committed bursts as separate undo steps", () => {
        const {editor, textarea} = makeEditor("", 0)
        type(textarea, "ab")
        editor.commitHistory() // simulate the idle pause that ends a burst
        type(textarea, "cd")
        editor.undo()
        assert.equal(textarea.value, "ab")
        editor.undo()
        assert.equal(textarea.value, "")
    })

    it("does nothing when there is nothing to undo or redo", () => {
        const {editor, textarea} = makeEditor("seed", 4)
        editor.undo()
        editor.redo()
        assert.equal(textarea.value, "seed")
    })

    it("a new edit after undo clears the redo stack", () => {
        const {editor, textarea} = makeEditor("", 0)
        type(textarea, "a")
        editor.undo()
        type(textarea, "b")
        editor.redo() // nothing to redo anymore
        assert.equal(snapshot(textarea), "b|")
    })

    it("Cmd+Z / Cmd+Shift+Z through handleKeyDown undo and redo", () => {
        const {editor, textarea} = makeEditor("", 0)
        type(textarea, "x")
        const undoEvent = keydown({key: "z", metaKey: true})
        editor.handleKeyDown(undoEvent)
        assert.true(undoEvent.defaultPrevented)
        assert.equal(textarea.value, "")
        const redoEvent = keydown({key: "z", metaKey: true, shiftKey: true})
        editor.handleKeyDown(redoEvent)
        assert.equal(textarea.value, "x")
    })

    it("Ctrl+Y through handleKeyDown redoes", () => {
        const {editor, textarea} = makeEditor("", 0)
        type(textarea, "x")
        editor.undo()
        const redoEvent = keydown({key: "y", ctrlKey: true})
        editor.handleKeyDown(redoEvent)
        assert.true(redoEvent.defaultPrevented)
        assert.equal(textarea.value, "x")
    })
})
