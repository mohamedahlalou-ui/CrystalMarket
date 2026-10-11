<?php
declare(strict_types=1);

use CrystalMarket\Core\Database;

if (!defined('CM_ROOT')) {
    define('CM_ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'CrystalMarket\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = CM_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once CM_ROOT . '/app/helpers.php';

$config = cm_load_config();
cm_secure_session_start();

if (is_array($config) && isset($config['database']) && is_array($config['database'])) {
    Database::configure($config['database']);
}
