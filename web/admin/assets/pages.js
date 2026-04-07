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

    // --- Folder collapse state (persisted in localStorage) ---

    var STORAGE_KEY = 'reboot_sidebar_expanded';
    function getExpandedFolders() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch (e) { return {}; }
    }
    function saveExpandedFolders(map) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
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
            folder.querySelector('.folder-icon').innerHTML = '&#9660;';
        }
        // Track show/hide and persist
        target.addEventListener('show.bs.collapse', function () {
            folder.querySelector('.folder-icon').innerHTML = '&#9660;';
            var map = getExpandedFolders();
            map[id] = true;
            saveExpandedFolders(map);
        });
        target.addEventListener('hide.bs.collapse', function () {
            folder.querySelector('.folder-icon').innerHTML = '&#9654;';
            var map = getExpandedFolders();
            delete map[id];
            saveExpandedFolders(map);
        });
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
            if (back.contentDocument) {
                back.contentDocument.documentElement.scrollTop = savedScrollTop;
            }
            // Swap: show back, hide front
            back.style.visibility = 'visible';
            front.style.visibility = 'hidden';
            previewFront = (previewFront === 'a') ? 'b' : 'a';
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
