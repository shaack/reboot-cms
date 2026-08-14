# cm-md-editor

A minimal, dependency-free markdown editor as a vanilla JavaScript ES6 module.

[Demo](https://shaack.com/projekte/cm-md-editor/)

![Screenshot](screenshot.png)

## Key features

- Vanilla JavaScript module, zero dependencies
- Syntax highlighting for headings, bold, italic, strikethrough, highlight, code, lists, links, images, blockquotes, HTML tags, horizontal rules, front matter and more
- Bare `http(s)` URLs are auto-detected, underlined, and protected from markdown formatting (e.g. underscores in a URL stay literal)
- Modular toolbar built from composable tools
- Word wrap toggle with persistent state (localStorage)
- Tab/Shift-Tab to indent/outdent the current line (any line, list nesting included); lists auto-continue on Enter
- Keyboard-accessible: Escape then Tab moves focus out of the editor (no keyboard trap); `indentWithTab: false` for plain-textarea Tab; Alt+F10 focuses the toolbar
- Move lines up/down with Alt+Up/Down (works on any line, not just list items)
- Bold with Ctrl/Cmd+B, italic with Ctrl/Cmd+I (provided by tools)
- Native undo/redo support (Ctrl/Cmd+Z / Ctrl/Cmd+Shift+Z)
- Lightweight, fast, easy to use

## Installation

```bash
npm install cm-md-editor
```

## Usage

```html
<textarea id="editor"></textarea>

<script type="module">
    import {MdEditor} from "cm-md-editor/src/MdEditor.js"

    const editor = new MdEditor(document.getElementById("editor"))
</script>
```

This creates an editor with the default toolbar: Headings (h1–h3), Bold, Italic, Strikethrough, Highlight, Unordered List, Ordered List, Insert Link, Insert Image.

### Setting the value programmatically

The editor renders the visible, syntax-highlighted text in an overlay and makes the textarea's own text transparent. That overlay repaints on the textarea's native `input` event. So when you change `textarea.value` **from code** — an autocomplete dropdown, a toolbar action outside the editor, a paste transform — no `input` event fires, the overlay keeps the old text, and the new text stays invisible until the next keystroke.

After a programmatic value change, trigger a repaint in one of two ways:

```javascript
// You hold the editor instance: call its public repaint method.
editor.updateHighlight()

// You only have the textarea (e.g. code attached around it): dispatch input,
// which also notifies any other listeners.
textarea.dispatchEvent(new Event("input", {bubbles: true}))
```

Typing into the editor needs none of this — it only matters for value changes made from code.

### Custom toolbar

Compose your own toolbar by passing a `tools` array:

```javascript
import {MdEditor} from "cm-md-editor/src/MdEditor.js"
import {Headings} from "cm-md-editor/src/tools/Headings.js"
import {Bold} from "cm-md-editor/src/tools/Bold.js"
import {Italic} from "cm-md-editor/src/tools/Italic.js"
import {Separator} from "cm-md-editor/src/tools/Separator.js"
import {InsertLink} from "cm-md-editor/src/tools/InsertLink.js"

new MdEditor(document.getElementById("editor"), {
    tools: [Headings, Separator, Bold, Italic, Separator, InsertLink]
})
```

### Configuring tools

Tools that accept options can be passed as `[ToolClass, props]` tuples:

```javascript
new MdEditor(document.getElementById("editor"), {
    tools: [[Headings, {minLevel: 2, maxLevel: 4}], Bold, Italic]
})
```

## Configuration (props)

All props are optional. Pass them as the second argument to the constructor.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `tools` | `array` | `defaultTools` | Array of tool classes (or `[class, props]` tuples). See [Tools](#tools) |
| `wordWrap` | `boolean` | `true` | Default word wrap state. Overridden by localStorage if the user has toggled it |
| `listIndent` | `string` | `"    "` (four spaces) | One level of indentation, inserted/removed with Tab/Shift-Tab at the start of the current line. Tabs and two-space levels are still accepted when reading existing text |
| `indentWithTab` | `boolean` | `true` | When `true`, Tab indents the current line and Shift+Tab outdents it (in lists and plain lines alike), and Escape then Tab moves focus out (see [Accessibility](#accessibility)). Set `false` for plain-textarea behaviour where Tab always moves focus |
| `tabReleaseHint` | `string` | (English sentence) | Screen-reader hint (`aria-describedby`) describing how to move focus out with the keyboard. Only used when `indentWithTab` is `true` |
| `toolbarInTabOrder` | `boolean` | `true` | When `true`, the toolbar is a single tab stop, so Tab from the textarea lands on the buttons. Set `false` to take it out of the tab order entirely, so Tab goes straight to the next control on the page; the toolbar is then reached with `focusToolbarShortcut` (see [Accessibility](#accessibility)) |
| `focusToolbarShortcut` | `string` | `"Alt+F10"` | Shortcut that moves focus from the textarea into the toolbar, written as modifiers plus key, e.g. `"Ctrl+Shift+T"`. Escape returns to the text. Set `null` to offer no shortcut |
| `toolbarFocusHint` | `string` | (English sentence) | Screen-reader hint (`aria-describedby`) for the toolbar shortcut. `{shortcut}` is replaced with the configured `focusToolbarShortcut` |
| `ariaLabel` | `string` | `null` | Accessible name applied to the textarea (`aria-label`) |
| `iconsPath` | `string` | bundled `src/tools/icons/` | Base URL for tool icon files referenced by `iconFile`. Resolved via `import.meta.url` by default |
| `colorChrome` | `string` | `"128,128,128"` | RGB tint for the toolbar chrome (background, borders, separators, button hover), applied at low alpha |
| `colorHeading` | `string` | `"100,160,255"` | RGB color for headings |
| `colorCode` | `string` | `"130,170,200"` | RGB color for code spans and fenced code blocks |
| `colorComment` | `string` | `"128,128,128"` | RGB color for HTML comments |
| `colorLink` | `string` | `"100,180,220"` | RGB color for links and images |
| `colorBlockquote` | `string` | `"100,200,150"` | RGB color for blockquote prefixes |
| `colorList` | `string` | `"100,200,150"` | RGB color for list markers |
| `colorStrikethrough` | `string` | `"255,100,100"` | RGB color for ~~strikethrough~~ |
| `colorHighlight` | `string` | `"230,200,90"` | RGB color for ==highlight== |
| `colorBold` | `string` | `"255,180,80"` | RGB color for **bold** |
| `colorItalic` | `string` | `"180,130,255"` | RGB color for _italic_ |
| `colorHtmlTag` | `string` | `"100,160,255"` | RGB color for HTML tag names |
| `colorHtmlTagBracket` | `string` | `"100,200,150"` | RGB color for HTML tag syntax characters (`< / > = " '`) |
| `colorHtmlTagAttribute` | `string` | `"180,130,255"` | RGB color for HTML attribute names |
| `colorHtmlTagValue` | `string` | `"255,180,80"` | RGB color for HTML attribute values |
| `colorHorizontalRule` | `string` | `"128,128,200"` | RGB color for horizontal rules |
| `colorEscape` | `string` | `"128,128,128"` | RGB color for escape sequences |
| `colorFrontMatter` | `string` | `"128,128,200"` | RGB color for YAML front matter |

Colors are specified as RGB strings (e.g. `"255,180,80"`). Syntax colors render at full opacity (headings fade slightly per level); `colorChrome` is applied at low alpha.

## Accessibility

The editor keeps the keyboard usable for everyone, including screen-reader users:

- **Tab is not a trap.** By default Tab indents the current line and Shift+Tab outdents it. To move focus out of the editor with the keyboard, press **Escape** and then **Tab** (or **Shift+Tab**). Escape releases the Tab key for a single move; the next edit re-arms indentation. This satisfies WCAG 2.1.2 (No Keyboard Trap), and the escape method is announced to screen readers via `aria-describedby` (customise the wording with `tabReleaseHint`).
- **Plain-textarea mode.** Set `indentWithTab: false` to disable Tab indentation entirely, so Tab simply moves focus like in any textarea.
- **Toolbar.** The toolbar is a single tab stop (`role="toolbar"`, roving tabindex); arrow keys move between buttons and Escape returns focus to the text. **Alt+F10** moves focus into the toolbar from the text, onto the button last used there (the convention in editors with an ARIA toolbar; change it with `focusToolbarShortcut`).
- **Toolbar outside the tab order.** Set `toolbarInTabOrder: false` when Tab should go from the text straight to the next control on the page, e.g. in a forum post form where the buttons in between are in the way. The toolbar then has no tab stop at all and Alt+F10 is the only way in, so do not also set `focusToolbarShortcut: null` — that would leave the buttons unreachable by keyboard and break WCAG 2.1.1 (Keyboard).
- **macOS and the F-keys.** Alt+F10 only reaches the browser when "Use F1, F2, etc. as standard function keys" is enabled in the system settings. If that matters for your users, configure a different combination, e.g. `focusToolbarShortcut: "Ctrl+Shift+T"`.
- **Accessible name.** Provide `ariaLabel` (or set your own `aria-label` / associated `<label>`) so the field is announced meaningfully.

## Tools

The toolbar is built entirely from tools. Each tool is a class that provides toolbar buttons, keyboard shortcuts, and/or syntax highlighting extensions.

### Built-in tools

| Tool | Buttons | Shortcut | Description |
|------|---------|----------|-------------|
| `Headings` | h1, h2, h3 | — | Toggle heading levels. Props: `{minLevel, maxLevel}` (defaults: 1–3) |
| `Bold` | bold | Ctrl/Cmd+B | Toggle bold (`**`) |
| `Italic` | italic | Ctrl/Cmd+I | Toggle italic (`_`) |
| `Strikethrough` | strikethrough | — | Toggle strikethrough (`~~`) |
| `Highlight` | highlight | — | Toggle highlight (`==`) |
| `UnorderedList` | ul | — | Toggle unordered list prefix (`- `) |
| `OrderedList` | ol | — | Toggle ordered list prefix (`1. `) |
| `InsertLink` | link | — | Insert markdown link |
| `InsertImage` | image | — | Insert markdown image |
| `Separator` | — | — | Visual divider in the toolbar. Can be used multiple times |

All built-in tools are exported from `src/tools/DefaultTools.js`:

```javascript
import {defaultTools} from "cm-md-editor/src/tools/DefaultTools.js"
```

The default toolbar order is:

```
Headings | Bold, Italic, Strikethrough, Highlight | UnorderedList, OrderedList | InsertLink, InsertImage
```

### Writing a custom tool

A tool is a class that receives the editor instance (and optional props) in its constructor. It can implement any combination of three optional methods — **all three are optional**, and a tool may implement just one of them:

- `toolbarButtons()` — add buttons to the toolbar. **Optional**: a tool does not have to contribute a button.
- `keyboardShortcuts()` — register Ctrl/Cmd shortcuts.
- `highlightInline(html)` — extend the syntax highlighting (inline, per line).
- `highlightLine(line, ctx)` — take over a whole line's highlighting, with block state across the pass.

Because every method is optional, a tool can do **pure syntax highlighting**: implement only `highlightInline(html)` and no `toolbarButtons()` / `keyboardShortcuts()`. Such a tool adds no toolbar chrome at all and only colors matching syntax in the editor.

```javascript
export class MyTool {
    constructor(editor, props = {}) {
        this.editor = editor
    }

    // Optional: add buttons to the toolbar
    toolbarButtons() {
        return [{
            name: 'mytool',
            title: 'My Tool',
            // Icon options (use one):
            icon: '<path d="..."/>',       // inline SVG path for a 16x16 viewBox
            iconFile: 'my-icon.svg',       // filename in src/tools/icons/
            iconUrl: 'https://...',        // full URL to an SVG file
            action: () => { /* ... */ }
        }]
    }

    // Optional: register keyboard shortcuts
    keyboardShortcuts() {
        return [{
            key: 'e',            // the key to match (KeyboardEvent.key)
            ctrlOrMeta: true,    // require Ctrl (Windows/Linux) or Cmd (Mac)
            action: (e) => { /* ... */ }
        }]
    }

    // Optional: extend syntax highlighting (receives already-escaped HTML)
    highlightInline(html) {
        return html.replace(...)
    }
}
```

#### Highlight-only tool (no toolbar button)

Since every method is optional, a tool that only implements `highlightInline(html)` adds no button and just colors matching syntax. `highlightInline` receives the already-escaped HTML of the line (it may already contain `<span>` tags from the built-in rules) and returns the modified HTML — so match on the text and leave existing tags intact:

```javascript
export class HashtagHighlight {
    constructor(editor, props = {}) {
        this.editor = editor
        this.color = props.color || "180,130,255"
    }

    // No toolbarButtons() and no keyboardShortcuts() — pure syntax highlighting.
    highlightInline(html) {
        // Only touch the plain-text slices between tags, never a <span>'s attributes.
        return html.replace(/<[^>]*>|[^<]+/g, (chunk) =>
            chunk[0] === "<" ? chunk : chunk.replace(/#(\w+)/g,
                (_, tag) => `<span style="color:rgba(${this.color},1)">#${tag}</span>`))
    }
}
```

#### Line-highlighting tool (block state)

`highlightInline(html)` only sees one line at a time and runs after the built-in rules. When a tool needs **block state** (whether the current line is inside some multi-line construct) or wants to **suppress a built-in rule** for a line, it implements `highlightLine(line, ctx)` instead. It is called once per line, before the built-in block rules. Returning a string renders that line and skips the built-in rules for it; returning `null`/nothing lets the built-in rules run as usual.

`ctx` is the same object for every line of a highlight pass:

| Property | Description |
|----------|-------------|
| `ctx.state` | A fresh, mutable object per pass — store your block state here |
| `ctx.lineIndex` | Index of the current line |
| `ctx.escapeHtml(s)` | The built-in HTML escaper |
| `ctx.highlightInline(line, opts)` | The built-in inline highlighting, reusable. `opts.skipOrderedList` suppresses the `1. ` ordered-list marker rule |

Example — only treat `1. ` as an ordered-list marker inside an explicit `<list>…</list>` wrapper, otherwise keep it as plain text:

```javascript
export class ListBlockHighlight {
    constructor(editor) {
        this.editor = editor
    }
    highlightLine(line, ctx) {
        const trimmed = line.trim()
        if (trimmed === '<list>')  { ctx.state.inList = true;  return null } // let the HTML-tag rule colour it
        if (trimmed === '</list>') { ctx.state.inList = false; return null }
        if (!ctx.state.inList && /^\s*\d+\.\s/.test(line)) {
            return ctx.highlightInline(line, {skipOrderedList: true})
        }
        return null
    }
}
```

For tools with co-located icons, use `import.meta.url` to resolve the icon path:

```javascript
toolbarButtons() {
    return [{
        name: 'mytool',
        title: 'My Tool',
        iconUrl: new URL("my-icon.svg", import.meta.url).href,
        action: () => { /* ... */ }
    }]
}
```

### Editor API available to tools

These public methods and properties are available via `this.editor`:

| Method / Property | Description |
|-------------------|-------------|
| `editor.element` | The textarea element (read `value`, `selectionStart`, `selectionEnd`) |
| `editor.insertTextAtCursor(text)` | Insert text at cursor, preserving native undo/redo |
| `editor.getCurrentLineInfo()` | Returns `{lineStart, lineEnd, line}` for the current line |
| `editor.selectLineRange(start, end)` | Set the textarea selection range |
| `editor.toggleWrap(marker)` | Toggle wrapping markers around the selection (e.g. `**` for bold) |
| `editor.escapeHtml(str)` | Escape a string for use in the highlight layer |
| `editor.colorSpan(colorProp, content)` | Wrap content in a colored `<span>` using an RGB color prop |

### Example: DummyText tool

A complete, self-contained tool that adds a toolbar button which inserts lorem
ipsum text at the cursor. The full source lives in
`example-addon-tools/DummyText.js`; its shape is:

```javascript
export class DummyText {
    constructor(editor) {
        this.editor = editor
    }

    // A single toolbar button. The icon ships next to the tool and is resolved
    // relative to this file via import.meta.url.
    toolbarButtons() {
        const iconUrl = new URL("bi-body-text.svg", import.meta.url).href
        return [{
            name: "dummy-text",
            title: "Insert dummy text",
            iconUrl: iconUrl,
            action: () => this.insertDummyText()
        }]
    }

    // The button's action uses the editor API to insert text at the cursor,
    // keeping the native undo/redo stack intact.
    insertDummyText() {
        const input = prompt("Word count (1–100):", "20")
        if (input === null) return
        const count = Math.max(1, Math.min(100, parseInt(input) || 20))
        this.editor.insertTextAtCursor(generateDummyText(count))
    }
}
```

Register it by adding the class to the `tools` array:

```javascript
import {MdEditor} from "cm-md-editor/src/MdEditor.js"
import {defaultTools} from "cm-md-editor/src/tools/DefaultTools.js"
import {Separator} from "cm-md-editor/src/tools/Separator.js"
import {DummyText} from "./example-addon-tools/DummyText.js"

new MdEditor(document.getElementById("editor"), {
    tools: [...defaultTools, Separator, DummyText]
})
```

## Keyboard shortcuts

| Shortcut | Action | Provided by |
|----------|--------|-------------|
| Ctrl/Cmd + B | Toggle bold | `Bold` tool |
| Ctrl/Cmd + I | Toggle italic | `Italic` tool |
| Tab | Indent list item or insert tab | Core editor |
| Shift + Tab | Outdent list item | Core editor |
| Alt + F10 | Move focus into the toolbar (Escape returns to the text) | Core editor |
| Alt + ↑ / ↓ | Move the current line(s) up or down | Core editor |
| Enter | Auto-continue list (unordered and ordered) | Core editor |
| Ctrl/Cmd + Z | Undo | Core editor |
| Ctrl/Cmd + Shift + Z (or Ctrl + Y) | Redo | Core editor |

## Testing

Unit tests use [Teevi](https://github.com/shaack/teevi) and run in a real browser. Open `test/index.html` in a browser for the report, or run a headless Chrome pass:

```bash
npm install -g puppeteer   # once, provides the headless runner
npm run test:headless
```

## License

MIT
