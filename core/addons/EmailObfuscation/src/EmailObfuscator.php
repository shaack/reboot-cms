<?php

namespace Shaack\Reboot\AddOns\EmailObfuscation;

/**
 * Replaces e-mail addresses in rendered HTML with inline scripts that
 * reconstruct them in the browser. Pure functions, no state.
 */
class EmailObfuscator
{
    /** Matches a `mailto:` link including its label. */
    private const MAILTO_LINK = '/<a[^>]*href=["\']mailto:([^"\']*)["\'][^>]*>(.*?)<\/a>/is';

    /** Matches an address in plain text, after tags were stripped. */
    private const EMAIL = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';

    /** Elements whose content is no visible text, an address there stays as it is. */
    private const RAW_TEXT_ELEMENTS = ['script', 'style', 'title', 'textarea', 'template'];

    /**
     * Obfuscate every `mailto:` link and every address in the visible text
     * of a page. Links are handled first, so their label is hidden together
     * with the link and not a second time as plain text. Addresses in
     * attributes, in `<title>` or in scripts are left alone, a script tag
     * there would break the page.
     */
    public static function obfuscatePage(string $html): string
    {
        $html = self::obfuscateMailtoLinks($html);
        $parts = preg_split('/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $rawText = null; // the element whose content is skipped, like "script"
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                // a tag or comment
                if ($rawText !== null) {
                    if (preg_match('/^<\/' . $rawText . '\b/i', $part)) {
                        $rawText = null;
                    }
                } elseif (preg_match('/^<(' . implode('|', self::RAW_TEXT_ELEMENTS) . ')\b/i', $part, $m)) {
                    $rawText = strtolower($m[1]);
                }
            } elseif ($rawText === null && $part !== '') {
                $parts[$i] = preg_replace_callback(self::EMAIL, fn($m) => self::obfuscateHtml($m[0]), $part);
            }
        }
        return implode('', $parts);
    }

    /**
     * Addresses in the visible text of the page, each once. Markup is
     * replaced by a space first, so an address that only occurs inside an
     * attribute or an already obfuscated script is not reported, and text on
     * both sides of a tag does not run together into one address.
     */
    public static function findEmailAddresses(string $html): array
    {
        $text = preg_replace('/<(' . implode('|', self::RAW_TEXT_ELEMENTS) . ')\b.*?<\/\1\s*>/is', ' ', $html);
        $text = preg_replace('/<[^>]*>/', ' ', $text);
        preg_match_all(self::EMAIL, $text, $matches);
        return array_values(array_unique($matches[0]));
    }

    /** Replace every `mailto:` link by a script writing a clean link. */
    public static function obfuscateMailtoLinks(string $html): string
    {
        return preg_replace_callback(self::MAILTO_LINK, function (array $matches): string {
            return self::obfuscateHtml('<a href="mailto:' . $matches[1] . '">' . $matches[2] . '</a>');
        }, $html);
    }

    /** Wrap a snippet of HTML in a script that writes it back into the page. */
    public static function obfuscateHtml(string $html): string
    {
        // atob() gives bytes, TextDecoder turns them back into UTF-8, so a label like "Jörg" stays intact
        return sprintf("<script>document.write(new TextDecoder().decode(Uint8Array.from(atob('%s'),c=>c.charCodeAt(0))));</script>", base64_encode($html));
    }
}
