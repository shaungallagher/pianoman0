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
        $weight = floatval($_POST['weight']);

        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (event_id, name, weight) VALUES (?,?,?)");
                $stmt->execute([$event_id, $name, $weight]);
                $message = "Category added.";
            } catch (Exception $e) {
                if (function_exists('log_db_error')) log_db_error('Add category failed: ' . $e->getMessage());
                $message = 'Failed to add category.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE event_id=?");
$stmt->execute([$event_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Judging Categories · Sprint";
include '../includes/header.php';
?>

<h1>Judging Categories</h1>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_input_field() ?>
    <label>Category Name
        <input type="text" name="name" required>
    </label>

    <label>Weight (default 1)
        <input type="number" step="0.1" name="weight" value="1">
    </label>

    <button class="btn">Add Category</button>
</form>

<h2>Current Categories</h2>
<ul class="list">
<?php foreach ($categories as $c): ?>
    <li>
        <strong><?= htmlspecialchars($c['name']) ?></strong>
        (weight: <?= htmlspecialchars($c['weight']) ?>)
    </li>
<?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
