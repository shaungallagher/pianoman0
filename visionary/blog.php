<?php
$nonce = bin2hex(random_bytes(16));
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();

$pdo = getPDO();
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE status = 'published'");
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$stmt = $pdo->prepare("SELECT id, slug, title, content, author_name, published_at, featured_image FROM posts WHERE status = 'published' ORDER BY published_at DESC LIMIT ? OFFSET ?");
$stmt->execute([(int)$limit, (int)$offset]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Blog — Visionary</title>
  <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
  <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
  <style>
    .blog-list { max-width: 900px; margin: 24px auto; padding: 0 16px; }
    .post-card { background: var(--card); padding: 18px; border-radius: 8px; margin-bottom: 14px; border: 1px solid var(--border); }
    .post-title { margin: 0 0 6px 0; font-size: 20px; }
    .post-meta { color: var(--muted); font-size: 13px; margin-bottom: 8px; }
    .post-excerpt { color: inherit; }
    .post-featured { width:100%; height:auto; border-radius:6px; margin-bottom:12px; object-fit:cover; display:block; }
    .pagination { display:flex;gap:8px;justify-content:center;margin-top:18px }
    .pagination a { padding:8px 12px;border-radius:6px;background:var(--card);border:1px solid var(--border);text-decoration:none }
  </style>
</head>
<body>
  <?php render_app_header('Blog', 'News and updates from Visionary', $user); ?>
  <main>
    <div class="blog-list">
      <h1>Visionary Blog</h1>
      <?php if (empty($posts)): ?>
        <p style="color:var(--muted)">No posts yet.</p>
      <?php else: ?>
            <?php foreach ($posts as $p): ?>
              <article class="post-card">
                <?php if (!empty($p['featured_image'])): ?>
                  <img class="post-featured" src="<?=h(responsive_image_url($p['featured_image']))?>" alt="<?=h($p['title'])?>" loading="lazy" decoding="async">
                <?php endif; ?>
                <h2 class="post-title"><a href="post.php?slug=<?=urlencode($p['slug'])?>"><?=h($p['title'])?></a></h2>
                <div class="post-meta">By <?=h($p['author_name'] ?? 'Staff')?> · <?=h(date('F j, Y', strtotime($p['published_at'] ?? $p['created_at'] ?? date('c'))))?></div>
                <div class="post-excerpt"><?=h(mb_substr(strip_tags($p['content']), 0, 300))?><?= mb_strlen(strip_tags($p['content'])) > 300 ? '…' : '' ?> <a href="post.php?slug=<?=urlencode($p['slug'])?>">Read more</a></div>
              </article>
            <?php endforeach; ?>
      <?php endif; ?>

      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="blog.php?page=<?= $page - 1 ?>">← Prev</a>
        <?php endif; ?>
        <?php if (($page * $limit) < $total): ?>
          <a href="blog.php?page=<?= $page + 1 ?>">Next →</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <?php render_app_footer(); ?>
  <?php render_theme_script(); ?>
</body>
</html>
