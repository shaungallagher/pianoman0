<?php
session_start();
require_once __DIR__ . '/includes.php';
if (empty($_SESSION['blog_admin'])) {
    header('Location: index.php');
    exit;
}
$posts = get_all_posts(false);
$editPost = null;
if (!empty($_GET['edit'])) {
    $editPost = get_post($_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard — Blog</title>
  <link rel="stylesheet" href="/style.css">
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
  <style>
    .admin-wrap { max-width:1200px; margin:40px auto; padding:20px; }
    .topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .posts-list { margin-top:18px; display:flex; flex-direction:column; gap:10px; }
    .post-row { display:flex; align-items:center; gap:12px; background:var(--main-header-background); border:2px solid var(--main-decor-color); padding:10px; border-radius:8px; }
    .editor { margin-top:22px; background:var(--main-header-background); padding:16px; border-radius:8px; border:2px solid var(--main-decor-color); }
    .field { margin-bottom:12px; }
    .muted { color:#aaa; font-size:0.9rem; }
    button { background:var(--main-decor-color); color:#000; padding:8px 12px; border-radius:6px; border:none; font-weight:700; }
  </style>
</head>
<body>
  <div class="admin-wrap">
    <div class="topbar">
      <h2 style="color:var(--main-decor-color)">Blog Admin</h2>
      <div>
        <a href="../" class="nav-link" style="margin-right:10px">View Blog</a>
        <a href="logout.php" class="nav-link">Log out</a>
      </div>
    </div>

    <section class="posts-list">
      <?php foreach ($posts as $p): ?>
        <div class="post-row">
          <div style="flex:1">
            <strong style="color:var(--main-decor-color)"><?php echo htmlspecialchars($p['title']); ?></strong>
            <div class="muted"><?php echo htmlspecialchars($p['slug']); ?> &middot; <?php echo htmlspecialchars($p['status'] ?? 'draft'); ?> &middot; <?php echo date('M j, Y', strtotime($p['created_at'])); ?></div>
          </div>
          <div style="display:flex;gap:8px">
            <a href="dashboard.php?edit=<?php echo urlencode($p['slug']); ?>">Edit</a>
            <form method="post" action="action.php" style="display:inline">
              <input type="hidden" name="slug" value="<?php echo htmlspecialchars($p['slug']); ?>">
              <input type="hidden" name="action" value="toggle">
              <button type="submit"><?php echo ($p['status'] === 'published') ? 'Unpublish' : 'Publish'; ?></button>
            </form>
            <form method="post" action="action.php" style="display:inline" onsubmit="return confirm('Delete this post?');">
              <input type="hidden" name="slug" value="<?php echo htmlspecialchars($p['slug']); ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" style="background:#ff6b6b">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="editor">
      <h3 style="color:var(--main-decor-color)"><?php echo $editPost ? 'Edit Post' : 'New Post'; ?></h3>
      <form id="postForm" method="post" action="save_post.php" enctype="multipart/form-data">
        <div class="field">
          <label>Title</label><br>
          <input id="title" name="title" type="text" style="width:100%;padding:10px;border-radius:6px;background:#111;border:1px solid #333;color:#fff" required>
        </div>
        <div class="field">
          <label>Slug (optional)</label><br>
          <input id="slug" name="slug" type="text" style="width:100%;padding:8px;border-radius:6px;background:#111;border:1px solid #333;color:#fff">
        </div>
        <div class="field">
          <label>Status</label>
          <select id="status" name="status">
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </div>
        <div class="field">
          <label>Cover image (optional)</label><br>
          <input type="file" name="image">
        </div>
        <div class="field">
          <label>Content</label>
          <div id="quill" style="height:320px;background:#fff;color:#000"></div>
          <textarea id="content" name="content" style="display:none"></textarea>
        </div>
        <input type="hidden" name="existing" id="existing" value="">
        <div style="margin-top:12px">
          <button type="submit">Save Post</button>
        </div>
      </form>
    </section>
  </div>

  <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
  <script>
    var quill = new Quill('#quill', { theme: 'snow' });
    document.getElementById('postForm').addEventListener('submit', function(e){
      document.getElementById('content').value = quill.root.innerHTML;
    });

    // populate edit values if provided from server
    var edit = <?php echo json_encode($editPost ?: null); ?>;
    if (edit) {
      document.getElementById('title').value = edit.title || '';
      document.getElementById('slug').value = edit.slug || '';
      document.getElementById('status').value = edit.status || 'draft';
      document.getElementById('existing').value = edit.slug || '';
      quill.root.innerHTML = edit.content || '';
    }
  </script>
</body>
</html>
