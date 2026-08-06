<?php
require_once '../config.php';
require_role('organizer');

$event_id = intval($_GET['event_id'] ?? 0);
if (!$event_id) abort_page('Missing event_id', 400);

try {
    $stmt = $pdo->prepare('SELECT s.*, t.name AS team_name, e.name AS event_name FROM submissions s JOIN teams t ON t.id = s.team_id JOIN events e ON e.id = s.event_id WHERE s.event_id = ? ORDER BY s.created_at DESC');
    $stmt->execute([$event_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    abort_page('Failed to fetch submissions: ' . $e->getMessage(), 500);
}

$filename = 'submissions_event_' . $event_id . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$out = fopen('php://output', 'w');
if (!$out) abort_page('Failed to open output stream', 500);

fputcsv($out, ['id','event_id','event_name','team_id','team_name','title','description','repo_url','demo_url','screenshot_path','video_path','created_at']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'], $r['event_id'], $r['event_name'] ?? '', $r['team_id'], $r['team_name'] ?? '',
        $r['title'] ?? '', $r['description'] ?? '', $r['repo_url'] ?? '', $r['demo_url'] ?? '',
        $r['screenshot_path'] ?? '', $r['video_path'] ?? '', $r['created_at'] ?? ''
    ]);
}

fclose($out);
exit;
