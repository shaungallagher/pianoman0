<?php
require_once '../config.php';
require_role('organizer');

$event_id = $_GET['event_id'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE event_id=?");
$stmt->execute([$event_id]);
$team_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE event_id=?");
$stmt->execute([$event_id]);
$submission_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS day, COUNT(*) AS count
    FROM submissions
    WHERE event_id=?
    GROUP BY DATE(created_at)
");
$stmt->execute([$event_id]);
$submission_days = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Analytics · Sprint";
include '../includes/header.php';
?>

<h1>Analytics</h1>

<div class="card-grid">
    <div class="card">
        <h2><?= (int)$team_count ?></h2>
        <p>Teams</p>
    </div>

    <div class="card">
        <h2><?= (int)$submission_count ?></h2>
        <p>Submissions</p>
    </div>
</div>

<h2>Submissions Over Time</h2>
<ul class="list">
<?php foreach ($submission_days as $d): ?>
    <li><?= htmlspecialchars($d['day']) ?> — <?= (int)$d['count'] ?> submissions</li>
<?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
