<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $secure ? 1 : 0);
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
} else {
    session_set_cookie_params(0, '/', '', $secure, true);
}
session_start();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Permissions-Policy: interest-cohort=()");
if ($secure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

if (!isset($nonce)) {
    $nonce = bin2hex(random_bytes(16));
}
$nonceDirective = " 'nonce-$nonce'";
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net" . $nonceDirective . "; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://api.github.com https://graph.microsoft.com;");

function getPDO() {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
    $dbPath = $dataDir . '/visionary.db';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password_hash TEXT,
        role TEXT,
        github_id TEXT,
        microsoft_id TEXT,
        email TEXT,
        created_at TEXT,
        bio TEXT,
        avatar_url TEXT,
        pronouns TEXT,
        reputation_points INTEGER DEFAULT 0
    )");
    try {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (!in_array('bio', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT");
        }
        if (!in_array('avatar_url', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url TEXT");
        }
        if (!in_array('pronouns', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN pronouns TEXT");
        }
        if (!in_array('microsoft_id', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN microsoft_id TEXT");
        }
        if (!in_array('email', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
        }
        if (!in_array('reputation_points', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reputation_points INTEGER DEFAULT 0");
        }
    } catch (Exception $e) {
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS ideas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        author_name TEXT,
        developer_name TEXT,
        status TEXT NOT NULL DEFAULT 'open',
        created_at TEXT,
        updated_at TEXT
    )");
    try {
        $cols = $pdo->query("PRAGMA table_info(ideas)")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (!in_array('tags', $names)) {
            $pdo->exec("ALTER TABLE ideas ADD COLUMN tags TEXT");
        }
    } catch (Exception $e) {
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        idea_id INTEGER NOT NULL,
        user_id INTEGER,
        username TEXT,
        message TEXT NOT NULL,
        attachment_url TEXT,
        created_at TEXT,
        FOREIGN KEY(idea_id) REFERENCES ideas(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        from_user_id INTEGER NOT NULL,
        to_user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        attachment_url TEXT,
        created_at TEXT,
        FOREIGN KEY(from_user_id) REFERENCES users(id),
        FOREIGN KEY(to_user_id) REFERENCES users(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reporter_id INTEGER NOT NULL,
        reporter_username TEXT,
        reported_user_id INTEGER NOT NULL,
        reported_username TEXT,
        reason TEXT NOT NULL,
        details TEXT,
        status TEXT DEFAULT 'new',
        created_at TEXT,
        updated_at TEXT,
        FOREIGN KEY(reporter_id) REFERENCES users(id),
        FOREIGN KEY(reported_user_id) REFERENCES users(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        username TEXT,
        subject TEXT,
        message TEXT,
        attachment_url TEXT,
        admin_reply TEXT,
        reply_by TEXT,
        status TEXT DEFAULT 'new',
        created_at TEXT,
        replied_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS followers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        follower_id INTEGER NOT NULL,
        followed_id INTEGER NOT NULL,
        created_at TEXT,
        FOREIGN KEY(follower_id) REFERENCES users(id),
        FOREIGN KEY(followed_id) REFERENCES users(id)
    )");
    try {
        $cols = $pdo->query("PRAGMA table_info(messages)")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (!in_array('attachment_url', $names)) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN attachment_url TEXT");
        }
        $cols2 = $pdo->query("PRAGMA table_info(direct_messages)")->fetchAll(PDO::FETCH_ASSOC);
        $names2 = array_column($cols2, 'name');
        if (!in_array('attachment_url', $names2)) {
            $pdo->exec("ALTER TABLE direct_messages ADD COLUMN attachment_url TEXT");
        }
        $cols3 = $pdo->query("PRAGMA table_info(admin_messages)")->fetchAll(PDO::FETCH_ASSOC);
        $names3 = array_column($cols3, 'name');
        if (!in_array('attachment_url', $names3)) {
            $pdo->exec("ALTER TABLE admin_messages ADD COLUMN attachment_url TEXT");
        }
    } catch (Exception $e) {
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        actor_user_id INTEGER,
        type TEXT,
        message TEXT,
        url TEXT,
        is_read INTEGER DEFAULT 0,
        created_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        author_id INTEGER,
        author_name TEXT,
        status TEXT DEFAULT 'draft', -- draft or published
        tags TEXT,
        featured_image TEXT,
        created_at TEXT,
        updated_at TEXT,
        published_at TEXT,
        FOREIGN KEY(author_id) REFERENCES users(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS collections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        is_public INTEGER DEFAULT 1,
        created_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS collection_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        collection_id INTEGER NOT NULL,
        idea_id INTEGER NOT NULL,
        added_at TEXT,
        FOREIGN KEY(collection_id) REFERENCES collections(id),
        FOREIGN KEY(idea_id) REFERENCES ideas(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS challenges (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        category TEXT,
        reward_points INTEGER DEFAULT 50,
        starts_at TEXT,
        ends_at TEXT,
        created_at TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS challenge_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        challenge_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        idea_id INTEGER NOT NULL,
        submitted_at TEXT,
        FOREIGN KEY(challenge_id) REFERENCES challenges(id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(idea_id) REFERENCES ideas(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS badges (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        description TEXT,
        icon TEXT,
        category TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_badges (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        badge_id INTEGER NOT NULL,
        unlocked_at TEXT,
        UNIQUE(user_id, badge_id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(badge_id) REFERENCES badges(id)
    )");
    
    try {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (!in_array('reputation_points', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reputation_points INTEGER DEFAULT 0");
        }
        if (!in_array('last_seen_at', $names)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_seen_at TEXT");
        }
    } catch (Exception $e) {}
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS hearts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        idea_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at TEXT,
        FOREIGN KEY(idea_id) REFERENCES ideas(id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        UNIQUE(idea_id, user_id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS trending_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        idea_id INTEGER NOT NULL,
        score REAL,
        period TEXT,
        updated_at TEXT,
        UNIQUE(idea_id, period)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        idea_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        username TEXT,
        text TEXT NOT NULL,
        parent_comment_id INTEGER,
        created_at TEXT,
        FOREIGN KEY(idea_id) REFERENCES ideas(id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(parent_comment_id) REFERENCES comments(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS idea_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        idea_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at TEXT,
        FOREIGN KEY(idea_id) REFERENCES ideas(id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        UNIQUE(idea_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS idea_favorites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        idea_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at TEXT,
        FOREIGN KEY(idea_id) REFERENCES ideas(id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        UNIQUE(idea_id, user_id)
    )");
    
    try {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_ideas_author ON ideas(author_name)",
            "CREATE INDEX IF NOT EXISTS idx_ideas_developer ON ideas(developer_name)",
            "CREATE INDEX IF NOT EXISTS idx_ideas_status ON ideas(status)",
            "CREATE INDEX IF NOT EXISTS idx_ideas_created_at ON ideas(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_messages_idea_id ON messages(idea_id)",
            "CREATE INDEX IF NOT EXISTS idx_messages_user_id ON messages(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_direct_messages_from ON direct_messages(from_user_id)",
            "CREATE INDEX IF NOT EXISTS idx_direct_messages_to ON direct_messages(to_user_id)",
            "CREATE INDEX IF NOT EXISTS idx_idea_likes_idea ON idea_likes(idea_id)",
            "CREATE INDEX IF NOT EXISTS idx_idea_likes_user ON idea_likes(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_idea_favorites_idea ON idea_favorites(idea_id)",
            "CREATE INDEX IF NOT EXISTS idx_idea_favorites_user ON idea_favorites(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_followers_follower ON followers(follower_id)",
            "CREATE INDEX IF NOT EXISTS idx_followers_followed ON followers(followed_id)",
            "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
            "CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read)",
            "CREATE INDEX IF NOT EXISTS idx_user_reports_reporter ON user_reports(reporter_id)",
            "CREATE INDEX IF NOT EXISTS idx_user_reports_reported ON user_reports(reported_user_id)",
            "CREATE INDEX IF NOT EXISTS idx_admin_messages_user ON admin_messages(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_admin_messages_status ON admin_messages(status)"
        ];
        foreach ($indexes as $idx) {
            try {
                $pdo->exec($idx);
            } catch (Exception $e) {
            }
        }
    } catch (Exception $e) {
    }
    return $pdo;
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_post_content($html) {
    if ($html === null) return '';
    $allowed = '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><blockquote><pre><code><img>';
    $clean = strip_tags($html, $allowed);

    $clean = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function($m) {
        $tag = strtolower($m[1]);
        $attrstr = $m[2];

        $attrstr = preg_replace('/\s(on[a-z]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrstr);
        $attrstr = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrstr);

        $attrs = [];
        if (preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrstr, $ams, PREG_SET_ORDER)) {
            foreach ($ams as $a) {
                $name = strtolower($a[1]);
                $val = isset($a[3]) && $a[3] !== '' ? $a[3] : (isset($a[4]) && $a[4] !== '' ? $a[4] : $a[5]);
                $val = trim($val, "'\" ");
                if ($name === 'href') {
                    if (preg_match('#^(https?://|/|#)#i', $val)) {
                        $attrs['href'] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                    }
                } elseif ($name === 'src') {
                    if (preg_match('#^(https?://|/|uploads/|data:image/(png|jpeg|jpg|gif|webp))#i', $val)) {
                        if (stripos($val, 'uploads/') === 0 && function_exists('responsive_image_url')) {
                            $val = responsive_image_url($val);
                        }
                        $attrs['src'] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                    }
                } elseif (in_array($name, ['alt','title'], true)) {
                    $attrs[$name] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        $outAttrs = '';
        if ($tag === 'a') {
            if (isset($attrs['href'])) $outAttrs .= ' href="' . $attrs['href'] . '"';
            $outAttrs .= ' rel="noopener noreferrer"';
            if (isset($attrs['title'])) $outAttrs .= ' title="' . $attrs['title'] . '"';
        } elseif ($tag === 'img') {
            if (!isset($attrs['src'])) {
                return '';
            }
            $outAttrs .= ' src="' . $attrs['src'] . '"';
            $outAttrs .= ' loading="lazy" decoding="async"';
            if (isset($attrs['alt'])) $outAttrs .= ' alt="' . $attrs['alt'] . '"';
            if (isset($attrs['title'])) $outAttrs .= ' title="' . $attrs['title'] . '"';
        } else {
            if (isset($attrs['title'])) $outAttrs .= ' title="' . $attrs['title'] . '"';
        }

        return '<' . $tag . $outAttrs . '>';
    }, $clean);

    return $clean;
}

// CSRF helpers
function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field() {
    $t = csrf_token();
    return '<input type="hidden" name="_csrf" value="' . h($t) . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? '';
        if (empty($token) || !hash_equals((string)csrf_token(), (string)$token)) {
            return false;
        }
    }
    return true;
}

function verify_api_csrf() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        if (empty($token) || !hash_equals((string)csrf_token(), (string)$token)) {
            return false;
        }
    }
    return true;
}

function validate_username($u) {
    if (!is_string($u)) return false;
    $u = trim($u);
    if ($u === '') return false;
    if (strlen($u) < 3 || strlen($u) > 30) return false;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $u)) return false;
    return $u;
}

function load_forbidden_list() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $path = __DIR__ . '/filter.txt';
    $words = [];
    if (is_file($path) && is_readable($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $l) {
            $t = trim($l);
            if ($t === '') continue;
            if ($t[0] === '#') continue;
            $words[] = mb_strtolower($t);
        }
    }
    $cache = $words;
    return $cache;
}

function contains_forbidden($text) {
    if (!is_string($text) || $text === '') return false;
    $hay = mb_strtolower($text);
    $hay = preg_replace('/\s+/u', ' ', $hay);
    $forbidden = load_forbidden_list();
    foreach ($forbidden as $term) {
        if ($term === '') continue;
        if (mb_stripos($hay, $term) !== false) return $term;
    }
    return false;
}

function forbidden_message($field, $term) {
    return ucfirst($field) . " contains a prohibited word: \"" . htmlspecialchars($term, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . "\"";
}

function report_forbidden_word_use($field, $term, $details = null, $user = null) {
    $pdo = getPDO();
    $userId = is_array($user) && isset($user['id']) ? (int)$user['id'] : 0;
    $username = is_array($user) && !empty($user['username']) ? $user['username'] : 'unknown';
    $subject = 'Forbidden word detected';
    $message = 'A forbidden word was detected in ' . $field . ' by ' . $username . '. Term: "' . $term . '".';
    if ($details) {
        $message .= ' Details: ' . $details;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_messages (user_id, username, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $username, $subject, $message, 'new', date('c')]);
    } catch (Exception $e) {
    }
    try {
        $admins = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $admins->execute();
        while ($admin = $admins->fetch(PDO::FETCH_ASSOC)) {
            create_notification((int)$admin['id'], 'filter_violation', 'User '. $username .' used a prohibited word in '. $field, 'admin.php', $userId ?: null);
        }
    } catch (Exception $e) {
    }
}

function check_forbidden($text, $field, $user = null, $details = null) {
    if (!$text || !is_string($text)) return false;
    $bad = contains_forbidden($text);
    if ($bad !== false) {
        report_forbidden_word_use($field, $bad, $details, $user);
        return $bad;
    }
    return false;
}

function handle_file_upload($fieldName) {
    if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) return null;
    $f = $_FILES[$fieldName];
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error');
    if ($f['size'] > 10 * 1024 * 1024) throw new Exception('File too large (max 10MB)');
    $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain', 'application/pdf'];
    $mime = $f['type'];
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actual = $finfo->file($f['tmp_name']);
        if ($actual !== false) {
            $mime = $actual;
        }
    }
    if (!in_array($mime, $allowed, true)) throw new Exception('File type not allowed');
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $fname = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . preg_replace('/[^A-Za-z0-9]/', '', $ext) : '');
    $dest = $uploadsDir . '/' . $fname;
    if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Failed to move uploaded file');
    try {
        if (strpos($mime, 'image/') === 0 && function_exists('getimagesize') && function_exists('imagecreatetruecolor')) {
            $info = @getimagesize($dest);
            if ($info && isset($info[0]) && isset($info[1])) {
                $width = $info[0];
                $height = $info[1];
                $max = 1200;
                if ($width > $max) {
                    $ratio = $height / $width;
                    $newW = $max;
                    $newH = (int)round($max * $ratio);
                    $src = null;
                    switch ($info[2]) {
                        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($dest); break;
                        case IMAGETYPE_PNG: $src = @imagecreatefrompng($dest); break;
                        case IMAGETYPE_GIF: $src = @imagecreatefromgif($dest); break;
                        case IMAGETYPE_WEBP: if (function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($dest); break;
                    }
                    if ($src) {
                        $dst = imagecreatetruecolor($newW, $newH);
                        if ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_GIF) {
                            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                            imagealphablending($dst, false);
                            imagesavealpha($dst, true);
                        }
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
                        $resizedName = 'resized_' . $fname;
                        $resizedPath = $uploadsDir . '/' . $resizedName;
                        switch ($info[2]) {
                            case IMAGETYPE_JPEG: imagejpeg($dst, $resizedPath, 85); break;
                            case IMAGETYPE_PNG: imagepng($dst, $resizedPath); break;
                            case IMAGETYPE_GIF: imagegif($dst, $resizedPath); break;
                            case IMAGETYPE_WEBP: if (function_exists('imagewebp')) imagewebp($dst, $resizedPath, 85); break;
                        }
                        imagedestroy($dst);
                        imagedestroy($src);
                    }
                }
            }
        }
    } catch (Throwable $e) {
    }

    return 'uploads/' . $fname;
}

function responsive_image_url($url) {
    if (!is_string($url) || trim($url) === '') return $url;
    $url = trim($url);
    if (!preg_match('#^uploads/([A-Za-z0-9_\-]+(?:\.[A-Za-z0-9_\-]+)*)$#', $url, $m)) return $url;
    $fname = $m[1];
    $resized = __DIR__ . '/uploads/resized_' . $fname;
    if (is_file($resized)) return 'uploads/resized_' . $fname;
    return $url;
}

function validate_attachment_url($url) {
    if (!is_string($url) || trim($url) === '') {
        return null;
    }
    $url = trim($url);
    if (preg_match('#^uploads/[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$#', $url)) {
        return $url;
    }
    return null;
}

function current_user() {
    if (!empty($_SESSION['user_id'])) {
        static $user = null;
        if ($user === null) {
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT id, username, role, github_id, microsoft_id, email, bio, avatar_url, pronouns, last_seen_at FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
            if ($user) {
                $lastSeen = strtotime($user['last_seen_at'] ?? '1970-01-01');
                if (time() - $lastSeen > 300) {
                    try {
                        $update = $pdo->prepare('UPDATE users SET last_seen_at = ? WHERE id = ?');
                        $update->execute([date('c'), $user['id']]);
                    } catch (Exception $e) {
                    }
                }
            }
            if ($user && $user['role'] === 'banned') {
                // Immediately sign out banned users to prevent actions
                logout_user();
                $user = false;
            }
        }
        return $user;
    }
    return null;
}

function asset_url($path) {
    $p = ltrim($path, '/');
    $base = __DIR__ . '/' . $p;
    if (preg_match('/\.js$/', $p)) {
        $min = preg_replace('/\.js$/', '.min.js', $p);
        if (is_file(__DIR__ . '/' . $min)) {
            $p = $min;
            $base = __DIR__ . '/' . $p;
        }
    }
    if (preg_match('/\.css$/', $p)) {
        $min = preg_replace('/\.css$/', '.min.css', $p);
        if (is_file(__DIR__ . '/' . $min)) {
            $p = $min;
            $base = __DIR__ . '/' . $p;
        }
    }
    $v = is_file($base) ? filemtime($base) : time();
    return $p . '?v=' . $v;
}

function login_user_by_id($id) {
    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = (int)$id;
}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// Notifications
function create_notification($user_id, $type, $message, $url = null, $actor_user_id = null) {
    try {
        $pdo = getPDO();
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, actor_user_id, type, message, url, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user_id, $actor_user_id, $type, $message, $url, $now]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

function fetch_notifications($user_id, $limit = 20) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT n.*, u.username AS actor_username FROM notifications n LEFT JOIN users u ON n.actor_user_id = u.id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT ?');
    $stmt->execute([$user_id, (int)$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mark_notification_read($id, $user_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    return $stmt->execute([$id, $user_id]);
}

function mark_all_notifications_read($user_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    return $stmt->execute([$user_id]);
}

function unread_notification_count($user_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}
