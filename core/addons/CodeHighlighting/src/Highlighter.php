<?php

namespace Shaack\Reboot\AddOns\CodeHighlighting;

/**
 * A small, generic syntax highlighter for the code blocks of a rendered page.
 * It knows no grammar, only the pieces most languages share: comments,
 * strings, numbers, keywords, function calls and variables. The language of a
 * block, ```php for example, decides which comments count and whether the
 * rules for markup (HTML, XML) or style sheets (CSS) apply. Pure functions,
 * no state.
 *
 * Every token becomes a `<span class="tok-…">`, the colors come from the
 * style sheet, see STYLE.
 */
class Highlighter
{
    /** A code block as Parsedown writes it, the text in it HTML escaped. */
    private const CODE_BLOCK = '/<pre><code(?: class="language-([\w#+.-]+)")?>(.*?)<\/code><\/pre>/s';

    /** Languages and their kind, a language not listed is highlighted generically. */
    private const LANGUAGES = [
        "c" => ["js", "javascript", "mjs", "jsx", "ts", "typescript", "tsx", "java", "kotlin", "kt", "c", "h", "cpp", "c++", "cs", "csharp", "go", "rust", "rs", "swift", "dart", "scala", "json", "groovy"],
        "php" => ["php"],
        "hash" => ["python", "py", "ruby", "rb", "perl", "pl", "r", "toml", "dockerfile", "makefile", "ini", "conf"],
        "yaml" => ["yaml", "yml"],
        "shell" => ["sh", "bash", "shell", "zsh", "console", "shell-session"],
        "sql" => ["sql", "mysql", "postgres", "postgresql", "sqlite"],
        "markup" => ["html", "xml", "svg", "xhtml", "vue"],
        "css" => ["css", "scss", "sass", "less"],
        "markdown" => ["markdown", "md"],
        "none" => ["text", "txt", "plain", "plaintext"],
    ];

    /** Keywords of the common languages, in one list, since a word rarely means something else elsewhere. */
    private const KEYWORDS = [
        "abstract", "and", "as", "assert", "async", "await", "break", "case", "catch", "class", "const",
        "continue", "def", "default", "del", "do", "done", "echo", "elif", "else", "elseif", "enum", "esac",
        "except", "export", "extends", "fi", "final", "finally", "fn", "for", "foreach", "from", "func",
        "function", "global", "goto", "if", "impl", "implements", "import", "in", "include", "instanceof",
        "interface", "is", "lambda", "let", "match", "module", "namespace", "new", "not", "or",
        "package", "pass", "private", "protected", "pub", "public", "raise", "readonly", "require", "return",
        "self", "static", "struct", "super", "switch", "then", "this", "throw", "throws", "trait", "try",
        "type", "typeof", "unless", "until", "use", "val", "var", "void", "when", "where", "while", "with", "yield",
        "true", "false", "null", "undefined", "None", "True", "False", "nil", "NaN",
    ];

    /** Keywords of SQL, written in any case. */
    private const SQL_KEYWORDS = [
        "add", "all", "alter", "and", "as", "asc", "begin", "between", "by", "case", "commit", "create",
        "database", "default", "delete", "desc", "distinct", "drop", "else", "end", "exists", "foreign", "from",
        "group", "having", "in", "index", "inner", "insert", "into", "is", "join", "key", "left", "like", "limit",
        "not", "null", "offset", "on", "or", "order", "outer", "primary", "references", "right", "rollback",
        "select", "set", "table", "then", "union", "unique", "update", "values", "view", "when", "where",
    ];

    /**
     * The default colors, overridden by the site with the variables, for
     * example `--code-keyword: #d4ff3a;` on `pre`. `:where()` keeps the
     * specificity at zero, so any rule of the site wins.
     */
    public const STYLE = '<style id="code-highlighting">'
        . ':where(.tok-comment){color:var(--code-comment,#7f8a96);font-style:italic}'
        . ':where(.tok-keyword){color:var(--code-keyword,#c678dd)}'
        . ':where(.tok-string){color:var(--code-string,#5fa95f)}'
        . ':where(.tok-number){color:var(--code-number,#d19a66)}'
        . ':where(.tok-function){color:var(--code-function,#4f9fde)}'
        . ':where(.tok-variable){color:var(--code-variable,#e06c75)}'
        . ':where(.tok-tag){color:var(--code-tag,#e06c75)}'
        . ':where(.tok-attr){color:var(--code-attr,#d19a66)}'
        . ':where(.tok-property){color:var(--code-property,#4f9fde)}'
        . '</style>';

