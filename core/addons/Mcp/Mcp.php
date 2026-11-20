<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot\AddOns;

use Shaack\Logger;
use Shaack\Reboot\AddOn;
use Shaack\Reboot\AddOns\Mcp\Media;
use Shaack\Reboot\AddOns\Mcp\Server;
use Shaack\Reboot\AddOns\Mcp\Tokens;
use Shaack\Reboot\AddOns\Mcp\Tools;
use Shaack\Reboot\AddOns\Mcp\Uploads;
use Shaack\Reboot\LoginThrottle;
use Shaack\Reboot\Request;
use Shaack\Reboot\Session;

/**
 * Built-in AddOn that opens the content of the site to AI agents through the
 * Model Context Protocol (MCP). An agent like Claude Code connects to `/mcp`
 * with a token and gets tools to read and write the pages, translate them,
 * and to read the block types and the media of the site.
 *
 * Tokens are created in the admin under "MCP" and stored hashed in
 * `local/mcp/tokens.json`. A token reads or writes, and only the pages below
 * its paths. Templates, configuration, PHP pages and `local/` are never
 * reachable.
 *
 * Configuration in `site/config.yml`:
 *
 *     addons: [ Mcp ]
 *     mcp:
 *       path: /mcp    # the address of the endpoint (default)
 *
 * The endpoint speaks the Streamable HTTP transport without sessions: a POST
 * with one JSON-RPC message, answered with JSON.
 *
 * Below it, `/mcp/upload/<id>` takes a file for `web/media` with a plain PUT,
 * at an address the tool create_upload_url made, see Uploads.
 */
class Mcp extends AddOn
{
    private string $path = '/mcp';

