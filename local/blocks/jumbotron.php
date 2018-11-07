<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

/**  @var \Shaack\Reboot\Block $this */
?>
<div class="container">
    <div class="jumbotron">

        <h1 class="display-4"><?php echo($this->value("headline")) ?></h1>
        <p class="lead"><?php echo($this->value("lead")) ?></p>
        <hr class="my-4">
        <?php echo($this->content()) ?>
        <p class="lead">
            <a class="btn btn-primary btn-lg" href="<?php echo($this->value("buttonLink")) ?>"
               role="button"><?php echo($this->value("buttonText")) ?></a>
        </p>
    </div>
</div>