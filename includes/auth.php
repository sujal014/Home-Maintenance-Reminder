<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('hmr_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,                       // JavaScript cannot read the cookie
        'samesite' => 'Lax',                      // not sent on cross-site POSTs
        'secure'   => !empty($_SERVER['HTTPS']),  // HTTPS-only when available
    ]);
    session_start();
}

/** Escape text for safe output inside HTML. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** The logged-in user (id, name, email) or null. */
function currentUser(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    $stmt = getDb()->prepare('SELECT id, name, email FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function loginUser(int $userId): void
{
    session_regenerate_id(true);   // prevents session fixation
    $_SESSION['user_id'] = $userId;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Send the visitor to the login page unless they are logged in. */
function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

/* ---------- CSRF protection ---------- */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfValid(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
