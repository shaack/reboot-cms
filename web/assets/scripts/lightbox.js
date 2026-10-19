/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 *
 * Opens linked images enlarged in a dialog. A link takes part with the
 * attribute `data-lightbox`, links with the same value form a group that can
 * be paged with the arrow keys or the buttons. `data-caption` sets the text
 * below the image. Escape, a click on the backdrop or the close button end it.
 */
(function () {
    "use strict"

    let dialog, image, caption, prevButton, nextButton, closeButton
    let group = []
    let index = 0

    function build() {
        dialog = document.createElement("dialog")
        dialog.className = "lightbox"
        dialog.setAttribute("aria-label", "Image")
        dialog.innerHTML =
            '<figure class="lightbox-figure">' +
            '<img class="lightbox-image" alt="">' +
            '<figcaption class="lightbox-caption"></figcaption>' +
            '</figure>' +
            '<button type="button" class="lightbox-button lightbox-prev" aria-label="Previous image">&#8249;</button>' +
            '<button type="button" class="lightbox-button lightbox-next" aria-label="Next image">&#8250;</button>' +
            '<button type="button" class="lightbox-button lightbox-close" aria-label="Close">&times;</button>'
        document.body.appendChild(dialog)
        image = dialog.querySelector(".lightbox-image")
        caption = dialog.querySelector(".lightbox-caption")
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
        dialog.addEventListener("close", () => {
            document.documentElement.classList.remove("lightbox-open")
            image.removeAttribute("src")
        })
    }

    function show(newIndex) {
        index = (newIndex + group.length) % group.length
        const link = group[index]
        image.src = link.getAttribute("href")
        image.alt = link.dataset.caption || ""
        caption.textContent = link.dataset.caption || ""
        caption.hidden = !link.dataset.caption
        const single = group.length < 2
        prevButton.hidden = single
        nextButton.hidden = single
    }

    document.addEventListener("click", (event) => {
        const link = event.target.closest("a[data-lightbox]")
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
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
