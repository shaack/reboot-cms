<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\Admin\AdminMenu;
use Shaack\Reboot\AddOns\Mcp;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\Session;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

/** @var Mcp $mcp */
$mcp = $admin->getDefaultSite()->getAddOn("Mcp");
$tokens = $mcp->getTokens();
$adminPath = ltrim($mcp->getAdminPath(), '/');
$endpoint = (Session::isHttps() ? "https://" : "http://") . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . $admin->getDefaultSite()->getWebPath() . $mcp->getPath();

$error = null;
$success = null;
$newToken = null;
$action = $request->getParam("action");
if ($action) {
    $result = AdminHelper::handleAction($request, function () use ($action, $request, $tokens) {
        if ($action === "create") {
            $paths = preg_split('/[\r\n,]+/', (string)$request->getParam("paths"), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $name = (string)$request->getParam("name");
            $token = $tokens->create($name, (string)$request->getParam("access"), $paths);
            return ['success' => "Token '$name' created. Copy it now, it is not shown again.", 'token' => $token];
        } elseif ($action === "revoke") {
            $tokens->revoke((string)$request->getParam("id"));
            return "Token revoked";
        }
        throw new \InvalidArgumentException("Unknown action");
    });
    $error = $result['error'];
    $success = $result['success'];
    $newToken = $result['token'] ?? null;
}
$list = $tokens->getTokens();
?>
<div class="container-fluid max-width-xxl">
    <?= AdminHelper::renderStatusMessages($error, $success) ?>
    <?= AdminHelper::renderPageTitle(AdminMenu::infoLabel($mcp), '', '
        <p>The MCP endpoint lets AI agents like Claude Code read the content of this site through the Model Context Protocol. Each agent gets a token of its own.</p>
        <h6>Tokens</h6>
        <ul>
            <li>A token reads, or reads and writes. Writing is not available yet, the tools come in a later version.</li>
            <li>A token reaches only the pages below its paths, <code>/</code> means all pages, <code>/blog</code> the pages in <code>site/pages/blog/</code>.</li>
            <li>Templates, configuration, PHP pages and <code>local/</code> are never reachable.</li>
            <li>The token is shown once. Only its hash is stored, in <code>local/mcp/tokens.json</code>. Revoke a token that is no longer needed or got lost.</li>
            <li>After five requests with a wrong token from one IP address, further requests from there are refused for 15 minutes.</li>
        </ul>
        <p>Put a guide for agents into <code>site/agents.md</code>, about the tone of the texts for example. Agents get it when they connect. More in <code>core/addons/Mcp/README.md</code>.</p>
    ') ?>

    <p class="text-body-secondary">Endpoint: <code><?= htmlspecialchars($endpoint) ?></code></p>

    <?php if ($newToken) { ?>
        <div class="card border-success mb-3">
            <div class="card-header">New token</div>
            <div class="card-body">
                <p class="mb-2"><code class="user-select-all fs-6"><?= htmlspecialchars($newToken) ?></code></p>
                <p class="small text-body-secondary mb-1">Connect Claude Code:</p>
                <pre class="small bg-body-tertiary p-2 mb-0 user-select-all"><code>claude mcp add --transport http <?= htmlspecialchars(preg_replace('/[^a-z0-9-]+/', '-', strtolower($_SERVER['HTTP_HOST'] ?? 'site'))) ?> <?= htmlspecialchars($endpoint) ?> --header "Authorization: Bearer <?= htmlspecialchars($newToken) ?>"</code></pre>
            </div>
        </div>
    <?php } ?>

    <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="card mb-3">
        <div class="card-header">Create token</div>
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="action" value="create">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="token-name">Name</label>
                    <input id="token-name" name="name" class="form-control form-control-sm" required
                           placeholder="Claude Code" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="token-access">Access</label>
                    <select id="token-access" name="access" class="form-select form-select-sm">
                        <option value="read">Read</option>
                        <option value="write">Read and write</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-1" for="token-paths">Paths, one per line</label>
                    <textarea id="token-paths" name="paths" class="form-control form-control-sm font-monospace" rows="1">/</textarea>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100">Create</button>
                </div>
            </div>
        </div>
    </form>

    <?php if (empty($list)) { ?>
        <p class="text-body-secondary">No tokens yet.</p>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Access</th>
                    <th>Paths</th>
                    <th style="width: 150px">Created</th>
                    <th style="width: 150px">Last used</th>
                    <th style="width: 100px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($list as $id => $token) { ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($token['name']) ?></td>
                        <td><?= ($token['access'] ?? 'read') === 'write' ? 'read and write' : 'read' ?></td>
                        <td>
                            <?php foreach ($token['paths'] ?? [] as $path) { ?>
                                <code class="me-2"><?= htmlspecialchars($path) ?></code>
                            <?php } ?>
                        </td>
                        <td class="small"><?= date("Y-m-d H:i", (int)($token['createdAt'] ?? 0)) ?></td>
                        <td class="small">
                            <?php if (!empty($token['lastUsedAt'])) { ?>
                                <?= date("Y-m-d H:i", (int)$token['lastUsedAt']) ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">never</span>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="d-inline"
                                  onsubmit="return confirm(<?= AdminHelper::jsArg("Revoke token '" . $token['name'] . "'?") ?>)">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="revoke">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                                <button class="btn btn-sm btn-outline-danger">Revoke</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
