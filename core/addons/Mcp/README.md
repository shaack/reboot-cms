# Mcp

Mcp is a site AddOn shipped with Reboot CMS. It opens the content of the site to AI agents like Claude Code through the [Model Context Protocol](https://modelcontextprotocol.io). An agent connects to the endpoint `/mcp` with a token and gets tools to read and write the pages, to translate them, and to read the block types and the media of the site.

## Activation

The AddOn is off by default. As long as it is not listed under `addons`, there is no endpoint, `/mcp` is an unknown page, and the admin shows no section "MCP".

1. Add `Mcp` to the list of AddOns in `site/config.yml`, in the admin under "Site Configuration" or in the file itself. Keep the AddOns already listed:

   ```yaml
   addons: [ LanguageRedirect, ProtectedAreas, Mcp ]
   ```

2. Reload the admin. The menu "AddOns (n)" now has the entry "MCP".
3. Create a token there: a name, read or read and write, the paths it may reach and, if it should, the only tools it may call. The page shows the token once, together with the command that connects Claude Code. Run it in a terminal of its own, not through an agent, so the token stays out of its transcript:

   ```bash
   claude mcp add --transport http example-com https://example.com/mcp --header "X-Reboot-Token: rbt_…" --scope user
   ```

   `--scope user` makes the server known in every folder, without it only in the folder the command runs in. In a new session `/mcp` shows whether the connection works.

To switch the AddOn off again, remove `Mcp` from `addons`. The endpoint is gone at once, the tokens stay in `local/mcp/tokens.json` and work again when the AddOn comes back. Revoke them first if they should not.

The address of the endpoint can be changed:

```yaml
mcp:
  path: /mcp
```

## Tokens

- A token reads, or reads and writes.
- A token reaches only the pages below its paths. `/` means all pages, `/blog` the pages in `site/pages/blog/`, `/customers/acme` only the pages of that customer.
- A token can be limited to single tools, `read_customer_tasks` and `update_customer_tasks` for a script that only updates one block, for example. Then it may call nothing else, not even `read_page`. Without tools it may use every tool its access allows.
- Only a SHA-256 hash of the token is stored, in `local/mcp/tokens.json`. A lost token cannot be shown again, revoke it and create a new one.
- After five requests with a wrong token from one IP address, further requests from there are refused for 15 minutes.

## Tools

| Tool | Access | What it does |
|---|---|---|
| `site_info` | read | Languages, block types, collections, navigation, the paths of the token and the guide for agents |
| `list_pages` | read | The Markdown and structured pages with address, language, title and md5, optionally below a folder |
| `read_page` | read | The source of a page and its md5 |
| `list_block_types` | read | The structured block types with their fields, and the Markdown blocks |
| `list_media` | read | The files and folders in `web/media` |
| `list_translations` | read | Missing, outdated and unknown translations |
| `read_translation` | read | Source and translation side by side |
| `write_page` | write | Creates or replaces a page |
| `delete_page` | write | Deletes a page |
| `write_translation` | write | Writes a translation |
| `upload_media` | write | Stores a small image or download in `web/media`, sent as base64 |
| `create_upload_url` | write | An address to send a file for `web/media` to with a plain PUT |

Pages are addressed by their file in `site/pages/`, `/blog/first-post.md` or `/about.de.md`. PHP pages are code and stay out of reach, so do templates, blueprints, configuration and everything in `local/`.

## Changes

- **No lost edits.** `write_page` and `delete_page` need the md5 the agent got from `read_page`. If the page changed in between, in the admin for example, the change is refused and the agent reads the page again. A new page is written without md5 and only if it does not exist yet.
- **History.** Before a page changes or is deleted, its previous version goes into the history of the admin, `local/history/pages/`. In the admin, *… › History* of the page shows it and restores it.
- **Log.** Every change is a line in `local/mcp/changes.log` with time, token, action, page and the md5 before and after.
- **Checks.** A structured page must be valid YAML. Block types that do not exist and the problems the editor of the admin would show come back as `problems`, the page is written anyway, like in the admin.
- **Media.** `upload_media` and `create_upload_url` need a token with the path `/`. They take images, PDF, audio, video, ZIP, text and CSV up to 20 MB, no SVG and no HTML, which could carry scripts. An existing file is only replaced with the md5 of its current content.
- **Upload addresses.** A tool call is JSON, so `upload_media` takes the file as base64 text, and the agent has to write all of it into its context. For a screenshot that is already a lot. `create_upload_url` returns an address below the endpoint, `/mcp/upload/<id>`, and the agent sends the file there with `curl -T shot.png <url>`. The address works once, for 15 minutes, only for the path it was made for, and only as long as its token is not revoked. `local/mcp/uploads.json` keeps the open addresses, with a hash of the id only. A wrong address counts as a failed attempt, like a wrong token.

## Translations

The agent translates, the server knows what needs translating and where it goes. The language of the source is `defaultLanguage`.

- A Markdown page has a file per language, the translation of `about.md` into German is `about.de.md`. `read_translation` returns both texts, `write_translation` takes the complete translated source.
- A structured page keeps all languages in one file. `read_translation` returns its text fields with a pointer each, `title`, `blocks.2.heading` or `blocks.3.items.0.caption`, `write_translation` takes `pointer => text` and changes only that language. A plain value becomes a value per language.
- When a translation is written, the md5 of its source goes into `local/mcp/translations.json`. If the source changes later, the translation is `outdated`. For a Markdown page `read_translation` then also returns the previous source from the history, so the agent sees what changed.
- A translation that exists but was not written through the endpoint is `unknown`. Writing it unchanged makes it `current`.

## Guide for agents

A file `site/agents.md` is handed to every agent that connects, in the instructions of the server and in `site_info`. Put the rules of the site there, the tone of the texts, the form of address, words to avoid or how images are named.

## Tools of other AddOns

An AddOn of the site can add tools of its own with a method `getMcpTools()`:

```php
public function getMcpTools(): array
{
    return [
        'count_posts' => [
            'description' => 'The number of blog posts',
            'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            'access' => 'read',
            'handler' => fn(array $arguments, array $token) => ['count' => count($this->site->pages('blog'))],
        ],
    ];
}
```

The handler returns an array, which the agent gets as JSON. An `\InvalidArgumentException` goes to the agent as an error message. A tool with `'access' => 'write'` is only offered to tokens that write.

## Transport

The endpoint speaks the Streamable HTTP transport of MCP without sessions: every request is a POST with one JSON-RPC message, answered with JSON. There is no stream from the server. Requests from web pages of other origins are refused.

The token goes in the header `X-Reboot-Token: …` or as `Authorization: Bearer …`. Prefer `X-Reboot-Token`, it reaches PHP on every server. Many hostings, Apache with PHP-FPM for example, drop `Authorization` before PHP sees it, the endpoint then answers 401 although the token is right. The `web/.htaccess` of Reboot CMS since 4.5 passes it on with

```apache
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

The updater does not replace `web/.htaccess`, an older installation has to add the line itself to use `Authorization`.

## Files

```
Mcp.php              the AddOn, the HTTP endpoint
src/Tokens.php       the tokens in local/mcp/tokens.json
src/Server.php       the JSON-RPC messages of MCP
src/Tools.php        the tools
src/Pages.php        reading and writing pages, conflicts, history and log
src/Translations.php missing and outdated translations
admin/index.php      the admin page, mounted at /admin/addons/mcp
```
