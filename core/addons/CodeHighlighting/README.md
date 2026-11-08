# CodeHighlighting

Colors the code blocks of a page, the ones written in Markdown between three backticks. It runs on the server after the page has been rendered, so visitors need no JavaScript and see the colors right away.

CodeHighlighting is a site AddOn shipped with Reboot CMS. It has no admin page, no storage and no configuration.

## Enabling

Add it to the `addons` list in `site/config.yml`:

```yaml
addons: [ CodeHighlighting ]
```

## Behaviour

The highlighting is deliberately simple. It knows no grammar, only what most languages share: comments, strings, numbers, keywords, function calls and variables like `$name`. The language after the backticks decides which comments count and whether the rules for markup or style sheets apply:

| Language after the backticks | Comments | Rules |
|---|---|---|
| `js`, `ts`, `java`, `kotlin`, `c`, `cpp`, `cs`, `go`, `rust`, `swift`, `json` and others | `//`, `/* */` | code |
| `php` | `//`, `/* */`, `#` | code |
| `python`, `ruby`, `perl`, `yaml`, `toml`, `ini` and others | `#` | code |
| `sh`, `bash`, `zsh`, `shell` | `#` at the start of a word | code, the command at the start of a line as function, `'` alone starts no string |
| `sql` | `--`, `/* */` | code, keywords in any case |
| `html`, `xml`, `svg` | `<!-- -->` | tags, attributes and their values |
| `css`, `scss`, `less` | `/* */` | properties, values, at-rules |
| `markdown`, `md` | `<!-- -->` | headings, the separators `---`, links and images, the text stays plain |
| `text`, `plain` | | not highlighted |
| none or another one | `//`, `/* */`, `#` at the start of a word | code |

A block that holds markup already, written by a template for example, stays as it is.

## Colors

Every token becomes a `<span class="tok-…">`: `tok-comment`, `tok-keyword`, `tok-string`, `tok-number`, `tok-function`, `tok-variable`, `tok-tag`, `tok-attr` and `tok-property`. The AddOn adds a small style element with default colors to the head of a page with code. The defaults read on dark and on light backgrounds. To fit them to the design, set the variables in the style sheet of the site:

```css
pre {
    --code-keyword: #d4ff3a;
    --code-string: #9fd8a0;
    --code-comment: rgba(255, 255, 255, 0.45);
}
```

The variables are `--code-comment`, `--code-keyword`, `--code-string`, `--code-number`, `--code-function`, `--code-variable`, `--code-tag`, `--code-attr` and `--code-property`. Rules for the classes themselves win as well, the defaults have no specificity.

## Files

- `CodeHighlighting.php` hooks into `postRender()`.
- `src/Highlighter.php` finds the code blocks and splits their text into tokens.
