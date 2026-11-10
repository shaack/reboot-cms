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
 *
 * A form with `data-edit-lang` has an input per language in its translated
 * fields, the buttons with `data-edit-lang-button` above it choose the language
 * shown. The choice is remembered for all pages.
 */
import {InsertMedia} from "./InsertMedia.js"

// before the forms are set up below, a const is not there before its line
const EDIT_LANG_KEY = "reboot_edit_lang"

// A textarea grows with its text, see @shaack/auto-resize-textarea. One of a
// language not shown or in a folded block has no height yet, it is set up
// when it is shown.
const resizeObserver = new ResizeObserver(entries => entries.forEach(entry => {
    if (entry.contentRect.height === 0) return
    resizeObserver.unobserve(entry.target)
    autoResizeTextarea([entry.target])
}))

const forms = document.querySelectorAll("form[data-field-form]")
let created = 0

forms.forEach(form => {
    const saveDot = form.querySelector("[data-save-dot]")
    let dirty = false
    // counts the changes, so a save that is answered later knows whether more came
    let changes = 0
    const markDirty = () => {
        dirty = true
        changes++
        if (saveDot) saveDot.classList.remove("d-none")
        // the page Pages updates its preview on it
        form.dispatchEvent(new CustomEvent("fieldchange"))
    }
    form.addEventListener("input", markDirty)
    form.addEventListener("change", markDirty)
    // the page Pages saves in the background (data-async-save) and cleans the form itself
    form.addEventListener("submit", () => {
        if (!form.hasAttribute("data-async-save")) dirty = false
    })
    window.addEventListener("beforeunload", event => {
        if (dirty) event.preventDefault()
    })
    form.markDirty = markDirty
    form.changeCount = () => changes
    // clean, unless something changed after `count`
    form.clean = (count = changes) => {
        if (count !== changes) return
        dirty = false
        if (saveDot) saveDot.classList.add("d-none")
    }

    restoreFolds(form)
    syncFoldAll(form)
    if (form.hasAttribute("data-edit-lang")) setupLanguages(form)
    form.addEventListener("toggle", event => {
        if (event.target.matches("details[data-fold]")) saveFolds(form)
    }, true)

    form.querySelectorAll("textarea.markdown").forEach(watchMarkdown)
    form.querySelectorAll("textarea").forEach(autoResize)
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
    item.querySelectorAll("textarea").forEach(autoResize)
    item.querySelectorAll("[data-image-input]").forEach(updatePreview)
    const first = item.querySelector("input:not([type=hidden]), textarea, select")
    if (first) first.focus()
}

function autoResize(textarea) {
    if (window.autoResizeTextarea) resizeObserver.observe(textarea)
}

// the Markdown editor changes its textarea without an input event on the form
function watchMarkdown(textarea) {
    textarea.addEventListener("input", () => {
        const form = textarea.closest("form")
        if (form && form.markDirty) form.markDirty()
    })
}

// --- The language shown in a multilingual form ---

function setupLanguages(form) {
    const buttons = [...document.querySelectorAll("[data-edit-lang-button]")]
    const languages = buttons.map(button => button.dataset.editLangButton)
    let saved = null
    try {
        saved = localStorage.getItem(EDIT_LANG_KEY)
    } catch (e) {
        // no storage, the form starts in the default language
    }
    if (saved && languages.includes(saved)) showLanguage(form, saved)
    buttons.forEach(button => button.addEventListener("click", () => {
        showLanguage(form, button.dataset.editLangButton)
        try {
            localStorage.setItem(EDIT_LANG_KEY, button.dataset.editLangButton)
        } catch (e) {
            // not remembered
        }
    }))
}

function showLanguage(form, language) {
    if (form.dataset.editLang === language) return
    form.dataset.editLang = language
    document.querySelectorAll("[data-edit-lang-button]").forEach(button => {
        const active = button.dataset.editLangButton === language
        button.classList.toggle("active", active)
        button.setAttribute("aria-pressed", active ? "true" : "false")
    })
    // a label points at the input of the language shown
    form.querySelectorAll("[data-lang-label]").forEach(label => {
        label.htmlFor = label.dataset.langLabel + "-" + language
    })
    // the page Pages shows the preview and the address in this language
    form.dispatchEvent(new CustomEvent("languagechange", {detail: {language}}))
}

function updatePreview(input) {
    const preview = input.closest(".field-language, .mb-3")?.querySelector("[data-image-preview]")
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
