<?php
session_start();
require_once __DIR__ . '/includes.php';
if (empty($_SESSION['blog_admin'])) {
    header('Location: index.php');
    exit;
}
// Expect: title, slug (optional), status, content, existing (optional), image (file)
$title = $_POST['title'] ?? '';
$slug = trim($_POST['slug'] ?? '');
$status = $_POST['status'] ?? 'draft';
$content = $_POST['content'] ?? '';
$existing = $_POST['existing'] ?? null;

// start building post data
$post = [];
$post['title'] = $title;
$post['slug'] = $slug;
$post['status'] = $status;
$post['content'] = $content;

// preserve created_at and existing image if editing
if ($existing) {
    $orig = get_post($existing);
    if ($orig) {
        if (!empty($orig['created_at'])) $post['created_at'] = $orig['created_at'];
        if (!empty($orig['image'])) $post['image'] = $orig['image'];
    }
}

// handle image upload
if (!empty($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    $uploaddir = uploads_dir();
    if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);
    $fname = unique_filename($_FILES['image']['name']);
    $dest = $uploaddir . DIRECTORY_SEPARATOR . $fname;
    if (@move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        // store relative path (blog page expects relative upload path)
        $post['image'] = 'uploads/' . $fname;
    }
}

$newslug = save_post($post, $existing ?: null);
header('Location: dashboard.php');
exit;
