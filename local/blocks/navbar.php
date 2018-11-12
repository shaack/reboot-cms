<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

global $reboot;
$config = $reboot->config['navbar'];
?>

<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="/"><?php echo $config["brand"] ?></a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar"
            aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbar">
        <ul class="navbar-nav mr-auto">
            <?php
            $structure = $config['structure'];
            foreach ($structure as $label => $path) {
                ?>
                <li class="nav-item <?= $reboot->uri == $path ? "active" : "" ?>">
                    <a class="nav-link" href="<?= $path ?>"><?= $label ?></a>
                </li>
                <?php
            }
            ?>
        </ul>
    </div>
</nav>
