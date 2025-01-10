<?php

use Shaack\Logger;

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var Shaack\Reboot\Authentication $authentication */
$authentication = $site->getAddOn("Authentication");

$username = htmlentities(@$_REQUEST["username"]);
$password = htmlentities(@$_REQUEST["password"]);
$error = null;
if ($username) {
    if ($authentication->login($username, $password)) {
        Logger::info("Login success " . $username);
        $reboot->redirect($reboot->getBaseWebPath() . $site->getWebPath() . "/pages");
    } else {
        Logger::error("Login failed " . $username);
        $error = "Login failed, please try again.";
    }
}
?>

<div class="container">
    <div class="card mx-auto" style="max-width: 32rem; margin-top: 200px;">
        <div class="card-body">
            <?php if ($error) { ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php } ?>
            <form id="loginForm" class="center-horizontal form-md" method="post">
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
                <button class="btn btn-primary btn-block px-4" type="submit">Login</button>
            </form>
        </div>
    </div>
</div>