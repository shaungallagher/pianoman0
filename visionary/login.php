<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
require_once __DIR__ . '/includes/app_layout.php';
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = [];
// remove attempts older than 15 minutes
$_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($t){ return $t > time() - 900; });

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf()) {
    $error = 'Invalid form submission';
  } elseif (count($_SESSION['login_attempts']) >= 5) {
    $error = 'Too many login attempts. Try again later.';
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
      $error = 'Username and password required';
    } else {
      $pdo = getPDO();
      $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
      $stmt->execute([$username]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row && password_verify($password, $row['password_hash'])) {
        login_user_by_id($row['id']);
        session_regenerate_id(true);
        header('Location: ideas.php');
        exit;
      } else {
        $_SESSION['login_attempts'][] = time();
        $error = 'Invalid credentials';
      }
    }
  }
}
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Visionary - A platform for developers and idea crafters to collaborate on innovative ideas.">
    <meta name="author" content="PianoMan0">
    <title>Login — Visionary</title>
    <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
  </head>
  <body>
    <?php render_app_header('Login', 'Sign in and continue building with the community.', $user); ?>
    <main class="container">
      <section class="page-card">
        <div class="page-heading">
          <div>
            <h1>Welcome back</h1>
            <p>Sign in to manage your ideas, chat with collaborators, and track what’s trending.</p>
          </div>
          <div class="auth-meta">
            New here? <a href="signup.php">Create an account</a>
          </div>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error"><?=h($error)?></div>
        <?php endif; ?>

        <form method="post" class="form-card">
          <?=csrf_field()?>
          <div class="form-group">
            <label for="username">Username</label>
            <input id="username" name="username" placeholder="Username" required>
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="Password" required>
          </div>
          <div class="form-actions">
            <button type="submit" class="button-primary">Sign in</button>
          </div>
        </form>

        <div class="divider"><span>Or sign in with</span></div>
        <div class="provider-buttons">
          <a class="provider-button github" href="github_callback.php?oauth=1">GitHub</a>
          <a class="provider-button hackatime" href="hackatime_callback.php?oauth=1">Hackatime</a>
        </div>
      </section>
    </main>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
  </body>
</html>