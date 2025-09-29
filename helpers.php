# app/helpers.php
<?php
function base_url(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $cfg = require __DIR__ . '/config.php';
    if (!empty($cfg['app']['base_url'])) return $cached = rtrim($cfg['app']['base_url'], '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $cached = rtrim("$scheme://$host$script", '/');
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): void {
    header('Location: ' . base_url() . '/' . ltrim($path, '/'));
    exit;
}

function flash(string $key, ?string $val=null): ?string {
    if ($val === null) {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }
    $_SESSION['_flash'][$key] = $val;
    return null;
}

function csrf_token(): string {
    $cfg = require __DIR__ . '/config.php';
    $key = $cfg['app']['csrf_key'];
    if (empty($_SESSION[$key])) $_SESSION[$key] = bin2hex(random_bytes(16));
    return $_SESSION[$key];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">';
}

function csrf_verify(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        die('CSRF token mismatch.');
    }
}

function role_weight(string $role): int {
    return ['member'=>1, 'staff'=>2, 'admin'=>3][$role] ?? 0;
}
