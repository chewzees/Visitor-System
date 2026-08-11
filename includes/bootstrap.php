<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['APP_TIMEZONE']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', (string) $config['SESSION_LIFETIME']);
    session_set_cookie_params([
        'lifetime' => $config['SESSION_LIFETIME'],
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!empty($config['APP_DEBUG'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

$pdo = db_connect($config);
ensure_app_schema($pdo);
i18n_boot($config);
