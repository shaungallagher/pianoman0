<?php
$nonce = bin2hex(random_bytes(16));
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();

$pdo = getPDO();
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($slug) {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
}
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post || $post['status'] !== 'published') {
    http_response_code(404);
    echo "Post not found or not published.";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta property="og:title" content="<?=h($post['title'])?>">
  <meta property="og:description" content="<?=h(mb_substr(strip_tags($post['content']), 0, 160))?>">
  <?php if (!empty($post['featured_image'])): ?>
    <meta property="og:image" content="<?=h(responsive_image_url($post['featured_image']))?>">
  <?php endif; ?>
  <title><?=h($post['title'])?> — Visionary Blog</title>
  <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
  <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
  <style>
    .post-page { max-width: 800px; margin: 24px auto; padding: 0 16px; }
    .post-header { margin-bottom: 12px; }
    .post-meta { color: var(--muted); font-size: 13px; margin-bottom: 12px; }
    .post-content img { max-width:100%; height:auto; border-radius:6px; }
    .post-featured { width:100%; height:auto; border-radius:8px; margin-bottom:16px; object-fit:cover; display:block; }
  </style>
</head>
<body>
  <?php render_app_header('Blog', 'News and updates from Visionary', $user); ?>
  <main>
    <div class="post-page">
      <a href="blog.php">← Back to blog</a>
      <header class="post-header">
        <h1><?=h($post['title'])?></h1>
        <div class="post-meta">By <?=h($post['author_name'] ?? 'Staff')?> · <?=h(date('F j, Y', strtotime($post['published_at'] ?? $post['created_at'] ?? date('c'))))?></div>
      </header>
      <article class="post-content">
        <?php if (!empty($post['featured_image'])): ?>
          <img class="post-featured" src="<?=h(responsive_image_url($post['featured_image']))?>" alt="<?=h($post['title'])?>" loading="lazy" decoding="async">
        <?php endif; ?>
        <?= sanitize_post_content($post['content']) ?>
      </article>
    </div>
  </main>
  <?php render_app_footer(); ?>
  <?php render_theme_script(); ?>
</body>
</html>
