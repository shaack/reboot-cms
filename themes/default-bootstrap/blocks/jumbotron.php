<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/** @var \Shaack\Reboot\Block $this */
?>
<section class="block block-jumbotron">
    <div class="container">
        <div class="jumbotron">
            <h1 class="display-4"><?= $this->query("/h1[1]/text()") ?></h1>
            <p class="lead"><?= $this->query("/p[1]/text()") ?></p>
            <hr class="my-4">
            <?= $this->query("/hr[1]/following-sibling::*") ?>
            <p>
                <a class="btn btn-primary btn-lg" href="<?= $this->query("(//a)[1]/@href") ?>"
                   role="button"><?= $this->query("(//a)[1]/text()") ?></a>
            </p>
        </div>
    </div>
</section>