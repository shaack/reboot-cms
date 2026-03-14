<?php

use Shaack\Reboot\CsrfProtection;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Request $request */
/** @var \Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");
$htpasswd = $authentication->getHtpasswd();

if (!$htpasswd->isEmpty()) {
    $reboot->redirect($reboot->getBaseWebPath() . $site->getWebPath() . "/login");
    return;
}

$error = null;
$username = htmlspecialchars($request->getParam("username") ?? "");
$password = $request->getParam("password") ?? "";

if ($username) {
    CsrfProtection::validate($request);
    try {
        if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $username)) {
            throw new \InvalidArgumentException("Username must contain only letters, numbers, underscores (max 64 chars)");
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException("Password must be at least 8 characters");
        }
        $localDir = $reboot->getBaseFsPath() . "/local";
        if (!is_dir($localDir)) {
            mkdir($localDir, 0700, true);
        }
        $htpasswd->addUser($username, $password);
        $authentication->setUserRole($username, \Shaack\Reboot\Authentication::ROLE_ADMIN);
        $authentication->login($username, $password);
        $reboot->redirect($reboot->getBaseWebPath() . $site->getWebPath() . "/pages");
        return;
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container">
    <div class="card mx-auto" style="max-width: 32rem; margin-top: 200px;">
        <div class="card-body">
            <h3>Create Admin Account</h3>
            <p class="text-muted">No users found. Create the first admin account to get started.</p>
            <?php if ($error) { ?>
                <script>statusMessage("<?= htmlspecialchars($error, ENT_QUOTES) ?>", "text-bg-danger")</script>
            <?php } ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <div class="form-group mb-3">
                    <label for="username" class="sr-only">Username</label>
                    <input id="username" name="username" class="form-control form-control-pair-top mb-1"
                           placeholder="Username" required type="text" value="<?= $username ?>"
                           pattern="[a-zA-Z0-9_]{1,64}" autocomplete="off">
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" class="form-control form-control-pair-bottom"
                           placeholder="Password (min. 8 characters)" type="password" required minlength="8"
                           autocomplete="new-password">
                </div>
                <button class="btn btn-primary btn-block px-4" type="submit">Create Account</button>
            </form>
        </div>
    </div>
</div>
