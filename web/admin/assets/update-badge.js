/**
 * Puts a red dot on the "Admin" menu and its "Update" entry as soon as a newer
 * version of Reboot CMS is published.
 *
 * The check runs in the browser and its result is cached for a day, so the
 * admin does not wait for GitHub on every page and the API is called at most
 * once per day and browser. The cache is keyed by the installed version, so the
 * dot disappears by itself after an update.
 *
 * The update page checks the version anyway. It reports its result here
 * (`window.rebootUpdateBadge.report()`, or `window.rebootUpdateCheckResult` if
 * this script has not run yet), which refreshes the cache and saves a second
 * request.
 */
(function () {
    var config = window.updateBadgeConfig
    if (!config || !config.localVersion) return

    var CACHE_KEY = "rebootUpdateCheck"
    var CACHE_TTL = 24 * 60 * 60 * 1000 // once a day is enough for a version check

    function readCache() {
        try {
            var cached = JSON.parse(window.localStorage.getItem(CACHE_KEY))
            if (!cached) return null
            if (cached.localVersion !== config.localVersion) return null
            if (cached.branch !== config.branch) return null
            if (Date.now() - cached.time > CACHE_TTL) return null
            return cached.remoteVersion
        } catch (e) {
            return null
        }
    }

    function writeCache(remoteVersion) {
        try {
            window.localStorage.setItem(CACHE_KEY, JSON.stringify({
                remoteVersion: remoteVersion,
                localVersion: config.localVersion,
                branch: config.branch,
                time: Date.now()
            }))
        } catch (e) {
            // private mode or full storage, the check just runs again next time
        }
    }

    function isNewer(remoteVersion) {
        return remoteVersion &&
            remoteVersion.localeCompare(config.localVersion, undefined, {numeric: true, sensitivity: "base"}) > 0
    }

    function addDot(element, remoteVersion) {
        if (!element || element.querySelector(".update-dot")) return
        var dot = document.createElement("span")
        dot.className = "update-dot"
        dot.title = "Version " + remoteVersion + " is available"
        dot.setAttribute("role", "img")
        dot.setAttribute("aria-label", "Update available")
        element.appendChild(dot)
    }

    function clearNavigation() {
        var dots = document.querySelectorAll(".update-dot")
        for (var i = 0; i < dots.length; i++) {
            dots[i].parentNode.removeChild(dots[i])
        }
    }

    function markNavigation(remoteVersion) {
        var updateLink = document.querySelector('[data-nav-path="/update"]')
        if (!updateLink) return
        addDot(updateLink, remoteVersion)
        var dropdown = updateLink.closest(".dropdown")
        if (dropdown) {
            addDot(dropdown.querySelector(".dropdown-toggle"), remoteVersion)
        }
    }

    /**
     * Takes a version that was just fetched elsewhere, usually by the update
     * page, refreshes the cache and sets or removes the dot right away.
     *
     * @param remoteVersion the published version
     * @param branch the branch it was read from, ignored if it is not the one
     *        this badge watches
     */
    function report(remoteVersion, branch) {
        if (!remoteVersion) return
        if (branch && branch !== config.branch) return
        writeCache(remoteVersion)
        if (isNewer(remoteVersion)) markNavigation(remoteVersion)
        else clearNavigation()
    }

    window.rebootUpdateBadge = {report: report, branch: config.branch}

    // The update page may have finished its own check before this script ran.
    if (window.rebootUpdateCheckResult) {
        report(window.rebootUpdateCheckResult.version, window.rebootUpdateCheckResult.branch)
        return
    }

    var cachedVersion = readCache()
    if (cachedVersion !== null) {
        if (isNewer(cachedVersion)) markNavigation(cachedVersion)
        return
    }

    // On the update page the check is running anyway, its result arrives
    // through report(). Asking GitHub a second time would be wasteful.
    if (window.updateConfig) return

    fetch("update?check_version=1&branch=" + encodeURIComponent(config.branch))
        .then(function (response) { return response.json() })
        .then(function (data) {
            var remoteVersion = (data && data.version) ? data.version : null
            if (!remoteVersion) return
            writeCache(remoteVersion)
            if (isNewer(remoteVersion)) markNavigation(remoteVersion)
        })
        .catch(function () {
            // no connection, no dot
        })
})()
