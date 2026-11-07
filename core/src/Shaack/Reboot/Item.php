<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

/**
 * One item of a `list` field, for example a card with image and caption.
 * See FieldAccess for its accessors.
 */
class Item
{
    use FieldAccess;

    private ?Site $site;

    public function __construct(array $values, ?Blueprint $blueprint = null, ?Site $site = null)
    {
        $this->fieldValues = $values;
        $this->fieldBlueprint = $blueprint;
        $this->site = $site;
    }

    protected function fieldSite(): ?Site
    {
        return $this->site;
    }
}
