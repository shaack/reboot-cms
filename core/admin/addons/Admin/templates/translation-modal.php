<?php
/**
 * How to set up the machine translation with DeepL, shown by the buttons
 * "Translate" while `local/config.local.yml` has no key, see pages.js.
 */
?>
<div class="modal fade" id="translation-setup-modal" tabindex="-1" aria-labelledby="translation-setup-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="translation-setup-label">Translate with DeepL</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>The buttons <em>Translate</em> fill a field with a translation by <a href="https://www.deepl.com/pro-api" target="_blank" rel="noopener">DeepL</a>. They need a key for the DeepL API, there is none yet.</p>
                <ol>
                    <li>Create a key at <a href="https://www.deepl.com/pro-api" target="_blank" rel="noopener">deepl.com/pro-api</a>. <em>DeepL API Free</em> costs nothing and translates a limited number of characters per month, its key ends with <code>:fx</code>.</li>
                    <li>Add the key to <code>local/config.local.yml</code> in the folder of the site, create the file if there is none:
                        <pre class="mt-2 mb-0"><code>translation:
  deepl:
    authKey: "your-key:fx"</code></pre>
                    </li>
                    <li>Reload this page.</li>
                </ol>
                <p class="mb-0 text-body-secondary small">Git ignores <code>local/config.local.yml</code>, so the key stays out of the repository. The texts go to DeepL, a company in Cologne. DeepL API Free comes without a data processing agreement, send it public texts only, nothing personal.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
