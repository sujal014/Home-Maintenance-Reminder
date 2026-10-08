<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// POST + CSRF token, so another website cannot log you out with a hidden image/link.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid($_POST['csrf'] ?? null)) {
    logoutUser();
}
header('Location: login.php');
exit;
