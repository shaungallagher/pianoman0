<?php
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
  <meta name="keywords" content="visionary, ideas, collaboration, developers, projects, innovation">
  <meta property="og:title" content="Visionary">
  <meta property="og:description" content="A platform for developers and idea crafters to collaborate on innovative ideas.">
  <title>Visionary — Home</title>
  <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
  <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
  <style>
    .hero { text-align: center; padding: 60px 16px; }
    .hero h1 { font-size: 48px; margin: 0 0 16px 0; }
    .hero p { font-size: 18px; color: var(--muted); margin-bottom: 32px; max-width: 600px; margin-left: auto; margin-right: auto; }
    .hero-buttons { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 32px; }
    .hero-buttons a { padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; }
    .btn-primary { background: var(--accent); color: white; }
    .btn-secondary { background: #f3f4f6; color: #111; border: 1px solid #ddd; }
    body.dark .btn-secondary { background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.2); }
    .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin: 60px 0; }
    .feature-card { background: var(--card); padding: 24px; border-radius: 6px; text-align: left; border: 1px solid var(--border); }
    .feature-card .emoji { font-size: 28px; margin-bottom: 12px; display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: rgba(37,99,235,0.08); }
    .feature-card h3 { margin: 0 0 8px 0; font-size: 18px; color: var(--accent); }
    .feature-card p { margin: 0; font-size: 14px; color: var(--muted); line-height: 1.6; }
    .stats-bar { background: var(--card); padding: 32px 24px; border-radius: 6px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 24px; text-align: center; margin: 40px 0; border: 1px solid var(--border); }
    .stat { }
    .stat-number { font-size: 28px; font-weight: bold; color: var(--accent); }
    .stat-label { font-size: 12px; color: var(--muted); }
    .cta-section { background: linear-gradient(135deg, rgba(37,99,235,0.95), rgba(79,70,229,0.95)); color: white; padding: 48px 24px; border-radius: 6px; text-align: center; margin: 60px 0; }
    .cta-section h2 { margin-top: 0; font-size: 28px; }
    .cta-section a { background: white; color: var(--accent); padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block; margin-top: 16px; }
    .nav-links { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin: 24px 0; }
    .nav-links a { padding: 8px 12px; background: var(--card); border-radius: 4px; text-decoration: none; font-size: 13px; color: var(--accent); border: 1px solid var(--border); }
  </style>
</head>
<body>
  <?php render_app_header('Home', 'Build your ideas with the world', $user); ?>

  <main style="max-width:1000px;margin:0 auto;padding:0 16px">
    <div class="content">
      <section class="hero">
        <h1>Turn Ideas Into Reality</h1>
        <p>Connect with developers and creators. Post innovative ideas. Build amazing products. Collaborate globally.</p>
        
        <div class="hero-buttons">
          <?php if ($user): ?>
            <a href="ideas.php" class="btn-primary">→ Ideas Board</a>
            <a href="dashboard.php" class="btn-secondary">📊 Dashboard</a>
            <a href="blog.php" class="btn-secondary">📰 Blog</a>
          <?php else: ?>
            <a href="ideas.php" class="btn-primary">Browse Ideas</a>
            <a href="login.php" class="btn-secondary">Sign In</a>
            <a href="signup.php" class="btn-secondary">Create Account</a>
            <a href="blog.php" class="btn-secondary">📰 Blog</a>
          <?php endif; ?>
        </div>
      </section>

      <div class="stats-bar">
        <div class="stat">
          <div class="stat-number" id="totalIdeasStat">0</div>
          <div class="stat-label">Ideas Posted</div>
        </div>
        <div class="stat">
          <div class="stat-number" id="completedIdeasStat">0</div>
          <div class="stat-label">Completed</div>
        </div>
        <div class="stat">
          <div class="stat-number" id="communityMembersStat">0</div>
          <div class="stat-label">Community Members</div>
        </div>
      </div>

      <section>
        <h2 style="text-align: center; margin: 60px 0 40px 0;">Platform Features</h2>
        <div class="features-grid">
          <div class="feature-card">
            <div class="emoji">💡</div>
            <h3>Post Ideas</h3>
            <p>Share your innovative ideas with the global developer community</p>
          </div>
          <div class="feature-card">
            <div class="emoji">🔍</div>
            <h3>Explore</h3>
            <p>Discover amazing ideas and find projects that inspire you</p>
          </div>
          <div class="feature-card">
            <div class="emoji">👥</div>
            <h3>Community</h3>
            <p>Connect with developers, designers, and creative builders</p>
          </div>
          <div class="feature-card">
            <div class="emoji">📊</div>
            <h3>Analytics</h3>
            <p>Track trends, engagement, and platform insights</p>
          </div>
        </div>
      </section>

      <?php if ($user): ?>
        <div class="cta-section">
          <h2>Ready to Build?</h2>
          <p>Your journey to creating amazing things starts here</p>
          <a href="ideas.php">Post or Explore Ideas</a>
        </div>

        <section>
          <h2 style="text-align: center; margin: 60px 0 40px 0;">Quick Navigation</h2>
          <div class="nav-links">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="ideas.php">💡 Ideas</a>
            <a href="people.php">👥 People</a>
            <a href="notifications.php">🔔 Notifications</a>
            <a href="analytics.php">📈 Analytics</a>
            <a href="guide.php">📖 Guide</a>
            <a href="blog.php">📰 Blog</a>
          </div>
        </section>
      <?php else: ?>
        <section>
          <h2 style="text-align: center; margin: 60px 0 40px 0;">Getting Started</h2>
          <div class="nav-links">
            <a href="ideas.php">Browse Ideas</a>
            <a href="guide.php">How It Works</a>
            <a href="login.php">Sign In</a>
            <a href="signup.php">Create Free Account</a>
            <a href="blog.php">📰 Blog</a>
          </div>
        </section>
      <?php endif; ?>

      <?php render_app_footer(); ?>
    </div>
  </main>
  <?php render_theme_script(); ?>
  <script defer src="<?=asset_url('js/app.js')?>"></script>
  <script nonce="<?=$nonce?>">
    async function updateHomeStats() {
      try {
        const res = await fetch('api.php?action=site_stats', {credentials:'same-origin'});
        const data = await res.json();
        if (!data.success || !data.data) return;
        const s = data.data;
        document.getElementById('totalIdeasStat').textContent = s.total_ideas;
        document.getElementById('completedIdeasStat').textContent = s.completed_ideas;
        document.getElementById('communityMembersStat').textContent = s.total_users;
      } catch (err) {
        console.error('Home stats load error', err);
      }
    }
    document.addEventListener('DOMContentLoaded', updateHomeStats);
  </script>
</body>
</html>