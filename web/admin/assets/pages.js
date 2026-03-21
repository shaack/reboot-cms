(function () {
    var config = window.pagesConfig;
    if (!config) return;

    var csrfToken = config.csrfToken;
    var currentPage = config.currentPage;
    var allFolders = config.allFolders;

    // --- Save ---

    window.savePageAsync = function () {
        var form = document.querySelector('form[action^="pages?page="]');
        if (!form) return;
        var formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData
        }).then(function (response) {
            if (response.ok) {
                return response.text().then(function (html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var resultEl = doc.getElementById('save-result');
                    if (resultEl) {
                        var data = JSON.parse(resultEl.textContent);
                        if (!data.changed) {
                            statusMessage("No changes", "text-bg-secondary");
                            return;
                        }
                        pageUnsaved = false;
                        savedContent = editorTextarea ? editorTextarea.value : '';
                        if (data.validationErrors && data.validationErrors.length > 0) {
                            var list = data.validationErrors.map(function (e) { return "<li>" + e + "</li>"; }).join("");
                            var msg = "Page saved with " + data.validationErrors.length + " schema warning(s):<ul class='mb-0 mt-1'>" + list + "</ul>";
                            var failingBlocks = {};
                            data.validationErrors.forEach(function (e) {
                                var m = e.match(/^Block '([^']+)':/);
                                if (m && data.examples[m[1]]) failingBlocks[m[1]] = true;
                            });
                            for (var blockName in failingBlocks) {
                                msg += "<hr class='my-2'><strong>Expected markdown for &quot;" + blockName + "&quot;:</strong>";
                                msg += "<pre class='mb-1 p-1 bg-light text-dark rounded' style='font-size:0.8rem;white-space:pre-wrap'>" + data.examples[blockName].replace(/</g, "&lt;") + "</pre>";
                            }
                            statusMessage(msg, "text-bg-warning");
                        } else {
                            statusMessage("Page saved");
                        }
                    } else {
                        statusMessage("Page saved");
                    }
                });
            } else {
                statusMessage("Error saving page", "text-bg-danger");
            }
        }).catch(function () {
            statusMessage("Error saving page", "text-bg-danger");
        });
    };

    // --- Unsaved changes tracking ---

    var pageUnsaved = false;
    var editorTextarea = document.querySelector('textarea[name="edited"]');
    var savedContent = editorTextarea ? editorTextarea.value : '';
    if (editorTextarea) {
        editorTextarea.addEventListener('input', function () {
            pageUnsaved = editorTextarea.value !== savedContent;
        });
    }
    window.addEventListener('beforeunload', function (e) {
        if (pageUnsaved) {
            e.preventDefault();
        }
    });
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 's') {
            e.preventDefault();
            window.savePageAsync();
        }
    });

    // --- Folder collapse icons ---

    document.querySelectorAll('.page-tree-folder').forEach(function (folder) {
        var target = document.querySelector(folder.getAttribute('href'));
        if (target) {
            target.addEventListener('show.bs.collapse', function () {
                folder.querySelector('.folder-icon').innerHTML = '&#9660;';
            });
            target.addEventListener('hide.bs.collapse', function () {
                folder.querySelector('.folder-icon').innerHTML = '&#9654;';
            });
        }
    });

    // --- Page actions ---

    function submitPageAction(action, name, extra) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'pages';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '">'
            + '<input type="hidden" name="action" value="' + action + '">'
            + '<input type="hidden" name="name" value="' + name + '">';
        if (extra) {
            for (var key in extra) {
                form.innerHTML += '<input type="hidden" name="' + key + '" value="' + extra[key] + '">';
            }
        }
        document.body.appendChild(form);
        form.submit();
    }

    window.renamePage = function () {
        if (!currentPage) return;
        var baseName = currentPage.replace(/\.md$/, '').split('/').pop();
        var newName = prompt('Rename page:', baseName);
        if (newName === null || newName === baseName) return;
        submitPageAction('rename_page', currentPage, {new_name: newName});
    };

    window.movePage = function () {
        if (!currentPage) return;
        var currentFolder = currentPage.replace(/\/[^/]+$/, '') || '/';
        var options = allFolders.map(function (f) {
            return (f === currentFolder ? '> ' : '  ') + f;
        }).join('\n');
        var dest = prompt('Move page to folder:\n\n' + options + '\n\nEnter folder path:', currentFolder);
        if (dest === null || dest === currentFolder) return;
        dest = dest.replace(/^\//, '').replace(/\/$/, '');
        submitPageAction('move_page', currentPage, {destination: dest});
    };

    window.deletePage = function () {
        if (!currentPage) return;
        var baseName = currentPage.replace(/\.md$/, '').split('/').pop();
        if (!confirm('Delete page \'' + baseName + '\'?')) return;
        submitPageAction('delete_page', currentPage);
    };

    window.renameFolder = function (folderPath, folderName) {
        var newName = prompt('Rename folder:', folderName);
        if (newName === null || newName === folderName) return;
        submitPageAction('rename_folder', folderPath, {new_name: newName});
    };

    window.deleteFolder = function (folderPath, folderName) {
        if (!confirm('Delete empty folder \'' + folderName + '\'?')) return;
        submitPageAction('delete_folder', folderPath);
    };

    // --- History ---

    var historyCache = null;

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;').replace(/"/g, '&quot;');
    }

    window.toggleHistory = function () {
        var modal = document.getElementById('page-history-modal');
        if (!modal) return;
        var body = document.getElementById('page-history-body');
        var count = document.getElementById('page-history-count');
        body.innerHTML = '<div class="p-4 text-center text-body-secondary">Loading…</div>';
        count.textContent = '';
        new bootstrap.Modal(modal).show();
        fetch('pages?history=1&page=' + encodeURIComponent(currentPage))
            .then(function (r) { return r.json(); })
            .then(function (versions) {
                historyCache = versions;
                count.textContent = versions.length + ' version(s)';
                if (versions.length === 0) {
                    body.innerHTML = '<div class="p-4 text-body-secondary">No history available.</div>';
                    return;
                }
                var html = '<ul class="list-group list-group-flush" style="max-height:60vh;overflow-y:auto;">';
                versions.forEach(function (v) {
                    var kb = (v.size / 1024).toFixed(1);
                    html += '<li class="list-group-item d-flex align-items-center" style="font-size:0.85rem;">'
                        + '<span class="flex-grow-1">' + escapeHtml(v.timestamp) + '</span>'
                        + '<span class="text-body-secondary me-2">' + kb + ' KB</span>'
                        + '<a href="#" class="btn btn-sm btn-outline-secondary me-1" onclick="previewVersion(\'' + escapeHtml(v.filename) + '\'); return false;">Preview</a>'
                        + '<a href="#" class="btn btn-sm btn-outline-primary" onclick="restoreVersion(\'' + escapeHtml(v.filename) + '\'); return false;">Restore</a>'
                        + '</li>';
                });
                html += '</ul>';
                body.innerHTML = html;
            })
            .catch(function () {
                body.innerHTML = '<div class="p-4 text-danger">Failed to load history.</div>';
            });
    };

    window.previewVersion = function (filename) {
        if (!historyCache) return;
        var version = historyCache.find(function (v) { return v.filename === filename; });
        if (!version) return;
        var win = window.open('', '_blank');
        win.document.write('<html><head><title>Preview: ' + filename + '</title>'
            + '<style>'
            + ':root { color-scheme: light dark; }'
            + 'body { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;'
            + ' padding: 2rem; white-space: pre-wrap; max-width: 80ch; margin: 0 auto;'
            + ' color: light-dark(#1a1a1a, #e0e0e0); background: light-dark(#fff, #1a1a1a); }'
            + '</style></head>'
            + '<body>' + version.content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</body></html>');
        win.document.close();
    };

    window.restoreVersion = function (filename) {
        if (!confirm('Restore this version? Current content will be saved as a snapshot first.')) return;
        submitPageAction('restore_page', currentPage, {version: filename});
    };

    // --- Preview (double-buffered) ---

    var previewActive = localStorage.getItem('reboot_preview') === 'true';
    var previewDebounceTimer = null;
    var previewInitialized = false;
    var previewFront = 'a';

    function getFrontIframe() {
        return document.getElementById('preview-iframe-' + previewFront);
    }

    function getBackIframe() {
        return document.getElementById('preview-iframe-' + (previewFront === 'a' ? 'b' : 'a'));
    }

    function getPreviewForm(targetName) {
        var previewForm = document.getElementById('preview-form');
        if (!previewForm) {
            previewForm = document.createElement('form');
            previewForm.id = 'preview-form';
            previewForm.method = 'POST';
            previewForm.action = 'pages?preview=1';
            previewForm.style.display = 'none';
            previewForm.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '">'
                + '<input type="hidden" name="page" value="">'
                + '<input type="hidden" name="content" value="">';
            document.body.appendChild(previewForm);
        }
        previewForm.target = targetName;
        return previewForm;
    }

    function updatePreview() {
        if (!previewActive || !currentPage) return;
        var front = getFrontIframe();
        var back = getBackIframe();
        var content = editorTextarea ? editorTextarea.value : '';
        var savedScrollTop = 0;
        if (previewInitialized && front.contentDocument) {
            var doc = front.contentDocument;
            savedScrollTop = doc.documentElement.scrollTop || doc.body.scrollTop || 0;
        }
        var form = getPreviewForm(back.name);
        form.querySelector('[name="page"]').value = currentPage;
        form.querySelector('[name="content"]').value = content;
        back.onload = function () {
            back.onload = null;
            previewInitialized = true;
            front.style.visibility = 'hidden';
            front.style.position = 'absolute';
            back.style.visibility = 'visible';
            back.style.position = '';
            previewFront = (previewFront === 'a') ? 'b' : 'a';
            if (back.contentDocument) {
                back.contentDocument.documentElement.scrollTop = savedScrollTop;
            }
        };
        form.submit();
    }

    function schedulePreviewUpdate() {
        if (previewDebounceTimer) clearTimeout(previewDebounceTimer);
        previewDebounceTimer = setTimeout(updatePreview, 500);
    }

    var lastSyncedBlock = -1;

    function syncPreviewToBlock() {
        if (!previewActive || !previewInitialized || !editorTextarea) return;
        var iframe = getFrontIframe();
        try {
            var doc = iframe.contentDocument;
            var sections = doc.querySelectorAll('section.block');
            if (sections.length === 0) return;
            var cursorPos = editorTextarea.selectionStart;
            var textBeforeCursor = editorTextarea.value.substring(0, cursorPos);
            var blockIndex = (textBeforeCursor.match(/<!--[\s\S]*?-->/g) || []).length - 1;
            if (blockIndex < 0) blockIndex = 0;
            if (blockIndex >= sections.length) blockIndex = sections.length - 1;
            if (blockIndex === lastSyncedBlock) return;
            lastSyncedBlock = blockIndex;
            var section = sections[blockIndex];
            var iframeHeight = iframe.clientHeight;
            var scrollTarget = section.offsetTop - (iframeHeight / 2) + (section.offsetHeight / 2);
            doc.documentElement.scrollTo({top: Math.max(0, scrollTarget), behavior: 'smooth'});
        } catch (e) {}
    }

    window.togglePreview = function () {
        previewActive = !previewActive;
        localStorage.setItem('reboot_preview', previewActive);
        var editorCol = document.getElementById('editor-column');
        var previewCol = document.getElementById('preview-column');
        var toggleBtn = document.getElementById('preview-toggle');
        if (previewActive) {
            editorCol.classList.remove('col-lg-9', 'col-xl-10');
            editorCol.classList.add('col-lg-5', 'col-xl-6');
            previewCol.classList.remove('d-lg-none');
            previewCol.classList.add('d-lg-block');
            toggleBtn.classList.remove('btn-outline-secondary');
            toggleBtn.classList.add('btn-secondary');
            updatePreview();
            if (editorTextarea) {
                editorTextarea.addEventListener('input', schedulePreviewUpdate);
                editorTextarea.addEventListener('click', syncPreviewToBlock);
                editorTextarea.addEventListener('keyup', syncPreviewToBlock);
            }
        } else {
            editorCol.classList.remove('col-lg-5', 'col-xl-6');
            editorCol.classList.add('col-lg-9', 'col-xl-10');
            previewCol.classList.remove('d-lg-block');
            previewCol.classList.add('d-lg-none');
            toggleBtn.classList.remove('btn-secondary');
            toggleBtn.classList.add('btn-outline-secondary');
            previewInitialized = false;
            if (editorTextarea) {
                editorTextarea.removeEventListener('input', schedulePreviewUpdate);
                editorTextarea.removeEventListener('click', syncPreviewToBlock);
                editorTextarea.removeEventListener('keyup', syncPreviewToBlock);
            }
        }
    };

    // Restore preview state on load (only on lg+ screens)
    if (previewActive && currentPage && window.innerWidth >= 992) {
        previewActive = false;
        window.togglePreview();
    }
})();
