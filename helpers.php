<?php
declare(strict_types=1);

function cm_load_config(): ?array
{
    $candidates = [dirname(CM_ROOT) . '/config.local.php', CM_ROOT . '/config/config.local.php'];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $value = require $path;
            return is_array($value) ? $value : null;
        }
    }
    return null;
}

function cm_secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $private = CM_ROOT . '/storage/private/sessions';
    if (!is_dir($private)) {
        @mkdir($private, 0700, true);
    }
    if (is_dir($private) && is_writable($private)) {
        session_save_path($private);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function cm_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cm_csrf_token(): string
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function cm_csrf_valid(mixed $candidate): bool
{
    return is_string($candidate) && isset($_SESSION['_csrf'])
        && is_string($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $candidate);
}

function cm_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
}
