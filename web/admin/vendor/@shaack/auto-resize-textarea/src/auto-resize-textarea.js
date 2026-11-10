/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/auto-resize-textarea
 * License: MIT, see file 'LICENSE'
 */
var autoResizeTextarea = function (elements, options) {
    var config = {
        maxHeight: Infinity
    }
    for (var option in options) {
        config[option] = options[option]
    }
    for (var i = 0; i < elements.length; i++) {
        forEachElement(elements[i])
    }
    function elementHeight(element) {
        return parseFloat(getComputedStyle(element, null).height.replace("px", ""))
    }
    function initInternal(element) {
        var initialDisplay = element.style.display
        element.style.display = "block" // prevent display="none"
        element.autoResizeTextarea.initialHeight = elementHeight(element)
        element.autoResizeTextarea.initialScrollHeight = parseFloat(element.scrollHeight)
        if (element.autoResizeTextarea.initialScrollHeight > element.autoResizeTextarea.initialHeight) {
            element.autoResizeTextarea.initialHeight = element.autoResizeTextarea.initialScrollHeight + 2
        }
        element.style.height = element.autoResizeTextarea.initialHeight + "px"
        element.style.display = initialDisplay
    }
    function init(element) {
        element.autoResizeTextarea = {}
        initInternal(element)
        updateElement(element)
        if (!element.autoResizeTextarea.initialHeight) {
            // try again, element might take longer to render
            setTimeout(function () {
                initInternal(element)
                updateElement(element)
            }, 500)
        }
    }
    function updateElement(element) {
        element.style.height = element.autoResizeTextarea.initialHeight + "px"
        var newHeight = element.autoResizeTextarea.initialHeight + element.scrollHeight - element.autoResizeTextarea.initialScrollHeight
        newHeight = Math.max(newHeight, element.autoResizeTextarea.initialHeight)
        element.style.height = Math.min(newHeight, config.maxHeight) + "px"
    }
    function forEachElement(element) {
        init(element)
        element.addEventListener("input", function () {
            updateElement(element)
        })
    }
}
