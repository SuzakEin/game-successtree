<?php

/**
 * SuccessTree — PSR-4 autoloader (zero dependency).
 * Usage : require __DIR__ . '/path/to/successtree/src/autoload.php';
 */

declare(strict_types=1);

spl_autoload_register(static function ($class) {
    $prefix = 'SuccessTree\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
