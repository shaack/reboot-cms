<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Reboot\Admin\AdminHelper;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

$baseFsPath = $reboot->getBaseFsPath();

$checks = [];

// PHP version
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.0.0', '>=');
$checks[] = [
    'label' => 'PHP version',
    'status' => $phpOk,
    'value' => $phpVersion,
    'hint' => $phpOk ? '' : 'PHP 8.0 or higher is required.',
];

// Required extensions
foreach (['json', 'fileinfo', 'dom'] as $ext) {
    $loaded = extension_loaded($ext);
    $checks[] = [
        'label' => "PHP extension: $ext",
        'status' => $loaded,
        'value' => $loaded ? 'loaded' : 'missing',
        'hint' => $loaded ? '' : "Enable <code>extension=$ext</code> in php.ini.",
    ];
}

// mod_rewrite (Apache)
if (function_exists('apache_get_modules')) {
    $modRewrite = in_array('mod_rewrite', apache_get_modules(), true);
    $checks[] = [
        'label' => 'Apache mod_rewrite',
        'status' => $modRewrite,
        'value' => $modRewrite ? 'enabled' : 'disabled',
        'hint' => $modRewrite ? '' : 'mod_rewrite is required for URL routing.',
    ];
} else {
    $checks[] = [
        'label' => 'Apache mod_rewrite',
        'status' => null,
        'value' => 'unknown',
        'hint' => 'Cannot detect modules (not running as Apache module). Verify manually.',
    ];
}

// Composer dependencies
$vendorAutoload = $baseFsPath . '/vendor/autoload.php';
$composerOk = file_exists($vendorAutoload);
$checks[] = [
    'label' => 'Composer dependencies',
    'status' => $composerOk,
    'value' => $composerOk ? 'installed' : 'missing',
    'hint' => $composerOk ? '' : 'Run <code>composer install</code> in the project root.',
];

// Directory: local/ exists and writable
$localDir = $baseFsPath . '/local';
$localExists = is_dir($localDir);
$localWritable = $localExists && is_writable($localDir);
$checks[] = [
    'label' => 'Directory: local/',
    'status' => $localWritable,
    'value' => !$localExists ? 'missing' : ($localWritable ? 'writable' : 'not writable'),
    'hint' => !$localExists ? 'Create the <code>local/</code> directory.' : ($localWritable ? '' : 'Make <code>local/</code> writable by the web server.'),
];

// Directory: site/pages/ writable
$pagesDir = $baseFsPath . '/site/pages';
$pagesWritable = is_dir($pagesDir) && is_writable($pagesDir);
$checks[] = [
    'label' => 'Directory: site/pages/',
    'status' => $pagesWritable,
    'value' => !is_dir($pagesDir) ? 'missing' : ($pagesWritable ? 'writable' : 'not writable'),
    'hint' => $pagesWritable ? '' : 'Make <code>site/pages/</code> writable so pages can be edited.',
];

// Directory: site/media/ writable (if exists)
$mediaDir = $baseFsPath . '/site/media';
if (is_dir($mediaDir)) {
    $mediaWritable = is_writable($mediaDir);
    $checks[] = [
        'label' => 'Directory: site/media/',
        'status' => $mediaWritable,
        'value' => $mediaWritable ? 'writable' : 'not writable',
        'hint' => $mediaWritable ? '' : 'Make <code>site/media/</code> writable for media uploads.',
    ];
}

// .htaccess protection: local/
$localHtaccess = $baseFsPath . '/local/.htaccess';
$localProtected = file_exists($localHtaccess) && str_contains(file_get_contents($localHtaccess), 'Deny from all');
$checks[] = [
    'label' => 'Access protection: local/',
    'status' => $localProtected,
    'value' => $localProtected ? 'protected' : 'not protected',
    'hint' => $localProtected ? '' : '<code>local/.htaccess</code> should contain <code>Deny from all</code> to prevent direct access to credentials.',
];

// .htaccess protection: core/
$coreHtaccess = $baseFsPath . '/core/.htaccess';
$coreProtected = file_exists($coreHtaccess) && str_contains(file_get_contents($coreHtaccess), 'Deny from all');
$checks[] = [
    'label' => 'Access protection: core/',
    'status' => $coreProtected,
    'value' => $coreProtected ? 'protected' : 'not protected',
    'hint' => $coreProtected ? '' : '<code>core/.htaccess</code> should contain <code>Deny from all</code> to prevent direct access to source files.',
];

// .htpasswd has at least one user
/** @var \Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
$htpasswd = $authentication->getHtpasswd();
$hasUsers = !$htpasswd->isEmpty();
$checks[] = [
    'label' => 'Admin users configured',
    'status' => $hasUsers,
    'value' => $hasUsers ? 'yes' : 'no users',
    'hint' => $hasUsers ? '' : 'No admin users found. Create one via the setup page.',
];

$allOk = true;
$hasWarnings = false;
foreach ($checks as $check) {
    if ($check['status'] === false) $allOk = false;
    if ($check['status'] === null) $hasWarnings = true;
}
?>

<div class="container-fluid max-width-lg">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">System Health Check</h5>
        </div>
        <div class="card-body">
            <?php if ($allOk && !$hasWarnings) { ?>
                <div class="alert alert-success mb-3">All checks passed.</div>
            <?php } elseif (!$allOk) { ?>
                <div class="alert alert-danger mb-3">Some checks failed. See details below.</div>
            <?php } ?>

            <table class="table">
                <thead>
                <tr>
                    <th>Check</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check) { ?>
                    <tr>
                        <td><?= htmlspecialchars($check['label']) ?></td>
                        <td>
                            <?php if ($check['status'] === true) { ?>
                                <span class="text-success">OK</span>
                            <?php } elseif ($check['status'] === false) { ?>
                                <span class="text-danger">FAIL</span>
                            <?php } else { ?>
                                <span class="text-warning">WARN</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($check['value']) ?>
                            <?php if ($check['hint']) { ?>
                                <br><small class="text-muted"><?= $check['hint'] ?></small>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
