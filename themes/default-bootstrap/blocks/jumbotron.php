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
            <h1 class="display-4"><?= $this->value("/html/body/hr[1]/preceding::h1/text()") ?></h1>
            <p class="lead"><?= $this->value("/html/body/hr[1]/preceding::p/text()") ?></p>
            <hr class="my-4">
            <?= $this->value("/html/body/hr[1]/following::*") ?>
            <p>
                <a class="btn btn-primary btn-lg" href="<?= $this->part(2)->value("/html/body/hr[1]/preceding::a[1]/@href") ?>"
                   role="button"><?= $this->part(2)->value("/html/body/hr[1]/preceding::a[1]/text()") ?></a>
            </p>
        </div>
    </div>
</section>
<!--
TODO
replace "/part(1)/" with
"/hr[1]/preceding-sibling::*/"

replace "/part(2)/" with
"/hr[1]/following-sibling::*/hr[2]/preceding-sibling::*/"

...

so:
"/hr[1]/preceding-sibling::*//a[1]/@href"
=> "/part(1)//a[1]/@href"

"/h1[1]/text()" => "/part(1)/h1/text()"
"/p[1]/text()" => "/part(1)/p/text()"

query => queryValue()
+ queryList() zum iterieren

-->