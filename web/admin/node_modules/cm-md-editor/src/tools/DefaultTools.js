import {Headings} from "./Headings.js"
import {Bold} from "./Bold.js"
import {Italic} from "./Italic.js"
import {Strikethrough} from "./Strikethrough.js"
import {UnorderedList} from "./UnorderedList.js"
import {OrderedList} from "./OrderedList.js"
import {InsertLink} from "./InsertLink.js"
import {InsertImage} from "./InsertImage.js"
import {Separator} from "./Separator.js"

export const defaultTools = [Headings, Separator,
    Bold, Italic, Strikethrough, Separator,
    UnorderedList, OrderedList, Separator,
    InsertLink, InsertImage]
