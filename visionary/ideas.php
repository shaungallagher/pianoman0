<?php
$nonce = bin2hex(random_bytes(16));
require_once __DIR__ . '/auth.php';
$user = current_user();
require_once __DIR__ . '/includes/app_layout.php';
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Visionary - A platform for developers and idea crafters to collaborate on innovative ideas.">
  <meta name="author" content="PianoMan0">
  <title>Visionary — Ideas Board</title>
  <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
  <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
</head>
<body>
  <?php render_app_header('Ideas Board', 'Post ideas. Developers claim them and implement.', $user); ?>

  <main class="container">
    <section id="post-idea">
      <h2 style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:0">
        <span>Share an idea</span>
        <a href="guide.php" style="font-size: 14px; text-decoration: none; color: var(--muted);">How to post</a>
      </h2>
      <div id="ideaFormMessage" style="margin-bottom:8px; font-size:14px; color:#0f5132; display:none; background:#d1e7dd; border:1px solid #badbcc; padding:8px; border-radius:4px"></div>
      <?php if (!$user || !in_array($user['role'], ['poster','both'])): ?>
        <p>You must <a href="login.php">sign in</a> as a poster to submit ideas.</p>
      <?php endif; ?>
      <form id="ideaForm" novalidate <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'class="disabled"';?>>
        <input type="text" name="title" id="title" placeholder="Short title" required <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>>
        <textarea name="description" id="description" placeholder="Describe the vision" rows="4" <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>></textarea>
        <input type="text" name="tags" id="tags" placeholder="Tags (comma separated, e.g. web, mobile)" <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>>
        <select id="templateSelect" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;margin:8px 0" <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>>
          <option value="">No template</option>
          <option value="web app">Web App Idea</option>
          <option value="mobile app">Mobile App Idea</option>
          <option value="game">Game Idea</option>
        </select>
        <button type="submit" <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>>Post idea</button>
      </form>
    </section>

    <section id="ideas-list">
      <h2 style="display:flex;justify-content:space-between;align-items:center;gap:16px">
        Ideas
        <div style="font-size:14px;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
          <input type="text" id="searchInput" placeholder="Search ideas..." style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:14px">
          <select id="sortSelect" style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:14px">
            <option value="date">Sort by Date</option>
            <option value="likes">Sort by Likes</option>
            <option value="messages">Sort by Messages</option>
          </select>
          <select id="statusFilter" style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:14px">
            <option value="all">All statuses</option>
            <option value="my">My Ideas</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
          </select>
          <button id="exportBtn" style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff">Export Ideas</button>
        </div>
      </h2>
      <div id="list"></div>
    </section>
    <br>
    <br>
    <p style="text-align:center;"><a href="logout.php">Log out</a></p>
  </main>

  <?php render_app_footer(); ?>
  <?php render_theme_script(); ?>
  <script nonce="<?=$nonce?>">window.CURRENT_USER = <?=json_encode($user)?>;</script>
  <script nonce="<?=$nonce?>" defer src="<?=asset_url('js/app.js')?>"></script>
</body>
</html>