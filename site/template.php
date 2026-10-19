<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */

$content = $page->render($request);
$pageConfig = $page->getConfig(); // the frontmatter config
?>
<!doctype html>
<html lang="en">
<head>
    <base href="<?= $site->getWebPath() ?>/"/>
    <title><?php echo $pageConfig['title'] ?? 'Reboot CMS'; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?php echo $pageConfig['description'] ?? ''; ?>">
    <meta name="author" content="<?php echo $pageConfig['author'] ?? 'shaack.com'; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="./assets/styles/screen.css" rel="stylesheet">
</head>
<body data-bs-theme="dark">
<?php $navbarConfig = $site->getConfig()['navbar']; ?>
<nav class="navbar navbar-expand-md fixed-top navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand me-5 text-muted" href="<?= $site->getWebPath() ?>/"><?php echo $navbarConfig["brand"] ?></a>
        <?php if(!@$pageConfig["hide-nav"]) { ?>
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
                        // an entry with a full URL leaves the site, in a new tab
                        $external = str_starts_with($path, "http://") || str_starts_with($path, "https://");
                        $active = false;
                        if ($external) {
                            $href = $path;
                        } else {
                            $href = $site->getWebPath() . $path;
                            $active = $path === "/" ? $request->getPath() === $path : str_starts_with($request->getPath(), $path);
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $active ? "active" : "" ?>" href="<?= $href ?>"
                               <?= $external ? 'target="_blank" rel="noopener"' : "" ?>><?= $label ?></a>
                        </li>
                        <?php
                    }
                }
                ?>
            </ul>
        </div>
        <?php } ?>
    </div>
</nav>
<main>
<?php
echo $content;
?>
</main>
<footer class="site-footer">
    <div class="container-fluid">
        <span>Built with <a href="https://github.com/shaack/reboot-cms">Reboot CMS</a>, open source under the MIT license.</span>
        <span><a href="https://github.com/shaack/reboot-cms">GitHub</a> · <a href="<?= $site->getWebPath() ?>/documentation">Documentation</a> · <a href="https://shaack.com">shaack.com</a></span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
        integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
        crossorigin="anonymous"></script>
</body>
</html>
