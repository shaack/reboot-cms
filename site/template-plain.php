<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */
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
</head>
<body>
<main>
    <?php
    echo $page->render($request);;
    ?>
</main>
</html>
