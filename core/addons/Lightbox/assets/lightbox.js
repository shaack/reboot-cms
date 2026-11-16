/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Opens linked images enlarged in a dialog. A link takes part with the
 * attribute `data-lightbox`, links with the same value form a group that can
 * be paged with the arrow keys, the buttons or a swipe. `data-caption` sets
 * the text below the image. Escape, a click on the backdrop or the close
 * button end it. The labels follow the language of the page, English or German.
 */
(function () {
    "use strict"

    const german = (document.documentElement.lang || "").slice(0, 2) === "de"
    const labels = german
        ? {dialog: "Bild", prev: "Vorheriges Bild", next: "Nächstes Bild", close: "Schließen"}
        : {dialog: "Image", prev: "Previous image", next: "Next image", close: "Close"}

    let dialog, image, caption, counter, prevButton, nextButton, closeButton
    let group = []
    let index = 0
    let touchX = null

    function build() {
        dialog = document.createElement("dialog")
        dialog.className = "lightbox"
        dialog.setAttribute("aria-label", labels.dialog)
        dialog.innerHTML =
            '<figure class="lightbox-figure">' +
            '<img class="lightbox-image" alt="">' +
            '<figcaption class="lightbox-caption"></figcaption>' +
            '</figure>' +
            '<span class="lightbox-counter"></span>' +
            '<button type="button" class="lightbox-button lightbox-prev" aria-label="' + labels.prev + '">&#8249;</button>' +
            '<button type="button" class="lightbox-button lightbox-next" aria-label="' + labels.next + '">&#8250;</button>' +
            '<button type="button" class="lightbox-button lightbox-close" aria-label="' + labels.close + '">&times;</button>'
        document.body.appendChild(dialog)
        image = dialog.querySelector(".lightbox-image")
        caption = dialog.querySelector(".lightbox-caption")
        counter = dialog.querySelector(".lightbox-counter")
        prevButton = dialog.querySelector(".lightbox-prev")
        nextButton = dialog.querySelector(".lightbox-next")
        prevButton.addEventListener("click", () => show(index - 1))
        nextButton.addEventListener("click", () => show(index + 1))
        closeButton = dialog.querySelector(".lightbox-close")
        closeButton.addEventListener("click", () => dialog.close())
        // a click outside the image, on the backdrop or the empty area, closes
        dialog.addEventListener("click", (event) => {
            if (event.target === dialog || event.target.classList.contains("lightbox-figure")) {
                dialog.close()
            }
        })
        dialog.addEventListener("keydown", (event) => {
            if (event.key === "ArrowLeft") {
                show(index - 1)
            } else if (event.key === "ArrowRight") {
                show(index + 1)
            }
        })
        // a horizontal swipe pages on touch screens
        dialog.addEventListener("touchstart", (event) => {
            touchX = event.touches.length === 1 ? event.touches[0].clientX : null
        }, {passive: true})
        dialog.addEventListener("touchend", (event) => {
            if (touchX !== null && group.length > 1) {
                const dx = event.changedTouches[0].clientX - touchX
                if (Math.abs(dx) > 50) {
                    show(dx < 0 ? index + 1 : index - 1)
                }
            }
            touchX = null
        })
        dialog.addEventListener("close", () => {
            document.documentElement.classList.remove("lightbox-open")
            image.removeAttribute("src")
        })
    }

    function show(newIndex) {
        index = (newIndex + group.length) % group.length
        const link = group[index]
        const text = link.dataset.caption || ""
        image.src = link.getAttribute("href")
        image.alt = text
        caption.textContent = text
        caption.hidden = !text
        const single = group.length < 2
        prevButton.hidden = single
        nextButton.hidden = single
        counter.hidden = single
        counter.textContent = (index + 1) + " / " + group.length
        // load the next image ahead, so paging does not wait
        if (!single) {
            new Image().src = group[(index + 1) % group.length].getAttribute("href")
        }
    }

    document.addEventListener("click", (event) => {
        const link = event.target.closest("a[data-lightbox]")
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return
        }
        event.preventDefault()
        if (!dialog) {
            build()
        }
        group = Array.from(document.querySelectorAll('a[data-lightbox="' + CSS.escape(link.dataset.lightbox) + '"]'))
        show(group.indexOf(link))
        document.documentElement.classList.add("lightbox-open")
        dialog.showModal()
        // the arrow keys page, so the focus belongs on the close button
        closeButton.focus()
    })
})()
