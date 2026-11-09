(function () {
    var config = window.updateConfig;
    if (!config) return;

    var localVersion = config.localVersion;
    var csrfToken = config.csrfToken;
    var branch = config.branch;
    var isUnstable = config.isUnstable;

    function major(version) {
        return String(version).split(".")[0];
    }

    // The next major version is published. It has its own notes on what changes,
    // update() never installs it, the button below upgrades on request: a backup
    // of the content first, the replaced files are kept for the way back.
    function showNextMajor(nextMajor) {
        if (!nextMajor || !nextMajor.version || !/^https:\/\/github\.com\//.test(nextMajor.notes || "")) return;
        var version = String(nextMajor.version).replace(/[^0-9.]/g, "");
        var notice = document.createElement("div");
        notice.className = "alert alert-info";
        var text = document.createElement("p");
        text.textContent = "Reboot CMS " + version + " is available. It is a major update, read what changes " +
            "before you upgrade. Pages, blocks and AddOns of your site keep working. ";
        var link = document.createElement("a");
        link.href = nextMajor.notes;
        link.target = "_blank";
        link.rel = "noopener";
        link.textContent = "What changes in Reboot CMS " + major(version);
        text.appendChild(link);
        notice.appendChild(text);
        var findings = Array.isArray(nextMajor.findings) ? nextMajor.findings : [];
        if (findings.length) {
            var intro = document.createElement("p");
            intro.className = "mb-1";
            intro.textContent = "In your site, Reboot CMS " + major(version) + " reads these differently:";
            notice.appendChild(intro);
            var list = document.createElement("ul");
            findings.forEach(function (finding) {
                var item = document.createElement("li");
                item.textContent = finding;
                list.appendChild(item);
            });
            notice.appendChild(list);
        }
        var form = document.createElement("form");
        form.method = "post";
        form.action = "update";
        form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
            '<input type="hidden" name="action" value="upgrade">' +
            '<button class="btn btn-sm btn-primary"></button>';
        form.querySelector("button").textContent = "Upgrade to Reboot CMS " + version;
        notice.appendChild(form);
        document.getElementById("major-notice").appendChild(notice);
        form.addEventListener("submit", function (e) {
            if (!window.confirm("Upgrade to Reboot CMS " + version + "? A backup of the content is made first. " +
                "core/, web/admin/ and vendor/ are replaced, the current version is kept in local/previous-version/ " +
                "and the Update page offers the way back.")) {
                e.preventDefault();
                return;
            }
            form.style.display = "none";
            var spinner = document.createElement("div");
            spinner.className = "d-flex align-items-center";
            spinner.innerHTML =
                '<div class="spinner-border spinner-border-sm text-primary" role="status" ' +
                'style="margin-right:.6rem"><span class="visually-hidden">Upgrading...</span></div>' +
                '<span>Upgrading Reboot CMS. This can take a moment, please do not close this page.</span>';
            notice.appendChild(spinner);
        });
    }

    // Going back to the version before an upgrade, or deleting it, asks first
    document.querySelectorAll(".update-previous-form").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            if (!window.confirm(form.getAttribute("data-confirm"))) {
                e.preventDefault();
            }
        });
    });

    document.getElementById("branch-select").addEventListener("change", function () {
        window.location.href = "update?branch=" + encodeURIComponent(this.value);
    });

    // Show a visible progress indicator while the (synchronous) update POST is
    // in flight. The <form> must stay in the document — removing it during its
    // own submit handler would cancel the POST — so it is only hidden, and the
    // spinner is added alongside it.
    function showUpdating(form) {
        form.style.display = "none";
        var spinner = document.createElement("div");
        spinner.className = "d-flex align-items-center";
        spinner.innerHTML =
            '<div class="spinner-border spinner-border-sm text-primary" role="status" ' +
            'style="margin-right:.6rem"><span class="visually-hidden">Updating...</span></div>' +
            '<span>Updating Reboot CMS &mdash; downloading and installing files. ' +
            'This can take a moment, please do not close this page.</span>';
        document.getElementById("update-actions").appendChild(spinner);
    }

    // Confirm the update on submit, then show the progress indicator before
    // the (synchronous) POST navigates away.
    function wireUpdateForm(confirmMessage) {
        var form = document.querySelector("#update-actions form");
        if (!form) return;
        form.addEventListener("submit", function (e) {
            if (!window.confirm(confirmMessage)) {
                e.preventDefault();
                return;
            }
            showUpdating(form);
        });
    }

    // Hand the freshly fetched version to the navbar badge, so its dot and its
    // cache match what this page just learned. The badge script may not have
    // run yet, then it picks the result up on startup.
    function reportToBadge(remoteVersion) {
        window.rebootUpdateCheckResult = {version: remoteVersion, branch: branch}
        if (window.rebootUpdateBadge) {
            window.rebootUpdateBadge.report(remoteVersion, branch)
        }
    }

    function updateForm(buttonLabel) {
        return '<form method="post" action="update?branch=' + encodeURIComponent(branch) + '">' +
            '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
            '<input type="hidden" name="action" value="update">' +
            '<button class="btn btn-sm btn-primary">' + buttonLabel + '</button>' +
            '</form>';
    }

    fetch("update?check_version=1&branch=" + encodeURIComponent(branch))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            data = data || {};
            showNextMajor(data.nextMajor);
            var cell = document.getElementById("remote-version-cell");
            var actions = document.getElementById("update-actions");
            if (isUnstable) {
                var remoteCommit = data.remoteCommit;
                var localCommit = data.localCommit;
                if (remoteCommit) {
                    // commit hashes, anything else from the answer is not shown
                    var shortRemote = remoteCommit.substring(0, 7).replace(/[^0-9a-f]/gi, '');
                    var shortLocal = localCommit ? localCommit.substring(0, 7).replace(/[^0-9a-f]/gi, '') : null;
                    var isUpToDate = localCommit && remoteCommit.startsWith(localCommit);
                    cell.innerHTML = '<strong>' + shortRemote + '</strong>';
                    if (isUpToDate) {
                        actions.innerHTML = '<p class="text-muted mb-0">You are running the latest commit (' + shortLocal + ').</p>';
                    } else {
                        var installedInfo = shortLocal ? ' (installed: ' + shortLocal + ')' : '';
                        actions.innerHTML = updateForm('Update to ' + shortRemote);
                        wireUpdateForm('Update Reboot CMS from branch ' + branch + ' (unstable) to commit ' +
                            shortRemote + '.' + installedInfo + ' To be safe, you should make a backup ' +
                            'of the project folder first. This will replace core/, web/admin/ and vendor/.');
                    }
                } else {
                    cell.innerHTML = '<span class="text-muted">unavailable</span>';
                    actions.innerHTML = '<p class="text-muted mb-0">Could not check for updates. Please verify your internet connection.</p>';
                }
            } else if (data.version) {
                reportToBadge(data.version);
                var version = document.createElement("strong");
                version.textContent = data.version;
                cell.innerHTML = "";
                cell.appendChild(version);
                if (major(data.version) !== major(localVersion)) {
                    actions.innerHTML = '<p class="text-muted mb-0">The branch holds another major version, the updater does not install it.</p>';
                } else if (data.version.localeCompare(localVersion, undefined, {numeric: true, sensitivity: 'base'}) > 0) {
                    var safeVersion = data.version.replace(/[<>"'&]/g, '');
                    actions.innerHTML = updateForm('Update to ' + safeVersion);
                    wireUpdateForm('Update Reboot CMS to version ' + safeVersion +
                        '. To be safe, you should make a backup of the project folder first. ' +
                        'This will replace core/, web/admin/ and vendor/.');
                } else {
                    actions.innerHTML = '<p class="text-muted mb-0">You are running the latest version.</p>';
                }
            } else {
                cell.innerHTML = '<span class="text-muted">unavailable</span>';
                actions.innerHTML = '<p class="text-muted mb-0">Could not check for updates. Please verify your internet connection.</p>';
            }
        })
        .catch(function () {
            document.getElementById("remote-version-cell").innerHTML = '<span class="text-muted">unavailable</span>';
            document.getElementById("update-actions").innerHTML = '<p class="text-muted mb-0">Could not check for updates. Please verify your internet connection.</p>';
        });
})();
