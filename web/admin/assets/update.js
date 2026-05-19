(function () {
    var config = window.updateConfig;
    if (!config) return;

    var localVersion = config.localVersion;
    var csrfToken = config.csrfToken;
    var branch = config.branch;

    document.getElementById("branch-select").addEventListener("change", function () {
        window.location.href = "update?branch=" + encodeURIComponent(this.value);
    });

    // Replace the action area with a visible progress indicator. The update is
    // a synchronous form POST; swapping the DOM right before it proceeds means
    // the spinner stays on screen while the files download and install.
    function showUpdating() {
        document.getElementById("update-actions").innerHTML =
            '<div class="d-flex align-items-center">' +
            '<div class="spinner-border spinner-border-sm text-primary" role="status" ' +
            'style="margin-right:.6rem">' +
            '<span class="visually-hidden">Updating...</span></div>' +
            '<span>Updating Reboot CMS &mdash; downloading and installing files. ' +
            'This can take a moment, please do not close this page.</span>' +
            '</div>';
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
            showUpdating();
        });
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
            var cell = document.getElementById("remote-version-cell");
            var actions = document.getElementById("update-actions");
            if (branch === "main") {
                var remoteCommit = data.remoteCommit;
                var localCommit = data.localCommit;
                if (remoteCommit) {
                    var shortRemote = remoteCommit.substring(0, 7);
                    var shortLocal = localCommit ? localCommit.substring(0, 7) : null;
                    var isUpToDate = localCommit && remoteCommit.startsWith(localCommit);
                    cell.innerHTML = '<strong>' + shortRemote + '</strong>';
                    if (isUpToDate) {
                        actions.innerHTML = '<p class="text-muted mb-0">You are running the latest commit (' + shortLocal + ').</p>';
                    } else {
                        var installedInfo = shortLocal ? ' (installed: ' + shortLocal + ')' : '';
                        actions.innerHTML = updateForm('Update to ' + shortRemote);
                        wireUpdateForm('Update Reboot CMS from branch main (unstable) to commit ' +
                            shortRemote + '.' + installedInfo + ' To be safe, you should make a backup ' +
                            'of the project folder first. This will replace core/, web/admin/ and vendor/.');
                    }
                } else {
                    cell.innerHTML = '<span class="text-muted">unavailable</span>';
                    actions.innerHTML = '<p class="text-muted mb-0">Could not check for updates. Please verify your internet connection.</p>';
                }
            } else if (data.version) {
                var version = document.createElement("strong");
                version.textContent = data.version;
                cell.innerHTML = "";
                cell.appendChild(version);
                if (data.version.localeCompare(localVersion, undefined, {numeric: true, sensitivity: 'base'}) > 0) {
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
