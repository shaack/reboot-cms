<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

use Shaack\Reboot\Blueprint;
use Shaack\Reboot\Collection;

/**
 * The form of a blueprint: one input per field, made for its type. Lists and
 * blocks repeat their fields, their items are added, moved and removed in the
 * browser by `assets/field-form.js`, from a `<template>` per kind of item.
 *
 * The inputs are named like the values, `fields[title]`, `fields[cards][2][caption]`,
 * FieldInput turns them back into values.
 */
class FieldForm
{
    private static int $counter = 0;

    /**
     * @param array $values as written in the file, without defaults
     * @param array $errors field path => message, as Blueprint::validate() returns them
     */
    public static function render(Blueprint $blueprint, array $values, array $errors = [], string $name = "fields", string $path = ""): string
    {
        $html = "";
        foreach ($blueprint->getFields() as $field => $props) {
            $html .= self::field($blueprint, $field, $props, $values[$field] ?? null, $errors, $name . "[" . $field . "]", $path . $field);
        }
        return $html;
    }

    private static function field(Blueprint $blueprint, string $field, array $props, $value, array $errors, string $name, string $path): string
    {
        $id = "field-" . (++self::$counter);
        $error = $errors[$path] ?? null;
        $label = self::e($props["label"]) . (!empty($props["required"]) ? ' <span class="text-danger" title="Required">*</span>' : '');
        $required = !empty($props["required"]) ? " required" : "";
        $invalid = $error ? " is-invalid" : "";
        $default = $props["default"] ?? null;
        $placeholder = is_scalar($default) && !is_bool($default) ? ' placeholder="' . self::e((string)$default) . '"' : "";
        $valueText = is_scalar($value) && !is_bool($value) ? (string)$value : "";
        $attr = ' class="form-control' . $invalid . '" id="' . $id . '" name="' . self::e($name) . '"' . $required;
        switch ($props["type"]) {
            case "list":
                return self::listField($props, is_array($value) ? $value : [], $errors, $name, $path, $label, $error);
            case "blocks":
                return self::blocksField($blueprint, $props, is_array($value) ? $value : [], $errors, $name, $path, $error);
            case "boolean":
                $checked = $value === null ? (bool)$default : (bool)$value;
                return '<div class="mb-3 form-check form-switch">'
                    . '<input type="hidden" name="' . self::e($name) . '" value="0">'
                    . '<input class="form-check-input' . $invalid . '" type="checkbox" role="switch" id="' . $id . '" name="' . self::e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
                    . '<label class="form-check-label" for="' . $id . '">' . $label . '</label>'
                    . self::help($props, $error) . '</div>';
            case "textarea":
                $control = '<textarea' . $attr . ' rows="3"' . $placeholder . '>' . self::e($valueText) . '</textarea>';
                break;
            case "markdown":
                $control = '<textarea class="form-control markdown editor-font' . $invalid . '" id="' . $id . '" name="' . self::e($name) . '" rows="8">'
                    . self::e($valueText) . '</textarea>';
                break;
            case "number":
                $control = '<input type="number" step="any"' . $attr . $placeholder
                    . (isset($props["min"]) ? ' min="' . self::e((string)$props["min"]) . '"' : '')
                    . (isset($props["max"]) ? ' max="' . self::e((string)$props["max"]) . '"' : '')
                    . ' value="' . self::e($valueText) . '">';
                break;
            case "date":
                $control = '<input type="date"' . $attr . ' value="' . self::e($valueText) . '">';
                break;
            case "select":
                $options = '<option value="">' . (is_scalar($default) ? '(' . self::e((string)$default) . ')' : '') . '</option>';
                foreach ((array)($props["options"] ?? []) as $option) {
                    $option = (string)$option;
                    $options .= '<option' . ($option === $valueText ? ' selected' : '') . '>' . self::e($option) . '</option>';
                }
                $control = '<select class="form-select' . $invalid . '" id="' . $id . '" name="' . self::e($name) . '"' . $required . '>' . $options . '</select>';
                break;
            case "entry":
                $control = self::entrySelect($blueprint, $props, $valueText, $id, $name, $invalid . '"' . $required);
                break;
            case "image":
                $control = '<div class="input-group">'
                    . '<input type="text"' . $attr . $placeholder . ' value="' . self::e($valueText) . '" data-image-input>'
                    . '<button type="button" class="btn btn-outline-secondary" data-choose-image>Choose</button>'
                    . '</div>'
                    . '<img class="field-image-preview" alt="" data-image-preview>';
                break;
            default:
                $control = '<input type="text"' . $attr . $placeholder . ' value="' . self::e($valueText) . '">';
        }
        return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . $label . '</label>'
            . $control . self::help($props, $error) . '</div>';
    }

