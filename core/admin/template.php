<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
$admin = $site->getAddOn("Admin");
$localConfig = $admin->getLocalConfig();
$editor = $localConfig['editor'] ?? [];
$editorFont = $editor['font'] ?? 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace';
$editorFontSize = $editor['fontSize'] ?? '1rem';
$editorLineHeight = $editor['lineHeight'] ?? '1.5';
$editorTabSize = $editor['tabSize'] ?? '4';
$editorWordWrap = ($editor['wordWrap'] ?? true) ? 'true' : 'false';
?>
<!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
    <base href="<?= $site->getWebPath() ?>/"/>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <?php
    $adminPageTitle = ucfirst(trim($request->getPath(), '/'));
    if (!$adminPageTitle) $adminPageTitle = 'Admin';
    ?>
    <title><?= htmlspecialchars($adminPageTitle) ?> – Reboot CMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Gelasio:wght@700&display=swap" rel="stylesheet">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/screen.css" rel="stylesheet">
    <style>textarea.editor-font { font-family: <?= $editorFont ?>; font-size: <?= $editorFontSize ?>; line-height: <?= $editorLineHeight ?>; tab-size: <?= $editorTabSize ?>; -moz-tab-size: <?= $editorTabSize ?>; }</style>
    <script src="node_modules/bootstrap-auto-dark-mode/src/bootstrap-auto-dark-mode.js"></script>
    <script>
        window._toastQueue = []
        window._toastsReady = false
        function statusMessage(body, toastClass) {
            var t = {body: body, toastClass: toastClass || "text-bg-success"}
            if (window._toastsReady) {
                bootstrap.showToast(t)
            } else {
                window._toastQueue.push(t)
            }
        }
    </script>
</head>
<body class="bg-body-tertiary">
<?php $navbarConfig = $site->getConfig()['navbar']; ?>
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <!--suppress HtmlUnknownTarget -->
        <a class="navbar-brand me-5" href="pages"><?php echo $navbarConfig["brand"] ?></a>
        <?php if ($authentication->getUser()) { ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbar">
                <ul class="navbar-nav me-auto">
                    <?php
                    $structure = $navbarConfig['structure'];
                    $isAdmin = $authentication->isAdmin();
                    if ($structure) {
                        foreach ($structure as $label => $path) {
                            if (!$isAdmin && !in_array($path, \Shaack\Reboot\Authentication::EDITOR_PAGES)) {
                                continue;
                            }
                            ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $request->getPath() == $path ? "active" : "" ?>" href="<?= $site->getWebPath() . $path ?>"><?= $label ?></a>
                            </li>
                            <?php
                        }
                    }
                    ?>
                </ul>
                <span class="me-3 navbar-text opacity-75 mt-2 mt-md-0">
                    Logged in as <?= $authentication->getUser() ?>
                </span>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <!--suppress HtmlUnknownTarget -->
                        <a href="logout" class="btn btn-outline-secondary btn-sm mt-1 mt-md-0 mb-2 mb-md-0">
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        <?php } ?>
    </div>
</nav>
<?php
echo($page->render($request));
?>
<!-- <script src="node_modules/jquery/jquery.min.js"></script> -->
<script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="node_modules/bootstrap-show-toast/src/bootstrap-show-toast.js"></script>
<script>
    window._toastQueue.forEach(function(t) { bootstrap.showToast(t) })
    window._toastsReady = true
</script>
<script type="module">
    import {MdEditor} from "./node_modules/cm-md-editor/src/MdEditor.js"
    import {defaultTools} from "./node_modules/cm-md-editor/src/tools/DefaultTools.js"
    import {Separator} from "./node_modules/cm-md-editor/src/tools/Separator.js"
    import {InsertBlock} from "./assets/InsertBlock.js"
    import {InsertMedia} from "./assets/InsertMedia.js"
    import {InsertPageLink} from "./assets/InsertPageLink.js"
    const blockExamplesEl = document.getElementById('block-examples')
    const blockExamples = blockExamplesEl ? JSON.parse(blockExamplesEl.textContent) : null
    document.querySelectorAll("textarea.markdown").forEach(editor => {
        const props = {wordWrap: <?= $editorWordWrap ?>}
        const extraTools = [
            [InsertPageLink, {pagesUrl: 'pages'}],
            [InsertMedia, {mediaUrl: 'media'}]
        ]
        if (blockExamples && Object.keys(blockExamples).length > 0) {
            extraTools.push([InsertBlock, {blocks: blockExamples}])
        }
        props.tools = [...defaultTools, Separator, ...extraTools]
        new MdEditor(editor, props)
    })
</script>
</body>
</html>
