<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Admin;

/**
 * Machine translation of the fields of a page with DeepL, for the button
 * "Translate" of a field in another language than the default one.
 *
 * The key is set in `local/config.yml` or, better, in the ignored
 * `local/config.local.yml`, never in the repository:
 *
 * ```yaml
 * translation:
 *   deepl:
 *     authKey: "…:fx"    # a key of DeepL API Free ends with ":fx"
 * ```
 *
 * DeepL knows no Markdown. A text is translated line by line, the marks at the
 * start of a line, `## ` or `- ` for example, stay as they are. Within a line,
 * code, images, HTML and the targets of links are kept out of the translation,
 * links and emphasis become XML tags that DeepL keeps around the translated words.
 */
class Translator
{
    private const DEEPL_FREE = "https://api-free.deepl.com/v2/translate";
    private const DEEPL_PRO = "https://api.deepl.com/v2/translate";
    private const BATCH = 50; // texts per request, the limit of DeepL

    private string $authKey;
    /** @var callable(string $url, array $headers, string $body): array [int $status, string $body] */
    private $post;
    private array $kept = []; // what a line keeps out of the translation, by number

    public function __construct(string $authKey, ?callable $post = null)
    {
        $this->authKey = $authKey;
        $this->post = $post ?? [self::class, "httpPost"];
    }

    /**
     * The translator of the local configuration, null without a key.
     */
    public static function fromConfig(array $localConfig): ?self
    {
        $key = trim((string)($localConfig["translation"]["deepl"]["authKey"] ?? ""));
        return $key === "" ? null : new self($key);
    }

    /**
     * Translates texts from one language of the site into another.
     * @param string[] $texts
     * @param string $format "markdown" or "text"
     * @return string[] the translations, in the order of the texts
     * @throws \RuntimeException with a message for the editor, when DeepL fails
     */
    public function translate(array $texts, string $source, string $target, string $format = "text"): array
    {
        $this->kept = [];
        // every text becomes lines, only the lines with words go to DeepL
        $plans = [];
        $segments = [];
        foreach (array_values($texts) as $index => $text) {
            $plans[$index] = $this->plan(str_replace("\r\n", "\n", (string)$text), $format === "markdown");
            foreach ($plans[$index] as $line) {
                if (isset($line["xml"])) {
                    $segments[] = $line["xml"];
                }
            }
        }
        $translated = [];
        foreach (array_chunk($segments, self::BATCH) as $batch) {
            array_push($translated, ...$this->request($batch, $source, $target));
        }
        $results = [];
        $next = 0;
        foreach ($plans as $index => $lines) {
            $out = [];
            foreach ($lines as $line) {
                $out[] = isset($line["xml"]) ? $line["prefix"] . $this->restore($translated[$next++] ?? "") : $line["text"];
            }
            $results[$index] = implode("\n", $out);
        }
        return $results;
    }

    /**
     * The lines of a text, each kept as it is, `text`, or to be translated, `xml`
     * after its `prefix`.
     */
    private function plan(string $text, bool $markdown): array
    {
        $lines = [];
        $fence = null;
        foreach (explode("\n", $text) as $line) {
            if ($markdown && preg_match('/^\s*(`{3,}|~{3,})/', $line, $matches)) {
                // a fenced block of code is kept, from its opening line to its closing one
                if ($fence === null) {
                    $fence = $matches[1][0];
                } else if ($matches[1][0] === $fence) {
                    $fence = null;
                }
                $lines[] = ["text" => $line];
                continue;
            }
            if ($fence !== null || trim($line) === ""
                || ($markdown && preg_match('/^\s*(<!--.*-->|-{3,}|\*{3,}|_{3,}|\|?\s*:?-{3,}.*)\s*$/', $line))) {
                $lines[] = ["text" => $line];
                continue;
            }
            $prefix = "";
            if ($markdown && preg_match('/^(\s*(?:(?:#{1,6}|[-*+]|\d+[.)]|>)\s+(?:\[[ xX]\]\s+)?)*)/', $line, $matches)) {
                $prefix = $matches[1];
            }
            $rest = substr($line, strlen($prefix));
            if (!preg_match('/\p{L}/u', $rest)) {
                $lines[] = ["text" => $line];
                continue;
            }
            $lines[] = ["prefix" => $prefix, "xml" => $markdown ? $this->markdownToXml($rest) : htmlspecialchars($rest, ENT_NOQUOTES)];
        }
        return $lines;
    }

