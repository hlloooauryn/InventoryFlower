<?php
// Auth middleware - require login and role checks

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('pages/login.php'));
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    if (current_user()['role'] !== $role) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

function require_admin(): void
{
    require_role('admin');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

function is_admin(): bool
{
    return is_logged_in() && current_user()['role'] === 'admin';
}
