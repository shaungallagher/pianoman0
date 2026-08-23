<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/judge_functions.php';
require_role('judge');

$judge_id = current_user_id();

$stmt = $pdo->prepare("
    SELECT events.*
    FROM judges
    JOIN events ON events.id = judges.event_id
    WHERE judges.user_id=?
");
$stmt->execute([$judge_id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Judge Dashboard · Sprint";
include '../includes/header.php';
?>

<h1>Judge Dashboard</h1>

<div class="card-grid">
<?php foreach ($events as $e): ?>
    <a class="card" href="submissions.php?event_id=<?= (int)$e['id'] ?>">
        <h2><?= htmlspecialchars($e['name']) ?></h2>
        <p><?= htmlspecialchars(substr($e['description'], 0, 120)) ?>...</p>
    </a>
<?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
