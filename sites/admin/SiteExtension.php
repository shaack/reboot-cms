<?php

namespace Shaack\Reboot;

class SiteExtension extends Site
{
    public function render(Request $request): string
    {
        return parent::render($request);
    }

    public function login($username, $password) {

    }
    public function logout() {

    }
    public function getUser() {

    }
}