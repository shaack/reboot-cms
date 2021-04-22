<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\SiteExtension $site */
/** @var \Shaack\Reboot\Request $request */
?>

<div class="container-fluid">
    <h1>Site configuration</h1>
    <!--suppress HtmlUnknownTarget -->
    <form method="post" action="/admin/config">
        <div class="form-group">
            <label for="configFile" class="sr-only">Configuration file</label>
            <textarea class="form-control simple-edit" id="configFile" rows="20"></textarea>
        </div>
    </form>
</div>