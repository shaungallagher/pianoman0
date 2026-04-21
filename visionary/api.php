<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();

function debug_log($label, $data) {
    $enabled = (getenv('VISIONARY_DEBUG') === '1') || is_file(__DIR__ . '/data/enable_api_debug');
    if (!$enabled) return;
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . '/api_debug.log';
    $entry = date('c') . " | " . $label . " | " . json_encode($data) . "\n";
    @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function require_user() {
    $user = current_user();
    if (!$user) {
        jsonResponse(['success' => false, 'error' => 'Login required or banned'], 401);
    }
    if (isset($user['role']) && $user['role'] === 'banned') {
        jsonResponse(['success' => false, 'error' => 'Banned users cannot perform this action'], 403);
    }
    return $user;
}

$action = $_REQUEST['action'] ?? $_REQUEST['a'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : null);
debug_log('request_start', ['action'=>$action, 'method'=>$_SERVER['REQUEST_METHOD'], 'remote'=>$_SERVER['REMOTE_ADDR'] ?? null]);

if (!verify_api_csrf()) {
    jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    if ($action === 'list') {
        $sort = $_REQUEST['sort'] ?? 'date';
        $limit = (int)($_REQUEST['limit'] ?? 20);
        $offset = (int)($_REQUEST['offset'] ?? 0);
        $user = current_user();
        
        $user_id = $user ? $user['id'] : null;
        $sql = "
            SELECT 
                i.id, i.title, i.description, i.author_name, i.developer_name, 
                i.status, i.created_at, i.updated_at, i.tags,
                COUNT(DISTINCT il.id) as likes_count,
                COUNT(DISTINCT m.id) as messages_count,
                CASE WHEN ulike.id IS NOT NULL THEN 1 ELSE 0 END as user_liked,
                CASE WHEN ufav.id IS NOT NULL THEN 1 ELSE 0 END as user_favorited
            FROM ideas i
            LEFT JOIN idea_likes il ON il.idea_id = i.id
            LEFT JOIN messages m ON m.idea_id = i.id
            LEFT JOIN idea_likes ulike ON ulike.idea_id = i.id AND ulike.user_id = ?
            LEFT JOIN idea_favorites ufav ON ufav.idea_id = i.id AND ufav.user_id = ?
            GROUP BY i.id
            ORDER BY i.created_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        $allIdeas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allIdeas as &$idea) {
            $idea['likes_count'] = (int)$idea['likes_count'];
            $idea['messages_count'] = (int)$idea['messages_count'];
            $idea['user_liked'] = (bool)$idea['user_liked'];
            $idea['user_favorited'] = (bool)$idea['user_favorited'];
        }
        
        if ($sort === 'likes') {
            usort($allIdeas, fn($a, $b) => $b['likes_count'] <=> $a['likes_count'] ?: $b['created_at'] <=> $a['created_at']);
        } elseif ($sort === 'messages') {
            usort($allIdeas, fn($a, $b) => $b['messages_count'] <=> $a['messages_count'] ?: $b['created_at'] <=> $a['created_at']);
        }
        
        $total = count($allIdeas);
        $ideas = array_slice($allIdeas, $offset, $limit);
        debug_log('list', ['count'=>$total, 'returned'=>count($ideas), 'sort'=>$sort, 'limit'=>$limit, 'offset'=>$offset]);
        jsonResponse(['success' => true, 'data' => $ideas, 'total' => $total]);
    }

    if ($action === 'update_idea') {
        $user = require_user();
        $id = (int)($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        if (!$id || !$title) jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        $bad = check_forbidden($title, 'idea title', $user);
        if (!$bad) $bad = check_forbidden($description, 'idea description', $user);
        if ($bad) jsonResponse(['success' => false, 'error' => forbidden_message('content', $bad)], 400);
        // check if user is author
        $stmt = $pdo->prepare('SELECT author_name FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $idea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$idea || $idea['author_name'] !== $user['username']) jsonResponse(['success' => false, 'error' => 'Not authorized'], 403);
        $stmt = $pdo->prepare('UPDATE ideas SET title = ?, description = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$title, $description, date('c'), $id]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'delete_idea') {
        $user = require_user();
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id'], 400);
        // check if user is author
        $stmt = $pdo->prepare('SELECT author_name FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $idea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$idea || $idea['author_name'] !== $user['username']) jsonResponse(['success' => false, 'error' => 'Not authorized'], 403);
        $stmt = $pdo->prepare('DELETE FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        // also delete likes and messages?
        $pdo->prepare('DELETE FROM idea_likes WHERE idea_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM messages WHERE idea_id = ?')->execute([$id]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'create_idea') {
        $user = require_user();
        if (!in_array($user['role'], ['poster','both'])) jsonResponse(['success' => false, 'error' => 'Not authorized to post ideas'], 403);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $tags = trim($input['tags'] ?? '');
        if (!$title) jsonResponse(['success' => false, 'error' => 'Title required'], 400);
        $bad = check_forbidden($title, 'idea title', $user);
        if (!$bad) $bad = check_forbidden($description, 'idea description', $user);
        if (!$bad) $bad = check_forbidden($tags, 'idea tags', $user);
        if ($bad) jsonResponse(['success' => false, 'error' => forbidden_message('content', $bad)], 400);
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO ideas (title, description, tags, author_name, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $tags, $user['username'], $now]);
        $id = $pdo->lastInsertId();
        jsonResponse(['success' => true, 'id' => $id]);
    }

    if ($action === 'report_idea') {
        $user = require_user();
        $id = (int)($input['id'] ?? 0);
        $reason = trim($input['reason'] ?? '');
        if (!$id || !$reason) jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        // persistent idea report for admin review
        $stmt = $pdo->prepare('INSERT INTO user_reports (reporter_id, reporter_username, reported_user_id, reported_username, reason, details, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $user['username'], 0, '', $reason, 'Idea ID: ' . $id, 'new', date('c'), date('c')]);
        debug_log('report_idea', ['idea_id'=>$id, 'user'=>$user['id'], 'reason'=>$reason]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'report_user') {
        $user = require_user();
        $reported = trim($input['reported_username'] ?? '');
        $reason = trim($input['reason'] ?? '');
        $details = trim($input['details'] ?? '');
        if (!$reported || !$reason) jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$reported]);
        $rep = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rep) jsonResponse(['success' => false, 'error' => 'Reported user not found'], 404);
        $reported_id = (int)$rep['id'];
        $stmt = $pdo->prepare('INSERT INTO user_reports (reporter_id, reporter_username, reported_user_id, reported_username, reason, details, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $user['username'], $reported_id, $reported, $reason, $details, 'new', date('c'), date('c')]);
        debug_log('report_user', ['reported'=>$reported,'reporter'=>$user['id'],'reason'=>$reason]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'admin_message_send') {
        $user = require_user();
        $subject = trim($input['subject'] ?? '');
        $message = trim($input['message'] ?? '');
        $attachment = validate_attachment_url(trim($input['attachment_url'] ?? ''));
        if (!$subject || !$message) jsonResponse(['success' => false, 'error' => 'Subject and message required'], 400);
        if ($bad = check_forbidden($subject, 'admin subject', $user)) jsonResponse(['success' => false, 'error' => forbidden_message('subject', $bad)], 400);
        if ($bad = check_forbidden($message, 'admin message', $user)) jsonResponse(['success' => false, 'error' => forbidden_message('message', $bad)], 400);
        $stmt = $pdo->prepare('INSERT INTO admin_messages (user_id, username, subject, message, attachment_url, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $user['username'], $subject, $message, $attachment ?: null, 'new', date('c')]);
        try {
            $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $admin) {
                create_notification((int)$admin['id'], 'admin_message', 'New message from ' . $user['username'], 'admin.php', $user['id']);
            }
        } catch (Throwable $e) {
        }
        jsonResponse(['success' => true]);
    }

    if ($action === 'admin_messages_list') {
        $user = require_user();
        if ($user['role'] !== 'admin') jsonResponse(['success' => false, 'error' => 'Admin only'], 403);
        $stmt = $pdo->prepare('SELECT * FROM admin_messages ORDER BY created_at DESC');
        $stmt->execute();
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'data' => $notes]);
    }

    if ($action === 'admin_message_reply') {
        $user = require_user();
        if ($user['role'] !== 'admin') jsonResponse(['success' => false, 'error' => 'Admin only'], 403);
        $id = (int)($input['id'] ?? 0);
        $reply = trim($input['reply'] ?? '');
        if (!$id || !$reply) jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        $stmt = $pdo->prepare('UPDATE admin_messages SET admin_reply = ?, reply_by = ?, status = ?, replied_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$reply, $user['username'], 'answered', date('c'), date('c'), $id]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'current_user') {
        $u = current_user();
        debug_log('current_user', ['user'=>$u]);
        jsonResponse(['success' => true, 'data' => $u ?: null]);
    }

    if ($action === 'create') {
        $user = require_user();
        debug_log('create_attempt', ['user'=>$user, 'input'=>$input]);
        if (!in_array($user['role'], ['poster','both'])) jsonResponse(['success' => false, 'error' => 'Not authorized to post ideas'], 403);
        $title = trim($input['title'] ?? '');
        if ($title === '') jsonResponse(['success' => false, 'error' => 'Title required'], 400);
        $description = $input['description'] ?? '';
        $tags = trim($input['tags'] ?? '');
        if ($bad = check_forbidden($title, 'idea title', $user)) jsonResponse(['success'=>false,'error'=>forbidden_message('title',$bad)],400);
        if ($description && ($bad = check_forbidden($description, 'idea description', $user))) jsonResponse(['success'=>false,'error'=>forbidden_message('description',$bad)],400);
        if ($tags && ($bad = check_forbidden($tags, 'idea tags', $user))) jsonResponse(['success'=>false,'error'=>forbidden_message('tags',$bad)],400);
        $author = $user['username'];
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO ideas (title, description, tags, author_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $tags, $author, 'open', $now, $now]);
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log('create_success', ['id'=>$id]);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    if ($action === 'claim') {
        $user = require_user();
        debug_log('claim_attempt', ['user'=>$user, 'input'=>$input]);
        if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can claim ideas'], 403);
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id'], 400);
        $dev = $user['username'];
        $now = date('c');
        $stmt = $pdo->prepare('UPDATE ideas SET developer_name = ?, status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$dev, 'in_progress', $now, $id]);
        $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log('claim_success', ['id'=>$id]);
        // notify idea author that their idea was claimed!
        try {
            if (!empty($row['author_name'])) {
                $q = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $q->execute([$row['author_name']]);
                $au = $q->fetch(PDO::FETCH_ASSOC);
                if ($au) create_notification($au['id'], 'idea_claimed', $user['username'] . ' claimed your idea "' . ($row['title'] ?? '') . '"', 'idea.php?id=' . $id, $user['id']);
            }
        } catch (Throwable $e) {}
        jsonResponse(['success' => true, 'data' => $row]);
    }

    if ($action === 'complete') {
        $user = require_user();
        debug_log('complete_attempt', ['user'=>$user, 'input'=>$input]);
        if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can mark complete'], 403);
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id'], 400);
        $now = date('c');
        $stmt = $pdo->prepare('UPDATE ideas SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(['completed', $now, $id]);
        $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log('complete_success', ['id'=>$id]);
        // notify idea author and developer that idea completed!
        try {
            if (!empty($row['author_name'])) {
                $q = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $q->execute([$row['author_name']]);
                $au = $q->fetch(PDO::FETCH_ASSOC);
                if ($au) create_notification($au['id'], 'idea_completed', 'Your idea "' . ($row['title'] ?? '') . '" was completed', 'idea.php?id=' . $id, $user['id']);
            }
            if (!empty($row['developer_name'])) {
                $q2 = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $q2->execute([$row['developer_name']]);
                $du = $q2->fetch(PDO::FETCH_ASSOC);
                if ($du) create_notification($du['id'], 'idea_completed', 'Idea "' . ($row['title'] ?? '') . '" marked completed', 'idea.php?id=' . $id, $user['id']);
            }
        } catch (Throwable $e) {}
        jsonResponse(['success' => true, 'data' => $row]);
    }

    if ($action === 'idea') {
        $id = (int)($_REQUEST['id'] ?? ($input['id'] ?? 0));
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id'], 400);
        $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonResponse(['success' => false, 'error' => 'Not found'], 404);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    if ($action === 'messages') {
        $id = (int)($_REQUEST['id'] ?? ($input['id'] ?? 0));
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id'], 400);
        $stmt = $pdo->prepare('SELECT * FROM messages WHERE idea_id = ? ORDER BY created_at ASC');
        $stmt->execute([$id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'data' => $msgs]);
    }

    if ($action === 'post_message') {
        $user = require_user();
        $idea_id = (int)($input['idea_id'] ?? $_REQUEST['idea_id'] ?? 0);
        $message = trim($input['message'] ?? '');
        $attachment = validate_attachment_url(trim($input['attachment_url'] ?? ''));
        if (!$idea_id || $message === '') jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        if ($bad = check_forbidden($message, 'message', $user)) jsonResponse(['success'=>false,'error'=>forbidden_message('message',$bad)],400);
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO messages (idea_id, user_id, username, message, attachment_url, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$idea_id, $user['id'], $user['username'], $message, $attachment, $now]);
        $mid = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
        $stmt->execute([$mid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log('message_posted', ['id'=>$mid, 'idea'=>$idea_id, 'user'=>$user['id']]);
        // notify idea author and developer about new message (if they exist and are not the poster)
        try {
            $s = $pdo->prepare('SELECT author_name, developer_name, title FROM ideas WHERE id = ?');
            $s->execute([$idea_id]);
            $ideaRow = $s->fetch(PDO::FETCH_ASSOC);
            if ($ideaRow) {
                foreach (['author_name','developer_name'] as $who) {
                    $uname = $ideaRow[$who] ?? null;
                    if ($uname && $uname !== $user['username']) {
                        $q = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                        $q->execute([$uname]);
                        $u = $q->fetch(PDO::FETCH_ASSOC);
                        if ($u) create_notification($u['id'], 'idea_message', $user['username'] . ' commented on "' . ($ideaRow['title'] ?? '') . '"', 'idea.php?id=' . $idea_id, $user['id']);
                    }
                }
            }
        } catch (Throwable $e) {}
        jsonResponse(['success' => true, 'data' => $row]);
    }

    // Get a user profile (by id or username)
    if ($action === 'profile') {
        $uid = $_REQUEST['id'] ?? null;
        $uname = $_REQUEST['username'] ?? null;
        if (!$uid && !$uname) {
            $cu = current_user();
            if ($cu && !empty($cu['id'])) {
                $uid = $cu['id'];
            } else {
                jsonResponse(['success' => false, 'error' => 'Missing identifier'], 400);
            }
        }
        if ($uid) {
        $stmt = $pdo->prepare('SELECT id, username, role, github_id, email, bio, avatar_url, pronouns, created_at FROM users WHERE id = ?');
        $stmt->execute([(int)$uid]);
        } elseif ($uname) {
            $stmt = $pdo->prepare('SELECT id, username, role, github_id, email, bio, avatar_url, pronouns, created_at FROM users WHERE username = ?');
            $stmt->execute([$uname]);
        }
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) jsonResponse(['success' => false, 'error' => 'Not found'], 404);
        // set default avatar if missing
        if (!$u['avatar_url'] && $u['github_id']) {
            $u['avatar_url'] = "https://avatars.githubusercontent.com/u/" . $u['github_id'] . "?v=4";
        }
        // indicate whether current user follows this profile
        try {
            $cu = current_user();
            if ($cu && !empty($cu['id'])) {
                $q = $pdo->prepare('SELECT id FROM followers WHERE follower_id = ? AND followed_id = ?');
                $q->execute([$cu['id'], $u['id']]);
                $u['is_following'] = (bool)$q->fetch();
            } else {
                $u['is_following'] = false;
            }
        } catch (Throwable $e) { $u['is_following'] = false; }
        // collect some basic stats about the user
        try {
            $s1 = $pdo->prepare('SELECT COUNT(*) FROM ideas WHERE author_name = ?');
            $s1->execute([$u['username']]);
            $posted = (int)$s1->fetchColumn();
            $s2 = $pdo->prepare('SELECT COUNT(*) FROM ideas WHERE developer_name = ?');
            $s2->execute([$u['username']]);
            $claimed = (int)$s2->fetchColumn();
            $s3 = $pdo->prepare("SELECT COUNT(*) FROM ideas WHERE developer_name = ? AND status = 'completed'");
            $s3->execute([$u['username']]);
            $completed = (int)$s3->fetchColumn();
            $s4 = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ?');
            $s4->execute([$u['id']]);
            $idea_messages = (int)$s4->fetchColumn();
            $s5 = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE from_user_id = ?');
            $s5->execute([$u['id']]);
            $dms_sent = (int)$s5->fetchColumn();
            $s6 = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE to_user_id = ?');
            $s6->execute([$u['id']]);
            $dms_received = (int)$s6->fetchColumn();
            // followers / following counts
            $s7 = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE followed_id = ?');
            $s7->execute([$u['id']]);
            $followers_count = (int)$s7->fetchColumn();
            $s8 = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE follower_id = ?');
            $s8->execute([$u['id']]);
            $following_count = (int)$s8->fetchColumn();
            // attachments count
            $s9 = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND attachment_url IS NOT NULL');
            $s9->execute([$u['id']]);
            $attachments_count = (int)$s9->fetchColumn();
            // build achievements list
            $ach = [];
            if ($posted >= 1) $ach[] = ['id'=>'first-idea','label'=>'First Idea'];
            if ($posted >= 10) $ach[] = ['id'=>'ten-ideas','label'=>'10 Ideas'];
            if ($claimed >= 1) $ach[] = ['id'=>'first-claim','label'=>'First Claim'];
            if ($claimed >= 10) $ach[] = ['id'=>'ten-claims','label'=>'Claimed 10 Ideas'];
            if ($completed >= 1) $ach[] = ['id'=>'first-complete','label'=>'First Completion'];
            if ($idea_messages >= 50) $ach[] = ['id'=>'chatter','label'=>'50 Chat Messages'];
            if ($followers_count >= 1) $ach[] = ['id'=>'first-follower','label'=>'First Follower'];
            if ($attachments_count >= 1) $ach[] = ['id'=>'uploader','label'=>'Uploaded a File'];
            if ($attachments_count >= 10) $ach[] = ['id'=>'uploader-10','label'=>'Uploaded 10 Files'];
            $u['stats'] = [
                'ideas_posted' => $posted,
                'ideas_claimed' => $claimed,
                'ideas_completed' => $completed,
                'idea_messages' => $idea_messages,
                'dms_sent' => $dms_sent,
                'dms_received' => $dms_received,
                'followers' => $followers_count,
                'following' => $following_count,
                'attachments' => $attachments_count,
                'ten_attachments' => $attachments_count,
            ];
            $u['achievements'] = $ach;
        } catch (Throwable $e) {
        }
        jsonResponse(['success' => true, 'data' => $u]);
    }

    // Update current user's profile
    if ($action === 'update_profile') {
        $user = require_user();
        $username = trim($input['username'] ?? $user['username']);
        $bio = $input['bio'] ?? null;
        $avatar = $input['avatar_url'] ?? null;
        $pronouns = $input['pronouns'] ?? null;
        $email = $input['email'] ?? null;
        // filter username, bio and pronouns
        if ($username === '') jsonResponse(['success'=>false,'error'=>'Username is required'],400);
        if (!validate_username($username)) jsonResponse(['success'=>false,'error'=>'Username must be 3-30 characters and use letters, numbers, or underscores'],400);
        if ($bio && ($bad = check_forbidden($bio, 'bio', $user))) jsonResponse(['success'=>false,'error'=>forbidden_message('bio',$bad)],400);
        if ($pronouns && ($bad = check_forbidden($pronouns, 'pronouns', $user))) jsonResponse(['success'=>false,'error'=>forbidden_message('pronouns',$bad)],400);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'error'=>'Invalid email'],400);
        if ($username !== $user['username']) {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) jsonResponse(['success'=>false,'error'=>'Username taken'],409);
            if ($bad = check_forbidden($username, 'username', $user, 'Username update')) jsonResponse(['success'=>false,'error'=>forbidden_message('username',$bad)],400);
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE users SET username = ?, bio = ?, avatar_url = ?, pronouns = ?, email = ? WHERE id = ?');
            $stmt->execute([$username, $bio, $avatar, $pronouns, $email, $user['id']]);
            if ($username !== $user['username']) {
                $oldUsername = $user['username'];
                $pdo->prepare('UPDATE ideas SET author_name = ? WHERE author_name = ?')->execute([$username, $oldUsername]);
                $pdo->prepare('UPDATE ideas SET developer_name = ? WHERE developer_name = ?')->execute([$username, $oldUsername]);
                $pdo->prepare('UPDATE messages SET username = ? WHERE user_id = ?')->execute([$username, $user['id']]);
                $pdo->prepare('UPDATE user_reports SET reporter_username = ? WHERE reporter_id = ?')->execute([$username, $user['id']]);
                $pdo->prepare('UPDATE user_reports SET reported_username = ? WHERE reported_user_id = ?')->execute([$username, $user['id']]);
                $pdo->prepare('UPDATE admin_messages SET username = ? WHERE user_id = ?')->execute([$username, $user['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['success'=>false,'error'=>'Failed to update profile'],500);
        }

        $stmt = $pdo->prepare('SELECT id, username, role, github_id, email, bio, avatar_url, pronouns, created_at FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        jsonResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    // Delete current user's account
    if ($action === 'delete_account') {
        $user = require_user();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
            $pdo->prepare('DELETE FROM ideas WHERE author_name = ? OR developer_name = ?')->execute([$user['username'], $user['username']]);
            $pdo->prepare('DELETE FROM messages WHERE user_id = ?')->execute([$user['id']]);
            $pdo->prepare('DELETE FROM direct_messages WHERE from_user_id = ? OR to_user_id = ?')->execute([$user['id'], $user['id']]);
            $pdo->prepare('DELETE FROM followers WHERE follower_id = ? OR followed_id = ?')->execute([$user['id'], $user['id']]);
            $pdo->prepare('DELETE FROM notifications WHERE user_id = ? OR actor_user_id = ?')->execute([$user['id'], $user['id']]);
            $pdo->commit();
            session_destroy();
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['success' => false, 'error' => 'Failed to delete account'], 500);
        }
    }

    // List direct messages between current user and another user
    if ($action === 'dm_list') {
        $user = require_user();
        $other = (int)($_REQUEST['user_id'] ?? ($input['user_id'] ?? 0));
        if (!$other) jsonResponse(['success' => false, 'error' => 'Missing user_id'], 400);
        $stmt = $pdo->prepare('SELECT dm.*, ufrom.username AS from_username, uto.username AS to_username FROM direct_messages dm LEFT JOIN users ufrom ON ufrom.id = dm.from_user_id LEFT JOIN users uto ON uto.id = dm.to_user_id WHERE (dm.from_user_id = ? AND dm.to_user_id = ?) OR (dm.from_user_id = ? AND dm.to_user_id = ?) ORDER BY dm.created_at ASC');
        $stmt->execute([$user['id'], $other, $other, $user['id']]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'data' => $msgs]);
    }

    // Send a direct message to another user
    if ($action === 'dm_send') {
        $user = require_user();
        $to = (int)($input['to_user_id'] ?? $_REQUEST['to_user_id'] ?? 0);
        $message = trim($input['message'] ?? '');
        $attachment = validate_attachment_url(trim($input['attachment_url'] ?? ''));
        if (!$to || $message === '') jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        if ($to === $user['id']) jsonResponse(['success' => false, 'error' => 'Cannot message yourself'], 400);
        if ($bad = check_forbidden($message, 'direct message', $user)) jsonResponse(['success'=>false,'error'=>forbidden_message('message',$bad)],400);
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO direct_messages (from_user_id, to_user_id, message, attachment_url, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $to, $message, $attachment, $now]);
        $did = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT dm.*, ufrom.username AS from_username, uto.username AS to_username FROM direct_messages dm LEFT JOIN users ufrom ON ufrom.id = dm.from_user_id LEFT JOIN users uto ON uto.id = dm.to_user_id WHERE dm.id = ?');
        $stmt->execute([$did]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // notify recipient of DM
        try {
            $q = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $q->execute([$to]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) create_notification($to, 'dm', $user['username'] . ' sent you a message', null, $user['id']);
        } catch (Throwable $e) {}
        jsonResponse(['success' => true, 'data' => $row]);
    }

    // Admin sends DM to selected user
    if ($action === 'admin_dm_send') {
        $user = require_user();
        if ($user['role'] !== 'admin') jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        $to_user_id = (int)($input['to_user_id'] ?? $_REQUEST['to_user_id'] ?? 0);
        $message = trim($input['message'] ?? '');
        if (!$to_user_id || $message === '') jsonResponse(['success' => false, 'error' => 'target user and message required'], 400);
        if ($bad = check_forbidden($message, 'admin direct message', $user)) jsonResponse(['success'=>false,'error'=>forbidden_message('message',$bad)],400);
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO direct_messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['id'], $to_user_id, $message, $now]);
        $did = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT dm.*, ufrom.username AS from_username, uto.username AS to_username FROM direct_messages dm LEFT JOIN users ufrom ON ufrom.id = dm.from_user_id LEFT JOIN users uto ON uto.id = dm.to_user_id WHERE dm.id = ?');
        $stmt->execute([$did]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        try {
            create_notification($to_user_id, 'dm', 'Admin sent you a message', null, $user['id']);
        } catch (Throwable $e) {}
        jsonResponse(['success' => true, 'data' => $row]);
    }

    // Follow a user
    if ($action === 'follow') {
        $user = require_user();
        $to = (int)($input['user_id'] ?? $_REQUEST['user_id'] ?? 0);
        if (!$to) jsonResponse(['success' => false, 'error' => 'Missing user_id'], 400);
        if ($to === $user['id']) jsonResponse(['success' => false, 'error' => 'Cannot follow yourself'], 400);
        $now = date('c');
        // avoid duplicates
        $stmt = $pdo->prepare('SELECT id FROM followers WHERE follower_id = ? AND followed_id = ?');
        $stmt->execute([$user['id'], $to]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare('INSERT INTO followers (follower_id, followed_id, created_at) VALUES (?, ?, ?)');
            $ins->execute([$user['id'], $to, $now]);
        }
        // notify followed user
        try {
            $q = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $q->execute([$to]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) create_notification($to, 'follow', $user['username'] . ' started following you', null, $user['id']);
        } catch (Throwable $e) {}
        jsonResponse(['success' => true]);
    }

    // Favorite an idea
    if ($action === 'favorite_idea') {
        $user = require_user();
        $idea_id = (int)($input['idea_id'] ?? $_REQUEST['idea_id'] ?? 0);
        if (!$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid idea_id'], 400);
        $now = date('c');
        // Insert favorite, ignore if already exists
        try {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO idea_favorites (idea_id, user_id, created_at) VALUES (?, ?, ?)');
            $stmt->execute([$idea_id, $user['id'], $now]);
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to favorite idea'], 500);
        }
    }

    // Unfavorite an idea
    if ($action === 'unfavorite_idea') {
        $user = require_user();
        $idea_id = (int)($input['idea_id'] ?? $_REQUEST['idea_id'] ?? 0);
        if (!$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid idea_id'], 400);
        try {
            $stmt = $pdo->prepare('DELETE FROM idea_favorites WHERE idea_id = ? AND user_id = ?');
            $stmt->execute([$idea_id, $user['id']]);
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to unfavorite idea'], 500);
        }
    }

    // Notifications: list, mark_read, mark_all_read, count
    if ($action === 'notifications_list') {
        $user = require_user();
        $notes = fetch_notifications($user['id'], 50);
        jsonResponse(['success' => true, 'data' => $notes]);
    }

    if ($action === 'notifications_mark_read') {
        $user = require_user();
        $id = (int)($input['id'] ?? ($_REQUEST['id'] ?? 0));
        if (!$id) jsonResponse(['success' => false, 'error' => 'Missing id'], 400);
        mark_notification_read($id, $user['id']);
        jsonResponse(['success' => true]);
    }

    if ($action === 'notifications_mark_all_read') {
        $user = require_user();
        mark_all_notifications_read($user['id']);
        jsonResponse(['success' => true]);
    }

    if ($action === 'notifications_count') {
        $user = current_user();
        if (!$user) jsonResponse(['success' => true, 'data' => ['count' => 0]]);
        $c = unread_notification_count($user['id']);
        jsonResponse(['success' => true, 'data' => ['count' => $c]]);
    }

    // Unfollow a user
    if ($action === 'unfollow') {
        $user = require_user();
        $to = (int)($input['user_id'] ?? $_REQUEST['user_id'] ?? 0);
        if (!$to) jsonResponse(['success' => false, 'error' => 'Missing user_id'], 400);
        $stmt = $pdo->prepare('DELETE FROM followers WHERE follower_id = ? AND followed_id = ?');
        $stmt->execute([$user['id'], $to]);
        jsonResponse(['success' => true]);
    }

    // File upload endpoint (multipart/form-data)
    if ($action === 'upload') {
        $user = require_user();
        try {
            $url = handle_file_upload('file');
            if ($url === null) jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
            jsonResponse(['success' => true, 'url' => $url]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // Like an idea
    if ($action === 'like_idea') {
        $user = require_user();
        $idea_id = (int)($input['idea_id'] ?? $_REQUEST['idea_id'] ?? 0);
        if (!$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid idea_id'], 400);
        $now = date('c');
        // Insert like, ignore if already exists
        try {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO idea_likes (idea_id, user_id, created_at) VALUES (?, ?, ?)');
            $stmt->execute([$idea_id, $user['id'], $now]);
            // Get like count
            $q = $pdo->prepare('SELECT COUNT(*) as count FROM idea_likes WHERE idea_id = ?');
            $q->execute([$idea_id]);
            $result = $q->fetch(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'data' => ['idea_id' => $idea_id, 'likes_count' => $result['count']]]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to like idea'], 500);
        }
    }

    // Unlike an idea
    if ($action === 'unlike_idea') {
        $user = require_user();
        $idea_id = (int)($input['idea_id'] ?? $_REQUEST['idea_id'] ?? 0);
        if (!$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid idea_id'], 400);
        try {
            $stmt = $pdo->prepare('DELETE FROM idea_likes WHERE idea_id = ? AND user_id = ?');
            $stmt->execute([$idea_id, $user['id']]);
            // Get like count
            $q = $pdo->prepare('SELECT COUNT(*) as count FROM idea_likes WHERE idea_id = ?');
            $q->execute([$idea_id]);
            $result = $q->fetch(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'data' => ['idea_id' => $idea_id, 'likes_count' => $result['count']]]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to unlike idea'], 500);
        }
    }

    // Get idea stats
    if ($action === 'idea_stats') {
        $idea_id = (int)($_REQUEST['idea_id'] ?? ($input['idea_id'] ?? 0));
        if (!$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid idea_id'], 400);
        $user = current_user();
        
        // Get likes count
        $likes_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM idea_likes WHERE idea_id = ?');
        $likes_stmt->execute([$idea_id]);
        $likes_count = $likes_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Check if current user liked it
        $user_liked = false;
        if ($user) {
            $check = $pdo->prepare('SELECT id FROM idea_likes WHERE idea_id = ? AND user_id = ?');
            $check->execute([$idea_id, $user['id']]);
            $user_liked = (bool)$check->fetch();
        }
        
        // Get messages count
        $msgs_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM messages WHERE idea_id = ?');
        $msgs_stmt->execute([$idea_id]);
        $messages_count = $msgs_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        jsonResponse(['success' => true, 'data' => [
            'idea_id' => $idea_id,
            'likes_count' => $likes_count,
            'messages_count' => $messages_count,
            'user_liked' => $user_liked
        ]]);
    }

    // TRENDING IDEAS - Algorithm to find hot ideas
    if ($action === 'trending') {
        $period = $_REQUEST['period'] ?? 'week'; // day, week, month
        $user = current_user();
        $user_id = $user ? $user['id'] : null;
        
        // Calculate trending score: likes + messages weighted by recency
        $sql = "
            SELECT 
                i.id, i.title, i.description, i.author_name, i.developer_name, 
                i.status, i.created_at, i.updated_at, i.tags,
                COUNT(DISTINCT il.id) as likes_count,
                COUNT(DISTINCT m.id) as messages_count,
                (COUNT(DISTINCT il.id) * 2 + COUNT(DISTINCT m.id)) as trend_score,
                CASE WHEN ulike.id IS NOT NULL THEN 1 ELSE 0 END as user_liked,
                CASE WHEN ufav.id IS NOT NULL THEN 1 ELSE 0 END as user_favorited
            FROM ideas i
            LEFT JOIN idea_likes il ON il.idea_id = i.id
            LEFT JOIN messages m ON m.idea_id = i.id
            LEFT JOIN idea_likes ulike ON ulike.idea_id = i.id AND ulike.user_id = ?
            LEFT JOIN idea_favorites ufav ON ufav.idea_id = i.id AND ufav.user_id = ?
            WHERE i.created_at > datetime('now', '-7 days')
            GROUP BY i.id
            ORDER BY trend_score DESC
            LIMIT 20
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ideas as &$idea) {
            $idea['likes_count'] = (int)$idea['likes_count'];
            $idea['messages_count'] = (int)$idea['messages_count'];
            $idea['user_liked'] = (bool)$idea['user_liked'];
            $idea['user_favorited'] = (bool)$idea['user_favorited'];
        }
        jsonResponse(['success' => true, 'data' => $ideas]);
    }

    // ACTIVITY FEED - Ideas from people you follow
    if ($action === 'feed') {
        $user = current_user();
        if (!$user) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        
        $sql = "
            SELECT DISTINCT
                i.id, i.title, i.description, i.author_name, i.developer_name, 
                i.status, i.created_at, i.updated_at, i.tags,
                COUNT(DISTINCT il.id) as likes_count,
                COUNT(DISTINCT m.id) as messages_count,
                CASE WHEN ulike.id IS NOT NULL THEN 1 ELSE 0 END as user_liked,
                CASE WHEN ufav.id IS NOT NULL THEN 1 ELSE 0 END as user_favorited
            FROM ideas i
            JOIN followers f ON (f.followed_id IN (
                SELECT u.id FROM users u WHERE u.username = i.author_name
            ) AND f.follower_id = ?)
            LEFT JOIN idea_likes il ON il.idea_id = i.id
            LEFT JOIN messages m ON m.idea_id = i.id
            LEFT JOIN idea_likes ulike ON ulike.idea_id = i.id AND ulike.user_id = ?
            LEFT JOIN idea_favorites ufav ON ufav.idea_id = i.id AND ufav.user_id = ?
            GROUP BY i.id
            ORDER BY i.created_at DESC
            LIMIT 30
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['id'], $user['id'], $user['id']]);
        $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ideas as &$idea) {
            $idea['likes_count'] = (int)$idea['likes_count'];
            $idea['messages_count'] = (int)$idea['messages_count'];
            $idea['user_liked'] = (bool)$idea['user_liked'];
            $idea['user_favorited'] = (bool)$idea['user_favorited'];
        }
        jsonResponse(['success' => true, 'data' => $ideas]);
    }

    // LEADERBOARD - Top users by period
    if ($action === 'leaderboard') {
        $period = $_REQUEST['period'] ?? 'week';
        $period = in_array($period, ['week', 'month', 'alltime'], true) ? $period : 'week';
        $dateCondition = "1=1";
        if ($period === 'week') {
            $dateCondition = "i.created_at > datetime('now', '-7 days')";
        } elseif ($period === 'month') {
            $dateCondition = "i.created_at > datetime('now', '-30 days')";
        }

        $sql = "
            SELECT 
                u.id, u.username, u.avatar_url,
                COUNT(DISTINCT CASE WHEN i.id IS NOT NULL AND ({$dateCondition}) THEN i.id END) as ideas_posted,
                SUM(CASE WHEN i.status = 'completed' AND ({$dateCondition}) THEN 1 ELSE 0 END) as ideas_completed,
                COUNT(DISTINCT il.id) as total_likes_received,
                (COUNT(DISTINCT CASE WHEN i.id IS NOT NULL AND ({$dateCondition}) THEN i.id END) * 10 + SUM(CASE WHEN i.status = 'completed' AND ({$dateCondition}) THEN 20 ELSE 0 END) + COUNT(DISTINCT il.id)) as points
            FROM users u
            LEFT JOIN ideas i ON i.author_name = u.username
            LEFT JOIN idea_likes il ON il.idea_id = i.id
            GROUP BY u.id
            ORDER BY points DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'data' => $users]);
    }

    // COLLECTIONS - Create collections of ideas
    if ($action === 'create_collection') {
        $user = require_user();
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $is_public = (int)($input['is_public'] ?? 1);
        if (!$name) jsonResponse(['success' => false, 'error' => 'Collection name required'], 400);
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO collections (user_id, name, description, is_public, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $name, $description, $is_public, $now]);
        $col_id = $pdo->lastInsertId();
        jsonResponse(['success' => true, 'id' => $col_id]);
    }

    if ($action === 'get_collections') {
        $user = require_user();
        $stmt = $pdo->prepare('SELECT * FROM collections WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user['id']]);
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'data' => $collections]);
    }

    if ($action === 'add_to_collection') {
        $user = require_user();
        $col_id = (int)($input['collection_id'] ?? 0);
        $idea_id = (int)($input['idea_id'] ?? 0);
        if (!$col_id || !$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        
        // Verify collection belongs to user
        $stmt = $pdo->prepare('SELECT id FROM collections WHERE id = ? AND user_id = ?');
        $stmt->execute([$col_id, $user['id']]);
        if (!$stmt->fetch()) jsonResponse(['success' => false, 'error' => 'Not authorized'], 403);
        
        $now = date('c');
        try {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO collection_items (collection_id, idea_id, added_at) VALUES (?, ?, ?)');
            $stmt->execute([$col_id, $idea_id, $now]);
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to add to collection'], 500);
        }
    }

    // COMMENTS - Threaded discussion system
    if ($action === 'post_comment') {
        $user = require_user();
        $idea_id = (int)($input['idea_id'] ?? 0);
        $text = trim($input['text'] ?? '');
        $parent_id = isset($input['parent_comment_id']) ? (int)$input['parent_comment_id'] : null;
        if (!$idea_id || !$text) jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        if ($bad = check_forbidden($text, 'comment', $user)) jsonResponse(['success'=>false,'error'=>forbidden_message('comment',$bad)],400);
        
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO comments (idea_id, user_id, username, text, parent_comment_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$idea_id, $user['id'], $user['username'], $text, $parent_id, $now]);
        $cid = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare('SELECT * FROM comments WHERE id = ?');
        $stmt->execute([$cid]);
        jsonResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'get_comments') {
        $idea_id = (int)($_REQUEST['idea_id'] ?? ($input['idea_id'] ?? 0));
        if (!$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid idea_id'], 400);
        $stmt = $pdo->prepare('SELECT * FROM comments WHERE idea_id = ? ORDER BY created_at ASC');
        $stmt->execute([$idea_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'data' => $comments]);
    }

    // CHALLENGES - Weekly building challenges
    if ($action === 'get_challenges') {
        $stmt = $pdo->prepare('SELECT * FROM challenges WHERE starts_at <= datetime("now") ORDER BY starts_at DESC LIMIT 10');
        $stmt->execute();
        $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'data' => $challenges]);
    }

    if ($action === 'submit_challenge') {
        $user = current_user();
        if (!$user) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        $challenge_id = (int)($input['challenge_id'] ?? 0);
        $idea_id = (int)($input['idea_id'] ?? 0);
        if (!$challenge_id || !$idea_id) jsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
        
        $now = date('c');
        try {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO challenge_submissions (challenge_id, user_id, idea_id, submitted_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$challenge_id, $user['id'], $idea_id, $now]);
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to submit to challenge'], 500);
        }
    }

    // SITE STATS - Homepage metrics!
    if ($action === 'site_stats') {
        $total_users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $total_ideas = (int)$pdo->query('SELECT COUNT(*) FROM ideas')->fetchColumn();
        $completed_ideas = (int)$pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'completed'")->fetchColumn();
        $total_messages = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        jsonResponse(['success' => true, 'data' => [
            'total_users' => $total_users,
            'total_ideas' => $total_ideas,
            'completed_ideas' => $completed_ideas,
            'total_messages' => $total_messages,
        ]]);
    }

    // USER STATS - Enhanced profile stats
    if ($action === 'user_stats') {
        $username = $_REQUEST['username'] ?? null;
        if (!$username) jsonResponse(['success' => false, 'error' => 'Username required'], 400);
        
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) jsonResponse(['success' => false, 'error' => 'User not found'], 404);
        
        // Calculate streak
        $stmt = $pdo->prepare('
            SELECT COUNT(DISTINCT DATE(created_at)) as active_days
            FROM ideas 
            WHERE author_name = ? AND created_at > datetime("now", "-30 days")
        ');
        $stmt->execute([$username]);
        $active = $stmt->fetch(PDO::FETCH_ASSOC)['active_days'];
        
        jsonResponse(['success' => true, 'data' => [
            'active_days_this_month' => (int)$active
        ]]);
    }

    // USERS LIST - Get list of users with avatars
    if ($action === 'users') {
        $limit = (int)($_REQUEST['limit'] ?? 50);
        $offset = (int)($_REQUEST['offset'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, username, avatar_url, bio, pronouns, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $offset]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) {
            if ($u['avatar_url']) {
                // Already has custom avatar
            } elseif (isset($u['github_id']) && $u['github_id']) {
                $u['avatar_url'] = "https://avatars.githubusercontent.com/u/" . $u['github_id'] . "?v=4";
            } else {
                $u['avatar_url'] = null; // No avatar
            }
        }
        // Attach followers_count for each returned user in one query for performance
        if (!empty($users)) {
            $ids = array_column($users, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $q = $pdo->prepare("SELECT followed_id, COUNT(*) as cnt FROM followers WHERE followed_id IN ($placeholders) GROUP BY followed_id");
            $q->execute($ids);
            $counts = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $counts[(int)$r['followed_id']] = (int)$r['cnt'];
            }
            foreach ($users as &$u) {
                $u['followers_count'] = $counts[(int)$u['id']] ?? 0;
            }
            unset($u);
        }
        jsonResponse(['success' => true, 'data' => $users]);
    }

    jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);

} catch (Throwable $e) {
    debug_log('exception', ['message'=>$e->getMessage(), 'file'=>$e->getFile(), 'line'=>$e->getLine()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}