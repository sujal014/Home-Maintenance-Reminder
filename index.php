<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();       // not logged in -> redirected to login.php
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
    <title>Home Maintenance Reminder</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="user-bar">
        <span>Signed in as <strong><?= h($user['name']) ?></strong> (<?= h($user['email']) ?>)</span>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <button type="submit" class="btn btn-light">Log out</button>
        </form>
    </div>
    <h1>🏠 Home Maintenance Reminder</h1>
    <p>Stay ahead of repairs, one task at a time.</p>
</header>

<main class="container">
    <section class="card">
        <h2>Add a task</h2>
        <form id="task-form" novalidate>
            <div class="field">
                <label for="title">Task</label>
                <input type="text" id="title" name="title" maxlength="100"
                       placeholder="e.g. Replace HVAC filter">
                <small class="error" data-error-for="title"></small>
            </div>

            <div class="row">
                <div class="field">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option>General</option>
                        <option>HVAC</option>
                        <option>Plumbing</option>
                        <option>Electrical</option>
                        <option>Exterior</option>
                        <option>Appliances</option>
                        <option>Safety</option>
                    </select>
                </div>

                <div class="field">
                    <label for="due_date">Due date</label>
                    <input type="date" id="due_date" name="due_date">
                    <small class="error" data-error-for="due_date"></small>
                </div>

                <div class="field">
                    <label for="frequency">Repeats</label>
                    <select id="frequency" name="frequency">
                        <option value="once">Never</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Every 3 months</option>
                        <option value="biannual">Every 6 months</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="notes">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="2" maxlength="500"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Add task</button>
            <small class="error" id="form-error"></small>
        </form>
    </section>

    <section class="card">
        <div class="list-header">
            <h2>Your tasks</h2>
            <div class="filters" id="filters">
                <button class="filter active" data-filter="all">All</button>
                <button class="filter" data-filter="overdue">Overdue</button>
                <button class="filter" data-filter="soon">Due soon</button>
                <button class="filter" data-filter="done">Done</button>
            </div>
        </div>
        <ul id="task-list" class="task-list"></ul>
        <p id="empty-state" class="empty" hidden>Nothing here. Add your first task above!</p>
    </section>
</main>

<footer class="site-footer">&copy; <?= $year ?> Home Maintenance Reminder</footer>
<script src="assets/js/app.js"></script>
</body>
</html>
