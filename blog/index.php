<?php
require_once __DIR__ . '/includes.php';
$posts = get_all_posts(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Blog - PianoMan0</title>
  <link rel="stylesheet" href="/style.css">
  <style>
    .blog-hero { padding: 80px 20px; text-align:center; }
    .blog-list { max-width:1100px; margin: 0 auto; padding: 40px 20px; display:flex; flex-direction:column; gap:24px; }
    .post-card { background: var(--main-header-background); border:2px solid var(--main-decor-color); border-radius:12px; padding:20px; display:flex; gap:20px; align-items:center; }
    .post-card img { width:180px; height:120px; object-fit:cover; border-radius:8px; border:1px solid var(--main-decor-color); }
    .post-meta { color: #aaa; font-size:0.9rem; }
    .read-link { background: var(--main-decor-color); padding:8px 12px; color:#000; border-radius:6px; text-decoration:none; font-weight:700; }
  </style>
</head>
<body>
  <div class="container">
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
      <section class="blog-hero">
        <h3>Blog<hr></h3>
        <p>Articles, tutorials and experiments.</p>
      </section>
      <section class="blog-list">
        <?php if (empty($posts)): ?>
          <div class="card">No posts yet.</div>
        <?php else: foreach ($posts as $p): ?>
          <article class="post-card">
            <?php if (!empty($p['image'])): ?>
              <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
            <?php endif; ?>
            <div style="flex:1">
              <h2 style="margin:0 0 8px 0;color:var(--main-decor-color)"><?php echo htmlspecialchars($p['title']); ?></h2>
              <div class="post-meta"><?php echo date('M j, Y', strtotime($p['created_at'])); ?></div>
              <p><?php echo htmlspecialchars(excerpt($p['content'], 220)); ?></p>
            </div>
            <div style="display:flex;align-items:center"><a class="read-link" href="/blog/post.php?slug=<?php echo urlencode($p['slug']); ?>">Read</a></div>
          </article>
        <?php endforeach; endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
