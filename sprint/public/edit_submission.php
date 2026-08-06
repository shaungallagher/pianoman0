<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$id = intval($_GET['id'] ?? 0);
$submission = get_submission($pdo, $id);

if (!$submission) abort_page('Submission not found', 404);

$team = get_user_team($pdo, (int)$submission['event_id'], current_user_id());
if (!$team || $team['id'] !== $submission['team_id']) {
    abort_page('You cannot edit this submission', 403);
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
        http_response_code(400);
        $message = "Invalid CSRF token.";
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $repo = trim($_POST['repo_url']);
        $demo = trim($_POST['demo_url']);

        // Server-side caps + validation to reduce stored injection surface.
        $title = normalize_submission_text($title, 120);
        $description = normalize_submission_text($description, 50000);
        $repo = validate_http_url_or_empty($repo, 2048) ?? '';
        $demo = validate_http_url_or_empty($demo, 2048) ?? '';

        // Handle optional replacement uploads
        $uploadDir = __DIR__ . '/uploads';
        $screenshotPath = $submission['screenshot_path'] ?? null;
        $videoPath = $submission['video_path'] ?? null;

        $imgTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $videoTypes = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

        if (!empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            if ((int)$_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
                $message = 'Screenshot file too large.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['screenshot']['tmp_name']);
                if (isset($imgTypes[$mime])) {
                    $ext = $imgTypes[$mime];
                    $fname = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                    // If replacing, remove previous file from disk (best-effort)
                    if (!empty($screenshotPath)) {
                        $basename = basename((string)$screenshotPath);
                        $full = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $basename;
                        if (is_file($full)) {
                            @unlink($full);
                        }
                    }

                    move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadDir . '/' . $fname);
                    $screenshotPath = 'uploads/' . $fname;
                }
            }
        }


        if (!empty($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            if ((int)$_FILES['video']['size'] > 100 * 1024 * 1024) {
                $message = 'Video file too large.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['video']['tmp_name']);
                if (isset($videoTypes[$mime])) {
                    $ext = $videoTypes[$mime];
                    $fname = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                    // If replacing, remove previous file from disk (best-effort)
                    if (!empty($videoPath)) {
                        $basename = basename((string)$videoPath);
                        $full = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $basename;
                        if (is_file($full)) {
                            @unlink($full);
                        }
                    }

                    move_uploaded_file($_FILES['video']['tmp_name'], $uploadDir . '/' . $fname);
                    $videoPath = 'uploads/' . $fname;
                }
            }
        }


        $stmt = $pdo->prepare("\
            UPDATE submissions\
            SET title=?, description=?, repo_url=?, demo_url=?, screenshot_path=?, video_path=?\
            WHERE id=?\
        ");
        $stmt->execute([$title, $description, $repo, $demo, $screenshotPath, $videoPath, $id]);

        header('Location: event.php?id=' . (int)$submission['event_id']);
        exit;
    }
}

$page_title = "Edit Submission · Sprint";
include '../includes/header.php';
?>

<h1>Edit Submission</h1>

<form method="post" class="form" enctype="multipart/form-data">
    <?= csrf_input_field() ?>
    <label>Title
        <input type="text" name="title" value="<?= htmlspecialchars($submission['title']) ?>" required>
    </label>

    <label>Description
        <textarea name="description" rows="5"><?= htmlspecialchars($submission['description']) ?></textarea>
    </label>

    <label>Repo URL
        <input type="url" name="repo_url" value="<?= htmlspecialchars($submission['repo_url']) ?>">
    </label>

    <label>Demo URL
        <input type="url" name="demo_url" value="<?= htmlspecialchars($submission['demo_url']) ?>">
    </label>

    <label>Screenshot (replace)
        <input type="file" name="screenshot" accept="image/*">
    </label>

    <label>Video (replace)
        <input type="file" name="video" accept="video/*">
    </label>

    <button class="btn">Save</button>
</form>

<?php include '../includes/footer.php'; ?>
