/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/cm-md-editor
 * License: MIT, see file 'LICENSE'
 *
 * Shared block logic for the list tools. Toggles every selected line into a
 * list of the given kind, or back out again when they already are one.
 */

const UL_MARKER = /^[ \t]*- /
const OL_MARKER = /^[ \t]*\d+\. /
const INDENT = /^[ \t]*/

/**
 * Toggle the lines touched by the selection into an unordered ("ul") or
 * ordered ("ol") list.
 *
 * The lines only lose their markers when *every* non-empty one already carries
 * a marker of that kind. A mixed selection is normalised into a list instead,
 * which is what makes a second click the reliable way back.
 *
 * Blank lines keep their place untouched: they carry no marker, so they neither
 * decide the direction nor consume an ordinal.
 *
 * @param editor the MdEditor instance
 * @param {string} kind "ul" or "ol"
 */
export function toggleListBlock(editor, kind) {
    const element = editor.element
    const hadSelection = element.selectionEnd > element.selectionStart
    const {blockStart, blockEnd} = editor.getSelectedLinesRange()
    const lines = element.value.substring(blockStart, blockEnd).split('\n')
    const marker = kind === 'ol' ? OL_MARKER : UL_MARKER
    const filled = lines.filter((line) => line.trim() !== '')
    const remove = filled.length > 0 && filled.every((line) => marker.test(line))
    let ordinal = 0
    const replacement = lines.map((line) => {
        if (line.trim() === '') {
            return line
        }
        const indent = line.match(INDENT)[0]
        // Strip a marker of either kind, so switching between the two list
        // types replaces the marker instead of stacking "- 1. item".
        const text = line.substring(indent.length)
            .replace(/^- /, '')
            .replace(/^\d+\. /, '')
        if (remove) {
            return indent + text
        }
        ordinal++
        return indent + (kind === 'ol' ? ordinal + '. ' : '- ') + text
    }).join('\n')
    editor.selectLineRange(blockStart, blockEnd)
    editor.insertTextAtCursor(replacement)
    if (hadSelection) {
        // Keep the block selected, otherwise the second click would only reach
        // the line the caret happened to land on.
        element.selectionStart = blockStart
        element.selectionEnd = blockStart + replacement.length
    }
}
