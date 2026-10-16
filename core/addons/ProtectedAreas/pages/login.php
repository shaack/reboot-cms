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
$linkLogin = $protectedAreas->canSendLoginLinks();
$error = null;
$notice = null;
if ($request->getParam('sent', 'get') === '1') {
    $notice = $texts['linkSent'];
} elseif ($request->getParam('invalid', 'get') === '1') {
    $error = $texts['linkInvalid'];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        CsrfProtection::validate($request);
        $email = trim((string)$request->getParam('email', 'post'));
        $password = (string)$request->getParam('password', 'post');
        $next = AccessControl::sanitizeNext($request->getParam('next', 'post'), $homePath);
        if ($linkLogin && $request->getParam('action', 'post') === 'link') {
            // Login link: the answer is the same whether the account exists or not.
            $protectedAreas->requestLoginLink($email, $ip, $request, $next);
            $params = ['sent' => '1'];
            if ($next !== $homePath) {
                $params['next'] = $next;
            }
            $reboot->redirect($webPath . $protectedAreas->getLoginPath($request) . '?' . http_build_query($params));
            return;
        }
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
        <?php if ($notice) { ?>
            <p class="protected-areas-notice" role="status"><?= htmlspecialchars($notice) ?></p>
        <?php } ?>
        <?php if ($linkLogin) { ?>
            <form method="post" action="<?= htmlspecialchars($webPath . $protectedAreas->getLoginPath($request)) ?>" class="protected-areas-link-form">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <input type="hidden" name="action" value="link">
                <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
                <p><?= htmlspecialchars($texts['linkIntro']) ?></p>
                <p>
                    <label for="protected-areas-link-email"><?= htmlspecialchars($texts['emailLabel']) ?></label><br>
                    <input type="email" id="protected-areas-link-email" name="email" autocomplete="email" required autofocus>
                </p>
                <p>
                    <button type="submit" class="protected-areas-button"><?= htmlspecialchars($texts['linkSubmit']) ?></button>
                </p>
            </form>
            <h2><?= htmlspecialchars($texts['orPassword']) ?></h2>
        <?php } ?>
        <form method="post" action="<?= htmlspecialchars($webPath . $protectedAreas->getLoginPath($request)) ?>" class="protected-areas-password-form">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <p>
                <label for="protected-areas-email"><?= htmlspecialchars($texts['emailLabel']) ?></label><br>
                <input type="email" id="protected-areas-email" name="email" autocomplete="email" required<?= $linkLogin ? '' : ' autofocus' ?>>
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
