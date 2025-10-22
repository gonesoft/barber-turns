<?php
/**
 * Application bootstrap. Defines APP_ROOT and sets include path helpers.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__));
}

if (!defined('INC_PATH')) {
    define('INC_PATH', APP_ROOT . '/includes');
}

set_include_path(INC_PATH . PATH_SEPARATOR . get_include_path());
