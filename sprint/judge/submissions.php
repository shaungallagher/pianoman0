<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/judge_functions.php';

require_login();

$event_id = intval($_GET['event_id'] ?? 0);
if (!$event_id) abort_page('Missing event_id', 400);

$event = get_event($pdo, $event_id);
if (!$event) abort_page('Event not found', 404);

// Permission: if event uses judges, require judge role; if peer, allow participants who are part of the event
if (($event['judging_mode'] ?? 'judges') === 'judges') {
    if (!is_judge()) { abort_page('Access denied', 403); }
} else {
    if (!is_judge()) {
        $member = get_user_team($pdo, $event_id, current_user_id());
        if (!$member) { abort_page('Access denied', 403); }
    }
}

$subs = get_event_submissions($pdo, $event_id);

$page_title = "Submissions · " . ($event['name'] ?? 'Event');
include '../includes/header.php';
?>

<h1>Submissions for <?= htmlspecialchars($event['name']) ?></h1>

<?php if (empty($subs)): ?>
    <p>No submissions yet.</p>
<?php else: ?>
    <div class="card-grid">
    <?php foreach ($subs as $s): ?>
        <div class="card">
            <h3><?= htmlspecialchars($s['title']) ?></h3>
            <p class="meta">Team: <?= htmlspecialchars($s['team_name']) ?> — <?= htmlspecialchars($s['created_at']) ?></p>
            <p><?= htmlspecialchars(substr($s['description'] ?? '', 0, 240)) ?></p>
            <p><a class="btn" href="<?= url('judge/score.php') ?>?id=<?= (int)$s['id'] ?>">Score / Review</a></p>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
