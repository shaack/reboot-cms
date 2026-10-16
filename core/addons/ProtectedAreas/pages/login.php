<?php
/**
 * The login page of the ProtectedAreas AddOn. Rendered inside the site
 * template like any PHP page in site/pages/, so it looks like the site.
 */

/** @var \Shaack\Reboot\Reboot $reboot */
/** @var \Shaack\Reboot\Site $site */
/** @var \Shaack\Reboot\Page $page */
/** @var \Shaack\Reboot\Request $request */

use Shaack\Reboot\AddOns\ProtectedAreas;
use Shaack\Reboot\AddOns\ProtectedAreas\AccessControl;
use Shaack\Reboot\CsrfProtection;

/** @var ProtectedAreas $protectedAreas */
$protectedAreas = $site->getAddOn('ProtectedAreas');
$texts = $protectedAreas->getTexts($request->getLanguage());
$webPath = $site->getWebPath();
$homePath = $request->getPath() !== $request->getPathWithoutLanguage() ? '/' . $request->getLanguage() : '/';
$next = AccessControl::sanitizeNext($request->getParam('next', 'get'), $homePath);
$denied = $request->getParam('denied', 'get') === '1';
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        CsrfProtection::validate($request);
        $email = trim((string)$request->getParam('email', 'post'));
        $password = (string)$request->getParam('password', 'post');
        $next = AccessControl::sanitizeNext($request->getParam('next', 'post'), $homePath);
        if ($protectedAreas->isThrottled($ip)) {
            $error = $texts['tooManyAttempts'];
        } elseif ($protectedAreas->login($email, $password, $ip)) {
            $reboot->redirect($webPath . $next);
            return;
        } else {
            usleep(300000); // slow down scripted guessing a little
            $error = $protectedAreas->isThrottled($ip) ? $texts['tooManyAttempts'] : $texts['wrongCredentials'];
        }
    } catch (\RuntimeException $e) {
        $error = $texts['wrongCredentials'];
    }
}

$user = $protectedAreas->getUser();
?>
<div class="protected-areas-login">
    <h1><?= htmlspecialchars($texts['title']) ?></h1>
    <?php if ($denied && $user !== null) { ?>
        <p class="protected-areas-error"><?= htmlspecialchars($texts['denied']) ?></p>
    <?php } ?>
    <?php if ($user !== null) { ?>
        <p><?= htmlspecialchars(sprintf($texts['loggedInAs'], $user)) ?></p>
        <p>
            <?php if (!$denied) { ?>
                <a class="protected-areas-button" href="<?= htmlspecialchars($webPath . $next) ?>"><?= htmlspecialchars($texts['continue']) ?></a>
            <?php } ?>
            <a class="protected-areas-link" href="<?= htmlspecialchars($webPath . $protectedAreas->getLogoutPath($request)) ?>"><?= htmlspecialchars($texts['logout']) ?></a>
        </p>
    <?php } else { ?>
        <p><?= htmlspecialchars($texts['intro']) ?></p>
        <?php if ($error) { ?>
            <p class="protected-areas-error" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php } ?>
        <form method="post" action="<?= htmlspecialchars($webPath . $protectedAreas->getLoginPath($request)) ?>">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <p>
                <label for="protected-areas-email"><?= htmlspecialchars($texts['emailLabel']) ?></label><br>
                <input type="email" id="protected-areas-email" name="email" autocomplete="email" required autofocus>
            </p>
            <p>
                <label for="protected-areas-password"><?= htmlspecialchars($texts['passwordLabel']) ?></label><br>
                <input type="password" id="protected-areas-password" name="password" autocomplete="current-password" required>
            </p>
            <p>
                <button type="submit" class="protected-areas-button"><?= htmlspecialchars($texts['submit']) ?></button>
            </p>
        </form>
    <?php } ?>
</div>
