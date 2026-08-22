<?php
require_once '../config.php';
require_once '../includes/functions.php';

$event_id = (int)($_GET['event_id'] ?? 0);
$event = get_event($pdo, $event_id);
if (!$event) abort_page('Event not found', 404);

// Cached leaderboard results per event.
$cacheDir = __DIR__ . '/../data/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheFile = $cacheDir . '/leaderboard_' . $event_id . '.json';
$cacheTtl = 20; // seconds
$rows = null;

$useCache = is_file($cacheFile) && (time() - filemtime($cacheFile) <= $cacheTtl);
if ($useCache) {
    $raw = @file_get_contents($cacheFile);
    $rows = $raw ? json_decode($raw, true) : null;
}

if (!is_array($rows)) {
    $stmt = $pdo->prepare("
    SELECT s.*, t.name AS team_name, COALESCE(AVG(scores.score),0) AS avg_score
    FROM submissions s
    JOIN teams t ON t.id = s.team_id
    LEFT JOIN scores ON scores.submission_id = s.id
    WHERE s.event_id = ?
    GROUP BY s.id
    ORDER BY avg_score DESC
");
    $stmt->execute([$event_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    @file_put_contents($cacheFile, json_encode($rows), LOCK_EX);
}

$page_title = "Leaderboard · Sprint";
include '../includes/header.php';
?>

<h1>Leaderboard — <?= htmlspecialchars($event['name']) ?></h1>

<table class="table">
    <thead>
        <tr>
            <th>Rank</th>
            <th>Project</th>
            <th>Team</th>
            <th>Avg Score</th>
        </tr>
    </thead>
    <tbody>
        <?php $rank = 1; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= $rank++ ?></td>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['team_name']) ?></td>
                <td><?= number_format($r['avg_score'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>
