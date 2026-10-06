<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!csrfValid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $stmt = getDb()->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        // Always run password_verify, even for unknown emails, so response
        // time does not reveal which emails are registered.
        $hash  = $row['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash) && $row;

        if ($valid) {
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                $upd = getDb()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                $upd->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $row['id']]);
            }
            loginUser((int)$row['id']);
            header('Location: index.php');
            exit;
        }
        // Deliberately vague: do not say whether the email or the password was wrong.
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Home Maintenance Reminder</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<main class="auth-card card">
    <h1>🏠 Welcome back</h1>
    <p class="muted">Log in to see your maintenance tasks.</p>

    <?php if ($error !== ''): ?>
        <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" id="login-form" data-auth-form="login" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="254" autocomplete="email"
                   value="<?= h($email) ?>" required>
            <small class="error" data-error-for="email"></small>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="72"
                   autocomplete="current-password" required>
            <small class="error" data-error-for="password"></small>
        </div>

        <label class="show-pw"><input type="checkbox" id="show-pw"> Show password</label>

        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>

    <p class="auth-switch">New here? <a href="register.php">Create an account</a></p>
</main>
<script src="assets/js/auth.js"></script>
</body>
</html>
