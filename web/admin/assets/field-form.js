/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * The browser side of FieldForm (core/admin/addons/Admin/src/FieldForm.php),
 * for every form with `data-field-form`: adds, moves and removes the items of
 * lists and blocks, switches blocks off and folds them, chooses images from the
 * media and keeps track of unsaved changes. Which blocks are folded, and whether
 * the page settings are open, is remembered per page in the localStorage.
 */
import {InsertMedia} from "./InsertMedia.js"

const forms = document.querySelectorAll("form[data-field-form]")
let created = 0

forms.forEach(form => {
    const saveDot = form.querySelector("[data-save-dot]")
    let dirty = false
    const markDirty = () => {
        dirty = true
        if (saveDot) saveDot.classList.remove("d-none")
        // the page Pages updates its preview on it
        form.dispatchEvent(new CustomEvent("fieldchange"))
    }
    form.addEventListener("input", markDirty)
    form.addEventListener("change", markDirty)
    form.addEventListener("submit", () => dirty = false)
    window.addEventListener("beforeunload", event => {
        if (dirty) event.preventDefault()
    })
    form.markDirty = markDirty
    form.clean = () => dirty = false

    restoreFolds(form)
    syncFoldAll(form)
    form.addEventListener("toggle", event => {
        if (event.target.matches("details[data-fold]")) saveFolds(form)
    }, true)

    form.querySelectorAll("textarea.markdown").forEach(watchMarkdown)
    form.querySelectorAll("[data-image-input]").forEach(updatePreview)

    form.addEventListener("click", event => {
        const button = event.target.closest("button")
        if (!button || !form.contains(button)) return
        if (button.matches("[data-markdown-toggle]")) {
            // opens the Markdown blocks inside the menu, which stays open
            event.stopPropagation()
            const expanded = button.getAttribute("aria-expanded") !== "true"
            button.setAttribute("aria-expanded", expanded ? "true" : "false")
            button.nextElementSibling.hidden = !expanded
            return
        }
        if (button.matches("[data-fold-all]")) {
            const blocks = [...topBlocks(form)]
            const collapse = blocks.some(block => !block.classList.contains("is-collapsed"))
            blocks.forEach(block => setCollapsed(block, collapse))
            saveFolds(form)
            syncFoldAll(form)
            return
        }
        if (button.matches("[data-list-add]")) {
            addItem(button.closest("[data-field-list]"), button.dataset.blockType)
            saveFolds(form)
            syncFoldAll(form)
        } else if (button.matches("[data-item-up], [data-item-down], [data-item-remove]")) {
            const item = button.closest("[data-list-item]")
            if (button.matches("[data-item-up]") && item.previousElementSibling) {
                item.previousElementSibling.before(item)
            } else if (button.matches("[data-item-down]") && item.nextElementSibling) {
                item.nextElementSibling.after(item)
            } else if (button.matches("[data-item-remove]")) {
                item.remove()
            }
            button.focus()
            saveFolds(form)
            syncFoldAll(form)
        } else if (button.matches("[data-block-collapse]")) {
            const block = button.closest(".field-block")
            setCollapsed(block, !block.classList.contains("is-collapsed"))
            saveFolds(form)
            syncFoldAll(form)
            return
        } else if (button.matches("[data-choose-image]")) {
            chooseImage(button.closest(".input-group").querySelector("[data-image-input]"))
            return
        } else {
            return
        }
        markDirty()
    })

    form.addEventListener("change", event => {
        if (event.target.matches("[data-block-enabled]")) {
            event.target.closest(".field-block").classList.toggle("is-disabled", !event.target.checked)
        }
    })
    form.addEventListener("input", event => {
        if (event.target.matches("[data-image-input]")) updatePreview(event.target)
    })
})

// Saving with ⌘ S / Ctrl S, the page Pages has its own handler that calls requestSubmit()
if (forms.length === 1 && !window.pagesConfig) {
    document.addEventListener("keydown", event => {
        if ((event.metaKey || event.ctrlKey) && event.key === "s") {
            event.preventDefault()
            forms[0].requestSubmit()
        }
    })
}

