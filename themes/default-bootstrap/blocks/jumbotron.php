<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

// $this->fields(["headline" => "text", "lead" => "text", "buttonLink" => "text", "buttonText" => "text", "content" => "markdown"]);

/**  @var \Shaack\Reboot\Block $this
 */
?>
<div class="container">
    <div class="jumbotron">
        <h1 class="display-4"><?= $this->value("headline") ?></h1>
        <p class="lead"><?= $this->value("lead") ?></p>
        <hr class="my-4">
        <?= $this->content() ?>
        <p class="lead">
            <a class="btn btn-primary btn-lg" href="<?= $this->value("buttonLink") ?>"
               role="button"><?= $this->value("buttonText") ?></a>
        </p>
    </div>
</div>
