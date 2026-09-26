<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns;

use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\Downloads\DownloadStore;
use Shaack\Reboot\Request;

/**
 * Built-in AddOn that hands out files through cryptic, short lived links which
 * are additionally protected by a six digit password. Files are uploaded in the
 * admin under "Downloads"; enable the delivery side by adding `Downloads` to
 * the `addons` list in `site/config.yml`.
 *
 * A link looks like `https://example.com/downloads/3ef938314c4d6cfe.pdf`. The
 * extension is cosmetic, the real file name never appears in the URL. It is
 * only sent in the `Content-Disposition` header, so the visitor still saves the
 * file under its proper name.
 *
 * A link expires one week after the upload, or one hour after the first
 * download, whichever comes first. Within that hour the file may be fetched
 * again, which keeps an interrupted transfer from ruining the link. After
 * expiry the file is deleted from the server, the log entry stays visible in
 * the admin list for a while.
 *
 * Ten wrong passwords lock a token for good, which is what keeps six digits
 * from being guessable.
 *
 * Optional configuration in `site/config.yml`:
 *
 *     downloads:
 *       path: /downloads        # URL prefix of the links
 *       linkLifetime: 604800    # seconds a fresh link stays valid
 *       downloadWindow: 3600    # seconds after the first download
 *       logRetention: 2592000   # seconds an expired entry stays in the list
 *       texts:                  # override the built-in texts per language
 *         de:
 *           title: "Datei herunterladen"
 */
class Downloads extends AddOn
{
    private const TEXTS = [
        'en' => [
            'title' => 'Download',
            'intro' => 'This file is protected. Please enter the six digit password you received.',
            'passwordLabel' => 'Password',
            'submit' => 'Download',
            'wrongPassword' => 'Wrong password.',
            'attemptsLeft' => 'Attempts left: %d',
            'notFoundTitle' => 'Link not available',
            'notFound' => 'This download link does not exist or has expired.',
            'lockedTitle' => 'Link blocked',
            'locked' => 'This link was blocked after too many wrong passwords. Please ask the sender for a new one.',
            'downloadStarted' => 'The download has started.',
        ],
        'de' => [
            'title' => 'Download',
            'intro' => 'Diese Datei ist geschützt. Bitte geben Sie das sechsstellige Passwort ein, das Sie erhalten haben.',
            'passwordLabel' => 'Passwort',
            'submit' => 'Herunterladen',
            'wrongPassword' => 'Falsches Passwort.',
            'attemptsLeft' => 'Verbleibende Versuche: %d',
            'notFoundTitle' => 'Link nicht verfügbar',
            'notFound' => 'Dieser Download-Link existiert nicht oder ist abgelaufen.',
            'lockedTitle' => 'Link gesperrt',
            'locked' => 'Dieser Link wurde nach zu vielen Fehlversuchen gesperrt. Bitte fordern Sie einen neuen Link an.',
            'downloadStarted' => 'Der Download wurde gestartet.',
        ],
    ];

    private array $config = [];
    private string $prefix = '/downloads';
    private array $texts = [];
    private string $language = 'en';

    /**
     * @see AddOn::init()
     */
    protected function init()
    {
        $this->config = $this->site->getConfig()['downloads'] ?? [];
        $this->prefix = '/' . trim((string)($this->config['path'] ?? '/downloads'), '/');
    }

    /**
     * @see AddOn::preRender()
     */
    public function preRender(Request $request): bool
    {
        $path = $request->getPathWithoutLanguage();
        if ($path !== $this->prefix && !str_starts_with($path, $this->prefix . '/')) {
            return true;
        }
        $this->language = $request->getLanguage();
        $this->texts = $this->textsFor($this->language);
        $store = new DownloadStore($this->reboot->getBaseFsPath(), $this->config);
        $store->purge();

        $token = $this->extractToken($path);
        $entry = $token ? $store->find($token) : null;
        if (!$entry) {
            Logger::info('Downloads: unknown token for path ' . $path);
            $this->respondMessage(404, $this->texts['notFoundTitle'], $this->texts['notFound']);
        }
        if ($store->isLocked($entry)) {
            Logger::info('Downloads: locked token ' . $entry['token']);
            $this->respondMessage(403, $this->texts['lockedTitle'], $this->texts['locked']);
        }
        if (!$store->isAvailable($entry)) {
            Logger::info('Downloads: expired token ' . $entry['token']);
            $this->respondMessage(410, $this->texts['notFoundTitle'], $this->texts['notFound']);
        }

        $password = $request->getParam('download_password', 'post');
        if ($password !== null) {
            if ($store->verifyPassword($entry['token'], (string)$password)) {
                $store->registerDownload($entry['token'], $_SERVER['REMOTE_ADDR'] ?? null);
                Logger::info('Downloads: delivering ' . $entry['token']);
                $this->sendFile($store->getFilePath($entry['token']), $entry['fileName']);
            }
            // Slow down scripted guessing a little.
            usleep(500000);
            $entry = $store->find($entry['token']) ?? $entry;
            if ($store->isLocked($entry)) {
                $this->respondMessage(403, $this->texts['lockedTitle'], $this->texts['locked']);
            }
            $this->respondForm($entry, $store, true);
        }
        $this->respondForm($entry, $store, false);
        return false; // not reached, respondForm() exits
    }

