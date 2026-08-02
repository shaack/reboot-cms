/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {makeEditor, keydown, snapshot} from "./EditorHarness.js"

describe("TestFormatting", () => {

    it("should wrap a selection in bold markers, keeping the inner text selected", () => {
        const {editor, textarea} = makeEditor("abc", 0, 3)
        editor.toggleWrap("**")
        assert.equal(snapshot(textarea), "**[abc]**")
    })

    it("should unwrap bold markers around the selection", () => {
        const {editor, textarea} = makeEditor("**abc**", 2, 5)
        editor.toggleWrap("**")
        assert.equal(snapshot(textarea), "[abc]")
    })

    it("should insert empty bold markers with the caret in the middle", () => {
        const {editor, textarea} = makeEditor("", 0)
        editor.toggleWrap("**")
        assert.equal(snapshot(textarea), "**|**")
    })

    it("should wrap a selection in italic markers", () => {
        const {editor, textarea} = makeEditor("abc", 0, 3)
        editor.toggleWrap("_")
        assert.equal(snapshot(textarea), "_[abc]_")
    })

    it("should route Ctrl/Cmd+B to bold", () => {
        const {editor, textarea} = makeEditor("abc", 0, 3)
        const e = keydown({key: "b", metaKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "**[abc]**")
    })

    it("should route Ctrl/Cmd+I to italic", () => {
        const {editor, textarea} = makeEditor("x", 0, 1)
        const e = keydown({key: "i", ctrlKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "_[x]_")
    })

    it("should swallow Ctrl/Cmd+Z and stop propagation (own undo stack)", () => {
        const {editor} = makeEditor("abc", 3)
        const e = keydown({key: "z", metaKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.true(e.propagationStopped)
    })

    it("should not act on a plain letter key", () => {
        const {editor, textarea} = makeEditor("abc", 3)
        const e = keydown({key: "b"})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
        assert.equal(snapshot(textarea), "abc|")
    })
})
