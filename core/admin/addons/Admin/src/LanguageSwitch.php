<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Site;

/**
 * The language switcher above the editor of a page on a multilingual site.
 *
 * A structured page holds every language in one file, the switcher shows the
 * inputs of one language, see `assets/field-form.js`. A Markdown page has a
 * file per language next to it, `about.md` in the default language and
 * `about.de.md` in German, the switcher opens the file of the language or
 * creates it as a copy of the open page.
 */
class LanguageSwitch
{
    /**
     * @param string[] $languages
     * @param ?array $files language => page file like `/about.de.md` or null if there is none,
     *     for a Markdown page, null for a structured page
     * @param ?string $active the language of the open file of a Markdown page
     */
    public static function render(array $languages, string $defaultLanguage, ?array $files, ?string $active = null): string
    {
        $html = '<div class="language-switch" role="group" aria-label="Language of the page">'
            . '<div class="btn-group btn-group-sm">';
        foreach ($languages as $language) {
            $code = htmlspecialchars(strtoupper($language));
            $title = $language === $defaultLanguage ? $language . ", the default language" : $language;
            if ($files === null) {
                $isActive = $language === $defaultLanguage;
                $html .= '<button type="button" class="btn btn-outline-secondary' . ($isActive ? ' active' : '') . '"'
                    . ' data-edit-lang-button="' . htmlspecialchars($language) . '" aria-pressed="' . ($isActive ? 'true' : 'false') . '"'
                    . ' title="' . htmlspecialchars($title) . '">' . $code . '</button>';
            } else if (($files[$language] ?? null) !== null) {
                $isActive = $language === $active;
                $html .= '<a class="btn btn-outline-secondary' . ($isActive ? ' active' : '') . '"'
                    . ' href="pages?page=' . urlencode($files[$language]) . '"' . ($isActive ? ' aria-current="page"' : '')
                    . ' title="' . htmlspecialchars($title . ", " . ltrim($files[$language], "/")) . '">' . $code . '</a>';
            } else {
                $html .= '<button type="button" class="btn btn-outline-secondary language-missing"'
                    . ' onclick="addTranslation(' . htmlspecialchars(json_encode($language)) . ')"'
                    . ' title="' . htmlspecialchars("There is no " . $language . " version yet, create it as a copy of this page") . '">'
                    . $code . AdminHelper::icon("plus") . '</button>';
            }
        }
        return $html . '</div></div>';
    }

    /**
     * The files of a Markdown page in each language, the open one and those next
     * to it. `/about.md` is the default language, `/about.de.md` German, the default
     * language may also be named, `/about.en.md`. Null for a page in the deprecated
     * folder per language, `/de/about.md`, which has no versions next to it.
     * @return ?array language => page file or null
     */
    public static function markdownFiles(Site $site, string $pagesDir, string $pageFile): ?array
    {
        $languages = $site->getLanguages();
        $firstSegment = explode("/", ltrim($pageFile, "/"))[0];
        if (!str_ends_with($pageFile, ".md") || (str_contains(ltrim($pageFile, "/"), "/") && in_array($firstSegment, $languages, true))) {
            return null;
        }
        $base = self::baseName($site, $pageFile);
        $files = [];
        foreach ($languages as $language) {
            $file = $base . "." . $language . ".md";
            if ($language === $site->getDefaultLanguage() && !is_file($pagesDir . $file)) {
                $file = $base . ".md";
            }
            $files[$language] = is_file($pagesDir . $file) ? $file : null;
        }
        return $files;
    }

    /**
     * A page file without its language and extension, `/about.de.md` is `/about`.
     */
    public static function baseName(Site $site, string $pageFile): string
    {
        $name = preg_replace('/\.(md|yml)$/', '', $pageFile);
        if (preg_match('/^(.*)\.([^.\/]+)$/', $name, $matches) && in_array($matches[2], $site->getLanguages(), true)) {
            return $matches[1];
        }
        return $name;
    }
}
