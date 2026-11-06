<?php

namespace Shaack\Reboot;

class CsrfProtection
{
    public static function getToken(): string
    {
        Session::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(Request $request): void
    {
        // only from the form body, a token in the URL would end up in logs and the Referer
        $token = $_POST["csrf_token"] ?? null;
        if (!is_string($token) || $token === "" || !hash_equals(self::getToken(), $token)) {
            http_response_code(403);
            throw new \RuntimeException("CSRF token validation failed");
        }
    }
}
