<?php

use Shaack\Logger;
use Shaack\Reboot\CsrfProtection;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars($_POST["username"] ?? "", ENT_QUOTES, 'UTF-8');
    $password = $_POST["password"] ?? "";

    // Rate limiting: max 5 attempts per 15 minutes per IP
    $rateLimitFile = $reboot->getBaseFsPath() . "/local/.login_attempts";
    $attempts = [];
    if (file_exists($rateLimitFile)) {
        $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    $ip = $_SERVER['REMOTE_ADDR'];
    $now = time();
    // Clean old entries
    $attempts = array_filter($attempts, function($entry) use ($now) {
        return $entry['time'] > ($now - 900);
    });
    $ipAttempts = array_filter($attempts, function($entry) use ($ip) {
        return $entry['ip'] === $ip;
    });

    if (count($ipAttempts) >= 5) {
        $error = "Too many login attempts. Please try again later.";
    } else {
        CsrfProtection::validate($request);
        if ($username && $authentication->login($username, $password)) {
            // Clear attempts for this IP on success
            $attempts = array_filter($attempts, function($entry) use ($ip) {
                return $entry['ip'] !== $ip;
            });
            file_put_contents($rateLimitFile, json_encode(array_values($attempts)));
            Logger::info("Login success " . $username);
            $reboot->redirect($reboot->getBaseWebPath() . $site->getWebPath() . "/pages");
        } else {
            $attempts[] = ['ip' => $ip, 'time' => $now];
            file_put_contents($rateLimitFile, json_encode(array_values($attempts)));
            Logger::error("Login failed " . $username);
            $error = "Login failed, please try again.";
        }
    }
} else {
    $username = "";
}
?>

<div class="container">
    <div class="card mx-auto" style="max-width: 32rem; margin-top: 200px;">
        <div class="card-body">
            <?php if ($error) { ?>
                <script>statusMessage("<?= htmlspecialchars($error, ENT_QUOTES) ?>", "text-bg-danger")</script>
            <?php } ?>
            <form id="loginForm" class="center-horizontal form-md" method="post">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <fieldset>
                    <!-- <legend>Login</legend> -->
                    <div class="form-group mb-3">
                        <label for="username" class="sr-only">Username</label>
                        <input id="username" name="username" class="form-control form-control-pair-top mb-1"
                               placeholder="Username" required="required" type="text" value="<?= $username ?>"
                               autocapitalize="off" autocorrect="off" autocomplete="off">
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" class="form-control form-control-pair-bottom"
                               placeholder="Password"
                               type="password" required="required" value="">
                    </div>
                    <input id="check" name="check" type="hidden" value="">
                </fieldset>
                <button class="btn btn-primary btn-sm btn-block px-4" type="submit">Login</button>
            </form>
        </div>
    </div>
</div>