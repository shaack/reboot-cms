<?php

/** @var Shaack\Reboot\Reboot $reboot */
/** @var Shaack\Reboot\Page $page */

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Reboot CMS</title>

    <link href="<?= $reboot->getBaseUrl() ?>/vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $reboot->getBaseUrl() ?>/theme/assets/style/screen.css" rel="stylesheet">
</head>
<body>
<?php $navbarConfig = $reboot->getGlobals()['navbar']; ?>
<nav class="navbar navbar-expand-md navbar-light bg-light fixed-top">
    <a class="navbar-brand" href="<?= $reboot->getBaseUrl() ?>/"><?php echo $navbarConfig["brand"] ?></a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar"
            aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbar">
        <ul class="navbar-nav mr-auto">
            <?php
            $structure = $navbarConfig['structure'];
            foreach ($structure as $label => $path) {
                ?>
                <li class="nav-item <?= $reboot->getRequestUri() == $path ? "active" : "" ?>">
                    <a class="nav-link" href="<?= $reboot->getBaseUrl() . $path ?>"><?= $label ?></a>
                </li>
                <?php
            }
            ?>
        </ul>
    </div>
</nav>
<?php
echo($page->render());
?>
<script src="<?= $reboot->getBaseUrl() ?>/vendor/components/jquery/jquery.slim.js"></script>
<script src="<?= $reboot->getBaseUrl() ?>/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
