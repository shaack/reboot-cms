<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\AddOns\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\AddOns\ProtectedAreas;
use Shaack\Reboot\AddOns\ProtectedAreas\UserStore;
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
    $result = AdminHelper::handleAction($request, function () use ($action, $request, $store, $parsePaths, $protectedAreas) {
        $email = UserStore::normalizeEmail((string)$request->getParam("email"));
        if ($action === "add") {
            $store->addUser($email, (string)$request->getParam("password"), $parsePaths($request->getParam("paths")));
            return "Account '$email' added";
        } elseif ($action === "set_password") {
            $store->setPassword($email, (string)$request->getParam("password"));
            $protectedAreas->getRememberStore()->deleteForSubject($email);
            return "Password of '$email' set";
        } elseif ($action === "set_paths") {
            $store->setPaths($email, $parsePaths($request->getParam("paths")));
            return "Paths of '$email' saved";
        } elseif ($action === "delete") {
            $store->deleteUser($email);
            $protectedAreas->getRememberStore()->deleteForSubject($email);
            return "Account '$email' deleted";
        } elseif ($action === "remove_password") {
            $store->removePassword($email);
            $protectedAreas->getRememberStore()->deleteForSubject($email);
            return "Password of '$email' removed, the account logs in by link only";
        } elseif ($action === "send_link") {
            $protectedAreas->sendLoginLink($email, $request);
            return "Login link sent to '$email'";
        }
        throw new \InvalidArgumentException("Unknown action");
    });
    $error = $result['error'];
    $success = $result['success'];
}

$users = $store->getUsers();
$linkLogin = $protectedAreas->canSendLoginLinks();
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
        <?php if (!$linkLogin) { ?>
            <div class="alert alert-secondary small">
                Login links by mail are off because no <code>mail</code> transport is configured in
                <code>local/config.yml</code>. Visitors log in with their password only. See
                <a href="health" class="alert-link">System Health</a>.
            </div>
        <?php } ?>
        <p class="text-body-secondary">
            Protected areas:
            <?php foreach ($areas as $area) { ?>
                <code class="me-2"><?= htmlspecialchars($area) ?></code>
            <?php } ?>
            Login page: <code><?= htmlspecialchars($protectedAreas->getLoginPath()) ?></code>
        </p>
    <?php } ?>

    <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="card mb-3">
        <div class="card-header">Add account</div>
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="action" value="add">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="add-email">E-mail address</label>
                    <input type="email" id="add-email" name="email" class="form-control form-control-sm"
                           autocomplete="off" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="add-password">
                        <?= $linkLogin ? 'Password (optional, min. 8 characters)' : 'Password (min. 8 characters)' ?>
                    </label>
                    <input type="text" id="add-password" name="password" class="form-control form-control-sm"
                           autocomplete="off" minlength="8"<?= $linkLogin ? ' placeholder="none, login by link"' : ' required' ?>>
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
                <?php if ($linkLogin) { ?>
                    Without a password the account logs in by mail link only, which is the simplest way for customers.
                <?php } ?>
            </div>
        </div>
    </form>

    <?php if (empty($users)) { ?>
        <p class="text-body-secondary">No accounts yet.</p>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 260px">E-mail address</th>
                    <th>Allowed paths</th>
                    <th style="width: 280px">Password</th>
                    <th style="width: 150px">Last login</th>
                    <th style="width: 220px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $email => $user) {
                    $formId = 'user-' . md5($email); ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= htmlspecialchars($email) ?></span>
                            <div class="small text-body-secondary">
                                since <?= date("Y-m-d", (int)($user['createdAt'] ?? 0)) ?>
                            </div>
                        </td>
                        <td>
                            <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="d-flex gap-2 align-items-start">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="set_paths">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
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
                                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                                <input type="text" name="password" class="form-control form-control-sm"
                                       autocomplete="off" required minlength="8" placeholder="<?= !empty($user['hasPassword']) ? 'new password, min. 8 characters' : 'min. 8 characters' ?>">
                                <button class="btn btn-sm btn-outline-secondary text-nowrap">Set</button>
                            </form>
                            <?php if (!empty($user['hasPassword'])) { ?>
                                <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                    <input type="hidden" name="action" value="remove_password">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                                    <button class="btn btn-sm btn-link text-body-secondary text-decoration-none p-0 mt-1 small">Remove password, login by link only</button>
                                </form>
                            <?php } else { ?>
                                <div class="small text-body-secondary mt-1">No password, logs in by link only.</div>
                            <?php } ?>
                        </td>
                        <td class="small">
                            <?php if (!empty($user['lastLoginAt'])) { ?>
                                <?= date("Y-m-d H:i", (int)$user['lastLoginAt']) ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">never</span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($linkLogin) { ?>
                                <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                    <input type="hidden" name="action" value="send_link">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                                    <button class="btn btn-sm btn-outline-secondary" title="Mail a link that logs this account in without a password">Send login link</button>
                                </form>
                            <?php } ?>
                            <form method="post" action="<?= htmlspecialchars($adminPath) ?>" class="d-inline"
                                  onsubmit="return confirm('Delete account \'<?= htmlspecialchars($email, ENT_QUOTES) ?>\'?')">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
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
