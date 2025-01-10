<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */

$content = $page->render($request);
$config = $page->getConfig(); // the frontmatter config
?>
<!doctype html>
<html lang="en">
<head>
    <base href="<?= $site->getWebPath() ?>/"/>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Reboot CMS demo page">
    <meta name="author" content="shaack.com">

    <title>Reboot CMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="./assets/styles/screen.css" rel="stylesheet">
</head>
<body data-bs-theme="dark">
<?php $navbarConfig = $site->getConfig()['navbar']; ?>
<nav class="navbar navbar-expand-md fixed-top bg-dark navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand me-5" href="<?= $site->getWebPath() ?>/"><?php echo $navbarConfig["brand"] ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbar">
            <ul class="navbar-nav mr-auto">
                <?php
                $structure = $navbarConfig['structure'];
                if ($structure) {
                    foreach ($structure as $label => $path) {
                        $active = false;
                        if ($path === "/") {
                            $active = $request->getPath() === $path;
                        } else {
                            $active = substr($request->getPath(), 0, strlen($path)) === $path;
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $active ? "active" : "" ?>"
                               href="<?= $site->getWebPath() . $path ?>"><?= $label ?></a>
                        </li>
                        <?php
                    }
                }
                ?>
            </ul>
        </div>
    </div>
</nav>
<main>
<?php
echo $content;
?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
        integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
        crossorigin="anonymous"></script>
</body>
</html>
