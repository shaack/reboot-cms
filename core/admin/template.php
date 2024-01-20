<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
?>
<!doctype html>
<html lang="en">
<head>
    <base href="<?= $site->getWebPath() ?>/"/>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Reboot CMS / Admin</title>
    <link href="node_modules/simplemde/dist/simplemde.min.css" rel="stylesheet">
    <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/screen.css" rel="stylesheet">

</head>
<body>
<?php $navbarConfig = $site->getConfig()['navbar']; ?>
<nav class="navbar navbar-expand-md navbar-light bg-light fixed-top">
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
                        <a href="logout" class="btn btn-outline-secondary mt-1 mt-md-0 mb-2 mb-md-0">
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
<script src="node_modules/simplemde/dist/simplemde.min.js"></script>
<script>
    let editors = document.querySelectorAll("textarea.markdown")
    for (const editor of editors) {
        editor.simpleMDE = new SimpleMDE({element: editor, promptURLs: true, spellChecker: false})
    }
</script>
</body>
</html>
