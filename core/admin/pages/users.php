<?php
/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var Shaack\Reboot\Admin $admin */
$admin = $site->getAddOn("Admin");

use Shaack\Reboot\CsrfProtection;

/** @var \Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
$htpasswd = $authentication->getHtpasswd();
$currentUser = $authentication->getUser();

$error = null;
$success = null;

$action = $request->getParam("action");
if ($action) {
    try {
        CsrfProtection::validate($request);
        $username = trim($request->getParam("username") ?? "");
        $password = $request->getParam("password") ?? "";

        if ($action === "add") {
            if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $username)) {
                throw new \InvalidArgumentException("Username must contain only letters, numbers, underscores (max 64 chars)");
            }
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException("Password must be at least 8 characters");
            }
            $htpasswd->addUser($username, $password);
            $authentication->refreshChecksum();
            $success = "User '$username' added";
        } elseif ($action === "change_password") {
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException("Password must be at least 8 characters");
            }
            $htpasswd->changePassword($username, $password);
            $authentication->refreshChecksum();
            $success = "Password changed for '$username'";
        } elseif ($action === "delete") {
            if ($username === $currentUser) {
                throw new \InvalidArgumentException("You cannot delete your own account");
            }
            $htpasswd->deleteUser($username);
            $authentication->refreshChecksum();
            $success = "User '$username' deleted";
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

$users = $htpasswd->getUsers();
?>

<div class="container-fluid">
    <?php if ($error) { ?>
        <script>statusMessage("<?= htmlspecialchars($error, ENT_QUOTES) ?>", "text-bg-danger")</script>
    <?php } ?>
    <?php if ($success) { ?>
        <script>statusMessage("<?= htmlspecialchars($success, ENT_QUOTES) ?>")</script>
    <?php } ?>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Users</h5></div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Username</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user) { ?>
                    <tr>
                        <td><?= htmlspecialchars($user) ?></td>
                        <td>
                            <form method="post" action="users" class="d-inline-flex align-items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                                <input type="password" name="password" class="form-control form-control-sm" style="width: 200px"
                                       placeholder="New password" required minlength="8">
                                <button class="btn btn-sm btn-outline-primary">Change Password</button>
                            </form>
                            <?php if ($user !== $currentUser) { ?>
                                <form method="post" action="users" class="d-inline ms-2"
                                      onsubmit="return confirm('Delete user \'<?= htmlspecialchars($user, ENT_QUOTES) ?>\'?')">
                                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Add User</h5></div>
        <div class="card-body">
            <form method="post" action="users" class="d-flex align-items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <input type="hidden" name="action" value="add">
                <input type="text" name="username" class="form-control form-control-sm" style="width: 200px"
                       placeholder="Username" required pattern="[a-zA-Z0-9_]{1,64}" autocomplete="off">
                <input type="password" name="password" class="form-control form-control-sm" style="width: 200px"
                       placeholder="Password" required minlength="8" autocomplete="new-password">
                <button class="btn btn-sm btn-primary">Add User</button>
            </form>
        </div>
    </div>
</div>
