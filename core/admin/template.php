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
    <link href="vendor/simplemde-markdown-editor/simplemde.min.css" rel="stylesheet">
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="../../web/default/assets/screen.css" rel="stylesheet">

</head>
<body>
<?php $navbarConfig = $site->getConfig()['navbar']; ?>
<nav class="navbar navbar-expand-md navbar-light bg-light fixed-top">
    <!--suppress HtmlUnknownTarget -->
    <a class="navbar-brand" href="pages"><?php echo $navbarConfig["brand"] ?></a>
    <?php if ($authentication->getUser()) { ?>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbar">
            <ul class="navbar-nav mr-auto">
                <?php
                $structure = $navbarConfig['structure'];
                if ($structure) {
                    foreach ($structure as $label => $path) {
                        ?>
                        <li class="nav-item <?= $request->getPath() == $path ? "active" : "" ?>">
                            <a class="nav-link" href="<?= $site->getWebPath() . $path ?>"><?= $label ?></a>
                        </li>
                        <?php
                    }
                }
                ?>
            </ul>
            <span class="mr-3 navbar-text mt-2 mt-md-0">
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
</nav>
<?php
echo($page->render($request));
?>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="vendor/simplemde-markdown-editor/simplemde.min.js"></script>
<script>
    setTimeout(function () {
        $(".fade-out").fadeOut(250);
    }, 1000)
    let editors = $("textarea.markdown")
    for (const editor of editors) {
        editor.simpleMDE = new SimpleMDE({element: editor, promptURLs: true, spellChecker: false})
    }
</script>
</body>
</html>
