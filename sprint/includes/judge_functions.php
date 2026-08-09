<?php

function get_event_categories($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE event_id=? ORDER BY id ASC");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_judge_scores($pdo, $judge_id, $submission_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM scores
        WHERE judge_id=? AND submission_id=?
    ");
    $stmt->execute([$judge_id, $submission_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function judge_has_scored($pdo, $judge_id, $submission_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM scores
        WHERE judge_id=? AND submission_id=?
    ");
    $stmt->execute([$judge_id, $submission_id]);
    return $stmt->fetchColumn() > 0;
}
