<?php
/**
 * Sample content for the screenshots, see docs/screenshots.md. It runs inside
 * the throwaway container of docs/capture-screenshots.sh, on copies of local/,
 * site/ and web/media/, never on a real installation.
 *
 * Usage: ADMIN_USER=admin ADMIN_PASSWORD=... php docs/screenshot-seed.php
 */

use Shaack\Reboot\AddOnLoader;
use Shaack\Reboot\AddOns\Admin\BackupHelper;
use Shaack\Reboot\AddOns\Admin\PageHistoryHelper;
use Shaack\Reboot\AddOns\ProtectedAreas\UserStore;
use Shaack\Reboot\AddOns\SecureDownloads\DownloadStore;
use Shaack\Reboot\Reboot;

$base = dirname(__DIR__);
require $base . "/vendor/autoload.php";
foreach ([$base . "/core/addons", $base . "/core/admin/addons"] as $pool) {
    foreach (glob($pool . "/*", GLOB_ONLYDIR) as $dir) {
        AddOnLoader::registerAutoloader(basename($dir), $dir);
    }
}
$password = getenv("ADMIN_PASSWORD") ?: exit("ADMIN_PASSWORD is not set\n");
$host = getenv("SITE_HOST") ?: "www.my-site.test";
$day = 86400;

// Admin accounts: the one that takes the screenshots and two editors
$htpasswd = new \Shaack\Htpasswd("$base/local/.htpasswd");
$htpasswd->addUser(getenv("ADMIN_USER") ?: "admin", $password);
foreach (["anna", "tom"] as $editor) {
    $htpasswd->addUser($editor, bin2hex(random_bytes(12)));
}
file_put_contents("$base/local/roles.yml", "anna: editor\ntom: editor\n");

// Register SecureDownloads, so its admin page exists
$siteConfig = "$base/site/config.yml";
$config = file_get_contents($siteConfig);
if (!str_contains($config, "SecureDownloads")) {
    file_put_contents($siteConfig, preg_replace('/^addons: \[(.*)\]/m', 'addons: [$1, "SecureDownloads"]', $config, 1));
}

// Mail settings, so the protected areas offer login links. Nothing is sent.
file_put_contents("$base/local/config.local.yml", "logLevel: 2\nmail:\n  transport: smtp\n  host: localhost\n  port: 25\n  from: noreply@$host\n");

// Page history: three earlier versions of the start page, days apart
$page = "$base/site/pages/index.md";
$historyDir = "$base/local/history/pages";
$original = file_get_contents($page);
foreach ([12 => 900, 7 => 300, 2 => 0] as $daysAgo => $cut) {
    file_put_contents($page, $cut ? substr($original, 0, -$cut) : $original);
    PageHistoryHelper::savePageSnapshot($page, "$base/site/pages", $historyDir, 50);
    $snapshots = glob("$historyDir/index/*.md");
    $newest = end($snapshots);
    rename($newest, "$historyDir/index/" . date("Y-m-d_H-i-s", time() - $daysAgo * $day - $cut * 7) . ".md");
}
file_put_contents($page, $original);

// Media: a folder of blog images, drawn as SVG gradients
$colors = [
    "speed-matters" => ["#0f172a", "#6366f1", "#ec4899"],
    "flat-files" => ["#111827", "#14b8a6", "#6366f1"],
    "blocks-for-editors" => ["#1e1b4b", "#f59e0b", "#ef4444"],
    "markdown-everywhere" => ["#0b1020", "#a3e635", "#22d3ee"],
    "release-notes" => ["#1c1917", "#f97316", "#a855f7"],
    "agent-workflow" => ["#020617", "#38bdf8", "#4ade80"],
];
@mkdir("$base/web/media/blog", 0775, true);
$i = 0;
foreach ($colors as $name => [$background, $first, $second]) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 1000">'
        . '<defs><radialGradient id="a" cx="25%" cy="30%" r="45%"><stop offset="0" stop-color="' . $first . '"/><stop offset="1" stop-color="' . $first . '" stop-opacity="0"/></radialGradient>'
        . '<radialGradient id="b" cx="75%" cy="70%" r="50%"><stop offset="0" stop-color="' . $second . '"/><stop offset="1" stop-color="' . $second . '" stop-opacity="0"/></radialGradient></defs>'
        . '<rect width="1600" height="1000" fill="' . $background . '"/><rect width="1600" height="1000" fill="url(#a)"/><rect width="1600" height="1000" fill="url(#b)"/></svg>';
    $file = "$base/web/media/blog/$name.svg";
    file_put_contents($file, $svg);
    touch($file, time() - (++$i * 2) * $day);
}

// Protected areas: three customer accounts, one logged in before
$users = new UserStore($base);
$users->addUser("team@acme.example", bin2hex(random_bytes(12)), ["/customers/acme"]);
$users->addUser("info@nordlicht.example", null, ["/customers/nordlicht"]);
$users->addUser("office@" . preg_replace('/^www\./', '', $host), bin2hex(random_bytes(12)), ["/"]);
$users->registerLogin("team@acme.example");

// Secure downloads: three files, the first one fetched twice
$store = new DownloadStore($base);
$first = null;
foreach (["Price list 2026.pdf" => "Prices", "Contract draft ACME.pdf" => null, "Brand assets.zip" => "Logos and fonts"] as $name => $label) {
    $tmp = tempnam(sys_get_temp_dir(), "download");
    file_put_contents($tmp, random_bytes(random_int(200000, 900000)));
    $entry = $store->add(fn(string $target) => rename($tmp, $target), $name, $label);
    $first = $first ?? $entry;
}
$token = $first["token"] ?? array_key_first($store->getEntries());
$store->registerDownload($token, "203.0.113.17");
$store->registerDownload($token, "198.51.100.4");

// Backups from the last weeks
$ref = new \ReflectionClass(Reboot::class);
$reboot = $ref->newInstanceWithoutConstructor();
$ref->getProperty("baseFsPath")->setValue($reboot, $base);
$backupDir = BackupHelper::directory($reboot);
foreach ([21, 9, 2] as $daysAgo) {
    $name = BackupHelper::create($reboot, $host);
    $time = strtotime("-$daysAgo days 09:" . (10 + $daysAgo));
    $dated = "Backup_" . $host . "_" . date("Y-m-d_Hi", $time) . ".zip";
    rename("$backupDir/$name", "$backupDir/$dated");
    touch("$backupDir/$dated", $time);
}

echo "Sample content written\n";