    /**
     * A select of the entries of the collection the field names, by the first field of each entry.
     */
    private static function entrySelect(Blueprint $blueprint, array $props, string $value, string $id, string $name, string $classRest): string
    {
        $options = '<option value=""></option>';
        $found = false;
        $site = $blueprint->getSite();
        $collection = (string)($props["collection"] ?? "");
        if ($site && $collection !== "") {
            $entries = Collection::load($site, $collection, true);
            $titleField = array_key_first($entries->getBlueprint()?->getFields() ?? []);
            foreach ($entries as $entry) {
                $title = $titleField ? (string)($entry->getValues()[$titleField] ?? "") : "";
                $selected = $entry->getSlug() === $value;
                $found = $found || $selected;
                $note = $entry->isDraft() ? " (draft)" : (!$entry->isValid() ? " (not valid)" : "");
                $options .= '<option value="' . self::e($entry->getSlug()) . '"' . ($selected ? ' selected' : '') . '>'
                    . self::e(($title !== "" ? $title : $entry->getSlug()) . $note) . '</option>';
            }
        }
        if ($value !== "" && !$found) {
            $options .= '<option value="' . self::e($value) . '" selected>' . self::e($value . " (missing)") . '</option>';
        }
        return '<select class="form-select' . $classRest . ' id="' . $id . '" name="' . self::e($name) . '">' . $options . '</select>';
    }

    private static function listField(array $props, array $items, array $errors, string $name, string $path, string $label, ?string $error): string
    {
        /** @var Blueprint $itemBlueprint */
        $itemBlueprint = $props["blueprint"];
        $token = "__item" . (++self::$counter) . "__";
        $html = '<fieldset class="mb-3 field-list" data-field-list data-token="' . $token . '">'
            . '<legend class="form-label">' . $label . '</legend>'
            . ($error ? '<div class="invalid-feedback d-block">' . self::e($error) . '</div>' : '')
            . '<div class="field-list-items">';
        foreach (array_values($items) as $i => $item) {
            $html .= self::listItem($itemBlueprint, is_array($item) ? $item : [], $errors, $name . "[" . $i . "]", $path . "." . $i . ".");
        }
        $html .= '</div>'
            . '<template>' . self::listItem($itemBlueprint, [], [], $name . "[" . $token . "]", $path . "." . $token . ".") . '</template>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-list-add>Add ' . self::e(strtolower($props["label"])) . '</button>'
            . (!empty($props["help"]) ? '<div class="form-text">' . self::e((string)$props["help"]) . '</div>' : '')
            . '</fieldset>';
        return $html;
    }

    private static function listItem(Blueprint $blueprint, array $values, array $errors, string $name, string $path): string
    {
        return '<div class="field-list-item" data-list-item>'
            . self::itemTools()
            . self::render($blueprint, $values, $errors, $name, $path)
            . '</div>';
    }