    // Internals

    /**
     * The token from a request path, or null if the path carries none.
     */
    private function extractToken(string $path): ?string
    {
        $rest = ltrim(substr($path, strlen($this->prefix)), '/');
        if ($rest === '' || str_contains($rest, '/')) {
            return null;
        }
        $token = pathinfo($rest, PATHINFO_FILENAME);
        return DownloadStore::isValidToken($token) ? $token : null;
    }

    private function textsFor(string $language): array
    {
        $texts = self::TEXTS[$language] ?? self::TEXTS['en'];
        $configured = $this->config['texts'][$language] ?? [];
        return is_array($configured) ? array_merge($texts, $configured) : $texts;
    }

    /**
     * Sends the file as an attachment under its real name and exits.
     */
    private function sendFile(string $path, string $fileName): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        // An ASCII fallback for old clients, the UTF-8 variant for the rest.
        $asciiName = preg_replace('/[^\x20-\x7e]/', '_', $fileName);
        $asciiName = str_replace(['"', '\\'], '_', $asciiName);
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $asciiName . '"; '
            . "filename*=UTF-8''" . rawurlencode($fileName));
        header('Content-Transfer-Encoding: binary');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        $this->sendNoCacheHeaders();
        readfile($path);
        exit;
    }

    /**
     * Renders the password form and exits.
     */
    private function respondForm(array $entry, DownloadStore $store, bool $wrongPassword): void
    {
        $attemptsLeft = DownloadStore::MAX_FAILED_ATTEMPTS - ($entry['failedAttempts'] ?? 0);
        $body = '';
        if ($entry['label']) {
            $body .= '<p class="label">' . htmlspecialchars($entry['label']) . '</p>';
        }
        $body .= '<p>' . htmlspecialchars($this->texts['intro']) . '</p>';
        $body .= '<p class="meta">' . htmlspecialchars(DownloadStore::formatSize((int)$entry['size'])) . '</p>';
        if ($wrongPassword) {
            $body .= '<p class="error">' . htmlspecialchars($this->texts['wrongPassword']) . ' '
                . htmlspecialchars(sprintf($this->texts['attemptsLeft'], max(0, $attemptsLeft))) . '</p>';
        }
        $body .= '<form method="post">'
            . '<label for="download_password">' . htmlspecialchars($this->texts['passwordLabel']) . '</label>'
            . '<input type="text" id="download_password" name="download_password" inputmode="numeric" '
            . 'autocomplete="off" pattern="[0-9 ]*" maxlength="9" required autofocus>'
            . '<button type="submit">' . htmlspecialchars($this->texts['submit']) . '</button>'
            . '</form>';
        $this->respondPage(200, $this->texts['title'], $body);
    }

    /**
     * Renders a short message page and exits.
     */
    private function respondMessage(int $status, string $title, string $message): void
    {
        $this->respondPage($status, $title, '<p>' . htmlspecialchars($message) . '</p>');
    }

    /**
     * A self contained page, independent of the site's template, so a download
     * link works the same in every Reboot CMS project.
     */
    private function respondPage(int $status, string $title, string $bodyHtml): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        $this->sendNoCacheHeaders();
        $language = htmlspecialchars($this->language);
        $title = htmlspecialchars($title);
        echo <<<HTML
<!doctype html>
<html lang="$language">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>$title</title>
    <style>
        :root { color-scheme: light dark; --bg: #f4f4f5; --card: #ffffff; --fg: #1d1d1f; --muted: #6b6b70; --line: #d8d8dc; --accent: #0d6efd; --error: #b02a37; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #16161a; --card: #1f1f24; --fg: #ececf1; --muted: #9a9aa2; --line: #34343c; --accent: #6ea8fe; --error: #ea868f; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
            background: var(--bg); color: var(--fg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.55; }
        main { width: 100%; max-width: 26rem; background: var(--card); border: 1px solid var(--line); border-radius: 0.75rem; padding: 1.75rem; }
        h1 { margin: 0 0 1rem; font-size: 1.35rem; }
        p { margin: 0 0 1rem; }
        .label { font-weight: 600; }
        .meta, footer { color: var(--muted); font-size: 0.875rem; }
        .error { color: var(--error); }
        label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; color: var(--muted); }
        input { width: 100%; padding: 0.6rem 0.75rem; font-size: 1.25rem; letter-spacing: 0.2em; text-align: center;
            border: 1px solid var(--line); border-radius: 0.5rem; background: var(--bg); color: var(--fg); }
        input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        button { width: 100%; margin-top: 1rem; padding: 0.65rem 1rem; font-size: 1rem; border: 0; border-radius: 0.5rem;
            background: var(--accent); color: #fff; cursor: pointer; }
        button:hover { filter: brightness(1.08); }
    </style>
</head>
<body>
<main>
    <h1>$title</h1>
    $bodyHtml
</main>
</body>
</html>

HTML;
        exit;
    }

    private function sendNoCacheHeaders(): void
    {
        // web/.htaccess sets a generous default expiry, a download must never be cached.
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
