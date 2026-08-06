<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_role('organizer');

$event_id = $_GET['event_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $message = 'Invalid CSRF token.';
    } else {
        $text = trim($_POST['message']);
        if ($text) {
            try {
                $stmt = $pdo->prepare("INSERT INTO announcements (event_id, message) VALUES (?,?)");
                $stmt->execute([$event_id, $text]);
                $message = "Announcement posted";
            } catch (Exception $e) {
                if (function_exists('log_db_error')) log_db_error('Post announcement failed: ' . $e->getMessage());
                $message = 'Failed to post announcement.';
            }
        }
    }
}

$page_title = "Announcements · Sprint";
include '../includes/header.php';
?>

<h1>Post Announcement</h1>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_input_field() ?>
    <label>Message
        <textarea name="message" rows="4" required></textarea>
    </label>
    <button class="btn">Post</button>
</form>

<?php include '../includes/footer.php'; ?>
