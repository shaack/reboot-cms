<?php
/** @var \Shaack\Reboot\Site $site */

use Symfony\Component\Yaml\Yaml;

// The blog index is built from the folder. A post is a Markdown file named
// YYYY-MM-DD-slug.md, the date comes from the file name, title and
// description from the frontmatter. A new post shows up here on its own.
$blogDir = $site->getFsPath() . "/pages/blog";
$posts = [];
foreach (scandir($blogDir) ?: [] as $file) {
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)\.md$/', $file, $m)) {
        continue;
    }
    $raw = (string)file_get_contents($blogDir . "/" . $file);
    $meta = [];
    if (preg_match('/^---\s*\n(.*?)\n---/s', $raw, $frontmatter)) {
        $meta = Yaml::parse($frontmatter[1]) ?? [];
    }
    if (!empty($meta["draft"])) {
        continue;
    }
    $posts[] = [
        "date" => $m[1],
        "slug" => substr($file, 0, -3),
        "title" => $meta["title"] ?? str_replace("-", " ", $m[2]),
        "description" => $meta["description"] ?? "",
    ];
}
// newest first, the slug starts with the ISO date
usort($posts, fn($a, $b) => strcmp($b["slug"], $a["slug"]));
?>
<section class="block block-text">
    <div class="container-fluid">
        <h1>Blog</h1>
        <p class="lead blog-lead">Notes on running websites with Reboot CMS. Every post is a Markdown file in
            <code>site/pages/blog/</code>, and this list is built from that folder.</p>
        <div class="blog-list">
            <?php foreach ($posts as $post) { ?>
                <article class="blog-teaser">
                    <time datetime="<?= $post["date"] ?>"><?= date("F j, Y", strtotime($post["date"])) ?></time>
                    <h2><a href="blog/<?= htmlspecialchars($post["slug"]) ?>"><?= htmlspecialchars($post["title"]) ?></a></h2>
                    <?php if ($post["description"]) { ?>
                        <p><?= htmlspecialchars($post["description"]) ?></p>
                    <?php } ?>
                </article>
            <?php } ?>
        </div>
    </div>
</section>
