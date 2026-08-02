/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 *
 * Test helpers: build a real MdEditor on a throwaway textarea and drive it
 * deterministically.
 */

import {MdEditor} from "../src/MdEditor.js"

let counter = 0

/**
 * Create an MdEditor on a fresh textarea appended to the document, seeded with
 * `value` and a selection of [selStart, selEnd).
 *
 * insertTextAtCursor is replaced with a plain-JS splice that mirrors the
 * semantics of document.execCommand("insertText"): the current selection is
 * replaced by the text and the caret lands right after it. This keeps the tests
 * independent of execCommand (which is unreliable headless) while exercising all
 * of the editor's own selection and line-manipulation logic unchanged.
 */
export function makeEditor(value = "", selStart = 0, selEnd = selStart, props = {}) {
    const n = counter++
    const container = document.createElement("div")
    container.id = "harness-" + n
    document.body.appendChild(container)
    const textarea = document.createElement("textarea")
    // Unique id so the wrap-toggle localStorage key is per-editor and tests can't leak state.
    textarea.id = "harness-input-" + n
    container.appendChild(textarea)
    const editor = new MdEditor(textarea, props)
    editor.insertTextAtCursor = function (text) {
        const el = this.element
        const s = el.selectionStart
        const e = el.selectionEnd
        el.value = el.value.slice(0, s) + text + el.value.slice(e)
        el.selectionStart = el.selectionEnd = s + text.length
    }
    textarea.value = value
    textarea.selectionStart = selStart
    textarea.selectionEnd = selEnd
    // The seed value is applied after construction, so rebase the undo history on
    // it, mirroring real use where the textarea already holds content when the
    // editor is created.
    editor.resetHistory()
    return {editor, textarea, container}
}

/**
 * Simulate a user typing `text` at the current caret (replacing any selection):
 * splice the value, move the caret, and fire the same `input` event the browser
 * would, so the editor's history recording runs exactly as in real use.
 */
export function type(textarea, text) {
    const s = textarea.selectionStart
    const e = textarea.selectionEnd
    textarea.value = textarea.value.slice(0, s) + text + textarea.value.slice(e)
    textarea.selectionStart = textarea.selectionEnd = s + text.length
    textarea.dispatchEvent(new InputEvent("input", {bubbles: true}))
}

/** Build a synthetic keydown event with the modifier flags a handler inspects. */
export function keydown(props) {
    return {
        key: props.key,
        altKey: !!props.altKey,
        ctrlKey: !!props.ctrlKey,
        metaKey: !!props.metaKey,
        shiftKey: !!props.shiftKey,
        defaultPrevented: false,
        propagationStopped: false,
        preventDefault() {
            this.defaultPrevented = true
        },
        stopPropagation() {
            this.propagationStopped = true
        }
    }
}

/** Compact "value + selection" snapshot: the text with the caret/selection marked. */
export function snapshot(textarea) {
    const v = textarea.value
    const s = textarea.selectionStart
    const e = textarea.selectionEnd
    if (s === e) {
        return v.slice(0, s) + "|" + v.slice(s)
    }
    return v.slice(0, s) + "[" + v.slice(s, e) + "]" + v.slice(e)
}
