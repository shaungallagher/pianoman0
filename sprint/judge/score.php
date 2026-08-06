<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/judge_functions.php';
require_login();

$submission_id = intval($_GET['id'] ?? 0);
$submission = get_submission($pdo, $submission_id);

if (!$submission) abort_page('Submission not found', 404);

$event_id = $submission['event_id'];
$event = get_event($pdo, $event_id);
$categories = get_event_categories($pdo, $event_id);

// Permission: if event uses judges, require judge role; if peer, allow
// participants who are part of the event to score (or judges always allowed)
if ($event && ($event['judging_mode'] ?? 'judges') === 'judges') {
    if (!is_judge()) {
        abort_page('Access denied', 403);
    }
} else {
    // peer scoring: ensure the current user is a participant in the event or a judge
    if (!is_judge()) {
        $member = get_user_team($pdo, $event_id, current_user_id());
        if (!$member) {
            abort_page('Access denied', 403);
        }
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        http_response_code(400);
        $message = "Invalid CSRF token.";
    } else {
        $judgeId = current_user_id();

        // Make scoring idempotent to prevent duplicate rows if a judge refreshes.
        // Use a transaction so we never end up with partial scores.
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM scores WHERE judge_id = ? AND submission_id = ?")->execute([$judgeId, $submission_id]);

            foreach ($categories as $c) {
                // Defensive reads: avoid PHP notices + unintended 0 scores when fields are missing.
                $scoreRaw = $_POST['score_' . $c['id']] ?? 0;
                $score = intval($scoreRaw);
                if ($score < 0) $score = 0;
                if ($score > 10) $score = 10;

                $comment = trim($_POST['comment_' . $c['id']] ?? '');
                // Optional safety cap to limit stored payload size.
                if (mb_strlen($comment) > 2000) {
                    $comment = mb_substr($comment, 0, 2000);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO scores (submission_id, judge_id, category, score, comment)
                    VALUES (?,?,?,?,?)
                ");
                $stmt->execute([
                    $submission_id,
                    $judgeId,
                    $c['name'],
                    $score,
                    $comment
                ]);
            }

            $pdo->commit();
            $message = "Scores saved.";
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            $message = "Failed to save scores.";
            if (function_exists('log_db_error')) {
                log_db_error('Score save failed: ' . $e->getMessage());
            }
        }
    }
}


$page_title = "Score Submission · Sprint";
include '../includes/header.php';
?>

<h1>Score: <?= htmlspecialchars($submission['title']) ?></h1>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_input_field() ?>
<?php foreach ($categories as $c): ?>
    <div class="card">
        <h3><?= htmlspecialchars($c['name']) ?></h3>
        <p class="meta">Weight: <?= htmlspecialchars($c['weight']) ?></p>

        <label>Score (0–10)
            <input type="number" name="score_<?= (int)$c['id'] ?>" min="0" max="10" required>
        </label>

        <label>Comment
            <input type="text" name="comment_<?= (int)$c['id'] ?>">
        </label>
    </div>
<?php endforeach; ?>

    <button class="btn">Submit Scores</button>
</form>

<?php include '../includes/footer.php'; ?>
