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
        $html = '';
        if ($error) {
            $html .= '<script>statusMessage("' . htmlspecialchars($error, ENT_QUOTES) . '", "text-bg-danger")</script>';
        }
        if ($success) {
            $html .= '<script>statusMessage("' . htmlspecialchars($success, ENT_QUOTES) . '")</script>';
        }
        return $html;
    }

    /**
     * The heading of an admin page. `$note` is shown small next to it, like the
     * folder of an AddOn. With `$help`, a "Help" button follows, which opens the
     * text below the heading. `$help` is markup written in the page itself, it
     * is not escaped.
     */
    public static function renderPageTitle(string $title, string $note = "", string $help = ""): string
    {
        $html = '<div class="admin-page-title">'
            . '<h1>' . htmlspecialchars($title) . '</h1>'
            . ($note !== "" ? '<code>' . htmlspecialchars($note) . '</code>' : '');
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
