<?php

use Shaack\Logger;
use Shaack\Reboot\AddOns\Admin\AdminHelper;
use Shaack\Reboot\CsrfProtection;
use Shaack\Reboot\LoginThrottle;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\AddOns\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars($_POST["username"] ?? "", ENT_QUOTES, 'UTF-8');
    $password = $_POST["password"] ?? "";

    // Rate limiting: max 5 attempts per 15 minutes per IP, checked and counted in one step
    $throttle = new LoginThrottle($reboot->getBaseFsPath() . "/local/.login_attempts");
    $ip = $_SERVER['REMOTE_ADDR'];
    CsrfProtection::validate($request);
    if (!$throttle->tryAttempt($ip)) {
        $error = "Too many login attempts. Please try again later.";
    } else {
        $remember = !empty($_POST["remember"]);
        if ($username && $authentication->login($username, $password, $remember)) {
            $throttle->clear($ip);
            Logger::info("Login success " . $username);
            $reboot->redirect($reboot->getBaseWebPath() . $site->getWebPath() . "/pages");
        } else {
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
            <?= AdminHelper::renderStatusMessages($error, null) ?>
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
                    <div class="form-check mb-3">
                        <input id="remember" name="remember" class="form-check-input" type="checkbox" value="1"<?= !empty($_POST["remember"]) ? " checked" : "" ?>>
                        <label for="remember" class="form-check-label">Stay signed in for 2 weeks</label>
                    </div>
                </fieldset>
                <button class="btn btn-primary btn-sm btn-block px-4" type="submit">Login</button>
            </form>
        </div>
    </div>
</div>
