<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\Admin\AdminHelper;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Shaack\Reboot\CsrfProtection;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

$defaultSite = $admin->getDefaultSite();
$configuration = $request->getParam("configuration");
$configPath = $defaultSite->getFsPath() . "/config.yml";
$configSaveError = null;
$configSaved = false;
if ($configuration) {
    $result = AdminHelper::handleAction($request, function() use ($configuration, $configPath) {
        $parsed = Yaml::parse($configuration);
        if (isset($parsed["addons"]) && is_array($parsed["addons"])) {
            foreach ($parsed["addons"] as $addonName) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $addonName)) {
                    throw new \InvalidArgumentException("Invalid addon name: " . $addonName);
                }
            }
        }
        file_put_contents($configPath, $configuration);
        return "Configuration saved";
    });
    $configSaveError = $result['error'];
    $configSaved = !$configSaveError;
}
$configFile = file_get_contents($configPath);
$configHasErrors = false;
try {
    $tmpConfig = Yaml::parseFile($configPath);
} catch (ParseException $e) {
    $configHasErrors = true;
}
?>

<div class="container-fluid max-width-lg">
    <?= AdminHelper::renderStatusMessages($configSaveError, $configSaved ? "Configuration saved" : null) ?>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Site Configuration</h5></div>
        <div class="card-body">
            <!--suppress HtmlUnknownTarget -->
            <form method="post" action="config">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <div class="form-group">
                    <label for="configFile" class="visually-hidden">Configuration file</label>
                    <textarea name="configuration"
                              class="mb-3 form-control editor-font simple-edit <?= $configHasErrors ? "border-danger" : "" ?>" id="configFile"
                              rows="20"><?= htmlspecialchars($configFile) ?></textarea>
                </div>
                <?php if ($configHasErrors) { ?>
                    <p class="text-danger">
                        Syntax Error in Configuration
                    </p>
                <?php } ?>
                <button class="btn btn-sm btn-primary px-3">Save</button>
            </form>
        </div>
    </div>
</div>
