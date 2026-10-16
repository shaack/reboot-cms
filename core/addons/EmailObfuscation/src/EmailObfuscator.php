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

    /**
     * Obfuscate every `mailto:` link and every address in the visible text
     * of a page. Links are handled first, so their label is hidden together
     * with the link and not a second time as plain text.
     */
    public static function obfuscatePage(string $html): string
    {
        $html = self::obfuscateMailtoLinks($html);
        foreach (self::findEmailAddresses($html) as $email) {
            $html = str_replace($email, self::obfuscateHtml($email), $html);
        }
        return $html;
    }

    /**
     * Addresses in the visible text of the page, each once. Markup is
     * stripped first, so an address that only occurs inside an attribute or
     * an already obfuscated script is not reported.
     */
    public static function findEmailAddresses(string $html): array
    {
        $text = preg_replace('/<[^>]*>/', '', $html);
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
        return sprintf("<script>document.write(atob('%s'));</script>", base64_encode($html));
    }
}
