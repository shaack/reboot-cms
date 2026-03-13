<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
?>
<!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
    <base href="<?= $site->getWebPath() ?>/"/>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Reboot CMS / Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Gelasio:wght@700&display=swap" rel="stylesheet">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/screen.css" rel="stylesheet">
    <script src="node_modules/bootstrap-auto-dark-mode/src/bootstrap-auto-dark-mode.js"></script>
    <script>
        window._toastQueue = []
        function statusMessage(body, toastClass) {
            window._toastQueue.push({body: body, toastClass: toastClass || "text-bg-success"})
        }
    </script>
</head>
<body class="bg-body-tertiary">
<?php $navbarConfig = $site->getConfig()['navbar']; ?>
<nav class="navbar navbar-expand-md fixed-top">
    <div class="container-fluid">
        <!--suppress HtmlUnknownTarget -->
        <a class="navbar-brand" href="pages"><?php echo $navbarConfig["brand"] ?></a>
        <?php if ($authentication->getUser()) { ?>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbar">
                <ul class="navbar-nav me-auto">
                    <?php
                    $structure = $navbarConfig['structure'];
                    if ($structure) {
                        foreach ($structure as $label => $path) {
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
</script>
<script type="module">
    import {MdEditor} from "./node_modules/cm-md-editor/src/MdEditor.js"
    document.querySelectorAll("textarea.markdown").forEach(editor => {
        new MdEditor(editor)
    })
</script>
</body>
</html>
