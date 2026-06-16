<?php
session_start();
require_once __DIR__ . '/../includes.php';
if (empty($_SESSION['blog_admin'])) {
    header('Location: index.php');
    exit;
}
$action = $_POST['action'] ?? '';
$slug = $_POST['slug'] ?? '';
if (!$slug) {
    header('Location: dashboard.php');
    exit;
}
if ($action === 'delete') {
    $file = posts_dir() . '/' . $slug . '.json';
    if (file_exists($file)) @unlink($file);
} elseif ($action === 'toggle') {
    $post = get_post($slug);
    if ($post) {
        $post['status'] = ($post['status'] === 'published') ? 'draft' : 'published';
        save_post($post, $slug);
    }
}
header('Location: dashboard.php');
exit;
