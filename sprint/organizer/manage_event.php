<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_role('organizer');

$event_id = (int)($_GET['id'] ?? 0);
$event = get_event($pdo, $event_id);

// Invalidate simple caches when entering event management.
$cacheDir = __DIR__ . '/../data/cache';
if (is_dir($cacheDir)) {
    foreach (['leaderboard_' . $event_id . '.json', 'events_list.json'] as $f) {
        @unlink($cacheDir . '/' . $f);
    }
}


if (!$event) abort_page('Event not found', 404);

$page_title = "Manage Event · Sprint";
include '../includes/header.php';
?>

<h1>Manage <?= htmlspecialchars($event['name']) ?></h1>

<p><strong>Judging mode:</strong> <?= htmlspecialchars($event['judging_mode'] ?? 'judges') ?></p>

<div class="card-grid">
    <a class="card" href="judges.php?event_id=<?= (int)$event_id ?>">
        <h2>Judges</h2>
        <p>Assign and manage judges</p>
    </a>

    <a class="card" href="manage_prizes.php?event_id=<?= (int)$event_id ?>">
        <h2>Prizes</h2>
        <p>Create and assign prizes</p>
    </a>

    <a class="card" href="analytics.php?event_id=<?= (int)$event_id ?>">
        <h2>Analytics</h2>
        <p>View event statistics</p>
    </a>

    <a class="card" href="export_submissions.php?event_id=<?= (int)$event_id ?>">
        <h2>Export</h2>
        <p>Download submissions CSV</p>
    </a>

    <a class="card" href="../public/announcements.php?event_id=<?= (int)$event_id ?>">
        <h2>Announcements</h2>
        <p>Post updates to participants</p>
    </a>
</div>

<?php include '../includes/footer.php'; ?>
