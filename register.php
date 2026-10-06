<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Already logged in? There is no reason to create another account.
if (currentUser()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$old    = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    $name     = trim((string)($_POST['name'] ?? ''));
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');
    $old      = ['name' => $name, 'email' => $email];

    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        $errors['name'] = 'Name must be 2 to 60 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {            // bcrypt ignores anything past 72 bytes
        $errors['password'] = 'Password must be 72 characters or fewer.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $db = getDb();

        // Rule: one account per email address.
        $stmt = $db->prepare('SELECT 1 FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email already exists. Please log in instead.';
        } else {
            try {
                $stmt = $db->prepare(
                    'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
                );
                $stmt->execute([
                    'name'  => $name,
                    'email' => $email,
                    'hash'  => password_hash($password, PASSWORD_DEFAULT),
                ]);
                loginUser((int)$db->lastInsertId());
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                // Two people registering the same email at the same instant:
                // the database's UNIQUE rule is the final safety net.
                if ($e->getCode() === '23000') {
                    $errors['email'] = 'An account with this email already exists. Please log in instead.';
                } else {
                    error_log($e->getMessage());
                    $errors['form'] = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · Home Maintenance Reminder</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<main class="auth-card card">
    <h1>🏠 Create your account</h1>
    <p class="muted">One account per email address.</p>

    <?php if (isset($errors['form'])): ?>
        <div class="alert"><?= h($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" action="register.php" id="register-form" data-auth-form="register" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">

        <div class="field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" maxlength="60" autocomplete="name"
                   value="<?= h($old['name']) ?>" required>
            <small class="error" data-error-for="name"><?= h($errors['name'] ?? '') ?></small>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="254" autocomplete="email"
                   value="<?= h($old['email']) ?>" required>
            <small class="error" data-error-for="email"><?= h($errors['email'] ?? '') ?></small>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="72"
                   autocomplete="new-password" required>
            <small class="error" data-error-for="password"><?= h($errors['password'] ?? '') ?></small>
        </div>

        <div class="field">
            <label for="confirm">Confirm password</label>
            <input type="password" id="confirm" name="confirm" maxlength="72"
                   autocomplete="new-password" required>
            <small class="error" data-error-for="confirm"><?= h($errors['confirm'] ?? '') ?></small>
        </div>

        <label class="show-pw"><input type="checkbox" id="show-pw"> Show passwords</label>

        <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
</main>
<script src="assets/js/auth.js"></script>
</body>
</html>
