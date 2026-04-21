<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$cfg = @include __DIR__ . '/config.php';
$ADMIN_PASSWORD = getenv('VISIONARY_ADMIN_PASSWORD') ?: ($cfg['admin_password'] ?? null);
if ($ADMIN_PASSWORD === '') $ADMIN_PASSWORD = null;

// Check if admin is logged in
$is_admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    $is_admin_logged_in = false;
}

// Get database connection if not already loaded
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/auth.php';
}
$user = current_user();
require_once __DIR__ . '/includes/app_layout.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    die('Database connection error: ' . htmlspecialchars($e->getMessage()));
}

// Get stats for dashboard
$stats = [];
function detect_signup_source(array $row) {
    if (!empty($row['github_id'])) {
        if (strpos($row['github_id'], 'hackatime:') === 0) return 'Hackatime';
        if (strpos($row['github_id'], 'hackclub:') === 0) return 'Hack Club';
        if (strpos($row['github_id'], 'slack:') === 0) return 'Slack';
        return 'GitHub';
    }
    if (!empty($row['microsoft_id'])) {
        return 'Microsoft';
    }
    if (!empty($row['bio']) && strpos($row['bio'], 'slack_id:') !== false) {
        return 'Slack';
    }
    return 'Email';
}
if ($is_admin_logged_in) {
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['banned_users'] = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'banned'")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['poster_users'] = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'poster'")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['developer_users'] = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'dev'")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['both_users'] = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'both'")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['total_ideas'] = $pdo->query("SELECT COUNT(*) as count FROM ideas")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['total_messages'] = $pdo->query("SELECT COUNT(*) as count FROM messages")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['ideas_completed'] = $pdo->query("SELECT COUNT(*) as count FROM ideas WHERE status = 'completed'")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['active_users_week'] = $pdo->query("SELECT COUNT(DISTINCT id) as count FROM users WHERE created_at > datetime('now', '-7 days')")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['total_reports'] = $pdo->query("SELECT COUNT(*) as count FROM user_reports")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['pending_reports'] = $pdo->query("SELECT COUNT(*) as count FROM user_reports WHERE status = 'new'")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['admin_messages'] = $pdo->query("SELECT COUNT(*) as count FROM admin_messages")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['pending_admin_messages'] = $pdo->query("SELECT COUNT(*) as count FROM admin_messages WHERE status = 'new'")->fetch(PDO::FETCH_ASSOC)['count'];
    $stats['forbidden_alerts'] = $pdo->query("SELECT COUNT(*) as count FROM admin_messages WHERE subject = 'Forbidden word detected'")->fetch(PDO::FETCH_ASSOC)['count'];

    // Get recent users
    $searchName = trim($_GET['search'] ?? '');
    if ($searchName !== '') {
        $stmt = $pdo->prepare("SELECT id, username, role, email, github_id, microsoft_id, bio, created_at FROM users WHERE username LIKE ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute(['%' . $searchName . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT id, username, role, email, github_id, microsoft_id, bio, created_at FROM users ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
    }
    $stats['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent ideas
    $searchIdea = trim($_GET['search_idea'] ?? '');
    if ($searchIdea !== '') {
        $stmt = $pdo->prepare("SELECT id, title, author_name, status, created_at FROM ideas WHERE title LIKE ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute(['%' . $searchIdea . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT id, title, author_name, status, created_at FROM ideas ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
    }
    $stats['recent_ideas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent user reports and admin messages
    $stmt = $pdo->prepare("SELECT * FROM user_reports ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $stats['recent_reports'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM admin_messages ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $stats['recent_admin_messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Recent blog posts for management
    try {
        $pstmt = $pdo->prepare('SELECT * FROM posts ORDER BY created_at DESC LIMIT 50');
        $pstmt->execute();
        $stats['recent_posts'] = $pstmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stats['recent_posts'] = [];
    }
    // If editing a post, load it
    $editPost = null;
    $edit_post_id = isset($_GET['edit_post']) ? (int)$_GET['edit_post'] : 0;
    if ($edit_post_id) {
        try {
            $ep = $pdo->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
            $ep->execute([$edit_post_id]);
            $editPost = $ep->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) { $editPost = null; }
    }
}

// Handle user actions
$message = '';
$csrf_token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Invalid CSRF token. Please refresh and try again.';
    } elseif (isset($_POST['admin_password'])) {
        if ($ADMIN_PASSWORD === null) {
            $message = 'Admin login is not configured. Set VISIONARY_ADMIN_PASSWORD env var or add it to secrets.txt.';
        } else {
            $provided = (string)($_POST['admin_password'] ?? '');
            if (function_exists('hash_equals') ? hash_equals($ADMIN_PASSWORD, $provided) : $ADMIN_PASSWORD === $provided) {
                $_SESSION['admin_logged_in'] = true;
                // Regenerate session id on successful admin login
                if (function_exists('session_regenerate_id')) session_regenerate_id(true);
                $is_admin_logged_in = true;
            } else {
                $message = 'Invalid admin password';
            }
        }
    } elseif ($is_admin_logged_in && isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
            $user_id = (int)$_POST['user_id'];
            try {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                $message = 'User deleted successfully';
            } catch (Exception $e) {
                $message = 'Error deleting user';
            }
        } elseif ($_POST['action'] === 'delete_idea' && isset($_POST['idea_id'])) {
            $idea_id = (int)$_POST['idea_id'];
            try {
                $pdo->prepare("DELETE FROM ideas WHERE id = ?")->execute([$idea_id]);
                $pdo->prepare("DELETE FROM messages WHERE idea_id = ?")->execute([$idea_id]);
                $pdo->prepare("DELETE FROM idea_likes WHERE idea_id = ?")->execute([$idea_id]);
                $message = 'Idea deleted successfully';
            } catch (Exception $e) {
                $message = 'Error deleting idea';
            }
        } elseif ($_POST['action'] === 'ban_user' && isset($_POST['user_id'])) {
            $user_id = (int)$_POST['user_id'];
            try {
                $pdo->prepare("UPDATE users SET role = 'banned' WHERE id = ?")->execute([$user_id]);
                $message = 'User banned';
            } catch (Exception $e) { $message = 'Error banning user'; }
        } elseif ($_POST['action'] === 'unban_user' && isset($_POST['user_id'])) {
            $user_id = (int)$_POST['user_id'];
            try {
                $pdo->prepare("UPDATE users SET role = 'poster' WHERE id = ?")->execute([$user_id]);
                $message = 'User unbanned (role set to poster)';
            } catch (Exception $e) { $message = 'Error unbanning user'; }
        } elseif ($_POST['action'] === 'change_idea_status' && isset($_POST['idea_id']) && isset($_POST['status'])) {
            $idea_id = (int)$_POST['idea_id'];
            $status = substr($_POST['status'],0,20);
            try {
                $pdo->prepare("UPDATE ideas SET status = ? WHERE id = ?")->execute([$status, $idea_id]);
                $message = 'Idea status updated';
            } catch (Exception $e) { $message = 'Error updating idea status'; }
        } elseif ($_POST['action'] === 'set_user_role' && isset($_POST['user_id']) && isset($_POST['role'])) {
            $user_id = (int)$_POST['user_id'];
            $role = in_array($_POST['role'], ['poster','dev','both','banned','admin']) ? $_POST['role'] : 'poster';
            try {
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $user_id]);
                $message = 'User role updated';
            } catch (Exception $e) { $message = 'Error updating user role'; }
        } elseif ($_POST['action'] === 'reply_admin_message' && isset($_POST['message_id']) && isset($_POST['reply'])) {
            $mid = (int)$_POST['message_id'];
            $reply = trim($_POST['reply']);
            try {
                $pdo->prepare('UPDATE admin_messages SET admin_reply = ?, status = ?, replied_at = ?, reply_by = ? WHERE id = ?')
                    ->execute([$reply, 'answered', date('c'), ($user['username'] ?? 'admin'), $mid]);
                $message = 'Admin message replied';
            } catch (Exception $e) { $message = 'Error replying to message'; }
        } elseif ($_POST['action'] === 'resolve_report' && isset($_POST['report_id'])) {
            $rid = (int)$_POST['report_id'];
            try {
                $pdo->prepare('UPDATE user_reports SET status = ?, updated_at = ? WHERE id = ?')->execute(['resolved', date('c'), $rid]);
                $message = 'Report marked resolved';
            } catch (Exception $e) { $message = 'Error resolving report'; }
        } elseif ($_POST['action'] === 'send_dm' && isset($_POST['target_user_id']) && isset($_POST['dm_message'])) {
            $targetId = (int)$_POST['target_user_id'];
            $dm = trim($_POST['dm_message']);
            if ($targetId && $dm !== '') {
                try {
                    $adminSender = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    $fromId = $adminSender ? (int)$adminSender['id'] : 0;
                    if ($fromId === 0) {
                        $first = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                        $fromId = $first ? (int)$first['id'] : 0;
                    }
                    if ($fromId === 0) {
                        throw new Exception('No sender account available');
                    }
                    $pdo->prepare('INSERT INTO direct_messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, ?)')
                        ->execute([$fromId, $targetId, $dm, date('c')]);
                    create_notification($targetId, 'dm', 'Admin sent you a direct message', null, $fromId);
                    $message = 'Direct message sent successfully';
                } catch (Exception $e) {
                    $message = 'Failed to send direct message';
                }
            } else {
                $message = 'Target user and message are required';
            }
        } elseif ($is_admin_logged_in && $_POST['action'] === 'create_post') {
            $title = trim($_POST['title'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $content = $_POST['content'] ?? '';
            $tags = trim($_POST['tags'] ?? '');
            $status = in_array($_POST['status'] ?? 'draft', ['draft','published'], true) ? $_POST['status'] : 'draft';
            if ($title === '') {
                $message = 'Post title is required';
            } else {
                // generate slug if missing
                if ($slug === '') {
                    $slug = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $title);
                    $slug = strtolower(preg_replace('/[\s_]+/u', '-', $slug));
                    $slug = trim($slug, '-');
                } else {
                    $slug = strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $slug));
                }
                // ensure unique slug
                $base = $slug;
                $i = 1;
                $check = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
                while (true) {
                    $check->execute([$slug]);
                    if (!$check->fetch()) break;
                    $slug = $base . '-' . $i++;
                }

                // Handle featured image: prefer uploaded file, fall back to provided URL
                $featured = null;
                try {
                    if (!empty($_FILES['featured_file']) && is_array($_FILES['featured_file'])) {
                        $uploaded = handle_file_upload('featured_file');
                        if ($uploaded) $featured = $uploaded;
                    }
                } catch (Exception $e) {
                    $message = 'Upload failed: ' . $e->getMessage();
                }
                $featured_input = trim($_POST['featured_image'] ?? '');
                if (!$featured && $featured_input !== '') {
                    $valid = validate_attachment_url($featured_input);
                    if ($valid) $featured = $valid;
                    elseif (filter_var($featured_input, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $featured_input)) {
                        $featured = $featured_input;
                    }
                }

                try {
                    $now = date('c');
                    $published_at = $status === 'published' ? $now : null;
                    $author_id = $user['id'] ?? null;
                    $author_name = $user['username'] ?? 'admin';
                    $ins = $pdo->prepare('INSERT INTO posts (slug, title, content, author_id, author_name, status, tags, featured_image, created_at, updated_at, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$slug, $title, $content, $author_id, $author_name, $status, $tags ?: null, $featured, $now, $now, $published_at]);
                    $message = 'Post created';
                } catch (Exception $e) {
                    $message = 'Failed to create post';
                }
            }
        } elseif ($is_admin_logged_in && $_POST['action'] === 'update_post') {
            $post_id = (int)($_POST['post_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $content = $_POST['content'] ?? '';
            $tags = trim($_POST['tags'] ?? '');
            $status = in_array($_POST['status'] ?? 'draft', ['draft','published'], true) ? $_POST['status'] : 'draft';
            if (!$post_id || $title === '') {
                $message = 'Invalid post data';
            } else {
                if ($slug === '') {
                    $slug = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $title);
                    $slug = strtolower(preg_replace('/[\s_]+/u', '-', $slug));
                    $slug = trim($slug, '-');
                } else {
                    $slug = strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $slug));
                }

                // Handle featured image updates: upload, URL, or remove
                $featured = null;
                try {
                    if (!empty($_FILES['featured_file']) && is_array($_FILES['featured_file'])) {
                        $uploaded = handle_file_upload('featured_file');
                        if ($uploaded) $featured = $uploaded;
                    }
                } catch (Exception $e) {
                    $message = 'Upload failed: ' . $e->getMessage();
                }
                $featured_input = trim($_POST['featured_image'] ?? '');
                if (!$featured && $featured_input !== '') {
                    $valid = validate_attachment_url($featured_input);
                    if ($valid) $featured = $valid;
                    elseif (filter_var($featured_input, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $featured_input)) {
                        $featured = $featured_input;
                    }
                }
                // If remove flag set, explicitly clear featured image
                if (!empty($_POST['remove_featured'])) {
                    $featured = null;
                }

                try {
                    $now = date('c');
                    $up = $pdo->prepare('UPDATE posts SET slug = ?, title = ?, content = ?, status = ?, tags = ?, featured_image = ?, updated_at = ?, published_at = ? WHERE id = ?');
                    $pubAt = $status === 'published' ? date('c') : null;
                    $up->execute([$slug, $title, $content, $status, $tags ?: null, $featured, $now, $pubAt, $post_id]);
                    $message = 'Post updated';
                } catch (Exception $e) { $message = 'Failed to update post'; }
            }
        } elseif ($is_admin_logged_in && $_POST['action'] === 'publish_post' && isset($_POST['post_id'])) {
            $post_id = (int)$_POST['post_id'];
            try {
                $now = date('c');
                $pdo->prepare('UPDATE posts SET status = ?, published_at = ?, updated_at = ? WHERE id = ?')->execute(['published', $now, $now, $post_id]);
                $message = 'Post published';
            } catch (Exception $e) { $message = 'Failed to publish post'; }
        } elseif ($is_admin_logged_in && $_POST['action'] === 'unpublish_post' && isset($_POST['post_id'])) {
            $post_id = (int)$_POST['post_id'];
            try {
                $now = date('c');
                $pdo->prepare('UPDATE posts SET status = ?, published_at = NULL, updated_at = ? WHERE id = ?')->execute(['draft', $now, $post_id]);
                $message = 'Post unpublished';
            } catch (Exception $e) { $message = 'Failed to unpublish post'; }
        } elseif ($is_admin_logged_in && $_POST['action'] === 'delete_post' && isset($_POST['post_id'])) {
            $post_id = (int)$_POST['post_id'];
            try {
                $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$post_id]);
                $message = 'Post deleted';
            } catch (Exception $e) { $message = 'Failed to delete post'; }
        } elseif ($_POST['action'] === 'announce' && isset($_POST['announcement'])) {
            $announcement = trim($_POST['announcement']);
            if ($announcement !== '') {
                try {
                    // send notification to all users
                    $stmt = $pdo->query('SELECT id FROM users');
                    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($all as $u) {
                        create_notification($u['id'], 'announcement', $announcement, null, null);
                    }
                    $message = 'Announcement sent to all users';
                } catch (Exception $e) { $message = 'Failed to send announcement'; }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Visionary</title>
    <link rel="preload" href="css/style.css" as="style">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .login-box {
            background: var(--card);
            padding: 40px;
            border-radius: 8px;
            max-width: 400px;
            margin: 60px auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .login-box h2 {
            margin-top: 0;
            color: var(--accent);
        }
        
        .login-box input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 16px;
        }
        
        .login-box button {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .login-box button:hover {
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: var(--card);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: var(--accent);
        }
        
        .stat-label {
            color: var(--muted);
            margin-top: 8px;
            font-size: 14px;
        }
        
        .table-container {
            background: var(--card);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            background: var(--card);
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid var(--border);
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        tr:hover {
            background: rgba(0,0,0,0.05);
        }
        
        body.dark tr:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .delete-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .delete-btn:hover {
            background: #dc2626;
        }
        
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        body.dark .success-message {
            background: rgba(16,185,129,0.1);
            color: #10b981;
        }
        
        .logout-btn {
            display: inline-block;
            background: #6b7280;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            margin-bottom: 20px;
        }
        
        .logout-btn:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <?php render_app_header('Admin', 'Admin dashboard & moderation', $user ?? null); ?>

    <main>
        <div class="admin-container">
            <?php if (!$is_admin_logged_in): ?>
                <div class="login-box">
                    <h2>🔐 Admin Login</h2>
                    <?php if ($ADMIN_PASSWORD === null): ?>
                        <p style="color: var(--muted); margin-bottom: 30px;">Admin login is not configured. Set <strong>VISIONARY_ADMIN_PASSWORD</strong> as an environment variable or add it to <code>secrets.txt</code>. See <a href="config.php.example">config.php.example</a> for guidance.</p>
                    <?php else: ?>
                        <p style="color: var(--muted); margin-bottom: 30px;">Enter the admin password to access the dashboard</p>
                        <form method="POST">
                            <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                            <input type="password" name="admin_password" placeholder="Enter admin password" required autofocus>
                            <button type="submit">Login</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($message): ?>
                    <div class="success-message"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <a class="logout-btn" href="?logout=1">Logout</a>
                <h2>📊 Dashboard Overview</h2>

                <div style="margin:12px 0;padding:12px;background:var(--card);border-radius:6px;">
                    <form method="POST">
                        <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                        <input type="hidden" name="action" value="announce">
                        <label for="announcement" style="display:block;margin-bottom:6px;font-weight:bold">Send announcement to all users</label>
                        <textarea id="announcement" name="announcement" rows="2" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:4px;margin-bottom:8px;background:var(--card);color:var(--text);" placeholder="Short announcement message"></textarea>
                        <button type="submit" style="padding:8px 12px;background:var(--accent);color:#fff;border:0;border-radius:4px">Send Announcement</button>
                    </form>
                </div>

                <div style="margin:12px 0;padding:12px;background:var(--card);border-radius:6px;">
                    <form method="POST">
                        <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                        <input type="hidden" name="action" value="send_dm">
                        <label style="display:block;margin-bottom:6px;font-weight:bold">Send direct message to user</label>
                        <input type="number" name="target_user_id" placeholder="User ID" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:4px;margin-bottom:8px;background:var(--card);color:var(--text);" required>
                        <textarea name="dm_message" rows="2" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:4px;margin-bottom:8px;background:var(--card);color:var(--text);" placeholder="Your message" required></textarea>
                        <button type="submit" style="padding:8px 12px;background:#10b981;color:#fff;border:0;border-radius:4px">Send DM</button>
                    </form>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_users'] ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['poster_users'] ?></div>
                        <div class="stat-label">Posters</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['developer_users'] ?></div>
                        <div class="stat-label">Developers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['both_users'] ?></div>
                        <div class="stat-label">Both Roles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['banned_users'] ?></div>
                        <div class="stat-label">Banned Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['active_users_week'] ?></div>
                        <div class="stat-label">Active Users (7d)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_ideas'] ?></div>
                        <div class="stat-label">Total Ideas Posted</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_messages'] ?></div>
                        <div class="stat-label">Total Messages</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['ideas_completed'] ?></div>
                        <div class="stat-label">Completed Ideas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_reports'] ?></div>
                        <div class="stat-label">Total Reports</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['pending_reports'] ?></div>
                        <div class="stat-label">Pending Reports</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['admin_messages'] ?></div>
                        <div class="stat-label">Admin Messages</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['pending_admin_messages'] ?></div>
                        <div class="stat-label">Pending Admin Messages</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['forbidden_alerts'] ?></div>
                        <div class="stat-label">Forbidden Alerts</div>
                    </div>
                </div>

                <h2>📰 Blog Management</h2>
                <div style="margin:12px 0;padding:12px;background:var(--card);border-radius:6px;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                        <input type="hidden" name="action" value="<?= $editPost ? 'update_post' : 'create_post' ?>">
                        <?php if ($editPost): ?>
                            <input type="hidden" name="post_id" value="<?= (int)$editPost['id'] ?>">
                        <?php endif; ?>
                        <div style="display:grid;grid-template-columns:1fr 220px;gap:8px;">
                            <div>
                                <label style="display:block;font-weight:bold">Title</label>
                                <input name="title" value="<?= htmlspecialchars($editPost['title'] ?? '') ?>" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px;" required>
                                <label style="display:block;font-weight:bold">Slug (optional)</label>
                                <input name="slug" value="<?= htmlspecialchars($editPost['slug'] ?? '') ?>" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px;">
                                <label style="display:block;font-weight:bold">Tags (comma separated)</label>
                                <input name="tags" value="<?= htmlspecialchars($editPost['tags'] ?? '') ?>" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px;">
                            </div>
                            <div>
                                <label style="display:block;font-weight:bold">Status</label>
                                <select name="status" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px;">
                                    <option value="draft" <?= (isset($editPost['status']) && $editPost['status'] === 'draft') ? 'selected' : '' ?>>Draft</option>
                                    <option value="published" <?= (isset($editPost['status']) && $editPost['status'] === 'published') ? 'selected' : '' ?>>Published</option>
                                </select>
                                <label style="display:block;font-weight:bold">Featured image URL</label>
                                <input name="featured_image" value="<?= htmlspecialchars($editPost['featured_image'] ?? '') ?>" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px;">
                                <label style="display:block;font-weight:bold;margin-top:6px">Or upload featured image</label>
                                <input type="file" name="featured_file" accept="image/*" style="width:100%;padding:6px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px;">
                                <?php if ($editPost && !empty($editPost['featured_image'])): ?>
                                    <div style="margin:8px 0;">
                                        <div style="font-size:12px;color:var(--muted);margin-bottom:6px;font-weight:bold">Current featured image</div>
                                        <img src="<?= htmlspecialchars(responsive_image_url($editPost['featured_image'])) ?>" alt="Featured preview" style="max-width:100%;border-radius:6px;margin-bottom:6px;display:block">
                                        <label style="font-size:13px"><input type="checkbox" name="remove_featured" value="1"> Remove featured image</label>
                                    </div>
                                <?php endif; ?>
                                <div style="margin-top:6px;">
                                    <button type="submit" style="padding:8px 12px;background:var(--accent);color:#fff;border:0;border-radius:4px"><?= $editPost ? 'Update Post' : 'Create Post' ?></button>
                                    <?php if ($editPost): ?>
                                        <a href="admin.php" style="margin-left:8px;">Cancel edit</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:8px;">
                            <label style="display:block;font-weight:bold">Content</label>
                            <textarea name="content" rows="10" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;"><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>
                        </div>
                    </form>
                </div>

                <h2>Recent Blog Posts</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Image</th>
                                <th>Status</th>
                                <th>Author</th>
                                <th>Created</th>
                                <th>Published</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_posts'] ?? [] as $post): ?>
                                <tr>
                                    <td><?= (int)$post['id'] ?></td>
                                    <td><?= htmlspecialchars($post['title']) ?></td>
                                    <td>
                                        <?php if (!empty($post['featured_image'])): ?>
                                            <img src="<?= htmlspecialchars(responsive_image_url($post['featured_image'])) ?>" alt="thumb" style="max-width:80px;max-height:48px;border-radius:4px;" />
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($post['status']) ?></td>
                                    <td><?= htmlspecialchars($post['author_name'] ?? 'Staff') ?></td>
                                    <td><?= htmlspecialchars($post['created_at'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($post['published_at'] ?? '-') ?></td>
                                    <td>
                                        <a href="admin.php?edit_post=<?= (int)$post['id'] ?>">Edit</a>
                                        <form method="POST" style="display:inline;margin-left:8px;">
                                            <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                            <input type="hidden" name="action" value="<?= $post['status'] === 'published' ? 'unpublish_post' : 'publish_post' ?>">
                                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                            <button type="submit" class="delete-btn"><?= $post['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button>
                                        </form>
                                        <form method="POST" style="display:inline;margin-left:6px;" onsubmit="return confirm('Delete this post?');">
                                            <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2>👥 Recent Users</h2>
                <form method="GET" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" name="search" placeholder="Search username..." value="<?= htmlspecialchars($searchName ?? '') ?>" style="flex:1;min-width:220px;padding:8px;border:1px solid var(--border);border-radius:6px;background:var(--card);color:var(--text);" />
                    <button type="submit" style="background:var(--accent);color:#fff;border:0;padding:8px 14px;border-radius:6px;">Search</button>
                </form>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Source</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_users'] as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['id']) ?></td>
                                    <td><?= htmlspecialchars($item['username']) ?></td>
                                    <td><?= htmlspecialchars($item['email'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars(detect_signup_source($item)) ?></td>
                                    <td><?= htmlspecialchars($item['role']) ?></td>
                                    <td><?= htmlspecialchars($item['created_at']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                        <form method="POST" style="display:inline;margin-left:6px;" onsubmit="return confirm('Change role for this user?');">
                                            <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                            <input type="hidden" name="action" value="set_user_role">
                                            <input type="hidden" name="user_id" value="<?= $item['id'] ?>">
                                            <select name="role" onchange="this.form.submit()" style="border-radius:4px;padding:4px;font-size:12px;">
                                                <?php foreach (['poster'=>'Poster','dev'=>'Developer','both'=>'Both','banned'=>'Banned','admin'=>'Admin'] as $roleValue => $roleLabel): ?>
                                                    <option value="<?= $roleValue ?>" <?= $item['role'] === $roleValue ? 'selected' : '' ?>><?= $roleLabel ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2>💡 Recent Ideas</h2>
                <form method="GET" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" name="search_idea" placeholder="Search idea title..." value="<?= htmlspecialchars($searchIdea ?? '') ?>" style="flex:1;min-width:220px;padding:8px;border:1px solid var(--border);border-radius:6px;background:var(--card);color:var(--text);" />
                    <button type="submit" style="background:var(--accent);color:#fff;border:0;padding:8px 14px;border-radius:6px;">Search</button>
                </form>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_ideas'] as $idea): ?>
                                <tr>
                                    <td><?= htmlspecialchars($idea['id']) ?></td>
                                    <td><?= htmlspecialchars($idea['title']) ?></td>
                                    <td><?= htmlspecialchars($idea['author_name'] ?? 'Anonymous') ?></td>
                                    <td><?= htmlspecialchars($idea['status']) ?></td>
                                    <td><?= htmlspecialchars($idea['created_at']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this idea?');">
                                            <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                            <input type="hidden" name="action" value="delete_idea">
                                            <input type="hidden" name="idea_id" value="<?= $idea['id'] ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                        <form method="POST" style="display:inline;margin-left:8px;">
                                            <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                            <input type="hidden" name="action" value="change_idea_status">
                                            <input type="hidden" name="idea_id" value="<?= $idea['id'] ?>">
                                            <select name="status" onchange="this.form.submit()" style="padding:6px;border-radius:4px;border:1px solid var(--border);background:var(--card);color:var(--text);">
                                                <option value="open" <?= $idea['status'] === 'open' ? 'selected' : '' ?>>open</option>
                                                <option value="in_progress" <?= $idea['status'] === 'in_progress' ? 'selected' : '' ?>>in_progress</option>
                                                <option value="completed" <?= $idea['status'] === 'completed' ? 'selected' : '' ?>>completed</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2>🚨 Recent User Reports</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reporter</th>
                                <th>Reported</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_reports'] as $report): ?>
                                <tr>
                                    <td><?= htmlspecialchars($report['id']) ?></td>
                                    <td><?= htmlspecialchars($report['reporter_username']) ?></td>
                                    <td><?= htmlspecialchars($report['reported_username']) ?></td>
                                    <td><?= htmlspecialchars($report['reason']) ?></td>
                                    <td><?= htmlspecialchars($report['status']) ?></td>
                                    <td><?= htmlspecialchars($report['created_at']) ?></td>
                                    <td>
                                        <?php if ($report['status'] !== 'resolved'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Mark as resolved?');">
                                                <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                                <input type="hidden" name="action" value="resolve_report">
                                                <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                                <button type="submit" class="delete-btn">Resolve</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#059669">Resolved</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2>✉️ Admin Messages</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Attachment</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Reply</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_admin_messages'] as $msg): ?>
                                <tr>
                                    <td><?= htmlspecialchars($msg['id']) ?></td>
                                    <td><?= htmlspecialchars($msg['username']) ?></td>
                                    <td><?= htmlspecialchars($msg['subject']) ?></td>
                                    <td style="max-width:240px;white-space:pre-wrap;overflow-wrap:break-word;"><?= nl2br(htmlspecialchars(substr($msg['message'] ?? '', 0, 240))) ?><?= strlen($msg['message'] ?? '') > 240 ? '…' : '' ?></td>
                                    <td>
                                        <?php if (!empty($msg['attachment_url'])): ?>
                                            <a href="<?= htmlspecialchars($msg['attachment_url']) ?>" target="_blank">View</a>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($msg['status']) ?></td>
                                    <td><?= htmlspecialchars($msg['created_at']) ?></td>
                                    <td>
                                        <?php if ($msg['admin_reply']): ?>
                                            <?= nl2br(htmlspecialchars($msg['admin_reply'])) ?><br><em>by <?= htmlspecialchars($msg['reply_by'] ?? '') ?></em>
                                        <?php else: ?>
                                            <form method="POST" style="display:flex;gap:4px;align-items:center;">
                                                <input type="hidden" name="_csrf" value="<?=$csrf_token?>">
                                                <input type="hidden" name="action" value="reply_admin_message">
                                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                <input type="text" name="reply" placeholder="Reply..." style="flex:1;padding:4px;border:1px solid var(--border);border-radius:4px;background:var(--card);color:var(--text);">
                                                <button type="submit" class="delete-btn">Send</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script nonce="<?=$nonce?>">
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
</body>
</html>
