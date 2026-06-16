<?php
require_once __DIR__ . '/includes.php';
$slug = $_GET['slug'] ?? '';
$post = get_post($slug);
if (!$post || ($post['status'] !== 'published')) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 — Post not found</h1>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($post['title']); ?> — PianoMan0</title>
  <link rel="stylesheet" href="/style.css">
  <style>
    .post-wrap { max-width:900px; margin: 80px auto; padding: 20px; }
    .post-meta { color:#aaa; margin-bottom:18px; }
    .post-image { width:100%; max-height:420px; object-fit:cover; border-radius:10px; border:1px solid var(--main-decor-color); margin-bottom:18px; }
    article.post-content { background: var(--main-header-background); padding:22px; border-radius:12px; border:2px solid var(--main-decor-color); }
  </style>
</head>
<body>
  <header>
    <a class="logo" href="/"></a>
    <nav>
      <ul class="nav-bar">
        <li><a class="nav-link" href="/">Home</a></li>
        <li><a class="nav-link" href="/#aboutme">About</a></li>
        <li><a class="nav-link" href="/#actualportfolio">Portfolio</a></li>
        <li><a class="nav-link active" href="/blog/">Blog</a></li>
        <li><a class="nav-link" href="/#contact">Contact</a></li>
      </ul>
    </nav>
  </header>
  <main>
    <section class="post-wrap">
      <h1 style="color:var(--main-decor-color)"><?php echo htmlspecialchars($post['title']); ?></h1>
      <div class="post-meta"><?php echo date('M j, Y', strtotime($post['created_at'])); ?></div>
      <?php if (!empty($post['image'])): ?>
        <img class="post-image" src="<?php echo htmlspecialchars($post['image']); ?>" alt="">
      <?php endif; ?>
      <article class="post-content">
        <?php echo $post['content']; ?>
      </article>
    </section>
  </main>
</body>
</html>
