<?php

namespace Shaack\Reboot\AddOns;

use Shaack\Reboot\AddOn;
use Shaack\Reboot\Request;

/**
 * Built-in AddOn that sends visitors to the language version matching their
 * preference. Enable it by adding `LanguageRedirect` to the `addons` list in
 * `site/config.yml`; it does nothing unless the site is multilingual (two or
 * more `languages` configured).
 *
 * On the homepage the visitor is redirected to their preferred language. An
 * explicit choice stored in the `lang` cookie wins; without a cookie the
 * browser's Accept-Language header decides. A visitor whose preference is the
 * default language is not redirected. Because the decision is taken on every
 * visit, a visitor whose browser prefers a non-default language consistently
 * lands on that language's homepage.
 *
 * The `lang` cookie only ever records an *explicit* choice. Language switcher
 * links must carry a `?setlang=xx` marker; when the addon sees it, it stores
 * the choice and redirects to the same page without the marker. That is what
 * keeps the default-language homepage reachable: clicking the switcher stores
 * the choice, so the following request to `/` is no longer auto-redirected.
 */
class LanguageRedirect extends AddOn
{
    public function preRender(Request $request): bool
    {
        $config = $this->site->getConfig();
        $languages = $config['languages'] ?? [];
        if (count($languages) < 2) {
            return true; // monolingual site, nothing to do
        }
        $defaultLanguage = $config['defaultLanguage'] ?? $languages[0];

        // Explicit choice via a language switcher (?setlang=xx): store it
        // and redirect to the same page without the marker.
        $setLang = $request->getParam('setlang');
        if (is_string($setLang) && in_array($setLang, $languages, true)) {
            $this->storePreference($setLang);
            $this->reboot->redirect($this->site->getWebPath() . $request->getPath());
            return false; // redirect() exits
        }

        // On the homepage, send the visitor to their preferred language.
        if ($request->getPath() === '/') {
            $preferred = $this->preferredLanguage($languages, $defaultLanguage);
            if ($preferred !== $defaultLanguage) {
                $this->reboot->redirect($this->site->getWebPath() . '/' . $preferred);
                return false;
            }
        }
        return true;
    }

    /**
     * The visitor's preferred language. An explicit choice from the `lang`
     * cookie wins, otherwise it is derived from the Accept-Language header.
     */
    private function preferredLanguage(array $languages, string $defaultLanguage): string
    {
        $cookie = $_COOKIE['lang'] ?? null;
        if (is_string($cookie) && in_array($cookie, $languages, true)) {
            return $cookie;
        }
        return $this->detectBrowserLanguage($languages, $defaultLanguage);
    }

    /**
     * Pick the visitor's most preferred language that the site offers, based
     * on the Accept-Language header. Falls back to the default language.
     */
    private function detectBrowserLanguage(array $languages, string $defaultLanguage): string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($header === '') {
            return $defaultLanguage;
        }
        $best = null;
        $bestQuality = -1.0;
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $quality = 1.0;
            if (preg_match('/;\s*q\s*=\s*([0-9.]+)/i', $part, $m)) {
                $quality = (float)$m[1];
            }
            $tag = strtolower(trim(explode(';', $part)[0]));
            $primary = explode('-', $tag)[0]; // "de-DE" -> "de"
            if (in_array($primary, $languages, true) && $quality > $bestQuality) {
                $best = $primary;
                $bestQuality = $quality;
            }
        }
        return $best ?? $defaultLanguage;
    }

    /** Store the visitor's explicit language choice in a long-lived cookie. */
    private function storePreference(string $language): void
    {
        if (!headers_sent()) {
            setcookie('lang', $language, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'samesite' => 'Lax',
                'httponly' => true,
            ]);
        }
        $_COOKIE['lang'] = $language;
    }
}
