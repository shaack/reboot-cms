/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 */

import {describe, it, assert} from "../node_modules/teevi/src/teevi.js"
import {makeEditor, keydown, snapshot} from "./EditorHarness.js"

describe("TestMoveLines", () => {

    it("should move the current line up", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc", 5)
        editor.moveLines(-1)
        assert.equal(snapshot(textarea), "b|bb\naaa\nccc")
    })

    it("should move the current line down", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc", 5)
        editor.moveLines(1)
        assert.equal(snapshot(textarea), "aaa\nccc\nb|bb")
    })

    it("should not move up past the top line", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb", 1)
        editor.moveLines(-1)
        assert.equal(snapshot(textarea), "a|aa\nbbb")
    })

    it("should not move down past the bottom line", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb", 5)
        editor.moveLines(1)
        assert.equal(snapshot(textarea), "aaa\nb|bb")
    })

    it("should move a multi-line selection down as a block", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc\nddd", 4, 11)
        editor.moveLines(1)
        assert.equal(snapshot(textarea), "aaa\nddd\n[bbb\nccc]")
    })

    it("should keep the selection on the moved block when moving up", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc\nddd", 8, 11)
        editor.moveLines(-1)
        assert.equal(snapshot(textarea), "aaa\n[ccc]\nbbb\nddd")
    })

    it("should move a line down past a trailing empty line", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\n", 5)
        editor.moveLines(1)
        assert.equal(snapshot(textarea), "aaa\n\nb|bb")
    })

    it("should route Alt+ArrowDown to move the line down and prevent default", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc", 5)
        const e = keydown({key: "ArrowDown", altKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "aaa\nccc\nb|bb")
    })

    it("should route Alt+ArrowUp to move the line up", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc", 5)
        const e = keydown({key: "ArrowUp", altKey: true})
        editor.handleKeyDown(e)
        assert.true(e.defaultPrevented)
        assert.equal(snapshot(textarea), "b|bb\naaa\nccc")
    })

    it("should ignore a plain ArrowUp without Alt", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc", 5)
        const e = keydown({key: "ArrowUp"})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
        assert.equal(snapshot(textarea), "aaa\nb|bb\nccc")
    })

    it("should not hijack Alt+Shift+ArrowUp (leave selection extend to the browser)", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb\nccc", 5)
        const e = keydown({key: "ArrowUp", altKey: true, shiftKey: true})
        editor.handleKeyDown(e)
        assert.false(e.defaultPrevented)
        assert.equal(snapshot(textarea), "aaa\nb|bb\nccc")
    })

    it("should not act on Alt+ArrowLeft/Right (indent stays on Tab)", () => {
        const {editor, textarea} = makeEditor("aaa\nbbb", 5)
        const left = keydown({key: "ArrowLeft", altKey: true})
        editor.handleKeyDown(left)
        assert.false(left.defaultPrevented)
        const right = keydown({key: "ArrowRight", altKey: true})
        editor.handleKeyDown(right)
        assert.false(right.defaultPrevented)
        assert.equal(snapshot(textarea), "aaa\nb|bb")
    })
})
