<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$eventId = intval($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
$submissionId = intval($_POST['submission_id'] ?? $_GET['submission_id'] ?? 0);

if ($submissionId <= 0) {
    abort_page('Missing submission id', 400);
}

// CSRF for POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        abort_page('Invalid CSRF token', 400);
    }
}

$targetEventId = $eventId ?: 0;
$repoUrl = '';

// Ensure the submission belongs to the event and that the current user is on its team.
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.event_id, s.repo_url, t.id AS team_id
        FROM submissions s
        JOIN teams t ON t.id = s.team_id
        JOIN team_members tm ON tm.team_id = t.id
        WHERE s.id = ? AND tm.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$submissionId, current_user_id()]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        abort_page('Not allowed to refresh this submission.', 403);
    }

    if ($eventId && intval($submission['event_id']) !== $eventId) {
        abort_page('Submission does not belong to this event.', 400);
    }

    $targetEventId = (int)$submission['event_id'];
    $repoUrl = (string)($submission['repo_url'] ?? '');
    if ($repoUrl === '') {
        $_SESSION['profile_error'] = 'This submission has no repo URL to fetch from.';
        header('Location: ' . url('/sprint/public/event.php') . '?id=' . (int)$targetEventId);
        exit;
    }

    github_fetch_and_cache_repo_preview($pdo, (int)$submissionId, $repoUrl);

    $_SESSION['profile_success'] = 'GitHub preview refreshed.';
    log_event('INFO', 'github_preview_refresh_success', [
        'submission_id' => (int)$submissionId,
        'event_id' => (int)$targetEventId,
    ]);

} catch (Exception $e) {
    $_SESSION['profile_error'] = 'Failed to refresh GitHub preview.';
    log_event('ERROR', 'github_preview_refresh_failed', [
        'submission_id' => (int)$submissionId,
        'event_id' => (int)$targetEventId,
        'error' => $e->getMessage(),
    ]);
}

header('Location: ' . url('/sprint/public/event.php') . '?id=' . (int)$targetEventId);
exit;