    /**
     * One line of Markdown as XML for DeepL: code, images, HTML and link targets
     * as `<x i="n"/>`, links as `<a i="n">`, emphasis as `<b m="**">` and `<i m="*">`.
     */
    private function markdownToXml(string $line): string
    {
        $keep = function (string $kept): string {
            $this->kept[] = $kept;
            return "\u{E000}" . (count($this->kept) - 1) . "\u{E001}";
        };
        // first what is never translated, the target of a link is kept as a number
        $line = preg_replace_callback('/`+[^`]*`+|!\[[^\]]*\]\([^)]*\)|<[^>]+>|https?:\/\/[^\s)<]+/u',
            fn($m) => $keep($m[0]), $line);
        $line = preg_replace_callback('/\]\(([^)]*)\)/u', fn($m) => "](" . $keep($m[1]) . ")", $line);
        $line = htmlspecialchars($line, ENT_NOQUOTES);
        $line = preg_replace('/\[([^\]]*)\]\(\x{E000}(\d+)\x{E001}\)/u', '<a i="$2">$1</a>', $line);
        $line = preg_replace('/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/u', '<b m="$1">$2</b>', $line);
        $line = preg_replace('/(?<![\w*])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![\w*])/u', '<i m="*">$1</i>', $line);
        $line = preg_replace('/(?<![\w_])_(?=[^\s_])(.+?)(?<=[^\s_])_(?![\w_])/u', '<i m="_">$1</i>', $line);
        return preg_replace('/\x{E000}(\d+)\x{E001}/u', '<x i="$1"/>', $line);
    }

    /**
     * A translated line back from XML, see markdownToXml().
     */
    private function restore(string $xml): string
    {
        $xml = preg_replace_callback('/<a i="(\d+)">(.*?)<\/a>/su',
            fn($m) => "[" . $m[2] . "](" . ($this->kept[(int)$m[1]] ?? "") . ")", $xml);
        $xml = preg_replace('/<b m="([^"]*)">(.*?)<\/b>/su', '$1$2$1', $xml);
        $xml = preg_replace('/<i m="([^"]*)">(.*?)<\/i>/su', '$1$2$1', $xml);
        $xml = preg_replace_callback('/<x i="(\d+)"\s*(?:\/>|><\/x>)/u',
            fn($m) => "\u{E000}" . $m[1] . "\u{E001}", $xml);
        $text = html_entity_decode($xml, ENT_QUOTES | ENT_XML1, "UTF-8");
        return preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', fn($m) => $this->kept[(int)$m[1]] ?? "", $text);
    }

    /**
     * One request to DeepL.
     * @return string[]
     */
    private function request(array $texts, string $source, string $target): array
    {
        $url = str_ends_with($this->authKey, ":fx") ? self::DEEPL_FREE : self::DEEPL_PRO;
        $body = json_encode([
            "text" => $texts,
            "source_lang" => strtoupper(explode("-", $source)[0]),
            "target_lang" => self::targetCode($target),
            "tag_handling" => "xml",
            "ignore_tags" => ["x"],
            "preserve_formatting" => true,
        ], JSON_UNESCAPED_UNICODE);
        [$status, $answer] = ($this->post)($url, [
            "Authorization: DeepL-Auth-Key " . $this->authKey,
            "Content-Type: application/json",
        ], $body);
        if ($status !== 200) {
            throw new \RuntimeException(match ($status) {
                0 => "DeepL cannot be reached.",
                403 => "DeepL does not accept the key in local/config.local.yml.",
                456 => "The characters of this month at DeepL are used up.",
                429 => "Too many requests to DeepL, try again in a moment.",
                default => "DeepL answered with the status " . $status . ".",
            });
        }
        $data = json_decode($answer, true);
        if (!is_array($data["translations"] ?? null) || count($data["translations"]) !== count($texts)) {
            throw new \RuntimeException("DeepL gave an answer that cannot be read.");
        }
        return array_map(fn($translation) => (string)($translation["text"] ?? ""), $data["translations"]);
    }

    /**
     * The target language for DeepL, which wants English and Portuguese with
     * their variant. `en-GB` in the languages of the site is taken as it is.
     */
    private static function targetCode(string $language): string
    {
        $code = strtoupper($language);
        return match ($code) {
            "EN" => "EN-US",
            "PT" => "PT-PT",
            default => $code,
        };
    }

    /**
     * @return array [int $status, string $body], status 0 if the server cannot be reached
     */
    private static function httpPost(string $url, array $headers, string $body): array
    {
        $context = stream_context_create(["http" => [
            "method" => "POST",
            "header" => implode("\r\n", $headers),
            "content" => $body,
            "timeout" => 20,
            "ignore_errors" => true,
        ]]);
        $answer = @file_get_contents($url, false, $context);
        if ($answer === false) {
            return [0, ""];
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $status = (int)$matches[1];
            }
        }
        return [$status, $answer];
    }
}
