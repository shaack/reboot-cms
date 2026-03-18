# cm-md-editor

A minimal, dependency-free markdown editor as a vanilla JavaScript ES6 module.

[Demo](https://shaack.com/projekte/cm-md-editor/)

![Screenshot](screenshot.png)

## Key features

- Vanilla JavaScript module, zero dependencies
- Syntax highlighting for headings, bold, italic, strikethrough, code, lists, links, images, blockquotes, HTML tags, horizontal rules, front matter and more
- Modular toolbar built from composable tools
- Word wrap toggle with persistent state (localStorage)
- List mode: Tab/Shift-Tab to indent/outdent, auto-continuation on Enter
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

This creates an editor with the default toolbar: Headings (h1–h3), Bold, Italic, Strikethrough, Unordered List, Ordered List, Insert Link, Insert Image.

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
| `colorHeading` | `string` | `"100,160,255"` | RGB color for headings |
| `colorCode` | `string` | `"130,170,200"` | RGB color for code spans and fenced code blocks |
| `colorComment` | `string` | `"128,128,128"` | RGB color for HTML comments |
| `colorLink` | `string` | `"100,180,220"` | RGB color for links and images |
| `colorBlockquote` | `string` | `"100,200,150"` | RGB color for blockquote prefixes |
| `colorList` | `string` | `"100,200,150"` | RGB color for list markers |
| `colorStrikethrough` | `string` | `"255,100,100"` | RGB color for ~~strikethrough~~ |
| `colorBold` | `string` | `"255,180,80"` | RGB color for **bold** |
| `colorItalic` | `string` | `"180,130,255"` | RGB color for _italic_ |
| `colorHtmlTag` | `string` | `"200,120,120"` | RGB color for HTML tags |
| `colorHorizontalRule` | `string` | `"128,128,200"` | RGB color for horizontal rules |
| `colorEscape` | `string` | `"128,128,128"` | RGB color for escape sequences |
| `colorFrontMatter` | `string` | `"128,128,200"` | RGB color for YAML front matter |

Colors are specified as RGB strings (e.g. `"255,180,80"`) and rendered at full opacity.

## Tools

The toolbar is built entirely from tools. Each tool is a class that provides toolbar buttons, keyboard shortcuts, and/or syntax highlighting extensions.

### Built-in tools

| Tool | Buttons | Shortcut | Description |
|------|---------|----------|-------------|
| `Headings` | h1, h2, h3 | — | Toggle heading levels. Props: `{minLevel, maxLevel}` (defaults: 1–3) |
| `Bold` | bold | Ctrl/Cmd+B | Toggle bold (`**`) |
| `Italic` | italic | Ctrl/Cmd+I | Toggle italic (`_`) |
| `Strikethrough` | strikethrough | — | Toggle strikethrough (`~~`) |
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
Headings | Bold, Italic, Strikethrough | UnorderedList, OrderedList | InsertLink, InsertImage
```

### Writing a custom tool

A tool is a class that receives the editor instance (and optional props) in its constructor. It can implement any combination of three optional methods:

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

A tool that inserts lorem ipsum text (see `example-addon-tools/DummyText.js`):

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
| Enter | Auto-continue list (unordered and ordered) | Core editor |

## License

MIT
