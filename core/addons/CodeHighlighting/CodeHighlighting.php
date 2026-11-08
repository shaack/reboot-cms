<?php

namespace Shaack\Reboot\AddOns;

use Shaack\Reboot\AddOn;
use Shaack\Reboot\AddOns\CodeHighlighting\Highlighter;
use Shaack\Reboot\Request;

/**
 * Built-in AddOn that colors the code blocks of a page, the ones written in
 * Markdown with three backticks. It runs on the server after the page has been
 * rendered, so visitors need no JavaScript and see the colors right away.
 *
 * Enable it by adding `CodeHighlighting` to the `addons` list in
 * `site/config.yml`. There is nothing to configure, the colors can be changed
 * in the style sheet of the site, see the README.
 */
class CodeHighlighting extends AddOn
{
    public function postRender(Request $request, string $content): string
    {
        return Highlighter::highlightPage(parent::postRender($request, $content));
    }
}