// --- What is folded, per page ---
// The blocks are remembered by position and type, `["intro", true]` for a folded
// intro first. Moving a block saves the new order, so its state moves with it.

function foldKey(form) {
    return "reboot_folds_" + form.getAttribute("action")
}

function topBlocks(form) {
    return form.querySelectorAll(".field-blocks > .field-list-items > .field-block")
}

function saveFolds(form) {
    const settings = form.querySelector("details[data-fold]")
    const state = {
        settings: settings ? settings.open : null,
        blocks: [...topBlocks(form)].map(block => [
            block.querySelector(':scope > input[name$="[_type]"]').value,
            block.classList.contains("is-collapsed")
        ])
    }
    try {
        localStorage.setItem(foldKey(form), JSON.stringify(state))
    } catch (e) {
        // no storage, the folds are not remembered
    }
}

function setCollapsed(block, collapsed) {
    block.classList.toggle("is-collapsed", collapsed)
    block.querySelector("[data-block-collapse]").setAttribute("aria-expanded", collapsed ? "false" : "true")
}

// "Collapse all" while a block is open, else "Expand all"
function syncFoldAll(form) {
    const button = form.querySelector("[data-fold-all]")
    if (!button) return
    const blocks = [...topBlocks(form)]
    button.disabled = blocks.length === 0
    const expand = blocks.length > 0 && blocks.every(block => block.classList.contains("is-collapsed"))
    button.querySelector("span").textContent = expand ? "Expand all" : "Collapse all"
    const [collapseIcon, expandIcon] = button.querySelectorAll("svg")
    // an svg has no hidden property, only the attribute
    collapseIcon.toggleAttribute("hidden", expand)
    expandIcon.toggleAttribute("hidden", !expand)
}

function restoreFolds(form) {
    let state = null
    try {
        state = JSON.parse(localStorage.getItem(foldKey(form)))
    } catch (e) {
        return
    }
    if (!state) return
    const settings = form.querySelector("details[data-fold]")
    if (settings && !settings.hasAttribute("data-fold-forced") && typeof state.settings === "boolean") {
        settings.open = state.settings
    }
    topBlocks(form).forEach((block, i) => {
        const saved = (state.blocks || [])[i]
        const type = block.querySelector(':scope > input[name$="[_type]"]').value
        // a block with errors stays open, so the marked fields are seen
        if (saved && saved[0] === type && saved[1] && !block.classList.contains("has-errors")) {
            setCollapsed(block, true)
        }
    })
}

/**
 * A new item from the `<template>` of the list, for blocks the one of the chosen type.
 * The token in its input names becomes a number no other item of the list has.
 */
function addItem(list, blockType) {
    const template = blockType
        ? list.querySelector(':scope > template[data-block-type="' + CSS.escape(blockType) + '"]')
        : list.querySelector(":scope > template")
    if (!template) return
    const index = "n" + Date.now().toString(36) + (++created)
    const holder = document.createElement("div")
    holder.innerHTML = template.innerHTML.replaceAll(list.dataset.token, index)
    const item = holder.firstElementChild
    list.querySelector(":scope > .field-list-items").append(item)
    item.querySelectorAll("textarea.markdown").forEach(textarea => {
        if (window.rebootMarkdownEditor) window.rebootMarkdownEditor(textarea)
        watchMarkdown(textarea)
    })
    item.querySelectorAll("[data-image-input]").forEach(updatePreview)
    const first = item.querySelector("input:not([type=hidden]), textarea, select")
    if (first) first.focus()
}

// the Markdown editor changes its textarea without an input event on the form
function watchMarkdown(textarea) {
    textarea.addEventListener("input", () => {
        const form = textarea.closest("form")
        if (form && form.markDirty) form.markDirty()
    })
}

function updatePreview(input) {
    const preview = input.closest(".mb-3")?.querySelector("[data-image-preview]")
    if (!preview) return
    const src = input.value || input.placeholder
    preview.src = src || ""
    preview.hidden = !src
}

function chooseImage(input) {
    const picker = new InsertMedia(null, {mediaUrl: "media"})
    picker.insertImage = webPath => {
        input.value = webPath
        input.dispatchEvent(new Event("input", {bubbles: true}))
    }
    picker.openBrowser()
}
