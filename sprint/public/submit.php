<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$event_id = intval($_GET['event_id'] ?? 0);
$event = get_event($pdo, $event_id);
if (!$event) abort_page('Event not found', 404);

$team = get_user_team($pdo, $event_id, current_user_id());
$message = '';

function handle_upload($file, $allowedTypes, $maxBytes, $uploadDir) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $maxBytes) return ['error' => 'File too large'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedTypes[$mime])) return ['error' => 'Unsupported file type'];
    $ext = $allowedTypes[$mime];
    $basename = bin2hex(random_bytes(8));
    $filename = sprintf('%s_%s.%s', time(), $basename, $ext);
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['error' => 'Failed to save file'];
    return ['path' => 'uploads/' . $filename];
}

function delete_upload_if_exists($relativePath, $uploadDir) {
    if (!$relativePath || !is_string($relativePath)) return;
    $rel = ltrim($relativePath, '/\\');

    $normalized = str_replace('\\', '/', $rel);
    if (!str_starts_with($normalized, 'uploads/')) return;

    $basename = basename($normalized);
    if ($basename === '' || $basename === '.' || $basename === '..') return;

    $full = $uploadDir . DIRECTORY_SEPARATOR . $basename;
    if (is_file($full)) {
        @unlink($full);
    }
}

function normalize_submission_text(string $s, int $maxLen): string {
    $s = trim($s);
    if (mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    return $s;
}

function validate_http_url_or_empty(string $url, int $maxLen = 2048): ?string {
    $url = trim($url);
    if ($url === '') return null;
    if (mb_strlen($url) > $maxLen) return null;
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme'])) return null;
    $scheme = strtolower((string)$parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return null;
    if (empty($parts['host'])) return null;
    return $url;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $message = "Invalid CSRF token.";
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $repo = trim($_POST['repo_url']);
        $demo = trim($_POST['demo_url']);

        // If the user isn't in a team yet, create a single-member team automatically
        if (!$team) {
            $ownerName = current_user()['name'] ?? 'Team';
            $teamName = $ownerName . "'s Team";
            $stmt = $pdo->prepare("INSERT INTO teams (event_id, name) VALUES (?,?)");
            $stmt->execute([$event_id, $teamName]);
            $teamId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id) VALUES (?,?)");
            $stmt->execute([$teamId, current_user_id()]);
            $team = ['id' => $teamId, 'event_id' => $event_id, 'name' => $teamName];
            $message = "A team was created for you: $teamName";
        }

        $uploadDir = __DIR__ . '/uploads';
        $screenshotPath = null;
        $videoPath = null;

        $imgTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $videoTypes = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];

        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        if (!empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $res = handle_upload($_FILES['screenshot'], $imgTypes, 5 * 1024 * 1024, $uploadDir);
            if (!empty($res['error'])) {
                $message = $res['error'];
            } else {
                $screenshotPath = $res['path'];
            }
        }

        if (!empty($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $res = handle_upload($_FILES['video'], $videoTypes, 100 * 1024 * 1024, $uploadDir);
            if (!empty($res['error'])) {
                $message = $res['error'];
            } else {
                $videoPath = $res['path'];
            }
        }


        $title = normalize_submission_text((string)$title, 120);
        $description = normalize_submission_text((string)$description, 50000);
        $repo = validate_http_url_or_empty((string)$repo, 2048) ?? '';
        $demo = validate_http_url_or_empty((string)$demo, 2048) ?? '';

        if ($team && $title) {
            $stmt = $pdo->prepare("INSERT INTO submissions (event_id, team_id, title, description, repo_url, demo_url, screenshot_path, video_path) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$event_id, $team['id'], $title, $description, $repo, $demo, $screenshotPath, $videoPath]);
            header('Location: event.php?id=' . (int)$event_id);
            exit;
        }
        $message = "You must be in a team and include a title.";
    }
}

$page_title = "Submit · Sprint";
include '../includes/header.php';
?>

<h1>Submit to <?= htmlspecialchars($event['name']) ?></h1>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" class="form" enctype="multipart/form-data">
    <?= csrf_input_field() ?>
    <label>Title
        <input type="text" name="title" required>
    </label>

    <label>Description
        <textarea name="description" rows="5"></textarea>
    </label>

    <label>Repo URL
        <input type="url" name="repo_url">
    </label>

    <label>Demo URL
        <input type="url" name="demo_url">
    </label>

    <label>Screenshot (optional)
        <input type="file" name="screenshot" accept="image/*">
    </label>

    <label>Video (optional)
        <input type="file" name="video" accept="video/*">
    </label>

    <button class="btn">Submit</button>
</form>

<?php include '../includes/footer.php'; ?>
