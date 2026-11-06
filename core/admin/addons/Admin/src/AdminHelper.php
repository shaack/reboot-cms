<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Request;
use Shaack\Reboot\Site;
use Shaack\Reboot\Reboot;

class AdminHelper
{
    // Icons from Bootstrap Icons (MIT), the paths of a 16x16 viewBox
    private const ICONS = [
        'file' => '<path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/>',
        'house' => '<path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z"/>',
        'folder' => '<path d="M.54 3.87.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.826a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31zM2.19 4a1 1 0 0 0-.996 1.09l.637 7a1 1 0 0 0 .995.91h10.348a1 1 0 0 0 .995-.91l.637-7A1 1 0 0 0 13.81 4zm4.69-1.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139q.323-.119.684-.12h5.396z"/>',
        'chevron' => '<path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/>',
        'chevron-left' => '<path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/>',
        'file-plus' => '<path d="M8 6.5a.5.5 0 0 1 .5.5v1.5H10a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V9.5H6a.5.5 0 0 1 0-1h1.5V7a.5.5 0 0 1 .5-.5"/><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/>',
        'folder-plus' => '<path d="m.5 3 .04.87a2 2 0 0 0-.342 1.311l.637 7A2 2 0 0 0 2.826 14H9v-1H2.826a1 1 0 0 1-.995-.91l-.637-7A1 1 0 0 1 2.19 4h11.62a1 1 0 0 1 .996 1.09L14.54 8h1.005l.256-2.819A2 2 0 0 0 13.81 3H9.828a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 6.172 1H2.5a2 2 0 0 0-2 2m5.672-1a1 1 0 0 1 .707.293L7.586 3H2.19q-.362.002-.683.12L1.5 2.98a1 1 0 0 1 1-.98z"/><path d="M13.5 9a.5.5 0 0 1 .5.5V11h1.5a.5.5 0 1 1 0 1H14v1.5a.5.5 0 1 1-1 0V12h-1.5a.5.5 0 0 1 0-1H13V9.5a.5.5 0 0 1 .5-.5"/>',
        'sidebar' => '<path d="M0 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm5-1v12h9a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1zM4 2H2a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h2z"/>',
        'arrow-up' => '<path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5"/>',
        'arrow-down' => '<path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1"/>',
        'toggle-on' => '<path d="M5 3a5 5 0 0 0 0 10h6a5 5 0 0 0 0-10zm6 9a4 4 0 1 1 0-8 4 4 0 0 1 0 8"/>',
        'toggle-off' => '<path d="M11 4a4 4 0 0 1 0 8H8a5 5 0 0 0 2-4 5 5 0 0 0-2-4zm-6 8a4 4 0 1 1 0-8 4 4 0 0 1 0 8M0 8a5 5 0 0 0 5 5h6a5 5 0 0 0 0-10H5a5 5 0 0 0-5 5"/>',
        'external' => '<path d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/>',
    ];

    /**
     * An inline SVG icon in the size and color of the text.
     */
    public static function icon(string $name, string $class = ""): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16"'
            . ' class="icon' . ($class !== "" ? ' ' . htmlspecialchars($class) : '') . '" aria-hidden="true">'
            . self::ICONS[$name] . '</svg>';
    }

    /**
     * Require admin role. Redirects non-admins to /pages.
     */
    public static function requireAdmin(Site $site, Reboot $reboot): bool
    {
        $authentication = $site->getAddOn("Authentication");
        if (!$authentication->isAdmin()) {
            $reboot->redirect($site->getWebPath() . "/pages");
            return false;
        }
        return true;
    }

    /**
     * Render status message script tags for error and/or success.
     */
    public static function renderStatusMessages(?string $error, ?string $success): string
    {
        // the toast shows the body as HTML, so escaped for HTML, then written as a JS string
        $html = '';
        if ($error) {
            $html .= '<script>statusMessage(' . self::jsValue(htmlspecialchars($error, ENT_QUOTES)) . ', "text-bg-danger")</script>';
        }
        if ($success) {
            $html .= '<script>statusMessage(' . self::jsValue(htmlspecialchars($success, ENT_QUOTES)) . ')</script>';
        }
        return $html;
    }

    /**
     * A value as a JavaScript literal, safe inside a `<script>` element. Quotes,
     * `<`, `>` and `&` are written as unicode escapes, so nothing ends the
     * string or the script.
     */
    public static function jsValue($value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A value as a JavaScript literal for an event handler attribute like
     * `onclick="deleteFolder(<?= AdminHelper::jsArg($name) ?>)"`. The browser
     * decodes HTML entities before it runs the handler, so escaping for HTML
     * alone would let an apostrophe in a file name end the string.
     */
    public static function jsArg($value): string
    {
        return htmlspecialchars(self::jsValue($value), ENT_QUOTES);
    }

    /**
     * The heading of an admin page. `$note` is shown small next to it, like the
     * folder of an AddOn. With `$help`, a "Help" button follows, which opens the
     * text below the heading. `$help` is markup written in the page itself, it
     * is not escaped.
     */
    public static function renderPageTitle(string $title, string $note = "", string $help = "", string $extraHtml = ""): string
    {
        $html = '<div class="admin-page-title">'
            . '<h1>' . htmlspecialchars($title) . '</h1>'
            . ($note !== "" ? '<code>' . htmlspecialchars($note) . '</code>' : '')
            . $extraHtml;
        if ($help !== "") {
            $html .= '<button type="button" class="admin-help-toggle" data-bs-toggle="collapse"'
                . ' data-bs-target="#admin-help" aria-expanded="false" aria-controls="admin-help">Help</button>';
        }
        $html .= '</div>';
        if ($help !== "") {
            $html .= '<div class="collapse" id="admin-help"><div class="admin-help-body">' . $help . '</div></div>';
        }
        return $html;
    }

    /**
     * Validate CSRF token and execute an action callback within a try/catch.
     * Returns ['error' => ?string, 'success' => ?string] plus any extra keys from the callback.
     * The callback should return a success message string or an array with 'success' and extra keys.
     */
    public static function handleAction(Request $request, callable $callback): array
    {
        try {
            CsrfProtection::validate($request);
            $result = $callback();
            if (is_string($result)) {
                return ['error' => null, 'success' => $result];
            }
            return array_merge(['error' => null, 'success' => null], $result);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'success' => null];
        }
    }

    /**
     * Send a JSON response and exit.
     */
    public static function jsonResponse(mixed $data): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
