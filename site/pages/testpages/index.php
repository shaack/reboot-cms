<?php
/**  @var \Shaack\Reboot\Site $site */

// The pages in this folder are edge cases used during development, see the
// "Blocks" page for the shipped block types. The list is built from the folder,
// so a new test page shows up here on its own.
$filenamesUnfiltered = scandir($site->getFsPath() . "/pages/testpages", SCANDIR_SORT_NONE);
$filenames = array_filter($filenamesUnfiltered, function ($filename) {
    return !str_starts_with($filename, ".") && $filename !== "index.php";
});
natcasesort($filenames);
?>
<section class="block block-text">
    <div class="container-fluid">
        <h1>Test pages</h1>
        <p>Edge cases for development. The shipped block types are shown on the <a href="blocks">Blocks</a> page.</p>
        <ul>
            <?php foreach ($filenames as $filename) {
                $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
                ?>
                <li><a href="./testpages/<?= $filenameWithoutExtension ?>"><?= $filename ?></a></li>
            <?php } ?>
        </ul>
    </div>
</section>
