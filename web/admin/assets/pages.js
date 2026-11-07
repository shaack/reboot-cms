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
        // a structured page is a form of fields, it is sent as it is, see field-form.js
        if (form.hasAttribute('data-field-form')) {
            form.requestSubmit();
            return;
        }
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

    // The page actions take the path of a page like "/blog/post.md", without
    // one they act on the open page. Folder paths have no leading slash.

    function pageBaseName(page) {
        return page.replace(/\.(md|yml)$/, '').split('/').pop();
    }

    window.renamePage = function (page) {
        page = page || currentPage;
        if (!page) return;
        var baseName = pageBaseName(page);
        var newName = prompt('Rename page:', baseName);
        if (newName === null || newName === baseName) return;
        submitPageAction('rename_page', page, {new_name: newName});
    };

    window.movePage = function (page) {
        page = page || currentPage;
        if (!page) return;
        var currentFolder = page.replace(/\/[^/]+$/, '') || '/';
        var dest = prompt('Move page to folder:', currentFolder);
        if (dest === null || dest === currentFolder) return;
        dest = dest.replace(/^\//, '').replace(/\/$/, '');
        submitPageAction('move_page', page, {destination: dest});
    };

    window.deletePage = function (page) {
        page = page || currentPage;
        if (!page) return;
        if (!confirm('Delete page \'' + pageBaseName(page) + '\'?')) return;
        submitPageAction('delete_page', page);
    };

    window.newPage = function (folder) {
        var name = prompt(folder ? 'New page in ' + folder + '/:' : 'New page:', '');
        if (name === null || !name.trim()) return;
        submitPageAction('add_page', (folder ? folder + '/' : '') + name.trim().replace(/^\/+/, ''));
    };

    window.newMarkdownPage = function (folder) {
        var name = prompt(folder ? 'New Markdown page in ' + folder + '/:' : 'New Markdown page:', '');
        if (name === null || !name.trim()) return;
        submitPageAction('add_markdown_page', (folder ? folder + '/' : '') + name.trim().replace(/^\/+/, ''));
    };

    window.newFolder = function (folder) {
        var name = prompt(folder ? 'New folder in ' + folder + '/:' : 'New folder:', '');
        if (name === null || !name.trim()) return;
        submitPageAction('add_folder', (folder ? folder + '/' : '') + name.trim().replace(/^\/+/, ''));
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

    // --- Context menu of the page list ---
    // A right click, or touch and hold on a touch screen, on a page, a folder or
    // the free space of the page list. The keyboard opens it with the context
    // menu key or Shift F10 on a focused entry.

    var pageTreeNav = document.getElementById('page-tree-nav');
    var treeMenu = null;
    var treeMenuTarget = null;

    function closeTreeMenu(restoreFocus) {
        if (!treeMenu) return;
        treeMenu.remove();
        treeMenu = null;
        if (treeMenuTarget) {
            treeMenuTarget.classList.remove('page-tree-menu-target');
            if (restoreFocus) treeMenuTarget.focus();
        }
        treeMenuTarget = null;
    }

    function treeMenuItems(target) {
        if (target && target.classList.contains('page-tree-file')) {
            var page = '/' + target.getAttribute('data-path');
            var folder = target.getAttribute('data-path').replace(/\/?[^/]+$/, '');
            return {
                header: target.getAttribute('data-path'),
                items: [
                    ['New page here', function () { window.newPage(folder); }],
                    ['New Markdown page here', function () { window.newMarkdownPage(folder); }],
                    ['New folder here', function () { window.newFolder(folder); }],
                    null,
                    ['Rename page', function () { window.renamePage(page); }],
                    ['Move page', function () { window.movePage(page); }]
                ].concat(/\.md$/.test(page) ? [['Convert to structured page', function () {
                    location.href = 'pages?page=' + encodeURIComponent(page) + '&convert=1';
                }]] : [], [
                    null,
                    ['Delete page', function () { window.deletePage(page); }, true]
                ])
            };
        }
        if (target && target.classList.contains('page-tree-folder')) {
            var path = target.getAttribute('data-path');
            var name = path.split('/').pop();
            return {
                header: path + '/',
                items: [
                    ['New page in folder', function () { window.newPage(path); }],
                    ['New Markdown page in folder', function () { window.newMarkdownPage(path); }],
                    ['New folder in folder', function () { window.newFolder(path); }],
                    null,
                    ['Rename folder', function () { window.renameFolder(path, name); }],
                    null,
                    ['Delete folder', function () { window.deleteFolder(path, name); }, true]
                ]
            };
        }
        return {
            header: null,
            items: [
                ['New page', function () { window.newPage(''); }],
                ['New Markdown page', function () { window.newMarkdownPage(''); }],
                ['New folder', function () { window.newFolder(''); }]
            ]
        };
    }

    function openTreeMenu(target, x, y) {
        closeTreeMenu(false);
        var spec = treeMenuItems(target);
        treeMenu = document.createElement('div');
        treeMenu.className = 'dropdown-menu page-tree-menu shadow';
        treeMenu.setAttribute('role', 'menu');
        if (spec.header) {
            var header = document.createElement('h6');
            header.className = 'dropdown-header';
            header.textContent = spec.header;
            treeMenu.appendChild(header);
        }
        spec.items.forEach(function (item) {
            if (!item) {
                var divider = document.createElement('hr');
                divider.className = 'dropdown-divider';
                treeMenu.appendChild(divider);
                return;
            }
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'dropdown-item' + (item[2] ? ' text-danger' : '');
            button.setAttribute('role', 'menuitem');
            button.textContent = item[0];
            button.addEventListener('click', function () {
                closeTreeMenu(false);
                item[1]();
            });
            treeMenu.appendChild(button);
        });
        document.body.appendChild(treeMenu);
        // keep the menu inside the window
        var rect = treeMenu.getBoundingClientRect();
        treeMenu.style.left = Math.max(4, Math.min(x, window.innerWidth - rect.width - 4)) + 'px';
        treeMenu.style.top = Math.max(4, Math.min(y, window.innerHeight - rect.height - 4)) + 'px';
        treeMenuTarget = target;
        if (target) target.classList.add('page-tree-menu-target');
        treeMenu.querySelector('.dropdown-item').focus({preventScroll: true});
    }

    // Bootstrap handles the keys of every .dropdown-menu with a capturing
    // listener on the document and would move the focus to the navbar
    // dropdowns. A capturing listener on the window runs before it.
    window.addEventListener('keydown', function (e) {
        if (!treeMenu || !treeMenu.contains(e.target)) return;
        var items = Array.from(treeMenu.querySelectorAll('.dropdown-item'));
        var index = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            e.stopPropagation();
            var next = (index + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next].focus();
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            e.preventDefault();
            e.stopPropagation();
            closeTreeMenu(true);
        }
    }, true);

    if (pageTreeNav) {
        var sidebarEl = document.getElementById('pages-sidebar');
        var treeTargetOf = function (el) {
            return el.closest('.page-tree-file, .page-tree-folder');
        };
        var lastTouchMenu = 0;
        sidebarEl.addEventListener('contextmenu', function (e) {
            if (e.target.closest('input, .page-tree-tools')) return;
            e.preventDefault();
            // Android fires contextmenu after the long press opened the menu
            if (Date.now() - lastTouchMenu < 1000) return;
            var target = treeTargetOf(e.target);
            var x = e.clientX, y = e.clientY;
            if (!x && !y && target) {
                // from the keyboard, at the entry
                var r = target.getBoundingClientRect();
                x = r.left + 16;
                y = r.bottom;
            }
            openTreeMenu(target, x, y);
        });

        // touch and hold
        var pressTimer = null, pressStart = null, suppressClick = false;
        sidebarEl.addEventListener('pointerdown', function (e) {
            // iOS sends no click after a long press, so the flag of the last one ends here
            suppressClick = false;
            if (e.pointerType !== 'touch' || e.target.closest('input, .page-tree-tools')) return;
            pressStart = {x: e.clientX, y: e.clientY};
            var target = treeTargetOf(e.target);
            clearTimeout(pressTimer);
            pressTimer = setTimeout(function () {
                pressTimer = null;
                suppressClick = true;
                lastTouchMenu = Date.now();
                openTreeMenu(target, pressStart.x, pressStart.y);
            }, 500);
        });
        var cancelPress = function () {
            clearTimeout(pressTimer);
            pressTimer = null;
        };
        sidebarEl.addEventListener('pointermove', function (e) {
            if (pressTimer && pressStart && Math.hypot(e.clientX - pressStart.x, e.clientY - pressStart.y) > 10) cancelPress();
        });
        sidebarEl.addEventListener('pointerup', cancelPress);
        sidebarEl.addEventListener('pointercancel', cancelPress);
        // the click after a long press must not open the page or fold the folder
        sidebarEl.addEventListener('click', function (e) {
            if (!suppressClick) return;
            suppressClick = false;
            e.preventDefault();
            e.stopPropagation();
        }, true);

        document.addEventListener('pointerdown', function (e) {
            if (treeMenu && !treeMenu.contains(e.target)) closeTreeMenu(false);
        }, true);
        window.addEventListener('resize', function () { closeTreeMenu(false); });
        window.addEventListener('scroll', function () { closeTreeMenu(false); }, true);
    }

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

    // --- Structure of the page ---
    // The editor knows where the frontmatter, the blocks and their parts are,
    // the same way Page::parseBlocks() reads them. Lines count from 0.

    var blockNames = config.blockNames || [];
    var blockExamplesEl = document.getElementById('block-examples');
    var blockExamples = {};
    try { blockExamples = blockExamplesEl ? JSON.parse(blockExamplesEl.textContent) : {}; } catch (e) { /* none */ }
    var structureCache = {text: null, value: null};

    // the block name of a comment, null for a note like "TODO: check prices"
    function blockNameOf(comment) {
        var header = comment.trim();
        if (!header) return null;
        var colon = header.indexOf(':');
        if (colon === -1) return header;
        var rest = header.substring(colon + 1).trim();
        if (rest === '' || rest.charAt(0) === '{' || /^:?\s*\n\s+\S/.test(header.substring(colon))) {
            return header.substring(0, colon).trim();
        }
        return null;
    }

    function pageStructure(text) {
        if (structureCache.text === text) return structureCache.value;
        var lines = text.split('\n');
        var starts = [];
        var pos = 0;
        lines.forEach(function (l) { starts.push(pos); pos += l.length + 1; });
        var result = {frontmatterEnd: -1, blocks: [], spans: [], lineStarts: starts, lines: lines};
        result.protectedLines = {};
        var protect = function (i) {
            result.spans.push({start: starts[i], end: starts[i] + lines[i].length, line: i});
            result.protectedLines[i] = true;
        };
        var i = 0;
        if (lines[0] !== undefined && lines[0].trim() === '---') {
            for (var j = 1; j < lines.length; j++) {
                if (lines[j].trim() === '---') {
                    result.frontmatterEnd = j;
                    protect(0);
                    protect(j);
                    // the names of the values, "title: ", the values stay editable
                    for (var f = 1; f < j; f++) {
                        var key = lines[f].match(/^[A-Za-z0-9_-]+[ \t]*:[ \t]?/);
                        if (key) result.spans.push({start: starts[f], end: starts[f] + key[0].length, line: f, key: true});
                    }
                    i = j + 1;
                    break;
                }
            }
        }
        var inFence = false;
        for (; i < lines.length; i++) {
            var trimmed = lines[i].trim();
            if (trimmed.indexOf('```') === 0) {
                inFence = !inFence;
                continue;
            }
            if (inFence) continue;
            if (trimmed.indexOf('<!--') === 0) {
                // the comment may run over several lines, a block config in YAML.
                // A new "<!--" before the end means this one is unfinished, for
                // example while it is typed, so it must not take the end of the next.
                var k = i;
                if (lines[i].indexOf('-->', lines[i].indexOf('<!--') + 4) === -1) {
                    k = i + 1;
                    while (k < lines.length && lines[k].indexOf('-->') === -1 && lines[k].indexOf('<!--') === -1) k++;
                    if (k >= lines.length || lines[k].indexOf('<!--') !== -1 && lines[k].indexOf('<!--') < lines[k].indexOf('-->')) continue;
                    if (lines[k].indexOf('-->') === -1) continue;
                }
                var comment = lines.slice(i, k + 1).join('\n');
                comment = comment.substring(comment.indexOf('<!--') + 4, comment.lastIndexOf('-->'));
                var name = blockNameOf(comment);
                if (name !== null) {
                    var known = blockNames.indexOf(name) !== -1;
                    // where the name stands, on the comment line or the first line inside
                    var nameAt = -1;
                    for (var n = i; n <= k && nameAt === -1; n++) {
                        var from = n === i ? lines[i].indexOf('<!--') + 4 : 0;
                        var found = lines[n].indexOf(name, from);
                        if (found !== -1) nameAt = starts[n] + found;
                    }
                    result.blocks.push({name: name, line: i, endLine: k, separators: [], known: known,
                        nameStart: nameAt, nameEnd: nameAt === -1 ? -1 : nameAt + name.length,
                        disabled: /(^|[\s{,])disabled\s*:\s*true\b/.test(comment)});
                    // Only a block that exists is protected, a typo in the name must
                    // stay editable. The config lines in between stay editable too.
                    if (known) {
                        protect(i);
                        if (k > i) {
                            var nameLine = i + 1;
                            while (nameLine < k && !lines[nameLine].trim()) nameLine++;
                            if (nameLine < k) protect(nameLine);
                            protect(k);
                        }
                    }
                }
                i = k;
                continue;
            }
            if (trimmed === '---' && result.blocks.length && result.blocks[result.blocks.length - 1].known) {
                result.blocks[result.blocks.length - 1].separators.push(i);
                protect(i);
            }
        }
        // The block of every line, from its comment up to the next block. The
        // empty lines at its end belong to no block, so the editor shows a gap
        // between two blocks.
        result.lineBlock = lines.map(function () { return -1; });
        result.blocks.forEach(function (block, index) {
            var end = index + 1 < result.blocks.length ? result.blocks[index + 1].line : lines.length;
            while (end - 1 > block.endLine && lines[end - 1].trim() === '') end--;
            for (var l = block.line; l < end; l++) result.lineBlock[l] = index;
        });
        structureCache = {text: text, value: result};
        return result;
    }

    function lineOfOffset(structure, offset) {
        var starts = structure.lineStarts;
        var lo = 0, hi = starts.length - 1;
        while (lo < hi) {
            var mid = (lo + hi + 1) >> 1;
            if (starts[mid] <= offset) lo = mid; else hi = mid - 1;
        }
        return lo;
    }

    // --- Status line: where the cursor is ---

    var editorStatus = document.getElementById('editor-status');
    var statusHintUntil = 0;

    // a line of a block example as words: "# Hero heading" is "Hero heading",
    // "[Call-to-action link](url)" is "Call-to-action link", an image is "Image"
    function exampleWords(line) {
        return line.trim()
            .replace(/^[-*]\s+/, '')
            .replace(/^#+\s*/, '')
            .replace(/!\[([^\]]*)\]\([^)]*\)/g, function (m, alt) { return alt && alt !== 'dummy' ? alt : 'Image'; })
            .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1');
    }

    function exampleParts(name) {
        var example = blockExamples[name];
        return example ? example.split(/\n\s*---\s*\n/) : null;
    }

    // what follows a "---" in a block, "part 2 of 3 · Description", from the example
    function separatorLabel(block, part) {
        var parts = block.known ? exampleParts(block.name) : null;
        if (!parts) return 'part ' + part;
        if (part > parts.length) return 'part ' + part + ', the block has only ' + parts.length;
        var hint = parts[part - 1].split('\n').map(exampleWords).filter(Boolean)[0] || '';
        return 'part ' + part + ' of ' + parts.length + (hint ? ' · ' + hint : '');
    }

    function cursorStatus() {
        var text = editorTextarea.value;
        var st = pageStructure(text);
        var line = lineOfOffset(st, editorTextarea.selectionStart);
        if (st.frontmatterEnd !== -1 && line <= st.frontmatterEnd) {
            return {text: 'Frontmatter · title, description and other page settings'};
        }
        var block = null;
        st.blocks.forEach(function (b) { if (b.line <= line) block = b; });
        if (!block) {
            return st.blocks.length ? {text: 'Before the first block · not shown on the page', warn: true} : {text: ''};
        }
        if (!block.known) {
            return {text: block.name + ' · there is no such block', warn: true};
        }
        if (block.disabled) {
            return {text: block.name + ' · switched off, not shown on the page'};
        }
        if (line <= block.endLine) {
            return {text: 'Block ' + block.name};
        }
        var part = 1;
        block.separators.forEach(function (s) { if (s < line) part++; });
        var parts = exampleParts(block.name);
        if (!parts) {
            return {text: block.name};
        }
        if (part > parts.length) {
            return {text: block.name + ' · part ' + part + ', the block has only ' + parts.length, warn: true};
        }
        // what belongs here, from the example of "Insert block"
        var hint = parts[part - 1].split('\n').map(exampleWords).filter(Boolean).join(' · ');
        return {text: block.name + (parts.length > 1 ? ' · part ' + part + ' of ' + parts.length : '') + (hint ? ' · ' + hint : '')};
    }

    function updateEditorStatus() {
        updateBlockActions();
        if (!editorStatus || !editorTextarea || Date.now() < statusHintUntil) return;
        var status = cursorStatus();
        editorStatus.textContent = status.text;
        editorStatus.title = status.text;
        editorStatus.classList.toggle('text-warning', !!status.warn);
    }

    function showEditorHint(text) {
        if (!editorStatus) return;
        statusHintUntil = Date.now() + 2500;
        editorStatus.textContent = text;
        editorStatus.title = text;
        editorStatus.classList.add('text-warning');
        setTimeout(function () {
            statusHintUntil = 0;
            updateEditorStatus();
        }, 2600);
    }

    if (editorTextarea && editorStatus) {
        var statusFrame = null;
        var scheduleStatus = function () {
            if (statusFrame) return;
            statusFrame = requestAnimationFrame(function () {
                statusFrame = null;
                updateEditorStatus();
            });
        };
        ['input', 'click', 'keyup', 'focus'].forEach(function (type) {
            editorTextarea.addEventListener(type, scheduleStatus);
        });
        document.addEventListener('selectionchange', function () {
            if (document.activeElement === editorTextarea) scheduleStatus();
        });
        scheduleStatus();
    }

    // --- Block actions: move a block, switch it off and on ---
    // With the cursor in a block, the buttons below the editor change the text
    // like typing would: the editor records it for undo, the preview and the
    // check follow.

    var blockActions = document.getElementById('block-actions');
    var blockUp = document.getElementById('block-up');
    var blockDown = document.getElementById('block-down');
    var blockToggle = document.getElementById('block-toggle');

    function cursorBlockIndex(st) {
        var line = lineOfOffset(st, editorTextarea.selectionStart);
        var index = -1;
        st.blocks.forEach(function (b, i) { if (b.line <= line) index = i; });
        return index;
    }

    function updateBlockActions() {
        if (!blockActions || !editorTextarea) return;
        var st = pageStructure(editorTextarea.value);
        var index = cursorBlockIndex(st);
        blockActions.classList.toggle('d-none', index === -1);
        if (index === -1) return;
        blockUp.disabled = index === 0;
        blockDown.disabled = index === st.blocks.length - 1;
        var off = st.blocks[index].disabled;
        var label = off ? 'Switch the block on' : 'Switch the block off';
        blockToggle.setAttribute('aria-pressed', off ? 'false' : 'true');
        blockToggle.title = label;
        blockToggle.setAttribute('aria-label', label);
    }

    // replaces lines from..to (exclusive) and puts the caret at the given offset
    function replaceLines(st, from, to, newLines, caret) {
        var start = st.lineStarts[from];
        var oldText = st.lines.slice(from, to).join('\n');
        // The editor groups changes within 400 ms into one undo step. A click is
        // a step of its own, so the typing before it and the change itself are
        // committed at once.
        var editor = editorTextarea.mdEditor;
        var commit = function () {
            if (editor && editor.historyDebounce) {
                clearTimeout(editor.historyDebounce);
                editor.historyDebounce = null;
                editor.commitHistory();
            }
        };
        commit();
        editorTextarea.focus();
        editorTextarea.setRangeText(newLines.join('\n'), start, start + oldText.length, 'end');
        editorTextarea.setSelectionRange(caret, caret);
        editorTextarea.dispatchEvent(new Event('input', {bubbles: true}));
        commit();
        var lineHeight = parseFloat(getComputedStyle(editorTextarea).lineHeight) || 20;
        var line = editorTextarea.value.substring(0, caret).split('\n').length - 1;
        var top = line * lineHeight;
        if (top < editorTextarea.scrollTop || top > editorTextarea.scrollTop + editorTextarea.clientHeight - lineHeight) {
            editorTextarea.scrollTop = Math.max(0, top - editorTextarea.clientHeight / 3);
        }
    }

    // the lines of a block, without the empty lines at its end, and those
    function blockChunk(st, index) {
        var from = st.blocks[index].line;
        var to = index + 1 < st.blocks.length ? st.blocks[index + 1].line : st.lines.length;
        var end = to;
        while (end - 1 > st.blocks[index].endLine && st.lines[end - 1].trim() === '') end--;
        return {from: from, to: to, content: st.lines.slice(from, end), trailing: st.lines.slice(end, to)};
    }

    function moveBlock(direction) {
        var st = pageStructure(editorTextarea.value);
        var index = cursorBlockIndex(st);
        var other = index + direction;
        if (index === -1 || other < 0 || other >= st.blocks.length) return;
        var first = blockChunk(st, Math.min(index, other));
        var second = blockChunk(st, Math.max(index, other));
        // the second comes first, an empty line keeps the two apart
        var gap = first.trailing.length ? first.trailing : [''];
        var newLines = second.content.concat(gap, first.content, second.trailing);
        var moved = direction < 0 ? second : first;
        var offsetInBlock = editorTextarea.selectionStart - st.lineStarts[moved.from];
        var newBlockStart = st.lineStarts[first.from]
            + (direction < 0 ? 0 : second.content.concat(gap).join('\n').length + 1);
        replaceLines(st, first.from, second.to, newLines,
            newBlockStart + Math.min(offsetInBlock, moved.content.join('\n').length));
    }

    // the comment with "disabled: true" in its config, or without it
    function toggleBlock() {
        var st = pageStructure(editorTextarea.value);
        var index = cursorBlockIndex(st);
        if (index === -1) return;
        var block = st.blocks[index];
        var lines = st.lines.slice(block.line, block.endLine + 1);
        var nameLine = lineOfOffset(st, block.nameStart) - block.line;
        var line = lines[nameLine];
        var nameEnd = block.nameEnd - st.lineStarts[block.line + nameLine];
        var head = line.substring(0, nameEnd), rest = line.substring(nameEnd);
        var flow = rest.match(/^\s*:\s*\{([^}]*)\}/);
        if (block.disabled) {
            if (flow) {
                var entries = flow[1].split(',').filter(function (e) { return !/^\s*disabled\s*:\s*true\s*$/.test(e); });
                var config = entries.map(function (e) { return e.trim(); }).filter(Boolean).join(', ');
                lines[nameLine] = head + (config ? ': {' + config + '}' : '') + rest.substring(flow[0].length);
            } else {
                lines = lines.filter(function (l) { return !/^\s*disabled\s*:\s*true\s*$/.test(l); });
            }
        } else if (flow) {
            lines[nameLine] = head + ': {' + (flow[1].trim() ? flow[1].trim() + ', ' : '') + 'disabled: true}' + rest.substring(flow[0].length);
        } else if (block.endLine > block.line && /^\s*:\s*$/.test(rest)) {
            // a config in YAML lines below the name, the new value gets their indent
            var next = lines[nameLine + 1] || '';
            var indent = /^\s+\S/.test(next) && next.indexOf('-->') === -1 ? next.match(/^\s+/)[0] : '    ';
            lines.splice(nameLine + 1, 0, indent + 'disabled: true');
        } else {
            lines[nameLine] = head + ': {disabled: true}' + rest.replace(/^\s*:\s*(null|~)?/, '');
        }
        var caret = st.lineStarts[block.line] + Math.min(lines[0].length, editorTextarea.selectionStart - st.lineStarts[block.line]);
        replaceLines(st, block.line, block.endLine + 1, lines, caret);
    }

    if (blockActions && editorTextarea) {
        [blockUp, blockDown, blockToggle].forEach(function (button) {
            // the caret stays in the editor
            button.addEventListener('mousedown', function (e) { e.preventDefault(); });
        });
        blockUp.addEventListener('click', function () { moveBlock(-1); });
        blockDown.addEventListener('click', function () { moveBlock(1); });
        blockToggle.addEventListener('click', toggleBlock);
    }

    // --- Check while typing ---
    // The server renders every block of the draft and reports what does not
    // fit the block, a few moments after the last key.

    var problemsToggle = document.getElementById('editor-problems-toggle');
    var problemsMenu = document.getElementById('editor-problems');
    var validateTimer = null;
    var validateRequest = 0;

    function jumpToLine(line) {
        var st = pageStructure(editorTextarea.value);
        var offset = st.lineStarts[Math.min(line, st.lineStarts.length - 1)] || 0;
        editorTextarea.focus();
        editorTextarea.setSelectionRange(offset, offset);
        var lineHeight = parseFloat(getComputedStyle(editorTextarea).lineHeight) || 20;
        editorTextarea.scrollTop = Math.max(0, line * lineHeight - editorTextarea.clientHeight / 3);
        updateEditorStatus();
    }

    function showProblems(problems) {
        if (!problemsToggle || !problemsMenu) return;
        problemsToggle.classList.toggle('d-none', problems.length === 0);
        problemsToggle.textContent = problems.length === 1 ? '1 problem' : problems.length + ' problems';
        problemsMenu.textContent = '';
        problems.forEach(function (p) {
            var item = document.createElement('div');
            item.className = 'editor-problem';
            var link = document.createElement('button');
            link.type = 'button';
            link.className = 'btn btn-link p-0 text-start';
            var where = document.createElement('strong');
            where.textContent = (p.block ? p.block + ' · ' : '') + 'line ' + (p.line + 1);
            link.appendChild(where);
            link.appendChild(document.createTextNode(' ' + p.message));
            link.addEventListener('click', function () { jumpToLine(p.line); });
            item.appendChild(link);
            if (p.block && blockExamples[p.block]) {
                var details = document.createElement('details');
                var summary = document.createElement('summary');
                summary.textContent = 'Expected Markdown';
                var pre = document.createElement('pre');
                pre.textContent = '<!-- ' + p.block + ' -->\n\n' + blockExamples[p.block];
                details.appendChild(summary);
                details.appendChild(pre);
                item.appendChild(details);
            }
            problemsMenu.appendChild(item);
        });
    }

    function validateDraft() {
        if (!editorTextarea || !currentPage) return;
        var request = ++validateRequest;
        var body = new FormData();
        body.append('csrf_token', csrfToken);
        body.append('page', currentPage);
        body.append('content', editorTextarea.value);
        fetch('pages?validate=1', {method: 'POST', body: body}).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (data) {
            // an answer to an older draft must not replace a newer one
            if (data && request === validateRequest) showProblems(data.problems || []);
        }).catch(function () { /* the check is a help, saving reports real errors */ });
    }

    if (editorTextarea && problemsToggle) {
        editorTextarea.addEventListener('input', function () {
            clearTimeout(validateTimer);
            validateTimer = setTimeout(validateDraft, 800);
        });
        validateDraft();
    }

    // --- Protected structure lines ---
    // The block comments and the "---" between the parts of a block do not take
    // typing or a backspace that would change them. A selection of the whole
    // line, or of everything, can delete or replace it, so a block can still be
    // removed on purpose. The lock in the toolbar switches this off.

    if (editorTextarea) {
        // The editor gives every block a background, the protected lines a
        // stronger one (template.php)
        editorTextarea.rebootLineClass = function (index) {
            var st = pageStructure(editorTextarea.value);
            var classes = [];
            var label = null;
            if (st.lineBlock[index] !== -1) {
                var block = st.blocks[st.lineBlock[index]];
                // the block background only while protected, the comment always
                if (protectStructure) classes.push('md-block');
                if (index <= block.endLine) classes.push('md-block-head');
                if (block.disabled) classes.push('md-block-disabled');
                var separator = block.separators.indexOf(index);
                if (separator !== -1) {
                    classes.push('md-separator');
                    label = separatorLabel(block, separator + 2);
                }
            }
            if (protectStructure && st.protectedLines[index]) classes.push('md-protected');
            if (!classes.length) return null;
            return label ? {className: classes.join(' '), label: label} : classes.join(' ');
        };
        // the block names, and the names in the frontmatter up to the value
        editorTextarea.rebootRangeClass = function (text) {
            var st = pageStructure(text);
            var ranges = st.blocks.filter(function (b) { return b.nameStart !== -1; }).map(function (b) {
                return {start: b.nameStart, end: b.nameEnd,
                    className: 'md-block-name' + (b.known ? '' : ' md-block-unknown') + (b.disabled ? ' md-block-name-off' : '')};
            });
            if (protectStructure) {
                st.spans.forEach(function (span) {
                    if (span.key) ranges.push({start: span.start, end: span.end, className: 'md-protected md-protected-part'});
                });
            }
            return ranges;
        };
        editorTextarea.addEventListener('beforeinput', function (e) {
            var type = e.inputType || '';
            if (!protectStructure || type.indexOf('history') === 0 || type.indexOf('format') === 0) return;
            var text = editorTextarea.value;
            var st = pageStructure(text);
            if (!st.spans.length) return;
            var s = editorTextarea.selectionStart, en = editorTextarea.selectionEnd;
            var isBreak = type === 'insertParagraph' || type === 'insertLineBreak';
            var backward = type.indexOf('delete') === 0 && type.indexOf('Backward') !== -1;
            var forward = type.indexOf('delete') === 0 && !backward;
            var lineIsEmpty = function (line) {
                return line < 0 || line >= st.lines.length || st.lines[line].trim() === '';
            };
            // In the frontmatter only values change: no new line, no joined lines,
            // so no name can be added or removed
            if (st.frontmatterEnd > 0) {
                var fmFrom = st.lineStarts[1] - 1, fmTo = st.lineStarts[st.frontmatterEnd] - 1;
                var inFm = function (offset) { return offset >= fmFrom && offset <= fmTo; };
                var data = e.data || (e.dataTransfer ? e.dataTransfer.getData('text/plain') : '') || '';
                // the whole frontmatter, both "---" included, may go at once
                var wholeFm = s === 0 && en >= st.lineStarts[st.frontmatterEnd] + st.lines[st.frontmatterEnd].length;
                var fmBlocked = !wholeFm && (
                    ((isBreak || data.indexOf('\n') !== -1) && inFm(s))
                    || (s === en && backward && text.charAt(s - 1) === '\n' && inFm(s - 1))
                    || (s === en && forward && text.charAt(s) === '\n' && inFm(s))
                    || (s !== en && text.substring(s, en).indexOf('\n') !== -1 && (inFm(s) || inFm(en - 1))));
                if (fmBlocked) {
                    e.preventDefault();
                    showEditorHint('In the frontmatter only the values can be changed. For more, switch off the lock in the toolbar.');
                    return;
                }
            }
            var blocked = st.spans.some(function (span) {
                if (span.key) {
                    // the value starts right after the colon, typing there is fine
                    if (s !== en) return en > span.start && s < span.end && !(s <= span.start && en >= span.end && wholeFm);
                    if (s > span.start && s < span.end) return true;
                    if (s === span.start) return true;
                    if (s === span.end) return backward;
                    return false;
                }
                // a selection may delete or replace a structure line as a whole,
                // so a block, or everything, can still be removed on purpose
                if (s !== en) return en > span.start && s < span.end && !(s <= span.start && en >= span.end);
                if (s > span.start && s < span.end) return true;
                if (s === span.start) {
                    if (isBreak) return false;
                    // joining with the line above keeps the structure line only if that one is empty
                    if (backward) return s > 0 && !lineIsEmpty(span.line - 1);
                    return true;
                }
                if (s === span.end) {
                    if (isBreak) return false;
                    if (forward) return !lineIsEmpty(span.line + 1);
                    return true;
                }
                // a backspace at the start of the next line would pull it up to the structure line
                if (backward && s === span.end + 1) return !lineIsEmpty(span.line + 1);
                if (forward && s === span.start - 1) return !lineIsEmpty(span.line - 1);
                return false;
            });
            if (blocked) {
                e.preventDefault();
                showEditorHint('Block lines are protected. Select the whole line to delete it, or switch off the lock in the toolbar.');
            }
        });
    }

    // --- Preview (double-buffered) ---

    // open by default on wide screens, until the preview button saves a choice
    var previewStored = store.get('reboot_preview');
    var previewActive = previewStored === null ? window.innerWidth >= 1200 : previewStored === 'true';
    var previewDebounceTimer = null;
    var previewInitialized = false;
    // a structured page is edited in a form of fields, see field-form.js
    var structuredForm = document.querySelector('form[data-field-form]');
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
        if (structuredForm) {
            // the form itself goes to the preview, the server writes the YAML in memory only
            var action = structuredForm.getAttribute('action');
            structuredForm.setAttribute('action', 'pages?preview=1&page=' + encodeURIComponent(currentPage));
            structuredForm.target = back.name;
            structuredForm.submit();
            structuredForm.setAttribute('action', action);
            structuredForm.removeAttribute('target');
            return;
        }
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
            if (structuredForm) {
                structuredForm.addEventListener('fieldchange', schedulePreviewUpdate);
            }
        } else {
            editorCol.classList.remove('col-lg-5', 'col-xl-6');
            editorCol.classList.add('col-lg-9', 'col-xl-10');
            editorCol.parentElement.classList.remove('preview-split');
            previewCol.classList.remove('d-lg-block');
            previewCol.classList.add('d-lg-none');
            previewInitialized = false;
            if (structuredForm) {
                structuredForm.removeEventListener('fieldchange', schedulePreviewUpdate);
            }
            if (editorTextarea) {
                editorTextarea.removeEventListener('input', schedulePreviewUpdate);
            }
        }
    };

    // The preview button sits in the editor toolbar, left of the word wrap
    // toggle. The editor builds the toolbar in a module script, which runs
    // before DOMContentLoaded.
    var previewButton = null;
    var protectButton = null;
    // on by default, the lock in the toolbar turns it off for this browser
    var protectStructure = store.get('reboot_editor_protect') !== 'false';

    function syncProtectButton() {
        if (protectButton) protectButton.setAttribute('aria-pressed', protectStructure ? 'true' : 'false');
    }

    function syncPreviewButton() {
        if (!previewButton) return;
        previewButton.setAttribute('aria-pressed', previewActive ? 'true' : 'false');
    }

    // Icons: "lock", "lock-fill", "eye" and "eye-fill" from Bootstrap Icons (MIT),
    // filled while the toggle is on
    var ICON_LOCK_FILL = '<path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/>';
    var ICON_EYE_FILL = '<path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>'
        + '<path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>';
    var ICON_LOCK = '<path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2M5 8h6a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1"/>';
    var ICON_EYE = '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>'
        + '<path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>';

    // a toggle in the editor toolbar, left of the word wrap toggle
    function addToolbarToggle(toolbar, id, title, icon, iconOn, onClick) {
        var buttons = toolbar.querySelectorAll('button');
        var wrapButton = buttons[buttons.length - 1];
        var button = document.createElement('button');
        button.type = 'button';
        button.id = id;
        button.className = 'editor-toolbar-button';
        button.title = title;
        button.setAttribute('aria-label', title);
        button.tabIndex = -1;
        var svg = function (paths, className) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" class="' + className + '">' + paths + '</svg>';
        };
        button.innerHTML = svg(icon, 'icon-off') + svg(iconOn, 'icon-on');
        button.addEventListener('mousedown', function (e) { e.preventDefault(); });
        button.addEventListener('click', onClick);
        toolbar.insertBefore(button, wrapButton);
        return button;
    }

    function addPreviewButton() {
        var structuredButton = document.getElementById('structured-preview-toggle');
        if (structuredButton) {
            previewButton = structuredButton;
            previewButton.addEventListener('click', window.togglePreview);
            syncPreviewButton();
            return;
        }
        var toolbar = editorTextarea && editorTextarea.parentNode.querySelector('[role="toolbar"]');
        if (!toolbar || !currentPage) return;
        protectButton = addToolbarToggle(toolbar, 'protect-toggle', 'Protect the block lines', ICON_LOCK, ICON_LOCK_FILL, function () {
            protectStructure = !protectStructure;
            store.set('reboot_editor_protect', protectStructure);
            syncProtectButton();
            if (editorTextarea.mdEditor) editorTextarea.mdEditor.updateHighlight();
        });
        syncProtectButton();
        previewButton = addToolbarToggle(toolbar, 'preview-toggle', 'Preview', ICON_EYE, ICON_EYE_FILL, window.togglePreview);
        syncPreviewButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addPreviewButton);
    } else {
        addPreviewButton();
    }

    // Restore preview state on load (only on lg+ screens), on a page with an editor
    if (previewActive && currentPage && (editorTextarea || structuredForm) && window.innerWidth >= 992) {
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

    // --- In a structured page the preview follows the field being edited ---
    // Page marks every block in the preview with the number of its panel,
    // `<!--reboot-preview-block:3-->`. The preview scrolls to the element that
    // shows the value of the field, for a Markdown field the cursor line, or else
    // to the block, and outlines the element for a moment.

    var structuredField = null;

    function structuredTarget(doc) {
        if (!structuredField) return null;
        var block = structuredField.closest('.field-blocks > .field-list-items > .field-block');
        if (!block) return null;
        var index = Array.prototype.indexOf.call(block.parentNode.children, block);
        var markers = [];
        var walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_COMMENT);
        var node;
        while ((node = walker.nextNode())) {
            var match = node.nodeValue.match(/^reboot-preview-block:(\d+)$/);
            if (match) markers.push({node: node, index: parseInt(match[1], 10)});
        }
        for (var i = 0; i < markers.length; i++) {
            if (markers[i].index === index) {
                var line = '';
                if (structuredField.matches('[data-image-input]')) {
                    line = structuredField.value ? '![](' + structuredField.value + ')' : '';
                } else if (structuredField.tagName === 'TEXTAREA') {
                    var before = structuredField.value.substring(0, structuredField.selectionStart);
                    line = structuredField.value.substring(before.lastIndexOf('\n') + 1).split('\n')[0];
                } else if (structuredField.type === 'text' || structuredField.type === 'url' || structuredField.type === 'number') {
                    line = structuredField.value;
                }
                var element = line ? findLineElement(markers[i], markers[i + 1] || null, line) : null;
                return {element: element || siblingElement(markers[i].node, true), exact: !!element};
            }
        }
        return null;
    }

    function outlineInPreview(element) {
        var previous = element.style.outline;
        var previousOffset = element.style.outlineOffset;
        element.style.outline = '2px solid #3d8bfd';
        element.style.outlineOffset = '4px';
        clearTimeout(element._rebootOutline);
        element._rebootOutline = setTimeout(function () {
            element.style.outline = previous;
            element.style.outlineOffset = previousOffset;
        }, 1500);
    }

    function previewYForCursor(doc, win) {
        if (structuredForm) {
            var target = structuredTarget(doc);
            if (!target || !target.element) return null;
            if (target.exact) outlineInPreview(target.element);
            return target.element.getBoundingClientRect().top + win.scrollY;
        }
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

    if (structuredForm) {
        structuredForm.addEventListener('focusin', function (e) {
            if (!e.target.matches('input, textarea, select')) return;
            structuredField = e.target;
            scrollPreviewToCursor(getFrontIframe(), true);
        });
        // a new line in a Markdown field, the preview reloads on typing anyway
        structuredForm.addEventListener('keyup', function (e) {
            if (e.target.tagName === 'TEXTAREA' && /^(Arrow|Page|Home|End)/.test(e.key)) {
                structuredField = e.target;
                scrollPreviewToCursor(getFrontIframe(), true);
            }
        });
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
