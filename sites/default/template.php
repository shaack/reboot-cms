<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */

?>
<!doctype html>
<html lang="en">
<head>
    <base href="<?= $site->getWebPath() ?>" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Reboot CMS</title>
    <link href="admin/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="assets/styles/screen.css" rel="stylesheet">
</head>
<body>
<?php $navbarConfig = $site->getConfig()['navbar']; ?>
<nav class="navbar navbar-expand-md navbar-light bg-light fixed-top">
    <a class="navbar-brand" href="/"><?php echo $navbarConfig["brand"] ?></a>
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
    </div>
</nav>
<?php
echo($page->render($request));
?>
<script src="admin/vendor/jquery/jquery.min.js"></script>
<script src="admin/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
