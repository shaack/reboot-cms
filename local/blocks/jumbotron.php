<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Slice $slice */
?>
<div class="jumbotron">
    <h1 class="display-4"><?php $slice->config("headline") ?></h1>
    <p class="lead"><?php $slice->config("lead") ?></p>
    <hr class="my-4">
    <?php $slice->content() ?>
    <p class="lead">
        <a class="btn btn-primary btn-lg" href="#" role="button"><?php $slice->config("buttonText") ?></a>
    </p>
</div>