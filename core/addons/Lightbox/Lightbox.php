<?php

namespace Shaack\Reboot\AddOns;

use Shaack\Reboot\AddOn;
use Shaack\Reboot\Request;

/**
 * Built-in AddOn that opens linked images enlarged in a dialog. A link takes
 * part with the attribute `data-lightbox`, links with the same value form a
 * group that can be paged:
 *
 *     <a href="large.jpg" data-lightbox="screenshots" data-caption="The editor"><img src="small.jpg" alt=""></a>
 *
 * Enable it by adding `Lightbox` to the `addons` list in `site/config.yml`.
 * It adds a small style and script to pages with such links only. The colors
 * can be changed in the style sheet of the site, see the README.
 */
class Lightbox extends AddOn
{
    public function postRender(Request $request, string $content): string
    {
        return self::enhancePage(parent::postRender($request, $content));
    }

    /**
     * The page with the style and script of the lightbox before `</body>`,
     * unchanged if no link has the attribute `data-lightbox`.
     */
    public static function enhancePage(string $html): string
    {
        if (!preg_match('/<a\b[^>]*\sdata-lightbox\b/i', $html)) {
            return $html;
        }
        $dir = __DIR__ . '/assets';
        $assets = "<style>\n" . file_get_contents($dir . '/lightbox.css') . "</style>\n"
            . "<script>\n" . file_get_contents($dir . '/lightbox.js') . "</script>\n";
        $pos = strripos($html, '</body>');
        return $pos === false ? $html . $assets : substr_replace($html, $assets, $pos, 0);
    }
}
