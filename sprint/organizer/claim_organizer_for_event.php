<?php
require_once '../config.php';
require_once '../includes/functions.php';

require_login();

if (is_organizer()) {
    header('Location: dashboard.php');
    exit;
}

$event_id = $_GET['event_id'] ?? null;
if (!$event_id) {
    abort_page('Missing event_id', 400);
}

// Make sure the event exists
$event = null;
try {
    $stmt = $pdo->prepare("SELECT id, name FROM events WHERE id = ?");
    $stmt->execute([(int)$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $event = null;
}

if (!$event) {
    abort_page('Event not found', 404);
}

// If already organizer/admin, just go to organizer dashboard
if (is_organizer()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = "Become Organizer · Sprint";
include '../includes/header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        abort_page('Invalid CSRF token', 400);
    }

    try {
        // Upgrade role globally (current app uses a single global role gate).
        $stmt = $pdo->prepare("UPDATE users SET role = 'organizer' WHERE id = ?");
        $stmt->execute([current_user_id()]);

        if (!empty($_SESSION['user'])) {
            $_SESSION['user']['role'] = 'organizer';
        }

        $success = 'You are now an organizer for this hackathon.';
        header('Location: dashboard.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<h1>Become organizer</h1>

<p>You’re attending <strong><?= htmlspecialchars($event['name']) ?></strong>. Want to run this hackathon as an organizer?</p>

<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post">
    <?= csrf_input_field() ?>
    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <button class="btn" type="submit">Become organizer</button>
        <a class="btn outline" href="<?= url('/sprint/public/event.php') . '?id=' . (int)$event_id ?>">Back to event</a>
    </div>
</form>

<?php include '../includes/footer.php'; ?>

