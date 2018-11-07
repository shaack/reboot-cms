<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

class Slice {

    private $module;

    public function __construct($moduleName)
    {
        $this->module = new $moduleName;
    }

    public function render() {
        $this->module->render();
    }
}