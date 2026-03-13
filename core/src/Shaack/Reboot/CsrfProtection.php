<?php

namespace Shaack\Reboot;

class CsrfProtection
{
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(Request $request): void
    {
        $token = $request->getParam("csrf_token");
        if (!$token || !hash_equals(self::getToken(), $token)) {
            http_response_code(403);
            throw new \RuntimeException("CSRF token validation failed");
        }
    }
}
