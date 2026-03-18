const WORDS = [
    "lorem", "ipsum", "dolor", "sit", "amet", "consectetur", "adipiscing", "elit",
    "sed", "do", "eiusmod", "tempor", "incididunt", "ut", "labore", "et", "dolore",
    "magna", "aliqua", "enim", "ad", "minim", "veniam", "quis", "nostrud",
    "exercitation", "ullamco", "laboris", "nisi", "aliquip", "ex", "ea", "commodo",
    "consequat", "duis", "aute", "irure", "in", "reprehenderit", "voluptate",
    "velit", "esse", "cillum", "fugiat", "nulla", "pariatur", "excepteur", "sint",
    "occaecat", "cupidatat", "non", "proident", "sunt", "culpa", "qui", "officia",
    "deserunt", "mollit", "anim", "id", "est", "laborum"
]

function generateDummyText(wordCount) {
    const result = []
    for (let i = 0; i < wordCount; i++) {
        result.push(WORDS[i % WORDS.length])
    }
    result[0] = result[0].charAt(0).toUpperCase() + result[0].slice(1)
    return result.join(" ") + "."
}

export class DummyText {
    constructor(editor) {
        this.editor = editor
    }

    toolbarButtons() {
        const iconUrl = new URL("bi-body-text.svg", import.meta.url).href
        return [{
            name: "dummy-text",
            title: "Insert dummy text",
            iconUrl: iconUrl,
            action: () => this.insertDummyText()
        }]
    }

    insertDummyText() {
        const input = prompt("Word count (1\u2013100):", "20")
        if (input === null) return
        const count = Math.max(1, Math.min(100, parseInt(input) || 20))
        this.editor.insertTextAtCursor(generateDummyText(count))
    }
}
