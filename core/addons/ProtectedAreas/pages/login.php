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
$passwordFailed = false; // opens the password form again after a wrong password
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
        $passwordFailed = true;
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
<style>
    /* Default look of the login page, deliberately without specificity (:where), so the
       Bootstrap classes in the markup and the site's own rules for .protected-areas-* win. */
    :where(.protected-areas-login) { max-width: 28rem; }
    :where(.protected-areas-login) :where(input) { display: block; width: 100%; box-sizing: border-box; padding: 0.5rem 0.75rem; font: inherit; color: inherit; background: transparent; border: 1px solid currentColor; border-radius: 0.375rem; }
    :where(.protected-areas-login) :where(button, a.protected-areas-button) { display: inline-block; padding: 0.5rem 1.25rem; font: inherit; color: inherit; background: transparent; border: 1px solid currentColor; border-radius: 0.375rem; cursor: pointer; text-decoration: none; }
    :where(.protected-areas-login) :where(summary) { cursor: pointer; margin: 1.5rem 0 1rem; }
    :where(.protected-areas-login) :where(.protected-areas-error) { color: #b02a37; }
</style>
<div class="protected-areas-login">
    <h1><?= htmlspecialchars($texts['title']) ?></h1>
    <?php if ($denied && $user !== null) { ?>
        <p class="protected-areas-error alert alert-danger"><?= htmlspecialchars($texts['denied']) ?></p>
    <?php } ?>
    <?php if ($user !== null) { ?>
        <p><?= htmlspecialchars(sprintf($texts['loggedInAs'], $user)) ?></p>
        <p>
            <?php if (!$denied) { ?>
                <a class="protected-areas-button btn btn-primary" href="<?= htmlspecialchars($webPath . $next) ?>"><?= htmlspecialchars($texts['continue']) ?></a>
            <?php } ?>
            <a class="protected-areas-link btn btn-link" href="<?= htmlspecialchars($webPath . $protectedAreas->getLogoutPath($request)) ?>"><?= htmlspecialchars($texts['logout']) ?></a>
        </p>
    <?php } else { ?>
        <p><?= htmlspecialchars($texts['intro']) ?></p>
        <?php if ($error) { ?>
            <p class="protected-areas-error alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php } ?>
        <?php if ($notice) { ?>
            <p class="protected-areas-notice alert alert-success" role="status"><?= htmlspecialchars($notice) ?></p>
        <?php } ?>
        <?php if ($linkLogin) { ?>
            <form method="post" action="<?= htmlspecialchars($webPath . $protectedAreas->getLoginPath($request)) ?>" class="protected-areas-link-form">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                <input type="hidden" name="action" value="link">
                <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
                <p><?= htmlspecialchars($texts['linkIntro']) ?></p>
                <p>
                    <label for="protected-areas-link-email" class="form-label"><?= htmlspecialchars($texts['emailLabel']) ?></label>
                    <input type="email" id="protected-areas-link-email" name="email" class="form-control" autocomplete="email" required autofocus>
                </p>
                <p>
                    <button type="submit" class="protected-areas-button btn btn-primary"><?= htmlspecialchars($texts['linkSubmit']) ?></button>
                </p>
            </form>
            <details class="protected-areas-password-form"<?= $passwordFailed ? ' open' : '' ?>>
            <summary><?= htmlspecialchars($texts['orPassword']) ?></summary>
        <?php } ?>
        <form method="post" action="<?= htmlspecialchars($webPath . $protectedAreas->getLoginPath($request)) ?>"<?= $linkLogin ? '' : ' class="protected-areas-password-form"' ?>>
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <p>
                <label for="protected-areas-email" class="form-label"><?= htmlspecialchars($texts['emailLabel']) ?></label>
                <input type="email" id="protected-areas-email" name="email" class="form-control" autocomplete="email" required<?= $linkLogin ? '' : ' autofocus' ?>>
            </p>
            <p>
                <label for="protected-areas-password" class="form-label"><?= htmlspecialchars($texts['passwordLabel']) ?></label>
                <input type="password" id="protected-areas-password" name="password" class="form-control" autocomplete="current-password" required>
            </p>
            <p>
                <button type="submit" class="protected-areas-button btn <?= $linkLogin ? 'btn-outline-secondary' : 'btn-primary' ?>"><?= htmlspecialchars($texts['submit']) ?></button>
            </p>
        </form>
        <?php if ($linkLogin) { ?>
            </details>
        <?php } ?>
    <?php } ?>
</div>
