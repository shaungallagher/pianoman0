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
        $email = trim($_POST['email']);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            try {
                $stmt = $pdo->prepare("INSERT INTO judges (user_id, event_id) VALUES (?,?)");
                $stmt->execute([$user['id'], $event_id]);
                $message = "Judge added.";
            } catch (Exception $e) {
                if (function_exists('log_db_error')) log_db_error('Add judge failed: ' . $e->getMessage());
                $message = 'Failed to add judge.';
            }
        } else {
            $message = "User not found.";
        }
    }
}

$stmt = $pdo->prepare("
    SELECT users.name, users.email
    FROM judges
    JOIN users ON users.id = judges.user_id
    WHERE judges.event_id = ?
");
$stmt->execute([$event_id]);
$judges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Judges · Sprint";
include '../includes/header.php';
?>

<h1>Manage Judges</h1>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_input_field() ?>
    <label>Add judge by email
        <input type="email" name="email" required>
    </label>
    <button class="btn">Add Judge</button>
</form>

<h2>Current Judges</h2>
<ul class="list">
<?php foreach ($judges as $j): ?>
    <li><?= htmlspecialchars($j['name']) ?> (<?= htmlspecialchars($j['email']) ?>)</li>
<?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
