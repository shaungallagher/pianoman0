<?php
require_once '../config.php';
require_role('organizer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        abort_page('Invalid CSRF token', 400);
    }
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    if ($id && in_array($action, ['ack','resolve'])) {
        if ($action === 'ack') {
            $stmt = $pdo->prepare("UPDATE emergency_alerts SET status='acknowledged' WHERE id=?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("UPDATE emergency_alerts SET status='resolved', resolved_at=CURRENT_TIMESTAMP, resolved_by=? WHERE id=?");
            $stmt->execute([current_user_id(), $id]);
        }
    }
}

$stmt = $pdo->query("SELECT ea.*, u.name AS reporter_name, e.name AS event_name FROM emergency_alerts ea LEFT JOIN users u ON u.id=ea.user_id LEFT JOIN events e ON e.id=ea.event_id ORDER BY ea.created_at DESC LIMIT 200");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Incident Alerts · Sprint";
include '../includes/header.php';
?>

<h1>Incident Alerts</h1>

<?php if (empty($alerts)): ?>
    <p>No alerts recorded.</p>
<?php else: ?>
    <div class="card-grid">
    <?php foreach ($alerts as $a): ?>
        <div class="card">
            <h3><?= htmlspecialchars($a['title'] ?: '(No title)') ?></h3>
            <p><strong>Event:</strong> <?= htmlspecialchars($a['event_name'] ?? '—') ?></p>
            <p><strong>Reporter:</strong> <?= htmlspecialchars($a['reporter_name'] ?? 'Anonymous') ?> — <small><?= htmlspecialchars($a['created_at']) ?></small></p>
            <p><?= nl2br(htmlspecialchars($a['description'])) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($a['location'] ?? '—') ?> — <strong>Severity:</strong> <?= htmlspecialchars($a['severity']) ?> — <strong>Status:</strong> <?= htmlspecialchars($a['status']) ?></p>

            <form method="post" style="margin-top:8px;">
                <?= csrf_input_field() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <?php if (($a['status'] ?? '') === 'open'): ?>
                    <button class="btn" name="action" value="ack">Acknowledge</button>
                <?php endif; ?>
                <?php if (($a['status'] ?? '') !== 'resolved'): ?>
                    <button class="btn" name="action" value="resolve">Resolve</button>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
