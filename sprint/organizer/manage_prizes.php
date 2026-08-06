<?php
require_once '../config.php';
require_role('organizer');

$event_id = $_GET['event_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $message = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);

        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO prizes (event_id, name, description) VALUES (?,?,?)");
                $stmt->execute([$event_id, $name, $desc]);
                $message = "Prize added.";
            } catch (Exception $e) {
                if (function_exists('log_db_error')) log_db_error('Add prize failed: ' . $e->getMessage());
                $message = 'Failed to add prize.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM prizes WHERE event_id=?");
$stmt->execute([$event_id]);
$prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Prizes · Sprint";
include '../includes/header.php';
?>

<h1>Manage Prizes</h1>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_input_field() ?>
    <label>Prize Name
        <input type="text" name="name" required>
    </label>

    <label>Description
        <textarea name="description" rows="3"></textarea>
    </label>

    <button class="btn">Add Prize</button>
</form>

<h2>Prizes</h2>
<ul class="list">
<?php foreach ($prizes as $p): ?>
    <li>
        <strong><?= htmlspecialchars($p['name']) ?></strong><br>
        <?= htmlspecialchars($p['description']) ?>
    </li>
<?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
