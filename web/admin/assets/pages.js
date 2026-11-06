(function () {
    var config = window.pagesConfig;
    if (!config) return;

    var csrfToken = config.csrfToken;

    // localStorage throws when the browser blocks site data, the settings are
    // then only kept for this page view
    var store = {
        get: function (key) {
            try { return localStorage.getItem(key); } catch (e) { return null; }
        },
        set: function (key, value) {
            try { localStorage.setItem(key, value); } catch (e) { /* not kept */ }
        },
        remove: function (key) {
            try { localStorage.removeItem(key); } catch (e) { /* not kept */ }
        }
    };
    var currentPage = config.currentPage;
    var allFolders = config.allFolders;

    // --- Save ---

    window.savePageAsync = function () {
        var form = document.querySelector('form[action^="pages?page="]');
        if (!form) return;
        var formData = new FormData(form);
        // what is saved, typing during the request stays unsaved
        var sentContent = editorTextarea ? editorTextarea.value : '';
        var notSaved = function (reason) {
            statusMessage("<strong>The page was not saved.</strong> " + reason, "text-bg-danger");
        };
        fetch(form.action, {
            method: 'POST',
            body: formData
        }).then(function (response) {
            if (!response.ok) {
                notSaved("The server answered with error " + response.status + ".");
                return;
            }
            return response.text().then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var resultEl = doc.getElementById('save-result');
                if (!resultEl) {
                    // fetch follows the redirect to the login, which answers with 200
                    notSaved(response.redirected
                        ? "Your session has expired. Log in again in a new tab, then save here once more."
                        : "The answer of the server was unexpected.");
                    return;
                }
                var data = JSON.parse(resultEl.textContent);
                if (data.error) {
                    notSaved(escapeHtml(data.error));
                    return;
                }
                savedContent = sentContent;
                updateUnsaved();
                if (!data.changed) {
                    statusMessage("No changes", "text-bg-secondary");
                    return;
                }
                if (data.validationErrors && data.validationErrors.length > 0) {
                    var list = data.validationErrors.map(function (e) { return "<li>" + escapeHtml(e) + "</li>"; }).join("");
                    var msg = "Page saved with " + data.validationErrors.length + " schema warning(s):<ul class='mb-0 mt-1'>" + list + "</ul>";
                    var failingBlocks = {};
                    data.validationErrors.forEach(function (e) {
                        var m = e.match(/^Block '([^']+)':/);
                        if (m && data.examples[m[1]]) failingBlocks[m[1]] = true;
                    });
                    for (var blockName in failingBlocks) {
                        msg += "<hr class='my-2'><strong>Expected markdown for &quot;" + escapeHtml(blockName) + "&quot;:</strong>";
                        msg += "<pre class='mb-1 p-1 bg-light text-dark rounded' style='font-size:0.8rem;white-space:pre-wrap'>" + escapeHtml(data.examples[blockName]) + "</pre>";
                    }
                    statusMessage(msg, "text-bg-warning");
                } else {
                    statusMessage("Page saved");
                }
            });
        }).catch(function () {
            notSaved("The server could not be reached.");
        });
    };

    // --- Unsaved changes tracking ---

    var pageUnsaved = false;
    var editorTextarea = document.querySelector('textarea[name="edited"]');
    var savedContent = editorTextarea ? editorTextarea.value : '';
    var unsavedBadge = document.getElementById('unsaved-badge');
    var unsavedDot = document.querySelector('#save-button .unsaved-dot');

    // shown next to the page name and on the save button
    function updateUnsaved() {
        pageUnsaved = editorTextarea ? editorTextarea.value !== savedContent : false;
        if (unsavedBadge) unsavedBadge.classList.toggle('d-none', !pageUnsaved);
        if (unsavedDot) unsavedDot.classList.toggle('d-none', !pageUnsaved);
    }

    if (editorTextarea) {
        editorTextarea.addEventListener('input', updateUnsaved);
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

    // --- Folder collapse state (persisted in localStorage) ---

    var STORAGE_KEY = 'reboot_sidebar_expanded';
    function getExpandedFolders() {
        try { return JSON.parse(store.get(STORAGE_KEY)) || {}; } catch (e) { return {}; }
    }
    function saveExpandedFolders(map) {
        store.set(STORAGE_KEY, JSON.stringify(map));
    }

    // Restore saved state: expand folders that were previously open
    var expanded = getExpandedFolders();
    document.querySelectorAll('.page-tree-folder').forEach(function (folder) {
        var target = document.querySelector(folder.getAttribute('href'));
        if (!target) return;
        var id = target.id;
        // Expand if saved as open (and not already expanded by the server for the active page)
        if (expanded[id] && !target.classList.contains('show')) {
            target.classList.add('show');
            folder.setAttribute('aria-expanded', 'true');
        }
        // Track show/hide and persist
        target.addEventListener('show.bs.collapse', function (e) {
            if (e.target !== target || filtering) return;
            var map = getExpandedFolders();
            map[id] = true;
            saveExpandedFolders(map);
        });
        target.addEventListener('hide.bs.collapse', function (e) {
            if (e.target !== target || filtering) return;
            var map = getExpandedFolders();
            delete map[id];
            saveExpandedFolders(map);
        });
    });

    // --- Find page ---
    // Shown from 30 pages on. Hides the pages that do not match and opens the
    // folders with matches, an empty field restores the folders as they were.

    var filtering = false;
    var pageFilter = document.getElementById('page-filter');
    if (pageFilter) {
        var folderStateBeforeFilter = null;
        pageFilter.addEventListener('input', function () {
            var query = pageFilter.value.trim().toLowerCase();
            var collapses = document.querySelectorAll('#pages-sidebar .page-tree .collapse');
            if (query && !folderStateBeforeFilter) {
                folderStateBeforeFilter = Array.from(collapses).map(function (c) { return c.classList.contains('show'); });
            }
            filtering = true;
            document.querySelectorAll('#pages-sidebar .page-tree-file').forEach(function (link) {
                var match = !query || link.getAttribute('data-path').toLowerCase().indexOf(query) !== -1;
                link.parentElement.classList.toggle('d-none', !match);
            });
            // innermost folders first, so a parent sees whether a child folder has matches
            Array.from(document.querySelectorAll('#pages-sidebar .page-tree-folder-item')).reverse().forEach(function (item) {
                var hasMatch = !!item.querySelector('.collapse li:not(.d-none) .page-tree-file');
                item.classList.toggle('d-none', !!query && !hasMatch);
            });
            collapses.forEach(function (c, i) {
                var show = query ? true : (folderStateBeforeFilter ? folderStateBeforeFilter[i] : c.classList.contains('show'));
                c.classList.toggle('show', show);
                var toggle = document.querySelector('[href="#' + c.id + '"]');
                if (toggle) toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
            });
            if (!query) folderStateBeforeFilter = null;
            filtering = false;
        });
        pageFilter.addEventListener('keydown', function (e) {
            // Enter opens the first match
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var first = document.querySelector('#pages-sidebar li:not(.d-none) > .page-tree-file');
            if (first && pageFilter.value.trim()) window.location.href = first.href;
        });
    }

    // --- Hide the page list ---

    var pagesRowEl = document.getElementById('pages-row');
    var sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle && pagesRowEl) {
        var syncSidebarToggle = function () {
            sidebarToggle.setAttribute('aria-expanded', pagesRowEl.classList.contains('sidebar-collapsed') ? 'false' : 'true');
        };
        syncSidebarToggle();
        sidebarToggle.addEventListener('click', function () {
            var collapsed = pagesRowEl.classList.toggle('sidebar-collapsed');
            store.set('reboot_sidebar_collapsed', collapsed);
            syncSidebarToggle();
        });
    }

    // --- Page actions ---

    function submitPageAction(action, name, extra) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'pages';
        // as elements, a name from prompt() with a quote must not change the form
        var fields = Object.assign({csrf_token: csrfToken, action: action, name: name}, extra || {});
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
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
        var dest = prompt('Move page to folder:', currentFolder);
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
                        + '<a href="#" class="btn btn-sm btn-outline-secondary me-1" onclick="previewVersion(' + escapeHtml(JSON.stringify(v.filename)) + '); return false;">Preview</a>'
                        + '<a href="#" class="btn btn-sm btn-outline-primary" onclick="restoreVersion(' + escapeHtml(JSON.stringify(v.filename)) + '); return false;">Restore</a>'
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
        win.document.write('<html><head><title>Preview: ' + escapeHtml(filename) + '</title>'
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

    // open by default on wide screens, until the preview button saves a choice
    var previewStored = store.get('reboot_preview');
    var previewActive = previewStored === null ? window.innerWidth >= 1200 : previewStored === 'true';
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
            if (back.contentDocument) {
                back.contentDocument.documentElement.scrollTop = savedScrollTop;
                scrollPreviewToCursor(back, false);
            }
            // Swap: show back, hide front. Safari sends the scroll wheel to the
            // iframe on top even when it is hidden, so the visible one has to be
            // on top and the hidden one must not take events.
            back.style.visibility = 'visible';
            back.style.zIndex = '1';
            back.style.pointerEvents = '';
            front.style.visibility = 'hidden';
            front.style.zIndex = '0';
            front.style.pointerEvents = 'none';
            previewFront = (previewFront === 'a') ? 'b' : 'a';
        };
        form.submit();
    }

    function schedulePreviewUpdate() {
        if (previewDebounceTimer) clearTimeout(previewDebounceTimer);
        previewDebounceTimer = setTimeout(updatePreview, 500);
    }

    window.togglePreview = function () {
        previewActive = !previewActive;
        store.set('reboot_preview', previewActive);
        syncPreviewButton();
        var editorCol = document.getElementById('editor-column');
        var previewCol = document.getElementById('preview-column');
        if (previewActive) {
            editorCol.classList.remove('col-lg-9', 'col-xl-10');
            editorCol.classList.add('col-lg-5', 'col-xl-6');
            editorCol.parentElement.classList.add('preview-split');
            previewCol.classList.remove('d-lg-none');
            previewCol.classList.add('d-lg-block');
            updatePreview();
            if (editorTextarea) {
                editorTextarea.addEventListener('input', schedulePreviewUpdate);
            }
        } else {
            editorCol.classList.remove('col-lg-5', 'col-xl-6');
            editorCol.classList.add('col-lg-9', 'col-xl-10');
            editorCol.parentElement.classList.remove('preview-split');
            previewCol.classList.remove('d-lg-block');
            previewCol.classList.add('d-lg-none');
            previewInitialized = false;
            if (editorTextarea) {
                editorTextarea.removeEventListener('input', schedulePreviewUpdate);
            }
        }
    };

    // The preview button sits in the editor toolbar, left of the word wrap
    // toggle. The editor builds the toolbar in a module script, which runs
    // before DOMContentLoaded. Icon: "eye" from Bootstrap Icons (MIT).
    var previewButton = null;

    function syncPreviewButton() {
        if (!previewButton) return;
        previewButton.setAttribute('aria-pressed', previewActive ? 'true' : 'false');
    }

    function addPreviewButton() {
        var toolbar = editorTextarea && editorTextarea.parentNode.querySelector('[role="toolbar"]');
        if (!toolbar || !currentPage) return;
        var buttons = toolbar.querySelectorAll('button');
        var wrapButton = buttons[buttons.length - 1];
        previewButton = document.createElement('button');
        previewButton.type = 'button';
        previewButton.id = 'preview-toggle';
        previewButton.className = 'preview-toolbar-button';
        previewButton.title = 'Preview';
        previewButton.setAttribute('aria-label', 'Preview');
        previewButton.tabIndex = -1;
        previewButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">'
            + '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>'
            + '<path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>';
        previewButton.addEventListener('mousedown', function (e) { e.preventDefault(); });
        previewButton.addEventListener('click', window.togglePreview);
        toolbar.insertBefore(previewButton, wrapButton);
        syncPreviewButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addPreviewButton);
    } else {
        addPreviewButton();
    }

    // Restore preview state on load (only on lg+ screens)
    if (previewActive && currentPage && window.innerWidth >= 992) {
        previewActive = false;
        window.togglePreview();
        if (previewStored === null) {
            // the default is no choice, togglePreview() saved it
            store.remove('reboot_preview');
        }
    }

    // --- Preview follows the cursor ---
    // In the preview, Page marks every block with a comment that holds the editor
    // line where the block starts, `<!--reboot-preview-line:12-->`, and the end
    // with `<!--reboot-preview-line:40:end-->`. The preview scrolls to the element
    // that shows the text of the cursor line, or else to the matching share of
    // the block the cursor is in.

    function previewMarkers(doc) {
        var markers = [];
        var walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_COMMENT);
        var node;
        while ((node = walker.nextNode())) {
            var match = node.nodeValue.match(/^reboot-preview-line:(\d+)(:end)?$/);
            if (match) {
                markers.push({node: node, line: parseInt(match[1], 10), end: !!match[2]});
            }
        }
        return markers;
    }

    function siblingElement(node, forward) {
        var sibling = forward ? node.nextSibling : node.previousSibling;
        while (sibling && sibling.nodeType !== Node.ELEMENT_NODE) {
            sibling = forward ? sibling.nextSibling : sibling.previousSibling;
        }
        return sibling;
    }

    // the document position of a marker: the top of the block after it, for the
    // end marker the bottom of the block before it
    function markerY(marker, win) {
        var element = siblingElement(marker.node, !marker.end) || marker.node.parentNode;
        var rect = element.getBoundingClientRect();
        return (marker.end ? rect.bottom : rect.top) + win.scrollY;
    }

    function normalizeText(text) {
        return text.replace(/\s+/g, ' ').trim().toLowerCase();
    }

    // the visible text of a Markdown line, without its syntax
    function lineNeedle(line) {
        var text = line
            .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
            .replace(/^\s*(#{1,6}\s+|[-*+]\s+|\d+[.)]\s+|>\s*)+/, '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/[*_`~]/g, '');
        return normalizeText(text).substring(0, 60);
    }

    // the innermost element between two markers that shows the cursor line
    function findLineElement(start, next, line) {
        var image = line.match(/!\[[^\]]*\]\(\s*([^)\s]+)/);
        var needle = image ? null : lineNeedle(line);
        if (!image && needle.length < 3) {
            return null;
        }
        for (var el = siblingElement(start.node, true); el && (!next || el.compareDocumentPosition(next.node) & Node.DOCUMENT_POSITION_FOLLOWING); el = siblingElement(el, true)) {
            if (image) {
                var images = el.tagName === 'IMG' ? [el] : el.querySelectorAll('img');
                for (var i = 0; i < images.length; i++) {
                    if (images[i].getAttribute('src') === image[1]) {
                        return images[i];
                    }
                }
            } else if (normalizeText(el.textContent).indexOf(needle) !== -1) {
                var found = el;
                var descend = true;
                while (descend) {
                    descend = false;
                    for (var c = 0; c < found.children.length; c++) {
                        if (normalizeText(found.children[c].textContent).indexOf(needle) !== -1) {
                            found = found.children[c];
                            descend = true;
                            break;
                        }
                    }
                }
                return found;
            }
        }
        return null;
    }

    function previewYForCursor(doc, win) {
        var markers = previewMarkers(doc);
        if (!markers.length || !editorTextarea) {
            return null;
        }
        var text = editorTextarea.value;
        var before = text.substring(0, editorTextarea.selectionStart);
        var line = before.split('\n').length - 1;
        var lineText = text.substring(before.lastIndexOf('\n') + 1).split('\n')[0];
        if (line < markers[0].line) {
            return 0; // the frontmatter
        }
        var index = 0;
        for (var i = 0; i < markers.length; i++) {
            if (!markers[i].end && markers[i].line <= line) {
                index = i;
            }
        }
        var start = markers[index];
        var next = markers[index + 1] || null;
        var element = findLineElement(start, next, lineText);
        if (element) {
            return element.getBoundingClientRect().top + win.scrollY;
        }
        var startY = markerY(start, win);
        if (!next) {
            return startY;
        }
        var share = (line - start.line) / Math.max(1, next.line - start.line);
        return startY + (markerY(next, win) - startY) * share;
    }

    function scrollPreviewToCursor(iframe, smooth) {
        if (!previewActive || !followCursor || !iframe || !iframe.contentDocument || !iframe.contentDocument.body) {
            return;
        }
        var win = iframe.contentWindow;
        var y = previewYForCursor(iframe.contentDocument, win);
        if (y === null) {
            return;
        }
        // the cursor position in the upper third of the preview
        win.scrollTo({top: Math.max(0, y - win.innerHeight / 3), behavior: smooth ? 'smooth' : 'auto'});
    }

    // on by default, the switch below the preview turns it off for this browser
    var followCursor = store.get('reboot_preview_follow') !== 'false';
    var followSwitch = document.getElementById('preview-follow');
    if (followSwitch) {
        followSwitch.checked = followCursor;
        followSwitch.addEventListener('change', function () {
            followCursor = followSwitch.checked;
            store.set('reboot_preview_follow', followCursor);
            if (followCursor) scrollPreviewToCursor(getFrontIframe(), true);
        });
    }

    // --- Preview zoom ---
    // The iframes are rendered wider than the column and scaled down, so the
    // page shows its desktop layout. "fit" scales a desktop width to the column.
    var PREVIEW_DESKTOP_WIDTH = 1280;
    var previewZoom = store.get('reboot_preview_zoom') || '0.75';
    var previewFrames = document.getElementById('preview-frames');
    var zoomSelect = document.getElementById('preview-zoom');

    function applyPreviewZoom() {
        if (!previewFrames) return;
        var scale = previewZoom === 'fit'
            ? Math.min(1, previewFrames.clientWidth / PREVIEW_DESKTOP_WIDTH)
            : parseFloat(previewZoom);
        if (!(scale > 0)) scale = 1;
        ['a', 'b'].forEach(function (id) {
            var iframe = document.getElementById('preview-iframe-' + id);
            iframe.style.width = (100 / scale) + '%';
            iframe.style.height = (100 / scale) + '%';
            iframe.style.transform = scale === 1 ? '' : 'scale(' + scale + ')';
        });
    }

    if (zoomSelect) {
        if (!zoomSelect.querySelector('option[value="' + previewZoom + '"]')) previewZoom = '0.75';
        zoomSelect.value = previewZoom;
        zoomSelect.addEventListener('change', function () {
            previewZoom = zoomSelect.value;
            store.set('reboot_preview_zoom', previewZoom);
            applyPreviewZoom();
        });
    }
    if (previewFrames && window.ResizeObserver) {
        // the column is hidden until the preview opens and changes with the window
        new ResizeObserver(applyPreviewZoom).observe(previewFrames);
    }
    applyPreviewZoom();

    // --- Width of editor and preview ---
    // The bar on the left edge of the preview sets the share of the preview in
    // the space next to the sidebar, saved for this browser.
    var PREVIEW_SHARE_MIN = 0.2, PREVIEW_SHARE_MAX = 0.8;
    var pagesRow = document.getElementById('pages-row');
    var resizer = document.getElementById('preview-resizer');

    function setPreviewShare(share, save) {
        if (share === null || !(share > 0)) {
            pagesRow.style.removeProperty('--preview-share');
            if (save) store.remove('reboot_preview_share');
            return;
        }
        share = Math.min(PREVIEW_SHARE_MAX, Math.max(PREVIEW_SHARE_MIN, share));
        pagesRow.style.setProperty('--preview-share', share.toFixed(3));
        if (save) store.set('reboot_preview_share', share.toFixed(3));
    }

    if (pagesRow && resizer) {
        setPreviewShare(parseFloat(store.get('reboot_preview_share')), false);
        var editorColumn = document.getElementById('editor-column');
        var previewColumn = document.getElementById('preview-column');
        var shareAt = function (x) {
            var left = editorColumn.getBoundingClientRect().left;
            var right = previewColumn.getBoundingClientRect().right;
            return (right - x) / (right - left);
        };
        var currentShare = function () {
            return shareAt(previewColumn.getBoundingClientRect().left);
        };
        resizer.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            e.preventDefault();
            resizer.setPointerCapture(e.pointerId);
            resizer.classList.add('dragging');
            document.body.classList.add('preview-resizing');
        });
        resizer.addEventListener('pointermove', function (e) {
            if (resizer.classList.contains('dragging')) setPreviewShare(shareAt(e.clientX), false);
        });
        var endDrag = function () {
            if (!resizer.classList.contains('dragging')) return;
            resizer.classList.remove('dragging');
            document.body.classList.remove('preview-resizing');
            setPreviewShare(parseFloat(pagesRow.style.getPropertyValue('--preview-share')), true);
        };
        resizer.addEventListener('pointerup', endDrag);
        resizer.addEventListener('pointercancel', endDrag);
        resizer.addEventListener('dblclick', function () {
            setPreviewShare(null, true);
        });
        resizer.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            e.preventDefault();
            setPreviewShare(currentShare() + (e.key === 'ArrowLeft' ? 0.02 : -0.02), true);
        });
    }

    var cursorScrollTimer = null;
    function scheduleScrollToCursor() {
        if (!previewActive || !previewInitialized || !followCursor) return;
        if (cursorScrollTimer) clearTimeout(cursorScrollTimer);
        cursorScrollTimer = setTimeout(function () {
            scrollPreviewToCursor(getFrontIframe(), true);
        }, 120);
    }

    if (editorTextarea) {
        // typing reloads the preview, which then scrolls on its own
        editorTextarea.addEventListener('click', scheduleScrollToCursor);
        editorTextarea.addEventListener('keyup', function (e) {
            if (/^(Arrow|Page|Home|End)/.test(e.key)) scheduleScrollToCursor();
        });
    }

    // --- Structured Editor ---

    var structuredActive = false;
    var structuredBlocks = null; // cached block data from API

    window.toggleStructuredEditor = function () {
        structuredActive = !structuredActive;
        var form = document.querySelector('form[action^="pages?page="]');
        var structuredDiv = document.getElementById('structured-editor');
        var toggleBtn = document.getElementById('structured-toggle');
        if (!form || !structuredDiv) return;

        if (structuredActive) {
            form.classList.add('d-none');
            structuredDiv.classList.remove('d-none');
            toggleBtn.classList.remove('btn-outline-secondary');
            toggleBtn.classList.add('btn-secondary');
            loadStructuredEditor();
        } else {
            // Sync structured editor values back to textarea before showing it
            if (structuredBlocks) {
                syncStructuredToTextarea();
            }
            form.classList.remove('d-none');
            structuredDiv.classList.add('d-none');
            toggleBtn.classList.remove('btn-secondary');
            toggleBtn.classList.add('btn-outline-secondary');
        }
    };

    function loadStructuredEditor() {
        var container = document.getElementById('structured-editor-blocks');
        container.innerHTML = '<div class="text-body-secondary p-3">Loading…</div>';
        fetch('pages?fields=1&page=' + encodeURIComponent(currentPage))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                structuredBlocks = data.blocks;
                renderStructuredEditor(container, data.blocks);
            })
            .catch(function () {
                container.innerHTML = '<div class="text-danger p-3">Failed to load block fields.</div>';
            });
    }

    function renderStructuredEditor(container, blocks) {
        container.innerHTML = '';
        if (!blocks || blocks.length === 0) {
            container.innerHTML = '<div class="text-body-secondary p-3">No blocks with fields found on this page.</div>';
            return;
        }
        blocks.forEach(function (block, blockIndex) {
            var card = document.createElement('div');
            card.className = 'card mb-3';
            var header = document.createElement('div');
            header.className = 'card-header';
            header.innerHTML = '<h6 class="mb-0">' + escapeHtml(block.name) + '</h6>';
            card.appendChild(header);

            var body = document.createElement('div');
            body.className = 'card-body d-flex flex-column gap-3';
            block.fields.forEach(function (field, fieldIndex) {
                var fieldId = 'sf-' + blockIndex + '-' + fieldIndex;
                var group = document.createElement('div');

                var label = document.createElement('label');
                label.className = 'form-label mb-1';
                label.setAttribute('for', fieldId);
                label.textContent = field.label;
                if (field.required) {
                    var req = document.createElement('span');
                    req.className = 'text-danger ms-1';
                    req.textContent = '*';
                    label.appendChild(req);
                }
                group.appendChild(label);

                var input = createFieldInput(field, fieldId);
                group.appendChild(input);
                body.appendChild(group);
            });
            card.appendChild(body);
            container.appendChild(card);
        });
    }

    function createFieldInput(field, fieldId) {
        var value = field.value || '';
        switch (field.type) {
            case 'textarea':
            case 'md-editor': {
                var textarea = document.createElement('textarea');
                textarea.className = 'form-control form-control-sm editor-font';
                textarea.id = fieldId;
                textarea.rows = field.type === 'md-editor' ? 6 : 3;
                textarea.value = value;
                textarea.required = field.required;
                if (field.type === 'md-editor') {
                    textarea.classList.add('structured-md-editor');
                }
                return textarea;
            }
            case 'media': {
                var wrapper = document.createElement('div');
                wrapper.className = 'd-flex gap-2 align-items-center';
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.id = fieldId;
                input.value = value;
                input.required = field.required;
                input.placeholder = 'Media path';
                wrapper.appendChild(input);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-secondary text-nowrap';
                btn.textContent = 'Browse';
                btn.addEventListener('click', function () {
                    openMediaPicker(input);
                });
                wrapper.appendChild(btn);
                return wrapper;
            }
            case 'media-list': {
                var wrapper = document.createElement('div');
                wrapper.className = 'structured-media-list';
                var items = Array.isArray(value) ? value : [];
                items.forEach(function (item) {
                    wrapper.appendChild(createMediaListItem(item.src, item.alt));
                });
                var addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'btn btn-sm btn-outline-secondary mt-1';
                addBtn.textContent = '+ Add';
                addBtn.addEventListener('click', function () {
                    wrapper.insertBefore(createMediaListItem('', ''), addBtn);
                });
                wrapper.appendChild(addBtn);
                return wrapper;
            }
            case 'link': {
                var wrapper = document.createElement('div');
                wrapper.className = 'd-flex gap-2 align-items-center';
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.id = fieldId;
                input.value = value;
                input.required = field.required;
                input.placeholder = 'Page link';
                wrapper.appendChild(input);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-secondary text-nowrap';
                btn.textContent = 'Browse';
                btn.addEventListener('click', function () {
                    openPagePicker(input);
                });
                wrapper.appendChild(btn);
                return wrapper;
            }
            default: {
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.id = fieldId;
                input.value = value;
                input.required = field.required;
                return input;
            }
        }
    }

    function createMediaListItem(src, alt) {
        var row = document.createElement('div');
        row.className = 'd-flex gap-2 align-items-center mb-1';
        var srcInput = document.createElement('input');
        srcInput.type = 'text';
        srcInput.className = 'form-control form-control-sm media-list-src';
        srcInput.placeholder = 'Image path';
        srcInput.value = src;
        row.appendChild(srcInput);
        var altInput = document.createElement('input');
        altInput.type = 'text';
        altInput.className = 'form-control form-control-sm media-list-alt';
        altInput.placeholder = 'Alt text';
        altInput.value = alt;
        row.appendChild(altInput);
        var browseBtn = document.createElement('button');
        browseBtn.type = 'button';
        browseBtn.className = 'btn btn-sm btn-outline-secondary text-nowrap';
        browseBtn.textContent = 'Browse';
        browseBtn.addEventListener('click', function () {
            openMediaPicker(srcInput);
        });
        row.appendChild(browseBtn);
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', function () {
            row.remove();
        });
        row.appendChild(removeBtn);
        return row;
    }

    function openMediaPicker(inputElement) {
        // TODO: integrate with InsertMedia picker
        inputElement.focus();
    }

    function openPagePicker(inputElement) {
        // TODO: integrate with InsertPageLink picker
        inputElement.focus();
    }

    function collectStructuredValues() {
        if (!structuredBlocks) return null;
        var container = document.getElementById('structured-editor-blocks');
        var blocks = [];
        structuredBlocks.forEach(function (block, blockIndex) {
            var fields = [];
            block.fields.forEach(function (field, fieldIndex) {
                var fieldId = 'sf-' + blockIndex + '-' + fieldIndex;
                var value = '';
                if (field.type === 'media-list') {
                    var items = [];
                    var card = container.children[blockIndex];
                    var listWrapper = card.querySelector('.structured-media-list');
                    if (listWrapper) {
                        listWrapper.querySelectorAll('.d-flex.align-items-center.mb-1').forEach(function (row) {
                            var src = row.querySelector('.media-list-src');
                            var alt = row.querySelector('.media-list-alt');
                            if (src) {
                                items.push({src: src.value, alt: alt ? alt.value : ''});
                            }
                        });
                    }
                    value = items;
                } else {
                    var el = document.getElementById(fieldId);
                    value = el ? el.value : '';
                }
                fields.push({
                    xpath: field.xpath,
                    label: field.label,
                    type: field.type,
                    value: value
                });
            });
            blocks.push({name: block.name, config: block.config, fields: fields});
        });
        return blocks;
    }

    function blocksToMarkdown(blocks) {
        var parts = [];
        blocks.forEach(function (block) {
            var blockComment = '<!-- ' + block.name;
            if (block.config && Object.keys(block.config).length > 0) {
                // YAML-style config: <!-- block-name:\n  key: value -->
                var configParts = [];
                for (var key in block.config) {
                    configParts.push('  ' + key + ': ' + block.config[key]);
                }
                blockComment = '<!-- ' + block.name + ':\n' + configParts.join('\n') + ' -->';
            } else {
                blockComment += ' -->';
            }
            var fieldsByPart = {};
            var maxPart = 1;
            block.fields.forEach(function (field) {
                var partNum = 1;
                var partMatch = field.xpath.match(/part\((\d)\)/);
                if (partMatch) partNum = parseInt(partMatch[1]);
                if (partNum > maxPart) maxPart = partNum;
                if (!fieldsByPart[partNum]) fieldsByPart[partNum] = [];
                fieldsByPart[partNum].push(field);
            });

            var contentParts = [];
            for (var p = 1; p <= maxPart; p++) {
                var partFields = fieldsByPart[p] || [];
                var partContent = buildPartMarkdown(partFields);
                contentParts.push(partContent);
            }
            parts.push(blockComment + '\n' + contentParts.join('\n\n---\n\n'));
        });
        return parts.join('\n\n');
    }

    function buildPartMarkdown(fields) {
        var lines = [];
        var pendingLinkHref = null;
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var value = field.value || '';
            var xpath = field.xpath;
            var type = field.type;
            var clean = xpath.replace(/\[part\(\d\)\]/g, '').replace(/^\/+/, '');

            if (type === 'md-editor') {
                lines.push(value);
                continue;
            }
            if (type === 'media') {
                var alt = '';
                for (var j = 0; j < fields.length; j++) {
                    if (fields[j] !== field && /img.*\/@alt/.test(fields[j].xpath)) {
                        alt = fields[j].value || '';
                    }
                }
                if (/^(\/\/)?li\/img/.test(clean)) {
                    lines.push('- ![' + alt + '](' + value + ')');
                } else {
                    lines.push('![' + alt + '](' + value + ')');
                }
                continue;
            }
            if (type === 'media-list') {
                var items = Array.isArray(value) ? value : [];
                items.forEach(function (item) {
                    if (/^(\/\/)?li\/img/.test(clean)) {
                        lines.push('- ![' + (item.alt || '') + '](' + (item.src || '') + ')');
                    } else {
                        lines.push('![' + (item.alt || '') + '](' + (item.src || '') + ')');
                    }
                });
                continue;
            }
            if (type === 'link') {
                pendingLinkHref = value;
                continue;
            }
            // Skip alt/title fields for media (already handled above)
            if (/\/@(alt|title)$/.test(xpath)) continue;

            if (pendingLinkHref !== null) {
                lines.push('[' + value + '](' + pendingLinkHref + ')');
                pendingLinkHref = null;
            } else {
                lines.push(valueToMarkdown(clean, value));
            }
        }
        if (pendingLinkHref !== null) {
            lines.push('[' + pendingLinkHref + '](' + pendingLinkHref + ')');
        }
        return lines.join('\n\n');
    }

    function valueToMarkdown(cleanXpath, value) {
        if (/^(\/\/)?h1/.test(cleanXpath)) return '# ' + value;
        if (/^(\/\/)?h2/.test(cleanXpath)) return '## ' + value;
        if (/^(\/\/)?h3/.test(cleanXpath)) return '### ' + value;
        if (/^(\/\/)?h4/.test(cleanXpath)) return '#### ' + value;
        return value;
    }

    function syncStructuredToTextarea() {
        var blocks = collectStructuredValues();
        if (!blocks || !editorTextarea) return;
        // Preserve frontmatter
        var existing = editorTextarea.value;
        var frontmatter = '';
        if (existing.indexOf('---') === 0) {
            var end = existing.indexOf('---', 3);
            if (end !== -1) {
                frontmatter = existing.substring(0, end + 3) + '\n';
            }
        }
        editorTextarea.value = frontmatter + blocksToMarkdown(blocks);
        pageUnsaved = true;
    }

    window.saveStructuredEditor = function () {
        syncStructuredToTextarea();
        window.savePageAsync();
    };
})();
