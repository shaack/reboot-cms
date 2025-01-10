<?php
/**  @var \Shaack\Reboot\Site $site */

// collect all pages in this folder
$filenamesUnfiltered = scandir($site->getFsPath() . "/pages/testpages", SCANDIR_SORT_NONE);
// remove files starting with . and allow only with .md extension
$filenames = array_filter($filenamesUnfiltered, function ($filename) {
    return !str_starts_with($filename, ".") && !($filename == "index.php");
});
// sort alphabetical
natcasesort($filenames);
?>
<div class="container-fluid">
    <h1>Testpages</h1>
    <ul>
        <?php foreach ($filenames as $filename) {
            $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
            ?>
            <li><a href="./testpages/<?= $filenameWithoutExtension ?>"><?= $filename ?></a></li>
        <?php } ?>
    </ul>
</div>
<!--
- [All shipped block types](testpages/all-blocks)
- [tmp](testpages/tmp)
- [cards](testpages/cards)
- [Test unknown block](testpages/unknown-block)
- [Empty page](testpages/empty)
-->