    private static function blocksField(Blueprint $blueprint, array $props, array $blocks, array $errors, string $name, string $path, ?string $error): string
    {
        $types = $blueprint->getSite() ? Blueprint::blockTypes($blueprint->getSite()) : [];
        $allowed = isset($props["allow"]) ? array_intersect_key($types, array_flip((array)$props["allow"])) : $types;
        $token = "__block" . (++self::$counter) . "__";
        $html = '<div class="mb-3 field-blocks" data-field-list data-token="' . $token . '">'
            . ($error ? '<div class="invalid-feedback d-block">' . self::e($error) . '</div>' : '')
            . '<div class="field-list-items">';
        foreach (array_values($blocks) as $i => $block) {
            $block = is_array($block) ? $block : [];
            $html .= self::block($blueprint, (string)($block["type"] ?? ""), $block, $errors, $name . "[" . $i . "]", $path . "." . $i . ".");
        }
        $html .= '</div>';
        foreach ($allowed as $type => $typeBlueprint) {
            $html .= '<template data-block-type="' . self::e($type) . '">'
                . self::block($blueprint, $type, ["type" => $type], [], $name . "[" . $token . "]", $path . "." . $token . ".")
                . '</template>';
        }
        if ($allowed) {
            $options = "";
            foreach ($allowed as $type => $typeBlueprint) {
                $options .= '<option value="' . self::e($type) . '">' . self::e($typeBlueprint->getTitle()) . '</option>';
            }
            $html .= '<div class="field-blocks-add input-group input-group-sm">'
                . '<select class="form-select" data-block-select aria-label="Block type">' . $options . '</select>'
                . '<button type="button" class="btn btn-outline-primary" data-list-add>Add block</button>'
                . '</div>';
        } else {
            $html .= '<p class="text-body-secondary small">There are no block types with a blueprint yet. A block type for structured pages has a template <code>site/blocks/&lt;name&gt;.php</code> and next to it a blueprint <code>&lt;name&gt;.yml</code> with its fields.</p>';
        }
        return $html . '</div>';
    }

    private static function block(Blueprint $pageBlueprint, string $type, array $values, array $errors, string $name, string $path): string
    {
        $blueprint = $type !== "" ? $pageBlueprint->blockBlueprint($type) : null;
        $disabled = !empty($values["disabled"]);
        // values the blueprint does not know travel along unchanged
        $known = $blueprint ? array_keys($blueprint->getFields()) : [];
        $extra = array_diff_key($values, array_flip(array_merge($known, ["type", "disabled"])));
        $blockErrors = array_filter($errors, fn($key) => str_starts_with($key, $path), ARRAY_FILTER_USE_KEY);
        $html = '<div class="field-block' . ($disabled ? ' is-disabled' : '') . ($blockErrors ? ' has-errors' : '') . '" data-list-item>'
            . '<input type="hidden" name="' . self::e($name) . '[_type]" value="' . self::e($type) . '">'
            . '<input type="hidden" name="' . self::e($name) . '[_extra]" value="' . self::e($extra ? json_encode($extra) : "") . '">'
            . '<div class="field-block-head">'
            . '<button type="button" class="field-block-title" data-block-collapse aria-expanded="true">'
            . AdminHelper::icon("chevron", "field-block-chevron")
            . '<strong>' . self::e($blueprint ? $blueprint->getTitle() : $type) . '</strong>'
            . '<span class="field-block-type">' . self::e($type) . '</span></button>'
            . '<label class="field-block-switch form-check form-switch mb-0" title="Show the block on the page">'
            . '<input type="hidden" name="' . self::e($name) . '[_disabled]" value="1">'
            . '<input class="form-check-input" type="checkbox" role="switch" name="' . self::e($name) . '[_disabled]" value="0"' . ($disabled ? '' : ' checked') . ' data-block-enabled>'
            . '<span class="visually-hidden">Show the block</span></label>'
            . self::itemTools()
            . '</div>'
            . '<div class="field-block-body">';
        if ($blueprint) {
            $html .= self::render($blueprint, $values, $errors, $name, $path);
        } else {
            $html .= '<p class="text-danger small mb-0">There is no block type \'' . self::e($type) . '\' with a blueprint in <code>site/blocks/</code>. Its values are kept as they are.</p>';
        }
        return $html . '</div></div>';
    }

    private static function itemTools(): string
    {
        return '<span class="field-item-tools">'
            . '<button type="button" class="btn btn-sm btn-link" data-item-up title="Move up" aria-label="Move up">' . AdminHelper::icon("arrow-up") . '</button>'
            . '<button type="button" class="btn btn-sm btn-link" data-item-down title="Move down" aria-label="Move down">' . AdminHelper::icon("arrow-down") . '</button>'
            . '<button type="button" class="btn btn-sm btn-link text-danger" data-item-remove title="Remove" aria-label="Remove">' . AdminHelper::icon("x") . '</button>'
            . '</span>';
    }

    private static function help(array $props, ?string $error): string
    {
        $html = $error ? '<div class="invalid-feedback d-block">' . self::e($error) . '</div>' : '';
        if (!empty($props["help"])) {
            $html .= '<div class="form-text">' . self::e((string)$props["help"]) . '</div>';
        }
        return $html;
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
