# Mcp

Mcp is a site AddOn shipped with Reboot CMS. It opens the content of the site to AI agents like Claude Code through the [Model Context Protocol](https://modelcontextprotocol.io). An agent connects to the endpoint `/mcp` with a token and gets tools to read the pages, the block types and the media of the site.

## Setup

List the AddOn in `site/config.yml`:

```yaml
addons: [ Mcp ]
```

Then create a token in the admin under "MCP". The page shows the token once, together with the command that connects Claude Code:

```bash
claude mcp add --transport http example-com https://example.com/mcp --header "Authorization: Bearer rbt_…"
```

The address of the endpoint can be changed:

```yaml
mcp:
  path: /mcp
```

## Tokens

- A token reads, or reads and writes. Writing tools come in a later version, until then both kinds read.
- A token reaches only the pages below its paths. `/` means all pages, `/blog` the pages in `site/pages/blog/`, `/customers/acme` only the pages of that customer.
- Only a SHA-256 hash of the token is stored, in `local/mcp/tokens.json`. A lost token cannot be shown again, revoke it and create a new one.
- After five requests with a wrong token from one IP address, further requests from there are refused for 15 minutes.

## Tools

| Tool | What it does |
|---|---|
| `site_info` | Languages, block types, collections, navigation, the paths of the token and the guide for agents |
| `list_pages` | The Markdown and structured pages with address, language, title and md5, optionally below a folder |
| `read_page` | The source of a page and its md5 |
| `list_block_types` | The structured block types with their fields, and the Markdown blocks |
| `list_media` | The files and folders in `web/media` |

Pages are addressed by their file in `site/pages/`, `/blog/first-post.md` or `/about.de.md`. PHP pages are code and stay out of reach, so do templates, blueprints, configuration and everything in `local/`.

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

The token is sent as `Authorization: Bearer …`. Some Apache setups drop this header before PHP sees it. The `web/.htaccess` of Reboot CMS passes it on with

```apache
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

which an older installation may have to add, since the updater does not replace `web/.htaccess`. The header `X-Reboot-Token: …` works as well.

## Files

```
Mcp.php              the AddOn, the HTTP endpoint
src/Tokens.php       the tokens in local/mcp/tokens.json
src/Server.php       the JSON-RPC messages of MCP
src/Tools.php        the tools
admin/index.php      the admin page, mounted at /admin/addons/mcp
```
