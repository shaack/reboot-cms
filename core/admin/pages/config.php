<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

$defaultSite = $admin->getDefaultSite();
$configuration = $request->getParam("configuration");
$configPath = $defaultSite->getFsPath() . "/config.yml";
if ($configuration) {
    file_put_contents($configPath, $configuration);
}
$configFile = file_get_contents($configPath);
$configHasErrors = false;
try {
    $tmpConfig = Yaml::parseFile($configPath);
} catch (ParseException $e) {
    $configHasErrors = true;
}
?>

<div class="container-fluid">
    <h1>Site configuration</h1>
    <!--suppress HtmlUnknownTarget -->
    <form method="post" action="config">
        <div class="form-group">
            <label for="configFile" class="sr-only">Configuration file</label>
            <textarea name="configuration"
                      class="form-control simple-edit <?= $configHasErrors ? "border-danger" : "" ?>" id="configFile"
                      rows="10"><?= $configFile ?></textarea>
        </div>
        <?php if ($configHasErrors) { ?>
            <p class="text-danger">
                Syntax Error in Configuration
            </p>
        <?php } ?>
        <button class="btn btn-primary">Save</button>
        <?= $configuration !== null ? "<span class='ms-2 text-info fade-out'>Configuration saved…</span>" : "" ?>
    </form>
</div>