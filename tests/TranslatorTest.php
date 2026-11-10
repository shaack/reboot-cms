<?php

namespace Shaack\Tests;

use Shaack\Reboot\AddOns\Admin\Translator;

/**
 * The machine translation of fields with DeepL, here with a fake DeepL that
 * swaps a few English words for German ones and leaves the tags alone.
 */
class TranslatorTest
{
    private array $sent = [];
    private array $requests = [];

    private function translator(int $status = 200, string $key = "abc:fx"): Translator
    {
        $this->sent = [];
        $this->requests = [];
        $words = ["Hello" => "Hallo", "world" => "Welt", "page" => "Seite", "the" => "die", "link" => "Verweis", "bold" => "fett"];
        return new Translator($key, function (string $url, array $headers, string $body) use ($status, $words) {
            $data = json_decode($body, true);
            $this->requests[] = ["url" => $url, "headers" => $headers, "data" => $data];
            array_push($this->sent, ...$data["text"]);
            $translations = array_map(fn($text) => ["text" => strtr($text, $words)], $data["text"]);
            return [$status, json_encode(["translations" => $translations])];
        });
    }

    public function testPlainTextLineByLine(): void
    {
        $result = $this->translator()->translate(["Hello world\n\nthe page"], "en", "de");
        Assert::equals(["Hallo Welt\n\ndie Seite"], $result);
        Assert::equals(["Hello world", "the page"], $this->sent, "empty lines are not sent");
    }

    public function testRequestToDeepL(): void
    {
        $this->translator()->translate(["Hello"], "en", "de");
        $request = $this->requests[0];
        Assert::equals("https://api-free.deepl.com/v2/translate", $request["url"], "a key ending in :fx is one of API Free");
        Assert::true(in_array("Authorization: DeepL-Auth-Key abc:fx", $request["headers"], true));
        Assert::equals("EN", $request["data"]["source_lang"]);
        Assert::equals("DE", $request["data"]["target_lang"]);
        Assert::equals("xml", $request["data"]["tag_handling"]);
        $this->translator(200, "pro-key")->translate(["Hello"], "de", "en");
        Assert::equals("https://api.deepl.com/v2/translate", $this->requests[0]["url"]);
        Assert::equals("EN-US", $this->requests[0]["data"]["target_lang"], "DeepL wants English with its variant");
    }

    public function testMarkdownKeepsItsMarks(): void
    {
        $markdown = "## Hello world\n\n- the **bold** page\n- a [link](https://example.com/the-page) and `the code`\n\n![the page](media/the.png)\n\n<!-- intro -->\n\n```\nthe code block\n```\n\n1. *the* page";
        $result = $this->translator()->translate([$markdown], "en", "de", "markdown")[0];
        Assert::equals("## Hallo Welt\n\n- die **fett** Seite\n- a [Verweis](https://example.com/the-page) and `the code`\n\n![the page](media/the.png)\n\n<!-- intro -->\n\n```\nthe code block\n```\n\n1. *die* Seite", $result);
        Assert::false(in_array("<!-- intro -->", $this->sent, true), "comments, code and lines without words are not sent");
        Assert::false((bool)array_filter($this->sent, fn($text) => str_contains($text, "example.com")), "the target of a link is not sent");
    }

    public function testSpecialCharactersSurvive(): void
    {
        $result = $this->translator()->translate(["Hello & <world> \"page\""], "en", "de")[0];
        Assert::equals("Hallo & <Welt> \"Seite\"", $result);
        $result = $this->translator()->translate(["the snake_case_name and a_b"], "en", "de", "markdown")[0];
        Assert::equals("die snake_case_name and a_b", $result, "underscores inside words are no emphasis");
    }

    public function testManyTextsInOneCall(): void
    {
        $result = $this->translator()->translate(["Hello", "", "world"], "en", "de");
        Assert::equals(["Hallo", "", "Welt"], $result);
    }

    public function testErrorsOfDeepL(): void
    {
        foreach ([403 => "key", 456 => "used up", 0 => "cannot be reached"] as $status => $words) {
            try {
                $this->translator($status)->translate(["Hello"], "en", "de");
                Assert::true(false, "status " . $status . " throws");
            } catch (\RuntimeException $e) {
                Assert::true(str_contains($e->getMessage(), $words), $e->getMessage());
            }
        }
    }

    public function testFromConfig(): void
    {
        Assert::true(Translator::fromConfig([]) === null, "no key, no translator");
        Assert::true(Translator::fromConfig(["translation" => ["deepl" => ["authKey" => " "]]]) === null);
        Assert::true(Translator::fromConfig(["translation" => ["deepl" => ["authKey" => "k:fx"]]]) instanceof Translator);
    }
}
