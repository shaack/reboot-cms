<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\SiteExtension $site */
/** @var \Shaack\Reboot\Request $request */

$defaultSite = $site->getDefaultSite();
$configuration = $request->getParam("configuration");
$configPath = $defaultSite->getFsPath() . "/config.yml";
if($configuration) {
    file_put_contents($configPath, $configuration);
}
$configFile = file_get_contents($configPath);
?>

<div class="container-fluid">
    <h1>Site configuration</h1>
    <!--suppress HtmlUnknownTarget -->
    <form method="post" action="config">
        <div class="form-group">
            <label for="configFile" class="sr-only">Configuration file</label>
            <textarea name="configuration" class="form-control simple-edit" id="configFile"
                      rows="10"><?= $configFile ?></textarea>
        </div>
        <button class="btn btn-primary">Save</button>
        <?= $configuration !== null ? "<span class='ml-2 text-info fade-out'>Configuration saved…</span>" : "" ?>
    </form>
</div>