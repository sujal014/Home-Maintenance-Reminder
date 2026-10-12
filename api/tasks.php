<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

const FREQUENCIES = [
    'once'      => null,
    'weekly'    => '+1 week',
    'monthly'   => '+1 month',
    'quarterly' => '+3 months',
    'biannual'  => '+6 months',
    'yearly'    => '+1 year',
];

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function readJsonBody(): array
{
    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

/** Returns [cleanData, errors]. */
function validateTask(array $in): array
{
    $errors = [];

    $title    = trim((string)($in['title'] ?? ''));
    $category = trim((string)($in['category'] ?? 'General'));
    $due      = trim((string)($in['due_date'] ?? ''));
    $freq     = (string)($in['frequency'] ?? 'once');
    $notes    = trim((string)($in['notes'] ?? ''));

    if ($title === '' || mb_strlen($title) > 100) {
        $errors['title'] = 'Title is required (max 100 characters).';
    }
    if ($category === '' || mb_strlen($category) > 50) {
        $errors['category'] = 'Category is required (max 50 characters).';
    }
    $d = DateTime::createFromFormat('Y-m-d', $due);
    if (!$d || $d->format('Y-m-d') !== $due) {
        $errors['due_date'] = 'Please enter a valid date.';
    }
    if (!array_key_exists($freq, FREQUENCIES)) {
        $errors['frequency'] = 'Invalid frequency.';
    }
    if (mb_strlen($notes) > 500) {
        $errors['notes'] = 'Notes must be 500 characters or fewer.';
    }

    return [[
        'title' => $title, 'category' => $category, 'due_date' => $due,
        'frequency' => $freq, 'notes' => $notes,
    ], $errors];
}

try {
    // --- Gatekeeping: must be logged in, and writes need the CSRF token ---
    $user = currentUser();
    if (!$user) {
        respond(['error' => 'Please log in.'], 401);
    }
    $userId = (int)$user['id'];

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'GET' && !csrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        respond(['error' => 'Invalid security token. Reload the page.'], 403);
    }

    $db = getDb();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Every query below filters on user_id, so people only ever see
    // and change their own tasks.
    switch ($method) {
        case 'GET':
            $stmt = $db->prepare(
                'SELECT * FROM tasks WHERE user_id = :uid ORDER BY completed ASC, due_date ASC'
            );
            $stmt->execute(['uid' => $userId]);
            respond($stmt->fetchAll());

        case 'POST':
            [$task, $errors] = validateTask(readJsonBody());
            if ($errors) {
                respond(['errors' => $errors], 422);
            }
            $task['user_id'] = $userId;
            $stmt = $db->prepare(
                'INSERT INTO tasks (user_id, title, category, due_date, frequency, notes)
                 VALUES (:user_id, :title, :category, :due_date, :frequency, :notes)'
            );
            $stmt->execute($task);
            respond(['id' => (int)$db->lastInsertId()], 201);

        case 'PUT':   // "mark complete"
            $stmt = $db->prepare('SELECT * FROM tasks WHERE id = :id AND user_id = :uid');
            $stmt->execute(['id' => $id, 'uid' => $userId]);
            $task = $stmt->fetch();
            if (!$task) {
                respond(['error' => 'Task not found.'], 404);
            }

            $today    = date('Y-m-d');
            $interval = FREQUENCIES[$task['frequency']];

            if ($interval === null) {
                $upd = $db->prepare(
                    'UPDATE tasks SET completed = 1, last_completed = :today
                     WHERE id = :id AND user_id = :uid'
                );
                $upd->execute(['today' => $today, 'id' => $id, 'uid' => $userId]);
            } else {
                $next = (new DateTime($today))->modify($interval)->format('Y-m-d');
                $upd  = $db->prepare(
                    'UPDATE tasks SET due_date = :next, last_completed = :today
                     WHERE id = :id AND user_id = :uid'
                );
                $upd->execute(['next' => $next, 'today' => $today, 'id' => $id, 'uid' => $userId]);
            }
            respond(['ok' => true]);

        case 'DELETE':
            $stmt = $db->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :uid');
            $stmt->execute(['id' => $id, 'uid' => $userId]);
            respond(['ok' => true]);

        default:
            respond(['error' => 'Method not allowed.'], 405);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(['error' => 'Server error.'], 500);
}
