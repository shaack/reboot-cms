<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\ProtectedAreas;
use Shaack\Reboot\CsrfProtection;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

// Mounted only while the AddOn is registered in site/config.yml, so it is always there.
/** @var ProtectedAreas $protectedAreas */
$protectedAreas = $admin->getDefaultSite()->getAddOn("ProtectedAreas");
$store = $protectedAreas->getUserStore();
$areas = $protectedAreas->getAreas();
$adminPath = ltrim($protectedAreas->getAdminPath(), '/');

$parsePaths = function ($raw): array {
    return preg_split('/[\r\n,]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
};

$error = null;
$success = null;
$action = $request->getParam("action");
if ($action) {
    $result = AdminHelper::handleAction($request, function () use ($action, $request, $store, $parsePaths) {
        $username = trim((string)$request->getParam("username"));
        if ($action === "add") {
            $store->addUser($username, (string)$request->getParam("password"), $parsePaths($request->getParam("paths")));
            return "User '$username' added";
        } elseif ($action === "set_password") {
            $store->setPassword($username, (string)$request->getParam("password"));
            return "Password of '$username' changed";
        } elseif ($action === "set_paths") {
            $store->setPaths($username, $parsePaths($request->getParam("paths")));
            return "Paths of '$username' saved";
        } elseif ($action === "delete") {
            $store->deleteUser($username);
            return "User '$username' deleted";
        }
        throw new \InvalidArgumentException("Unknown action");
    });
    $error = $result['error'];
    $success = $result['success'];
}

$users = $store->getUsers();
?>
<div class="container-fluid max-width-xxl">
    <?= AdminHelper::renderStatusMessages($error, $success) ?>

    <?php if (empty($areas)) { ?>
        <div class="alert alert-warning">
            No protected areas are configured, so nothing is protected yet. Add the path prefixes to
            <a href="config" class="alert-link">Site Configuration</a>, for example:
            <code>protectedAreas: { areas: [ /customers ] }</code>
        </div>
    <?php } else { ?>
        <p class="text-body-secondary">
            Protected areas:
            <?php foreach ($areas as $area) { ?>
                <code class="me-2"><?= htmlspecialchars($area) ?></code>
            <?php } ?>
            Login page: <code><?= htmlspecialchars($protectedAreas->getLoginPath()) ?></code>
        </p>
    <?php } ?>

    <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="card mb-3">
        <div class="card-header">Add user</div>
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="action" value="add">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="add-username">User name</label>
                    <input type="text" id="add-username" name="username" class="form-control form-control-sm"
                           autocomplete="off" required pattern="[a-zA-Z0-9][a-zA-Z0-9._@-]{0,63}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="add-password">Password (min. 8 characters)</label>
                    <input type="text" id="add-password" name="password" class="form-control form-control-sm"
                           autocomplete="off" required minlength="8">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="add-paths">Allowed paths, one per line</label>
                    <textarea id="add-paths" name="paths" class="form-control form-control-sm font-monospace" rows="1"
                              placeholder="<?= htmlspecialchars(($areas[0] ?? '/customers') . '/acme') ?>"></textarea>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100">Add</button>
                </div>
            </div>
            <div class="form-text">
                A user may open every page below one of their paths. The path <code>/</code> grants access to all areas.
            </div>
        </div>
    </form>

    <?php if (empty($users)) { ?>
        <p class="text-body-secondary">No users yet.</p>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 200px">User</th>
                    <th>Allowed paths</th>
                    <th style="width: 260px">New password</th>
                    <th style="width: 150px">Last login</th>
                    <th style="width: 90px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $username => $user) {
                    $formId = 'user-' . md5($username); ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= htmlspecialchars($username) ?></span>
                            <div class="small text-body-secondary">
                                since <?= date("Y-m-d", (int)($user['createdAt'] ?? 0)) ?>
                            </div>
                        </td>
                        <td>
                            <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="d-flex gap-2 align-items-start">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="set_paths">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                                <textarea name="paths" class="form-control form-control-sm font-monospace"
                                          rows="<?= max(1, count($user['paths'] ?? [])) ?>"><?= htmlspecialchars(implode("\n", $user['paths'] ?? [])) ?></textarea>
                                <button class="btn btn-sm btn-outline-secondary text-nowrap">Save</button>
                            </form>
                            <?php if (empty($user['paths'])) { ?>
                                <div class="small text-warning mt-1">No paths, this user cannot open any protected page.</div>
                            <?php } ?>
                        </td>
                        <td>
                            <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="set_password">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                                <input type="text" name="password" class="form-control form-control-sm"
                                       autocomplete="off" required minlength="8" placeholder="min. 8 characters">
                                <button class="btn btn-sm btn-outline-secondary text-nowrap">Set</button>
                            </form>
                        </td>
                        <td class="small">
                            <?php if (!empty($user['lastLoginAt'])) { ?>
                                <?= date("Y-m-d H:i", (int)$user['lastLoginAt']) ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">never</span>
                            <?php } ?>
                        </td>
                        <td>
                            <form method="post" action="<?= htmlspecialchars($adminPath) ?>"
                                  onsubmit="return confirm('Delete user \'<?= htmlspecialchars($username, ENT_QUOTES) ?>\'?')">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
