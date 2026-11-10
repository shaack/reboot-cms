<?php

/** @var Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Page $page */
/** @var Shaack\Reboot\Request $request */

$content = $page->render($request);
$e = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
// on a site with more than one language, the page in every language, for the switcher and hreflang
$language = $request->getLanguage();
$languageUrls = $site->isMultilingual() ? $site->languageUrls($request) : [];
?>
<!doctype html>
<html lang="<?= $e($language) ?>">
<head>
    <base href="<?= $e($site->getWebPath()) ?>/"/>
    <title><?= $page->meta('title', 'Reboot CMS') ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?= $page->meta('description', '') ?>">
    <meta name="author" content="<?= $page->meta('author', 'shaack.com') ?>">
    <?php foreach ($languageUrls as $code => $url) { ?>
    <link rel="alternate" hreflang="<?= $e($code) ?>" href="<?= $e($url) ?>">
    <?php } ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="preload" href="./assets/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link href="./assets/styles/screen.css?v=<?= @filemtime(dirname($site->getFsPath()) . "/web/assets/styles/screen.css") ?>" rel="stylesheet">
</head>
<body data-bs-theme="dark">
<?php $navbarConfig = $site->getConfig()['navbar'] ?? []; ?>
<nav class="navbar navbar-expand-md fixed-top navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand me-5 text-muted" href="<?= $e($site->getWebPath() . $site->languagePath("/", $language)) ?>"><?php echo $navbarConfig["brand"] ?? "" ?></a>
        <?php if(!$page->meta('hide-nav')) { ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbar">
            <ul class="navbar-nav mr-auto">
                <?php
                $structure = $navbarConfig['structure'] ?? [];
                if ($structure) {
                    foreach ($structure as $key => $value) {
                        // `/about: {en: About, de: Über uns}`, the path first and a label per
                        // language or one for all, or the older `About: /about`
                        $key = (string)$key;
                        $pathFirst = str_starts_with($key, "/") || str_starts_with($key, "http://") || str_starts_with($key, "https://");
                        $path = $pathFirst ? $key : $value;
                        $label = $pathFirst ? $site->localize($value, $language) : $key;
                        // an entry with a full URL leaves the site, in a new tab
                        $external = str_starts_with($path, "http://") || str_starts_with($path, "https://");
                        $active = false;
                        if ($external) {
                            $href = $path;
                        } else {
                            // the navigation stays in the language of the page
                            $href = $site->getWebPath() . $site->languagePath($path, $language);
                            $current = $request->getPathWithoutLanguage();
                            $active = $path === "/" ? $current === $path : str_starts_with($current, $path);
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $active ? "active" : "" ?>" href="<?= $e($href) ?>"
                               <?= $external ? 'target="_blank" rel="noopener"' : "" ?>><?= $label ?></a>
                        </li>
                        <?php
                    }
                }
                ?>
            </ul>
            <?php if ($languageUrls) { ?>
                <ul class="navbar-nav ms-auto language-switch" aria-label="Language">
                    <?php foreach ($languageUrls as $code => $url) { ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $code === $language ? "active" : "" ?>" href="<?= $e($url) ?>" hreflang="<?= $e($code) ?>" lang="<?= $e($code) ?>"
                               <?= $code === $language ? 'aria-current="true"' : "" ?>><?= $e(strtoupper($code)) ?></a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
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
        <span><a href="https://github.com/shaack/reboot-cms">GitHub</a> · <a href="<?= $e($site->getWebPath()) ?>/documentation">Documentation</a> · <a href="https://shaack.com">shaack.com</a></span>
    </div>
</footer>
<?php // the whole site is a demo, its seminars and posts are examples ?>
<div class="demo-ribbon" title="Example content, made up to show the CMS"><span>Demo</span></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
        integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
        crossorigin="anonymous"></script>
<?php // the modification time as version, so a changed script is not served from the cache ?>
<script src="./assets/scripts/lightbox.js?v=<?= @filemtime(dirname($site->getFsPath()) . "/web/assets/scripts/lightbox.js") ?>" defer></script>
</body>
</html>