    protected function init()
    {
        $path = (string)($this->site->getConfig()['mcp']['path'] ?? '/mcp');
        $this->path = '/' . trim($path, '/');
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @see AddOn::getAdminMenu()
     */
    public function getAdminMenu(): array
    {
        return ['MCP' => $this->getAdminPath()];
    }

    public function getTokens(): Tokens
    {
        return new Tokens($this->reboot->getBaseFsPath());
    }

    /**
     * @see AddOn::preRender()
     */
    public function preRender(Request $request): bool
    {
        $path = $request->getPath();
        if (str_starts_with($path, $this->path . '/upload/')) {
            $this->serveUpload(substr($path, strlen($this->path . '/upload/')));
            return false;
        }
        if ($path !== $this->path) {
            return true;
        }
        $this->serve();
        return false;
    }

    private function serve(): void
    {
        header('Cache-Control: no-store');
        if (!$this->isSameOrigin()) {
            $this->respond(403, Server::error(null, -32600, 'Requests from web pages of other origins are not allowed'));
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // No stream from the server, no sessions to end
            header('Allow: POST');
            $this->respond(405, null);
            return;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $throttle = new LoginThrottle($this->reboot->getBaseFsPath() . '/local/mcp/.attempts');
        if ($throttle->isBlocked($ip)) {
            $this->respond(429, Server::error(null, -32000, 'Too many requests with a wrong token, try again later'));
            return;
        }
        $token = $this->getTokens()->authenticate($this->bearerToken() ?? '');
        if ($token === null) {
            if (!is_dir($this->reboot->getBaseFsPath() . '/local/mcp')) {
                @mkdir($this->reboot->getBaseFsPath() . '/local/mcp', 0700, true);
            }
            $throttle->registerFailure($ip);
            Logger::info('MCP: request without a valid token from ' . $ip);
            header('WWW-Authenticate: Bearer realm="Reboot CMS"');
            $this->respond(401, Server::error(null, -32001, 'A valid token is required, create one in the admin under MCP'));
            return;
        }
        $message = json_decode((string)file_get_contents('php://input'), true);
        if ($message === null) {
            $this->respond(400, Server::error(null, -32700, 'Parse error, the body is no JSON'));
            return;
        }
        if (isset($message['method']) && $message['method'] === 'tools/call') {
            Logger::info("MCP: '" . $token['name'] . "' calls " . ($message['params']['name'] ?? '?'));
        }
        $uploadUrl = (Session::isHttps() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . $this->site->getWebPath() . $this->path . '/upload/';
        $tools = new Tools($this->site, $this->reboot->getBaseFsPath(), $this->reboot->getBaseWebPath(), $token,
            (int)($this->reboot->getConfig()['history']['maxVersions'] ?? 50), $uploadUrl);
        $response = (new Server($tools, $this->version()))->handle($message);
        $this->respond($response === null ? 202 : 200, $response);
    }

    /**
     * A file sent to an upload address. The address is the authorization, it
     * works once and only for its path, as long as its token is not revoked.
     */
    private function serveUpload(string $id): void
    {
        header('Cache-Control: no-store');
        if (!$this->isSameOrigin()) {
            $this->respond(403, ['error' => 'Requests from web pages of other origins are not allowed']);
            return;
        }
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) {
            header('Allow: PUT');
            $this->respond(405, ['error' => 'Send the file with PUT']);
            return;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $throttle = new LoginThrottle($this->reboot->getBaseFsPath() . '/local/mcp/.attempts');
        if ($throttle->isBlocked($ip)) {
            $this->respond(429, ['error' => 'Too many requests with a wrong address, try again later']);
            return;
        }
        $upload = (new Uploads($this->reboot->getBaseFsPath()))->redeem($id);
        if ($upload === null) {
            $throttle->registerFailure($ip);
            $this->respond(404, ['error' => 'Unknown upload address, it was used or has expired. Get a new one with create_upload_url']);
            return;
        }
        $token = $this->getTokens()->getTokens()[$upload['tokenId'] ?? ''] ?? null;
        if ($token === null) {
            $this->respond(403, ['error' => 'The token of this upload address was revoked']);
            return;
        }
        // one byte more than allowed, to know that it is too much
        $content = (string)stream_get_contents(fopen('php://input', 'rb'), Media::MAX_BYTES + 1);
        try {
            $result = (new Media($this->reboot->getBaseFsPath(), $this->reboot->getBaseWebPath()))
                ->store($token, (string)$upload['path'], $content, (string)($upload['md5'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            $this->respond(strlen($content) > Media::MAX_BYTES ? 413 : 400, ['error' => $e->getMessage()]);
            return;
        }
        Logger::info("MCP: '" . ($token['name'] ?? '?') . "' uploaded " . $result['path']);
        $this->respond(201, $result);
    }

    private function respond(int $status, ?array $body): void
    {
        http_response_code($status);
        if ($body !== null) {
            header('Content-Type: application/json');
            echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * The token from `Authorization: Bearer …`. Some Apache setups drop that
     * header before PHP sees it, `X-Reboot-Token: …` works as well.
     */
    private function bearerToken(): ?string
    {
        $header = ($_SERVER['HTTP_AUTHORIZATION'] ?? '') ?: ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') ?: null;
        if ($header === null && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $header = $value;
                }
            }
        }
        if (is_string($header) && preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches)) {
            return $matches[1];
        }
        $token = $_SERVER['HTTP_X_REBOOT_TOKEN'] ?? null;
        return is_string($token) && $token !== '' ? trim($token) : null;
    }

    /**
     * A browser sends an Origin header. A page of another site must not reach
     * the endpoint, an agent sends none or the own one.
     */
    private function isSameOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin === null || $origin === '') {
            return true;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $origin = $host . ($port ? ':' . $port : '');
        return is_string($host) && strcasecmp($origin, (string)($_SERVER['HTTP_HOST'] ?? '')) === 0;
    }

    private function version(): string
    {
        $data = json_decode((string)@file_get_contents($this->reboot->getBaseFsPath() . '/composer.json'), true);
        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : '';
    }
}