    /**
     * Highlights every code block of the page and adds STYLE to its head, if
     * there was a block to highlight. A block that holds markup already, from
     * a template for example, stays as it is.
     */
    public static function highlightPage(string $html): string
    {
        if (!str_contains($html, "<pre><code")) {
            return $html;
        }
        $count = 0;
        $html = preg_replace_callback(self::CODE_BLOCK, function ($m) use (&$count) {
            if (str_contains($m[2], "<")) {
                return $m[0];
            }
            $kind = self::kindOf($m[1]);
            if ($kind === "none") {
                return $m[0];
            }
            $count++;
            $text = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, "UTF-8");
            $class = $m[1] !== "" ? ' class="language-' . $m[1] . '"' : "";
            return "<pre><code" . $class . ">" . self::highlight($text, $kind) . "</code></pre>";
        }, $html);
        if ($count === 0) {
            return $html;
        }
        $pos = stripos($html, "</head>");
        return $pos === false ? self::STYLE . $html : substr_replace($html, self::STYLE, $pos, 0);
    }

    /** The kind of rules for a language name, "generic" for an unknown or missing one. */
    public static function kindOf(string $language): string
    {
        $language = strtolower($language);
        foreach (self::LANGUAGES as $kind => $names) {
            if (in_array($language, $names, true)) {
                return $kind;
            }
        }
        return "generic";
    }

    /** The text as HTML with a span around each token, the rest escaped. */
    public static function highlight(string $text, string $kind = "generic"): string
    {
        if ($kind === "markup") {
            return self::tokenize($text, '/(?<comment><!--.*?-->)|(?<tag><[!\/]?[A-Za-z][^<>]*>)/s', fn($token, $type) => $type === "tag"
                ? self::markupTag($token)
                : self::span("comment", $token));
        }
        if ($kind === "css") {
            return self::css($text);
        }
        if ($kind === "markdown") {
            // only the structure: block comments, headings, the separators `---` between the parts, links and images
            return self::tokenize($text, '/(?<comment><!--.*?-->)|(?<keyword>^#{1,6}[ \t][^\n]*)|(?<tag>^(?:-{3,}|\*{3,}|_{3,})[ \t]*$)'
                . '|(?<string>!?\[[^\]\n]*\]\([^)\n]*\))/sm',
                fn($token, $type) => self::span($type, $token));
        }
        $comments = match ($kind) {
            "c" => ['(?<!:)\/\/[^\n]*', '\/\*.*?\*\/'],
            "php" => ['(?<!:)\/\/[^\n]*', '\/\*.*?\*\/', '#(?!\[)[^\n]*', '<!--.*?-->'],
            "hash" => ['#[^\n]*'],
            "yaml" => ['(?<!\S)#[^\n]*'],
            "shell" => ['(?<!\S)#[^\n]*'],
            "sql" => ['--[^\n]*', '\/\*.*?\*\/'],
            default => ['(?<!:)\/\/[^\n]*', '\/\*.*?\*\/', '(?<!\S)#[^\n]*'],
        };
        $strings = ['"""[\s\S]*?"""', "'''[\\s\\S]*?'''", '"(?:\\\\.|[^"\\\\\n])*"', "'(?:\\\\.|[^'\\\\\n])*'", '`(?:\\\\.|[^`\\\\])*`'];
        if ($kind === "shell") {
            // a single quote in a shell string is plain text, `don't` in an echo for example
            $strings = ['"(?:\\\\.|[^"\\\\])*"', "'[^'\n]*'", '`[^`]*`'];
        }
        $extra = match ($kind) {
            // a PHP template is HTML as well, its tag names and attributes
            "php" => '|(?<tag><\/?[A-Za-z][\w:-]*)|(?<attr>[\w:-]+)(?==["\'])',
            // a key at the start of a line, or after `{` or `,` in a flow mapping
            "yaml" => '|(?<property>(?:^|(?<=[{,\s]))[\w.-]+(?=:(?:\s|$)))',
            // the command at the start of a line, after a `$ ` prompt or after `|`, `;`, `&&` and `(`,
            // not on a line continued with `\` and not a variable assignment `NAME=value`
            "shell" => '|(?:^(?<!\\\\\n)[ \t]*(?:\$[ \t]+)?|[|;&(][ \t]*)\K(?<command>(?:\.{1,2}\/|~\/|\/)?[\w.][\w.\/-]*+)(?![=\w])',
            default => '',
        };
        $pattern = '/(?<comment>' . implode("|", $comments) . ')'
            . '|(?<string>' . implode("|", $strings) . ')'
            . $extra
            . '|(?<variable>\$\{?[A-Za-z_]\w*\}?)'
            . '|(?<word>[A-Za-z_][\w]*)(?<call>(?=\s*\())?'
            . '|(?<number>\b(?:0x[0-9a-fA-F]+|\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)\b)/sm';
        $sql = $kind === "sql";
        return self::tokenize($text, $pattern, function ($token, $type, $m) use ($sql) {
            if ($type === "word") {
                if ($sql ? in_array(strtolower($token), self::SQL_KEYWORDS, true) : in_array($token, self::KEYWORDS, true)) {
                    return self::span("keyword", $token);
                }
                return $m["call"] !== null ? self::span("function", $token) : self::escape($token);
            }
            if ($type === "command") {
                return self::span(in_array($token, self::KEYWORDS, true) ? "keyword" : "function", $token);
            }
            return self::span($type, $token);
        });
    }

    /** A tag of markup: its name, its attributes and their values. */
    private static function markupTag(string $tag): string
    {
        if (str_starts_with($tag, "<!") || !preg_match('/^(<\/?)([^\s>\/]+)(.*?)(\/?>)$/s', $tag, $parts)) {
            return self::span("tag", $tag);
        }
        $attributes = self::tokenize($parts[3], '/(?<attr>[^\s="\'\/]+)(?=\s*=)|(?<string>"[^"]*"|\'[^\']*\')/', fn($token, $type) => self::span($type, $token));
        return self::span("tag", $parts[1] . $parts[2]) . $attributes . self::span("tag", $parts[4]);
    }

    /**
     * A style sheet: properties, values, at-rules and comments. A property is a
     * name before a colon whose value ends with `;` or `}`, in `a:hover {` the
     * `a` is a selector.
     */
    private static function css(string $text): string
    {
        $pattern = '/(?<comment>\/\*.*?\*\/)'
            . '|(?<string>"[^"\n]*"|\'[^\'\n]*\')'
            . '|(?<keyword>@[\w-]+|!important)'
            . '|(?<variable>--[\w-]+|\$[\w-]+)'
            . '|(?<property>-?[A-Za-z][\w-]*)(?<colon>(?=\s*:(?!:)[^{;]*(?:;|}|$)))?'
            . '|(?<number>#[0-9a-fA-F]{3,8}\b|-?\d*\.?\d+(?:%|[a-z]+)?)/s';
        return self::tokenize($text, $pattern, function ($token, $type, $m) {
            if ($type === "property") {
                return $m["colon"] !== null ? self::span("property", $token) : self::escape($token);
            }
            return self::span($type, $token);
        });
    }

    /**
     * Runs the pattern over the text. A match goes to $render with the name of
     * its group and all named groups, the text between the matches is escaped.
     */
    private static function tokenize(string $text, string $pattern, callable $render): string
    {
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        $html = "";
        $pos = 0;
        foreach ($matches as $match) {
            [$token, $offset] = $match[0];
            if ($token === "") {
                continue;
            }
            $html .= self::escape(substr($text, $pos, $offset - $pos));
            $groups = [];
            $type = null;
            foreach ($match as $name => $value) {
                if (is_string($name)) {
                    $groups[$name] = $value[0];
                    if ($type === null && $value[0] !== null && $value[0] !== "") {
                        $type = $name;
                    }
                }
            }
            $html .= $render($token, $type, $groups);
            $pos = $offset + strlen($token);
        }
        return $html . self::escape(substr($text, $pos));
    }

    private static function span(string $type, string $token): string
    {
        return '<span class="tok-' . $type . '">' . self::escape($token) . '</span>';
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES, "UTF-8");
    }
}
