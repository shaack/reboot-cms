<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\Admin\AdminHelper;
use Shaack\Reboot\Authentication;
use Shaack\Reboot\CsrfProtection;

if (!AdminHelper::requireAdmin($site, $reboot)) return;

/** @var Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
$htpasswd = $authentication->getHtpasswd();
$currentUser = $authentication->getUser();

$error = null;
$success = null;

$action = $request->getParam("action");
if ($action) {
    $result = AdminHelper::handleAction($request, function() use ($action, $request, $htpasswd, $authentication, $currentUser, $reboot, $site) {
        $username = trim($request->getParam("username") ?? "");
        $password = $request->getParam("password") ?? "";

        // Require admin password re-entry for sensitive actions
        if (in_array($action, ["add", "change_password", "delete"])) {
            $adminPassword = $request->getParam("admin_password") ?? "";
            $adminUser = $authentication->getImpersonator() ?? $currentUser;
            if (!$htpasswd->validate($adminUser, $adminPassword)) {
                throw new \InvalidArgumentException("Incorrect admin password");
            }
        }

        if ($action === "impersonate") {
            if ($username === $currentUser) {
                throw new \InvalidArgumentException("You are already logged in as this user");
            }
            if (!in_array($username, $htpasswd->getUsers(), true)) {
                throw new \InvalidArgumentException("User '$username' does not exist");
            }
            $authentication->impersonate($username);
            $reboot->redirect($reboot->getBaseWebPath() . $site->getWebPath() . "/users");
            return null;
        } elseif ($action === "add") {
            if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $username)) {
                throw new \InvalidArgumentException("Username must contain only letters, numbers, underscores (max 64 chars)");
            }
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException("Password must be at least 8 characters");
            }
            $role = $request->getParam("role") ?? Authentication::ROLE_EDITOR;
            $htpasswd->addUser($username, $password);
            $authentication->setUserRole($username, $role);
            $authentication->refreshChecksum();
            return "User '$username' added as $role";
        } elseif ($action === "change_password") {
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException("Password must be at least 8 characters");
            }
            $htpasswd->changePassword($username, $password);
            $authentication->refreshChecksum();
            return "Password changed for '$username'";
        } elseif ($action === "change_role") {
            if ($username === $currentUser) {
                throw new \InvalidArgumentException("You cannot change your own role");
            }
            $role = $request->getParam("role") ?? Authentication::ROLE_EDITOR;
            $authentication->setUserRole($username, $role);
            return "Role changed for '$username' to $role";
        } elseif ($action === "delete") {
            if ($username === $currentUser) {
                throw new \InvalidArgumentException("You cannot delete your own account");
            }
            $htpasswd->deleteUser($username);
            $authentication->deleteUserRole($username);
            $authentication->refreshChecksum();
            return "User '$username' deleted";
        }
    });
    $error = $result['error'];
    $success = $result['success'];
}

$users = $htpasswd->getUsers();
?>

<div class="container-fluid max-width-lg">
    <?php if ($authentication->isImpersonating()) { ?>
        <div class="alert alert-warning d-flex align-items-center justify-content-between">
            <span>You are impersonating <strong><?= htmlspecialchars($currentUser) ?></strong> (logged in as <?= htmlspecialchars($authentication->getImpersonator()) ?>)</span>
            <form method="post" action="users" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <input type="hidden" name="action" value="stop_impersonate">
                <button class="btn btn-sm btn-warning">Stop Impersonating</button>
            </form>
        </div>
    <?php } ?>
    <?= AdminHelper::renderStatusMessages($error, $success) ?>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Users</h5></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($users as $user) {
                $userRole = $authentication->getUserRole($user);
                ?>
                <li class="list-group-item">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong style="min-width: 120px"><?= htmlspecialchars($user) ?></strong>
                        <?php if ($user !== $currentUser) { ?>
                            <form method="post" action="users" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                                <select name="role" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                                    <option value="<?= Authentication::ROLE_ADMIN ?>" <?= $userRole === Authentication::ROLE_ADMIN ? 'selected' : '' ?>>Admin</option>
                                    <option value="<?= Authentication::ROLE_EDITOR ?>" <?= $userRole === Authentication::ROLE_EDITOR ? 'selected' : '' ?>>Editor</option>
                                </select>
                            </form>
                        <?php } else { ?>
                            <span class="badge text-bg-secondary"><?= htmlspecialchars($userRole) ?></span>
                        <?php } ?>
                        <span class="me-auto"></span>
                        <form method="post" action="users" class="d-flex flex-wrap align-items-center gap-2 admin-password-form">
                            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                            <input type="password" name="password" class="form-control form-control-sm" style="width: 200px"
                                   placeholder="New password" required minlength="8">
                            <button class="btn btn-sm btn-outline-primary text-nowrap">Change Password</button>
                        </form>
                        <?php if ($user !== $currentUser && !$authentication->isImpersonating()) { ?>
                            <form method="post" action="users">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="impersonate">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                                <button class="btn btn-sm btn-outline-secondary text-nowrap">Impersonate</button>
                            </form>
                        <?php } ?>
                        <?php if ($user !== $currentUser && !$authentication->isImpersonating()) { ?>
                            <form method="post" action="users" class="admin-password-form"
                                  data-confirm="Delete user '<?= htmlspecialchars($user, ENT_QUOTES) ?>'?">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        <?php } else if ($user !== $currentUser) { ?>
                            <button class="btn btn-sm btn-outline-danger invisible">Delete</button>
                        <?php } ?>
                    </div>
                </li>
            <?php } ?>
        </ul>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Add User</h5></div>
        <div class="card-body">
            <form method="post" action="users" class="d-flex flex-wrap align-items-center gap-2 admin-password-form">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <input type="hidden" name="action" value="add">
                <input type="text" name="username" class="form-control form-control-sm" style="width: 200px"
                       placeholder="Username" required pattern="[a-zA-Z0-9_]{1,64}" autocomplete="off">
                <input type="password" name="password" class="form-control form-control-sm" style="width: 200px"
                       placeholder="Password" required minlength="8" autocomplete="new-password">
                <select name="role" class="form-select form-select-sm" style="width: auto">
                    <option value="<?= Authentication::ROLE_EDITOR ?>">Editor</option>
                    <option value="<?= Authentication::ROLE_ADMIN ?>">Admin</option>
                </select>
                <button class="btn btn-sm btn-primary text-nowrap">Add User</button>
            </form>
        </div>
    </div>
</div>

<script type="module">
    document.querySelectorAll(".admin-password-form").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault()
            if (!form.checkValidity()) {
                form.reportValidity()
                return
            }
            const confirmMessage = form.dataset.confirm
            if (confirmMessage && !confirm(confirmMessage)) {
                return
            }
            bootstrap.showModal({
                title: "Confirm your password",
                body: '<input type="password" class="form-control" placeholder="Your password" id="admin-password-input" required>',
                footer: '<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                    '<button class="btn btn-primary" id="admin-password-submit">Confirm</button>',
                onShown: function (modal) {
                    const input = document.getElementById("admin-password-input")
                    const submitBtn = document.getElementById("admin-password-submit")
                    input.focus()
                    input.addEventListener("keydown", function (e) {
                        if (e.key === "Enter") {
                            e.preventDefault()
                            submitBtn.click()
                        }
                    })
                    submitBtn.addEventListener("click", function () {
                        if (!input.value) {
                            input.classList.add("is-invalid")
                            return
                        }
                        const hiddenInput = document.createElement("input")
                        hiddenInput.type = "hidden"
                        hiddenInput.name = "admin_password"
                        hiddenInput.value = input.value
                        form.appendChild(hiddenInput)
                        modal.hide()
                        form.submit()
                    })
                }
            })
        })
    })
</script>
