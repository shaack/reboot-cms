<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**
 * The info page of a site AddOn at `/addons/<name>`, linked in the menu
 * "AddOns" for AddOns without an own admin page. Shows the README.md of the
 * AddOn folder, or the doc comment of its class.
 */

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\Admin\AdminMenu;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

$admin = $site->getAddOn("Admin");
$addOn = null;
foreach ($admin->getDefaultSite()->getAddOns() as $candidate) {
    if (AdminMenu::infoPath($candidate) === $request->getPath()) {
        $addOn = $candidate;
        break;
    }
}
if ($addOn === null) {
    http_response_code(404);
    echo '<div class="container-fluid max-width-xxl"><p class="text-body-secondary">This AddOn is not registered.</p></div>';
    return;
}

$baseFsPath = $reboot->getBaseFsPath();
$dir = $addOn->getDir();
$readme = $dir !== null && is_file($dir . '/README.md') ? file_get_contents($dir . '/README.md') : null;
if ($readme !== null) {
    // the README names the AddOn in its first heading, the page title does that already
    $readme = preg_replace('/\A\s*#\s[^\n]*\n/', '', $readme);
    $description = (new \Parsedown())->text($readme);
} else {
    $docComment = (new \ReflectionClass($addOn))->getDocComment() ?: '';
    $docComment = trim(preg_replace('#^\s*(/\*\*|\*/|\*)\s?#m', '', $docComment));
    $description = $docComment !== ''
        ? '<p>' . nl2br(htmlspecialchars($docComment)) . '</p>'
        : '<p class="text-body-secondary">This AddOn has no description. Add a <code>README.md</code> to its folder.</p>';
}
$location = $dir !== null ? substr($dir, strlen($baseFsPath) + 1) : null;
$ownPages = $addOn->getAdminPages();
?>
<div class="container-fluid max-width-xxl">
    <?= AdminHelper::renderPageTitle(AdminMenu::infoLabel($addOn), $location !== null ? $location . '/' : '') ?>
    <div class="card">
        <div class="card-body addon-info">
            <?php if ($addOn->getDeprecation() !== null) { ?>
                <div class="alert alert-warning"><?= htmlspecialchars($addOn->getDeprecation()) ?></div>
            <?php } ?>
            <?php if (isset($ownPages[$addOn->getAdminPath()])) { ?>
                <p><a href="<?= htmlspecialchars($site->getWebPath() . $addOn->getAdminPath()) ?>" class="btn btn-sm btn-outline-primary">Open the admin page of this AddOn</a></p>
            <?php } ?>
            <?= $description ?>
        </div>
    </div>
</div>
