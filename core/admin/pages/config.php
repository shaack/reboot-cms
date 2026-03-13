<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Shaack\Reboot\CsrfProtection;

$defaultSite = $admin->getDefaultSite();
$configuration = $request->getParam("configuration");
$configPath = $defaultSite->getFsPath() . "/config.yml";
$configSaveError = null;
if ($configuration) {
    CsrfProtection::validate($request);
    // Validate YAML before saving
    try {
        $parsed = Yaml::parse($configuration);
        // Validate addon names contain only safe characters
        if (isset($parsed["addons"]) && is_array($parsed["addons"])) {
            foreach ($parsed["addons"] as $addonName) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $addonName)) {
                    throw new \InvalidArgumentException("Invalid addon name: " . $addonName);
                }
            }
        }
        file_put_contents($configPath, $configuration);
    } catch (\Exception $e) {
        $configSaveError = $e->getMessage();
    }
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
        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
        <div class="form-group">
            <label for="configFile" class="visually-hidden">Configuration file</label>
            <textarea name="configuration"
                      class="mb-3 form-control font-monospace simple-edit <?= $configHasErrors ? "border-danger" : "" ?>" id="configFile"
                      rows="15"><?= $configFile ?></textarea>
        </div>
        <?php if ($configHasErrors) { ?>
            <p class="text-danger">
                Syntax Error in Configuration
            </p>
        <?php } ?>
        <?php if ($configSaveError) { ?>
            <p class="text-danger">
                Configuration not saved: <?= htmlspecialchars($configSaveError) ?>
            </p>
        <?php } ?>
        <button class="btn btn-primary">Save</button>
        <?= $configuration !== null ? "<span class='ms-2 text-info fade-out'>Configuration saved…</span>" : "" ?>
    </form>
</div>