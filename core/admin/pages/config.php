<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\AddOnLoader;
use Shaack\Reboot\AddOns\Admin\AdminHelper;
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
    $result = AdminHelper::handleAction($request, function() use ($configuration, $configPath, $reboot, $defaultSite) {
        $parsed = Yaml::parse($configuration);
        if (isset($parsed["addons"]) && is_array($parsed["addons"])) {
            foreach ($parsed["addons"] as $addonName) {
                $addonName = (string)$addonName;
                if (!AddOnLoader::isValidName($addonName)) {
                    throw new \InvalidArgumentException("Invalid addon name: " . $addonName);
                }
                if (AddOnLoader::locate($reboot->getBaseFsPath(), $defaultSite->getFsPath(), $addonName) === null) {
                    throw new \InvalidArgumentException("AddOn not found: " . $addonName
                        . ". Expected site/addons/$addonName/$addonName.php or core/addons/$addonName/$addonName.php.");
                }
            }
        }
        // Browsers submit textarea content with CRLF line endings, the file is kept with LF
        file_put_contents($configPath, str_replace("\r\n", "\n", $configuration));
        return "Configuration saved";
    });
    $configSaveError = $result['error'];
    $configSaved = !$configSaveError;
}
$configFile = file_get_contents($configPath);
$configHasErrors = false;
$missingAddOns = [];
try {
    $tmpConfig = Yaml::parseFile($configPath);
    // AddOns listed in the file that do not exist, e.g. after editing it by hand. Checked
    // against the file, not the loaded site, so the hint is current right after saving.
    foreach ((array)($tmpConfig["addons"] ?? []) as $addonName) {
        $addonName = (string)$addonName;
        if (!AddOnLoader::isValidName($addonName)
            || AddOnLoader::locate($reboot->getBaseFsPath(), $defaultSite->getFsPath(), $addonName) === null) {
            $missingAddOns[] = $addonName;
        }
    }
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
                <?php if ($missingAddOns) { ?>
                    <p class="text-danger">
                        AddOn not found: <?= htmlspecialchars(implode(', ', $missingAddOns)) ?>.
                        An AddOn lives in <code>site/addons/Name/Name.php</code> or <code>core/addons/Name/Name.php</code>, remove the entry or check the spelling.
                    </p>
                <?php } ?>
                <button class="btn btn-sm btn-primary px-3">Save</button>
            </form>
        </div>
    </div>
</div>
