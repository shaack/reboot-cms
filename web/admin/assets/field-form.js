/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * The browser side of FieldForm (core/admin/addons/Admin/src/FieldForm.php),
 * for every form with `data-field-form`: adds, moves and removes the items of
 * lists and blocks, switches blocks off and folds them, chooses images from the
 * media and keeps track of unsaved changes.
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

    form.querySelectorAll("textarea.markdown").forEach(watchMarkdown)
    form.querySelectorAll("[data-image-input]").forEach(updatePreview)

    form.addEventListener("click", event => {
        const button = event.target.closest("button")
        if (!button || !form.contains(button)) return
        if (button.matches("[data-list-add]")) {
            addItem(button.closest("[data-field-list]"))
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
        } else if (button.matches("[data-block-collapse]")) {
            const block = button.closest(".field-block")
            const collapsed = block.classList.toggle("is-collapsed")
            button.setAttribute("aria-expanded", collapsed ? "false" : "true")
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

/**
 * A new item from the `<template>` of the list, for blocks the one of the chosen type.
 * The token in its input names becomes a number no other item of the list has.
 */
function addItem(list) {
    let template = null
    const select = list.querySelector(":scope > .field-blocks-add [data-block-select]")
    if (select) {
        template = list.querySelector(':scope > template[data-block-type="' + CSS.escape(select.value) + '"]')
    } else {
        template = list.querySelector(":scope > template")
    }
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
