<?php
/**
 * The dialog of the page history, filled by `toggleHistory()` in pages.js.
 * Included by the Markdown editor and by the editor of structured pages.
 */
?>
<div class="modal fade" id="page-history-modal" tabindex="-1" aria-labelledby="page-history-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="page-history-label">History</h5>
                <span id="page-history-count" class="text-body-secondary ms-2"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="page-history-body">
                <div class="p-4 text-center text-body-secondary">Loading…</div>
            </div>
        </div>
    </div>
</div>
