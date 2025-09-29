# app/auth.php
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function auth_user(): ?array { return $_SESSION['user'] ?? null; }

function auth_require(): void {
    if (!auth_user()) redirect('login');
}

function auth_check(string $minRole): bool {
    $u = auth_user();
    if (!$u) return false;
    return role_weight($u['role']) >= role_weight($minRole);
}

function auth_require_role(string $minRole): void {
    if (!auth_check($minRole)) {
        http_response_code(403);
        die('Forbidden');
    }
}

function auth_login(string $email, string $password): bool {
    $u = model_user_by_email($email);
    if ($u && password_verify($password, $u['password_hash'])) {
        unset($u['password_hash']);
        $_SESSION['user'] = $u;
        return true;
    }
    return false;
}

function auth_logout(): void {
    unset($_SESSION['user']);
}

function install_needed(): bool {
    return model_users_count() === 0;
}
