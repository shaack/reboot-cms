<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns\Mcp;

use Shaack\Logger;

/**
 * Answers the JSON-RPC messages of the Model Context Protocol, without
 * knowing about HTTP. The server is stateless: every request carries its
 * token, there are no sessions, no notifications to the client and no
 * stream. That is all an agent needs to read and edit the content of a site.
 */
class Server
{
    /** Supported versions of the protocol, the newest first. */
    public const PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];

    private const INSTRUCTIONS = "This server edits the content of a website built with Reboot CMS, a flat-file CMS. "
        . "Pages are files in site/pages: Markdown pages (.md) with optional YAML front matter, and structured pages (.yml), "
        . "a list of blocks with the fields of their block type. Call site_info first, it lists the languages, the block types "
        . "and the paths this token may touch, and it contains the site's own guide for agents.";

    private Tools $tools;
    private string $version;

    public function __construct(Tools $tools, string $version)
    {
        $this->tools = $tools;
        $this->version = $version;
    }

    /**
     * The response to a message, null for a notification or a response,
     * which need no answer.
     */
    public function handle(mixed $message): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return self::error(null, -32600, 'Invalid request, expected a JSON-RPC 2.0 message');
        }
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;
        if (!is_string($method)) {
            return null; // a response of the client
        }
        if (!array_key_exists('id', $message)) {
            return null; // a notification, notifications/initialized for example
        }
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        return match ($method) {
            'initialize' => self::result($id, $this->initialize($params)),
            'ping' => self::result($id, new \stdClass()),
            'tools/list' => self::result($id, ['tools' => $this->tools->definitions()]),
            'tools/call' => self::result($id, $this->callTool($params)),
            default => self::error($id, -32601, "Method '$method' not found"),
        };
    }

    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        $guide = $this->tools->guide();
        return [
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'reboot-cms', 'title' => 'Reboot CMS', 'version' => $this->version],
            'instructions' => self::INSTRUCTIONS . ($guide !== null ? "\n\nGuide of this site (site/agents.md):\n\n" . $guide : ''),
        ];
    }

    private function callTool(array $params): array
    {
        $name = (string)($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        try {
            $result = $this->tools->call($name, $arguments);
        } catch (\InvalidArgumentException $e) {
            return self::toolError($e->getMessage());
        } catch (\Throwable $e) {
            Logger::error("MCP: tool '$name' failed: " . $e->getMessage());
            return self::toolError("The tool '$name' failed, see the error log of the site");
        }
        return [
            'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]],
            'structuredContent' => $result,
            'isError' => false,
        ];
    }

    private static function toolError(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
    }

    private static function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    public static function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
